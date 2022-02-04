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
            $invoiceId = DNA_ORDER_PREFIX . $cart->id;
        }

        $customer = new Customer($cart->id_customer);
        $address = new Address($cart->id_address_delivery);
        $country = new Country($address->id_country);
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
                'accountId' => $cart->id_customer,
                'firstname' => $address->firstname,
                'lastname' => $address->lastname,
                'address1' => $address->address1,
                'city' => $address->city,
                'country' => $country->iso_code,
                'phone' => $address->phone ? $address->phone : $address->phone_mobile,
                'email' => $customer->email,
                'postcode' => $address->postcode,
                'orderId' => $invoiceId,
                'currency' => $currency->iso_code,
                'amount' => $cart->getOrderTotal(),
                'description' => Configuration::get('DNA_PAYMENT_GATEWAY_ORDER_DESCRIPTION'),
                'postLink' => $this->context->link->getModuleLink($this->module->name, 'confirm'),
                'failurePostLink' => $this->context->link->getModuleLink($this->module->name, 'confirm'),
                'backLink' => $this->getReturnlink($cart, $order_id, 'success'),
                'failureBackLink' => $this->getReturnlink($cart, $order_id, 'failed')
            );
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
