<?php
class DnapaymentsReturnModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $this->context->smarty->assign(array(
            'id_cart' => Tools::getValue('id_cart'),
            'status' => Tools::getValue('status'),
            'check_url' => $this->context->link->getModuleLink($this->module->name, 'check')
        ));

        $this->setTemplate('module:dnapayments/views/templates/front/temp_return_page.tpl');
    }
}
