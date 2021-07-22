<?php

require_once DNA_ROOT_URL.'/vendor/autoload.php';
require_once DNA_ROOT_URL.'/includes/ConfigStore.php';

class DnapaymentsOrderModuleFrontController extends ModuleFrontController
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
    

    private function getAuthData($id, $amount, $currency) {
        return array(
            'client_id' => $this->configStore->cliend_id,
            'client_secret' => $this->configStore->client_secret,
            'terminal' => $this->configStore->terminal_id,
            'invoiceId' => strval($id),
            'amount' => floatval($amount),
            'currency' => $currency
        );
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
        $order = $this->createOrder($cart);
        $customer = new Customer($order->id_customer);
        $address = new Address($order->id_address_delivery);
        $country = new Country($address->id_country);
        $currency = new Currency((int) $cart->id_currency);

        try {
            $auth = $this->dnaPayment->auth(
                $this->getAuthData($order->id, $cart->getOrderTotal(), $currency->iso_code)
            );


            $data = array(
                'auth' => $auth,
                'accountId' => $cart->id_customer,
                'firstname' => $address->firstname,
                'lastname' => $address->lastname,
                'address1' => $address->address1,
                'city' => $address->city,
                'country' => $country->iso_code,
                'email' => $customer->email,
                'postcode' => $address->postcode,
                'orderId' => $order->id,
                'currency' => $currency->iso_code,
                'amount' => $cart->getOrderTotal(),
                'backLink' => Configuration::get('DNA_PAYMENT_BACK_LINK') ? $this->module->getBaseUrl().Configuration::get('DNA_PAYMENT_BACK_LINK') : $this->getOrderConfirmationLink($cart, $order)
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

    public function getOrderConfirmationLink($cart, $order)
    {
        $url = $this->context->link->getPageLink('order-confirmation', true);
        $url .= '?key=' . $order->secure_key;
        $url .= '&total=' . $cart->getOrderTotal();
        $url .= '&id_cart=' . $order->id_cart;
        $url .= '&id_order=' . $order->id;
        $url .= '&id_module=' . $this->module->id;

        return $url;
    }
}
