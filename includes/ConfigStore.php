<?php
require_once DNA_ROOT_URL.'/vendor/autoload.php';

class ConfigStore {
    /**
     * @var bool
     */
    public $is_test;
    /**
     * @var string
     */
    public $cliend_id;
    /**
     * @var string
     */
    public $client_secret;
    /**
     * @var string
     */
    public $terminal_id;

    public function __construct()
    {
        $this->is_test = (boolean)Configuration::get('DNA_PAYMENT_TEST_MODE');
        $this->cliend_id = $this->is_test ? Configuration::get('DNA_MERCHANT_TEST_CLIENT_ID') : Configuration::get('DNA_MERCHANT_CLIENT_ID');
        $this->client_secret = $this->is_test ? Configuration::get('DNA_MERCHANT_TEST_CLIENT_SECRET') : Configuration::get('DNA_MERCHANT_CLIENT_SECRET');
        $this->terminal_id = $this->is_test ? Configuration::get('DNA_MERCHANT_TEST_TERMINAL_ID') : Configuration::get('DNA_MERCHANT_TERMINAL_ID');
    }

    public static function isDNAPaymentOrder($order) {
        return DNA_PAYMENT_METHOD_CODE === $order->module;
    }
}