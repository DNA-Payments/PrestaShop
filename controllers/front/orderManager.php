<?php


require_once DNA_ROOT_URL.'/includes/ConfigStore.php';

class DnapaymentsOrderManagerModuleFrontController extends ModuleFrontController
{

    /**
     * @var ConfigStore
     */
    private $configStore;
    /**
     * @var DNAPayments
     */
    private $dnaPayment;

    public function __construct()
    {
        $this->configStore = new ConfigStore();
        $this->dnaPayment = new \DNAPayments\DNAPayments([
            'isTestMode' => $this->configStore->is_test,
            'scopes' => [
                'allowHosted' => true
            ]
        ]);
        parent::__construct();
    }

    public function initContent()
    {
        parent::initContent();
    }

    public function run()
    {
        $method = Tools::getValue('action');

        if (method_exists($this, $method)) {
            return call_user_func(array($this, $method));
        }

    }

    public function cancelOrder() {
        global $kernel;
        if(!$kernel){
            require_once _PS_ROOT_DIR_.'/app/AppKernel.php';
            $kernel = new \AppKernel('prod', false);
            $kernel->boot();
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input['invoiceId']) throw new Error('Can not find order');
        if (!empty($input) && !empty($input['invoiceId']) && !$input['success'] && \DNAPayments\DNAPayments::isValidSignature($input, $this->configStore->client_secret)) {
            try {
                $order = new Order($input['invoiceId']);

                if (!$this->configStore::isDNAPaymentOrder($order)) {
                    return;
                }

                // to fix error with localization
                Context::getContext()->currency = Context::getContext()->currency ?? new Currency((int)$order->id_currency);
                $order->setCurrentState((int)Configuration::get('PS_OS_ERROR'));
                
                echo $input['invoiceId'];
                return;
            } catch (Exception $e) {
                throw $e;
            }
        }
    }

    public function confirmOrder() {
        // To fix error with not founding Kernel
        global $kernel;
        if(!$kernel){
            require_once _PS_ROOT_DIR_.'/app/AppKernel.php';
            $kernel = new \AppKernel('prod', false);
            $kernel->boot();
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if(!$input['invoiceId']) throw new Error('Can not find order');

        if (!empty($input) && !empty($input['invoiceId']) && $input['success'] && \DNAPayments\DNAPayments::isValidSignature($input, $this->configStore->client_secret)) {
            try {
                $id_order = (int)$input['invoiceId'];
                $order = new Order($id_order);

                if(!$this->configStore::isDNAPaymentOrder($order)) {
                    throw new Error($id_order . ' is not DNA Payments order');
                }

                // to fix error with localization
                Context::getContext()->currency = Context::getContext()->currency ?? new Currency((int)$order->id_currency);

                $state = (int)Configuration::get('PS_OS_PAYMENT');
                $order->setCurrentState($state);

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

                echo $input['invoiceId'];
                return;
            } catch (Exception $e) {
                PrestaShopLogger::addLog($e->getMessage(), 3);
                throw $e;
            }
            return;
        }
    }
}
