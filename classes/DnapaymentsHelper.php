<?php

class DnapaymentsHelper {

    /** @var DnapaymentsHelper */
    protected static $instance;

    /** @var ConfigStore */
    public $configStore;

    /** @var DNAPayments */
    public $dnaPayment;

    private $module;

    public function __construct($module)
    {
        $this->module = $module;
        $this->configStore = new ConfigStore();
        $this->dnaPayment = new \DNAPayments\DNAPayments([
            'isTestMode' => $this->configStore->is_test,
            'scopes' => [
                'allowHosted' => true,
                'allowEmbedded' => Configuration::get('DNA_PAYMENT_INTEGRATION_TYPE') == 'embedded'
            ]
        ]);
    }

    /**
     * Get a singleton instance of DnapaymentsHelper object.
     *
     * @return DnapaymentsHelper|null
     */
    public static function getInstance($module)
    {
        if (!isset(self::$instance)) {
            self::$instance = new DnapaymentsHelper($module);
        }

        return self::$instance;
    }

    public function validateAndGetStatus($input) {

        if (!$input['invoiceId']) throw new Error('Can not find order');
        
        if (!\DNAPayments\DNAPayments::isValidSignature($input, $this->configStore->client_secret)) {
            throw new Error('Order data is not valid');
        }

        if (!$input['success']) {
            return Configuration::get('PS_OS_ERROR');
        }

        return $input['settled'] ? Configuration::get('PS_OS_PAYMENT') : Configuration::get('DNA_OS_WAITING_CAPTURE');
    }

    public function getAuthData($id_order, $amount, $currency) {
        return array(
            'client_id' => $this->configStore->client_id,
            'client_secret' => $this->configStore->client_secret,
            'terminal' => $this->configStore->terminal_id,
            'invoiceId' => strval($id_order),
            'amount' => floatval($amount),
            'currency' => $currency
        );
    }

    public function createOrder($input, $status_id) {
        $invoiceId = strval($input['invoiceId']);
        $amount = (float) $input['amount'];
        $currency = $input['currency'];
        $transaction_id = $input['id'];

        $transaction = new DnapaymentsTransaction();
        $transaction->getDnapaymentsTransactionByDnaOrderId($invoiceId);
    
        $cart_id = false;
        $order_id = false;

        if (strpos($invoiceId, DNA_ORDER_PREFIX) !== false) {
            $cart_id = (int) str_replace(DNA_ORDER_PREFIX, "", $invoiceId);
        } else {
            $order_id = (int) $invoiceId;
        }

        /** Check if currency is valid */
        $id_currency = (int)Currency::getIdByIsoCode($currency);
        if (!$id_currency) {
            throw new Error('Currency is not loaded');
        }
        Context::getContext()->currency = new Currency($id_currency);

        $transaction->id_transaction = $transaction_id;
        $transaction->amount = $amount;
        $transaction->currency = $currency;

        if (!$cart_id) {
            $transaction->id_order = $order_id;
            $order = new Order($order_id);

            if (!Validate::isLoadedObject($order)) {
                throw new Error('Order is not loaded. Order id: ' . $order_id);
            }

            if (!$this->configStore::isDNAPaymentOrder($order)) {
                throw new Error($order_id . ' is not DNA Payments order');
            }

            $order->setCurrentState($status_id);
        } else {
            /** Check if cart is valid */
            $cart = new Cart($cart_id);
            if (!Validate::isLoadedObject($cart)) {
                throw new Error('Cart is not loaded');
            }
            Context::getContext()->cart = $cart;

            /** Check if customer is valid */
            $id_customer = $cart->id_customer;
            $customer = new Customer($id_customer);
            if (!Validate::isLoadedObject($customer)) {
                throw new Error('Customer is not loaded');
            }
            Context::getContext()->customer = $customer;
            
            if ($this->module->validateOrder(
                $cart_id,
                $status_id,
                $amount,
                $this->module->displayName,
                '',
                null,
                $id_currency,
                false,
                $customer->secure_key
            )) {
                $order = Order::getByCartId($cart_id);
                $transaction->id_cart = $cart_id;
                $transaction->id_customer = $cart->id_customer;
                $transaction->id_order = $order->id;
            } else {
                throw new Error('Order is not validated. Cart id: ' . $cart_id . ', status: ' . $status_id . ', id_currency: ' . $id_currency);
            }
        }

        $transaction->status = $status_id;
        $transaction->save();

        $id_order_payment = (int) Db::getInstance()->getValue(
            'SELECT `id_order_payment`
            FROM `' . _DB_PREFIX_ . 'order_invoice_payment`
            WHERE `id_order` =  ' . $order->id
        );


        if ($id_order_payment) {
            Db::getInstance()->execute(
                'UPDATE `'._DB_PREFIX_.'order_payment`
                SET `order_reference` = "'.pSQL($order->reference).'",
                    `transaction_id` = "'.$input['id'].'",
                    `card_number` = "'.($input['cardPanStarred'] ?? '').'",
                    `card_expiration` = "'.($input['cardExpiryDate'] ?? '').'",
                    `card_brand` = "'.($input['cardSchemeName'] ?? '').'"
                WHERE  `id_order_payment` = '.$id_order_payment
            );
        }

        return $order;
    }

    public function getBacklink($cart, $order_id) {
        return Configuration::get('DNA_PAYMENT_BACK_LINK') ? $this->module->getBaseUrl().Configuration::get('DNA_PAYMENT_BACK_LINK') : $this->getOrderConfirmationLink($cart, $order_id);
    }

    public function getFailureBackLink() {
        return Configuration::get('DNA_PAYMENT_FAILURE_BACK_LINK') ? $this->getBaseUrl().Configuration::get('DNA_PAYMENT_FAILURE_BACK_LINK') : Context::getContext()->link->getModuleLink($this->module->name, 'orderFailureResult');
    }

    public function getOrderConfirmationLink($cart, $order_id)
    {
        $url = Context::getContext()->link->getPageLink('order-confirmation', true);
        $url .= '?key=' . $cart->secure_key;
        $url .= '&total=' . $cart->getOrderTotal();
        $url .= '&id_cart=' . $cart->id;
        $url .= '&id_order=' . $order_id;
        $url .= '&id_module=' . $this->module->id;

        return $url;
    }

}
