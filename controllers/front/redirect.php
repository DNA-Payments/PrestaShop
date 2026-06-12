<?php

class DnapaymentsRedirectModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        try {
            $cart = $this->context->cart;
            $builder = new DnapaymentsPaymentBuilder($this->module);

            $errors = $builder->validate($cart);
            if (!empty($errors)) {
                PrestaShopLogger::addLog('DNA Payments redirect: ' . implode('; ', $errors), 3);
                Tools::redirect($this->module->helper->getFailureBackLink());
                return;
            }

            $data = $builder->build($cart);

            $auth = isset($data['auth']) ? $data['auth'] : null;
            unset($data['auth']);

            if (empty($auth)) {
                throw new Exception('DNA auth token is empty');
            }

            // Build the hosted checkout URL on the server and redirect to it.
            $url = \DNAPayments\DNAPayments::generateUrl($data, $auth);

            Tools::redirect($url);
        } catch (Throwable $e) {
            PrestaShopLogger::addLog($e->getMessage(), 3);
            Tools::redirect($this->module->helper->getFailureBackLink());
        }
    }
}
