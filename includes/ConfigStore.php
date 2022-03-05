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
    public $client_id;
    /**
     * @var string
     */
    public $client_secret;
    /**
     * @var string
     */
    public $terminal_id;
    /**
     * @var bool
     */
    public $should_create_order_after_payment = true;
    /**
     * @var bool
     */
    public $should_create_order_after_only_successful_payment = true;

    public function __construct()
    {
        $this->is_test = (boolean)Configuration::get('DNA_PAYMENT_TEST_MODE');
        $this->client_id = $this->is_test ? Configuration::get('DNA_MERCHANT_TEST_CLIENT_ID') : Configuration::get('DNA_MERCHANT_CLIENT_ID');
        $this->client_secret = $this->is_test ? Configuration::get('DNA_MERCHANT_TEST_CLIENT_SECRET') : Configuration::get('DNA_MERCHANT_CLIENT_SECRET');
        $this->terminal_id = $this->is_test ? Configuration::get('DNA_MERCHANT_TEST_TERMINAL_ID') : Configuration::get('DNA_MERCHANT_TERMINAL_ID');
        $this->should_create_order_after_only_successful_payment = Configuration::get('DNA_PAYMENT_CREATE_ORDER_AFTER_SUCCESSFUL_PAYMENT');
        $this->dna_payment_card_vault_enabled = (boolean)Configuration::get('DNA_PAYMENT_CARD_VAULT_ENABLED');
    }

    public static function isDNAPaymentOrder($order) {
        return DNA_PAYMENT_METHOD_CODE === $order->module;
    }
}