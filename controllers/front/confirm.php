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

        $statusId = $helper->validateAndGetStatus($input);

        $order = $helper->createOrder($input, $statusId);

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
