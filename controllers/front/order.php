<?php

class DnapaymentsOrderModuleFrontController extends ModuleFrontController
{
    public function displayAjaxCreateOrder()
    {
        header('Content-Type: application/json');

        try {
            $cart = $this->context->cart;
            $builder = new DnapaymentsPaymentBuilder($this->module);

            $errors = $builder->validate($cart);
            if (!empty($errors)) {
                echo json_encode(['errors' => $errors]);
                return;
            }

            echo json_encode($builder->build($cart));
            return;
        } catch (Throwable $e) {
            PrestaShopLogger::addLog($e->getMessage(), 3);
            echo json_encode([
                'errors' => [
                    'Ooops, something went wrong! Please try again later.'
                ]
            ]);
            return;
        }
    }
}
