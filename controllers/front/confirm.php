<?php

class DnapaymentsConfirmModuleFrontController extends ModuleFrontController
{

    public function init()
    {
        parent::init();

        header('Content-Type: application/json');

        try {
            $helper = $this->module->helper;
            $input = json_decode(file_get_contents('php://input'), true);

            $statusId = $helper->validateAndGetStatus($input);

            if (
                $helper->configStore->should_create_order_after_only_successful_payment
                && !in_array($statusId, [
                    Configuration::get('PS_OS_PAYMENT'),
                    Configuration::get('DNA_OS_WAITING_CAPTURE'),
                ])
            ) {
                die(json_encode(['orderId' => null]));
            }

            if ($helper->configStore->dna_payment_card_vault_enabled) {
                $helper->saveCard($input);
            }

            $order = $helper->createOrder($input, $statusId);

            die(json_encode([
                'orderId' => $order ? (int)$order->id : null,
            ]));
        } catch (Throwable $e) {
            PrestaShopLogger::addLog($e->getMessage(), 3);
            http_response_code(400);
            die(json_encode([
                'errors' => ['Ooops, something went wrong! Please try again later.'],
            ]));
        }
    }

    public function postProcess()
    {
        if ($this->context->cart && $this->context->cart->orderExists()) {
            return;
        }

        parent::postProcess();
    }

}
