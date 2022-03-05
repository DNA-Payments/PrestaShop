<?php

class DnapaymentsConfirmModuleFrontController extends ModuleFrontController
{

    public function init()
    {
        parent::init();

        $helper = $this->module->helper;
        $input = json_decode(file_get_contents('php://input'), true);
        $status_id = $helper->validateAndGetStatus($input);

        if (
            $helper->configStore->should_create_order_after_only_successful_payment
            && !in_array($status_id, [Configuration::get('PS_OS_PAYMENT'), Configuration::get('DNA_OS_WAITING_CAPTURE')])
        ) {
            die(json_encode([ 'orderId' => null]));
        }

        if ($helper->configStore->dna_payment_card_vault_enabled) {
            $helper->saveCard($input);
        }
        $order = $helper->createOrder($input, $status_id);
        die(json_encode([ 'orderId' => $order->id]));
    }
}
