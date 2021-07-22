<?php
class DnapaymentsOrderFailureResultModuleFrontController extends ModuleFrontController
{

    public function init()
    {
        parent::init();
    }

    public function initContent()
    {
        parent::initContent();

        $this->setTemplate('module:dnapayments/views/templates/front/failure_page.tpl');
    }
}