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
        $test_mode = (boolean)Configuration::get('DNA_PAYMENT_TEST_MODE');

        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'dnapayments') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'validation'));
        }

        $validateOrders = $this->validateOrderFields($cart);

        if(count($validateOrders) > 0) {
            echo json_encode(array(
                'errors' => $validateOrders
            ));
            return;
        }

        $invoiceId = '';
        $order_id = 0;
        if (!$this->getConfigStore()->should_create_order_after_payment) {
            $order = $this->createOrder($cart);
            $invoiceId = $order->id;
            $order_id = $order->id;
        } else {
            $invoiceId = $cart->id;
        }

        $invoiceId = DNA_ORDER_PREFIX . $invoiceId . '_' . date("YmdHis");

        $customer = new Customer($cart->id_customer);
        $billingAddress = new Address($cart->id_address_invoice);
        $shippingAddress = new Address($cart->id_address_delivery);
        $country = new Country($billingAddress->id_country);
        $currency = new Currency((int) $cart->id_currency);

        try {
            $auth = $this->getDnaPayment()->auth(
                $this->module->helper->getAuthData($invoiceId, $cart->getOrderTotal(), $currency->iso_code)
            );

            $transaction = new DnapaymentsTransaction();
            $transaction->getDnapaymentsTransactionByCart($cart->id);
            $transaction->status = Configuration::get('DNA_OS_AWAITING_PAYMENT');
            $transaction->id_customer = $cart->id_customer;
            $transaction->id_cart = $cart->id;
            $transaction->id_order = $order_id;
            $transaction->dnaOrderId = $invoiceId;
            $transaction->amount = $cart->getOrderTotal();
            $transaction->currency = $currency->iso_code;
            $transaction->save();
            
            $data = array(
                'auth' => $auth,
                'invoiceId' => $invoiceId,
                'description' => Configuration::get('DNA_PAYMENT_GATEWAY_ORDER_DESCRIPTION'),
                'amount' => $cart->getOrderTotal(),
                'currency' => $currency->iso_code,
                'paymentSettings' => array(
                    'terminalId' => $test_mode ? Configuration::get('DNA_MERCHANT_TEST_TERMINAL_ID') : Configuration::get('DNA_MERCHANT_TERMINAL_ID'),
                    'returnUrl' => $this->getReturnlink($cart, $order_id, 'success'),
                    'failureReturnUrl' => $this->getReturnlink($cart, $order_id, 'failed'),
                    'callbackUrl' => $this->context->link->getModuleLink($this->module->name, 'confirm'),
                    'failureCallbackUrl' => $this->context->link->getModuleLink($this->module->name, 'confirm')
                ),
                'customerDetails' => array(
                    'email' => $customer->email,
                    'accountDetails' => array(
                        'accountId' => $cart->id_customer ? $cart->id_customer : '',
                    ),
                    'billingAddress' => $this->getAddress($billingAddress),
                    'deliveryDetails' => array(
                        'deliveryAddress' => $this->getAddress($shippingAddress)
                    )
                ),
                'language' => 'en-gb',
                'amountBreakdown' => $this->getAmountBreakDown($cart),
                'orderLines' => $this->getOrderLines($cart)
            );

            $transactionType = Configuration::get('DNA_PAYMENT_TRANSACTION_TYPE');
            if ($transactionType && $transactionType != 'default') {
                $data['transactionType'] = $transactionType;
            }

            if ($this->getConfigStore()->dna_payment_card_vault_enabled) {
                $data['periodic'] = array(
                    'periodicType' => 'ucof'
                );
            }

            echo json_encode($data);
            return;
        }
        catch (Exception $exception) {
            PrestaShopLogger::addLog($exception->getMessage(), 3);
            echo json_encode(array(
                'errors' => array(
                    'Ooops, something went wrong! Please check system API credentials or try later'
                )
            ));
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

    public function getReturnlink($cart, $order_id, $status) {
        $link = $status == 'success' ? $this->module->helper->getBacklink($cart, $order_id) : $this->module->helper->getFailureBackLink();

        if (!$this->getConfigStore()->should_create_order_after_payment) {
            return $link;
        }
        return $this->context->link->getModuleLink($this->module->name, 'return', array('id_cart' => $cart->id, 'status' => $status));
    }
}
