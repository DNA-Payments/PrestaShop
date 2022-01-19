<?php

class DnapaymentsCheckModuleFrontController extends ModuleFrontController
{

    public function init()
    {
        parent::init();

        $helper = $this->module->helper;

        $id_cart = (int) Tools::getValue('id_cart');
        $status = Tools::getValue('status');

        $transaction = new DnapaymentsTransaction();
        $transaction->getDnapaymentsTransactionByCart($id_cart);
    
        $link = $status == 'success' ?
            $helper->getBacklink(new Cart($id_cart), $transaction->id_order) :
            $helper->getFailureBackLink();

        die(json_encode([
            'isCompleted' => $transaction->isCompleted(),
            'link' => $link
        ]));
    }
}
