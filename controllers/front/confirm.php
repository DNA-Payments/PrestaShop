<?php

class DnapaymentsConfirmModuleFrontController extends ModuleFrontController
{

    public function init()
    {
        if (Tools::getValue('module') === 'dnapayments') {
            $this->display_header = false;
            $this->display_footer = false;
        }
        parent::init();

        $helper = $this->module->helper;
        $input = json_decode(file_get_contents('php://input'), true);

        PrestaShopLogger::addLog(
            '[DNA CONFIRM] Incoming payload: ' . json_encode($input),
            1
        );

        $statusId = $helper->validateAndGetStatus($input);

        PrestaShopLogger::addLog(
            '[DNA CONFIRM] Status resolved: ' . $statusId,
            1
        );

        $order = $helper->createOrder($input, $statusId);

        PrestaShopLogger::addLog(
            '[DNA CONFIRM] Order processed. ID=' . ($order ? $order->id : 'null'),
            1
        );

        die(json_encode([
            'orderId' => $order ? (int)$order->id : null
        ]));
    }

    public function postProcess()
    {
        if ($this->context->cart && $this->context->cart->orderExists()) {
            return;
        }

        parent::postProcess();
    }

}
