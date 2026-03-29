<?php

class DnapaymentsOrderModuleFrontController extends ModuleFrontController
{
    public function getConfigStore() {
        return $this->module->helper->configStore;
    }

    public function getDnaPayment() {
        return $this->module->helper->dnaPayment;
    }

    public function initContent()
    {
    	parent::initContent();
    }
    
    public function validateOrderFields($cart) {
        $errors = [];
        $customer = new Customer($cart->id_customer);
        $address_billing = new Address($cart->id_address_invoice);
        $country_billing = new Country($address_billing->id_country);

        if( strlen ($country_billing->iso_code) > 2 ) {
            $errors[] = 'Country must be less than 2 symbols';
        } else if( strlen($address_billing->city) > 50 ) {
            $errors[] = 'City must be less than 50 symbols';
        } else if( strlen($address_billing->address1) > 50 ) {
            $errors[] = 'Address must be less than 50 symbols';
        }  else if(strlen($customer->email) > 256) {
            $errors[] = 'Email must be less than 256 symbols';
        } else if( strlen ($address_billing->lastname) > 32 ) {
            $errors[] = 'Lastname must be less than 32 symbols';
        } else if( strlen ($address_billing->firstname) > 32 ) {
            $errors[] = 'Firstname must be less than 32 symbols';
        } else if( strlen ($address_billing->postcode) > 13 ) {
            $errors[] = 'Postcode must be less than 13 symbols';
        }

        return $errors;
    }

    public function displayAjaxCreateOrder()
    {
        header('Content-Type: application/json');

        try {
            $test_mode = (bool) Configuration::get('DNA_PAYMENT_TEST_MODE');
            $cart = $this->context->cart;

            // Basic checks
            if (
                !$cart ||
                (int)$cart->id_customer === 0 ||
                (int)$cart->id_address_delivery === 0 ||
                (int)$cart->id_address_invoice === 0 ||
                !$this->module->active
            ) {
                echo json_encode([
                    'errors' => ['Invalid cart or module inactive']
                ]);
                return;
            }

            // Checking the module's availability
            $authorized = false;
            foreach (Module::getPaymentModules() as $module) {
                if ($module['name'] === 'dnapayments') {
                    $authorized = true;
                    break;
                }
            }

            if (!$authorized) {
                echo json_encode([
                    'errors' => [$this->module->l('This payment method is not available.', 'validation')]
                ]);
                return;
            }

            // Validation
            $validationErrors = $this->validateOrderFields($cart);
            if (!empty($validationErrors)) {
                echo json_encode([
                    'errors' => $validationErrors
                ]);
                return;
            }

            // Defining invoice/order
            $order_id = 0;

            if (!$this->getConfigStore()->should_create_order_after_payment) {
                $order = $this->createOrder($cart);
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

            // Save ttransaction
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
                'description'=> Configuration::get('DNA_PAYMENT_GATEWAY_ORDER_DESCRIPTION'),
                'amount'     => $cart->getOrderTotal(),
                'currency'   => $currency->iso_code,

                'paymentSettings' => [
                    'terminalId' => $test_mode
                        ? Configuration::get('DNA_MERCHANT_TEST_TERMINAL_ID')
                        : Configuration::get('DNA_MERCHANT_TERMINAL_ID'),

                    // SUCCESS → order-confirmation
                    'returnUrl' => $this->getReturnlink($cart, $order_id, 'success'),

                    // CANCEL / FAILED → checkout (step=3)
                    'failureReturnUrl' => $this->getReturnlink($cart, $order_id, 'failed'),

                    // Webhook
                    'callbackUrl' => $this->context->link->getModuleLink(
                        $this->module->name,
                        'confirm',
                        [],
                        true
                    ),
                    'failureCallbackUrl' => $this->context->link->getModuleLink(
                        $this->module->name,
                        'confirm',
                        [],
                        true
                    ),
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
                'amountBreakdown'=> $this->getAmountBreakDown($cart),
                'orderLines'     => $this->getOrderLines($cart),
            ];

            // Transaction type
            $transactionType = Configuration::get('DNA_PAYMENT_TRANSACTION_TYPE');
            if ($transactionType && $transactionType !== 'default') {
                $data['transactionType'] = $transactionType;
            }

            // 10. Card vault
            if ($this->getConfigStore()->dna_payment_card_vault_enabled) {
                $data['periodic'] = [
                    'periodicType' => 'ucof',
                ];
            }

            echo json_encode($data);
            return;

        } catch (Exception $e) {
            PrestaShopLogger::addLog($e->getMessage(), 3);
            echo json_encode([
                'errors' => [
                    'Ooops, something went wrong! Please try again later.'
                ]
            ]);
            return;
        }
    }


    public function getAmountBreakDown(Cart $cart)
    {
        $productTotal = round((float)$cart->getOrderTotal(false, Cart::ONLY_PRODUCTS), 2);
        $shippingTotal = round((float)$cart->getOrderTotal(true, Cart::ONLY_SHIPPING), 2);
        $discountTotal = round((float)abs($cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS)), 2);

        $productTotalWithTax = round((float)$cart->getOrderTotal(true, Cart::ONLY_PRODUCTS), 2);
        $taxTotal = round($productTotalWithTax - $productTotal, 2);

        return [
            'itemTotal' => ['totalAmount' => $productTotal],
            'shipping' => ['totalAmount' => $shippingTotal],
            'taxTotal' => ['totalAmount' => $taxTotal],
            'discount' => ['totalAmount' => $discountTotal]
        ];
    }

    public function getOrderLines(Cart $cart)
    {
        $products = $cart->getProducts();
        $link = Context::getContext()->link;
        $orderLines = [];

        foreach ($products as $product) {

            $imageUrl = $link->getImageLink(
                isset($product['link_rewrite']) ? $product['link_rewrite'] : $product['name'],
                (int)$product['id_image'], 'medium_default'
            );

            $orderLines[] = [
                'reference' => $product['id_product'],
                'name' => $product['name'],
                'quantity' => $product['quantity'],
                'unitPrice' => $product['price'],
                'imageUrl' => $imageUrl,
                'productUrl' => $link->getProductLink($product),
                'totalAmount' => $product['total']
            ];
        }

        return $orderLines;
    }

    public function getAddress($address) {
        $country = new Country($address->id_country);
        return array(
            'firstName' => $address->firstname,
            'lastName' => $address->lastname,
            'addressLine1' => $address->address1,
            'addressLine2' => $address->address2,
            'postalCode' => $address->postcode,
            'city' => $address->city,
            'phone' =>  $address->phone ? $address->phone : $address->phone_mobile,
            'country' => $country->iso_code
        );
    }

    public function createOrder($cart)
    {
        try {
            $this->module->validateOrder(
                $cart->id,
                (int)Configuration::get('DNA_OS_AWAITING_PAYMENT'),
                $cart->getOrderTotal(),
                $this->module->displayName
            );
            return Order::getByCartId($cart->id);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function getReturnlink($cart, $order_id, $status)
    {
        // cancel / failed → back to checkout
        if ($status !== 'success') {
            return $this->context->link->getPageLink('order');
        }

        // SUCCESS
        if ($this->getConfigStore()->should_create_order_after_payment) {
            // go to the return controller, it MUST redirect to order-confirmation
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

        // if the order was created BEFORE payment
        return $this->module->helper->getBacklink($cart, $order_id);
    }

}
