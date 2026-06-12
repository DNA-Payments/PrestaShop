<?php

/**
 * Builds the DNA payment payload and persists the pending transaction.
 *
 * Extracted from the order front controller so that both the AJAX/embedded
 * flow (order.php) and the hosted redirect flow (redirect.php) reuse the same
 * domain logic via composition — no controller-to-controller inheritance.
 */
class DnapaymentsPaymentBuilder
{
    /** @var Module */
    private $module;

    /** @var Context */
    private $context;

    public function __construct($module)
    {
        $this->module = $module;
        $this->context = Context::getContext();
    }

    private function getConfigStore()
    {
        return $this->module->helper->configStore;
    }

    private function getDnaPayment()
    {
        return $this->module->helper->dnaPayment;
    }

    /**
     * Context validation shared by both flows.
     *
     * @return string[] error messages (empty array = context is valid)
     */
    public function validate($cart)
    {
        if (
            !$cart ||
            (int) $cart->id_customer === 0 ||
            (int) $cart->id_address_delivery === 0 ||
            (int) $cart->id_address_invoice === 0 ||
            !$this->module->active
        ) {
            return ['Invalid cart or module inactive'];
        }

        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] === 'dnapayments') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            return [$this->module->l('This payment method is not available.', 'validation')];
        }

        return $this->validateOrderFields($cart);
    }

    public function validateOrderFields($cart)
    {
        $errors = [];
        $customer = new Customer($cart->id_customer);
        $address_billing = new Address($cart->id_address_invoice);
        $country_billing = new Country($address_billing->id_country);

        if (strlen($country_billing->iso_code) > 2) {
            $errors[] = 'Country must be less than 2 symbols';
        } elseif (strlen($address_billing->city) > 50) {
            $errors[] = 'City must be less than 50 symbols';
        } elseif (strlen($address_billing->address1) > 50) {
            $errors[] = 'Address must be less than 50 symbols';
        } elseif (strlen($customer->email) > 256) {
            $errors[] = 'Email must be less than 256 symbols';
        } elseif (strlen($address_billing->lastname) > 32) {
            $errors[] = 'Lastname must be less than 32 symbols';
        } elseif (strlen($address_billing->firstname) > 32) {
            $errors[] = 'Firstname must be less than 32 symbols';
        } elseif (strlen($address_billing->postcode) > 13) {
            $errors[] = 'Postcode must be less than 13 symbols';
        }

        return $errors;
    }

    /**
     * Builds the DNA payment payload (auth token + order data) and persists the
     * pending transaction. Returns the payload including the 'auth' key.
     */
    public function build($cart)
    {
        $test_mode = (bool) Configuration::get('DNA_PAYMENT_TEST_MODE');

        // Defining invoice/order
        $order_id = 0;

        if (!$this->getConfigStore()->should_create_order_after_payment) {
            $order = $this->createPendingOrder($cart);
            $order_id = (int) $order->id;
            $invoiceBase = $order_id;
        } else {
            $invoiceBase = (int) $cart->id;
        }

        $invoiceId = DNA_ORDER_PREFIX . $invoiceBase . '_' . date('YmdHis');

        // Loading entities
        $customer        = new Customer($cart->id_customer);
        $billingAddress  = new Address($cart->id_address_invoice);
        $shippingAddress = new Address($cart->id_address_delivery);
        $currency        = new Currency((int) $cart->id_currency);

        // Authorization in DNA
        $auth = $this->getDnaPayment()->auth(
            $this->module->helper->getAuthData(
                $invoiceId,
                $cart->getOrderTotal(),
                $currency->iso_code
            )
        );

        // Save (upsert) the pending transaction
        $transaction = new DnapaymentsTransaction();
        $transaction->getDnapaymentsTransactionByCart($cart->id);

        $transaction->status      = (int) Configuration::get('DNA_OS_AWAITING_PAYMENT');
        $transaction->id_customer = (int) $cart->id_customer;
        $transaction->id_cart     = (int) $cart->id;
        $transaction->id_order    = (int) $order_id;
        $transaction->dnaOrderId  = $invoiceId;
        $transaction->amount      = (float) $cart->getOrderTotal();
        $transaction->currency    = $currency->iso_code;
        $transaction->save();

        // Generating a payload for the SDK
        $data = [
            'auth'        => $auth,
            'invoiceId'   => $invoiceId,
            'description' => Configuration::get('DNA_PAYMENT_GATEWAY_ORDER_DESCRIPTION'),
            'amount'      => $cart->getOrderTotal(),
            'currency'    => $currency->iso_code,

            'paymentSettings' => [
                'terminalId' => $test_mode
                    ? Configuration::get('DNA_MERCHANT_TEST_TERMINAL_ID')
                    : Configuration::get('DNA_MERCHANT_TERMINAL_ID'),
                'returnUrl' => $this->getReturnlink($cart, $order_id, 'success'),
                'failureReturnUrl' => $this->getReturnlink($cart, $order_id, 'failed'),
                'callbackUrl' => $this->context->link->getModuleLink($this->module->name, 'confirm', [], true),
                'failureCallbackUrl' => $this->context->link->getModuleLink($this->module->name, 'confirm', [], true),
            ],

            'customerDetails' => [
                'email' => $customer->email,
                'accountDetails' => [
                    'accountId' => (string) $cart->id_customer,
                ],
                'billingAddress' => $this->getAddress($billingAddress),
                'deliveryDetails' => [
                    'deliveryAddress' => $this->getAddress($shippingAddress),
                ],
            ],

            'language'        => 'en-gb',
            'amountBreakdown' => $this->getAmountBreakDown($cart),
            'orderLines'      => $this->getOrderLines($cart),
        ];

        $transactionType = Configuration::get('DNA_PAYMENT_TRANSACTION_TYPE');
        if ($transactionType && $transactionType !== 'default') {
            $data['transactionType'] = $transactionType;
        }

        if ($this->getConfigStore()->dna_payment_card_vault_enabled) {
            $data['periodic'] = [
                'periodicType' => 'ucof',
            ];
        }

        return $data;
    }

    private function createPendingOrder($cart)
    {
        $this->module->validateOrder(
            $cart->id,
            (int) Configuration::get('DNA_OS_AWAITING_PAYMENT'),
            $cart->getOrderTotal(),
            $this->module->displayName
        );

        return Order::getByCartId($cart->id);
    }

    private function getReturnlink($cart, $order_id, $status)
    {
        // cancel / failed -> configured failure/back link (falls back to checkout)
        if ($status !== 'success') {
            return $this->module->helper->getFailureBackLink();
        }

        // SUCCESS — order created after payment: go through the return controller
        // which redirects to order-confirmation once the order exists.
        if ($this->getConfigStore()->should_create_order_after_payment) {
            return $this->context->link->getModuleLink(
                $this->module->name,
                'return',
                [
                    'id_cart' => (int) $cart->id,
                    'status'  => 'success',
                ],
                true
            );
        }

        // Order was created BEFORE payment
        return $this->module->helper->getBacklink($cart, $order_id);
    }

    private function getAddress($address)
    {
        $country = new Country($address->id_country);

        return [
            'firstName' => $address->firstname,
            'lastName' => $address->lastname,
            'addressLine1' => $address->address1,
            'addressLine2' => $address->address2,
            'postalCode' => $address->postcode,
            'city' => $address->city,
            'phone' => $address->phone ? $address->phone : $address->phone_mobile,
            'country' => $country->iso_code,
        ];
    }

    public function getAmountBreakDown(Cart $cart)
    {
        $productTotal = round((float) $cart->getOrderTotal(false, Cart::ONLY_PRODUCTS), 2);
        $shippingTotal = round((float) $cart->getOrderTotal(true, Cart::ONLY_SHIPPING), 2);
        $discountTotal = round((float) abs($cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS)), 2);

        $productTotalWithTax = round((float) $cart->getOrderTotal(true, Cart::ONLY_PRODUCTS), 2);
        $taxTotal = round($productTotalWithTax - $productTotal, 2);

        return [
            'itemTotal' => ['totalAmount' => $productTotal],
            'shipping' => ['totalAmount' => $shippingTotal],
            'taxTotal' => ['totalAmount' => $taxTotal],
            'discount' => ['totalAmount' => $discountTotal],
        ];
    }

    public function getOrderLines(Cart $cart)
    {
        $products = $cart->getProducts();
        $link = $this->context->link;
        $orderLines = [];

        foreach ($products as $product) {
            $imageUrl = $link->getImageLink(
                isset($product['link_rewrite']) ? $product['link_rewrite'] : $product['name'],
                (int) $product['id_image'],
                'medium_default'
            );

            $orderLines[] = [
                'reference' => $product['id_product'],
                'name' => $product['name'],
                'quantity' => $product['quantity'],
                'unitPrice' => $product['price'],
                'imageUrl' => $imageUrl,
                'productUrl' => $link->getProductLink($product),
                'totalAmount' => $product['total'],
            ];
        }

        return $orderLines;
    }
}
