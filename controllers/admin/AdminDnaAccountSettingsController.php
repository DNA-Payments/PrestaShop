<?php

class AdminDnaAccountSettingsController extends ModuleAdminController
{
    public $values;
    public $parametres;

    public function __construct()
    {
        $this->controller_type = 'moduleadmin';
        $this->bootstrap = true;
        $this->parametres = array(
            'dna_payment_title',
            'dna_payment_description',
            'dna_merchant_client_id',
            'dna_merchant_client_secret',
            'dna_merchant_terminal_id',
            'dna_payment_test_mode',
            'dna_merchant_test_client_id',
            'dna_merchant_test_client_secret',
            'dna_merchant_test_terminal_id',
            'dna_payment_create_order_after_successful_payment',
            'dna_payment_integration_type',
            'dna_payment_back_link',
            'dna_payment_failure_back_link',
            'dna_payment_gateway_order_description'
        );
        parent::__construct();
    }

    public function initContent()
    {
        parent::initContent();
        $tpl_vars = array();
        $this->initAccountSettingsBlock();
        $formAccountSettings = $this->renderForm();
        $tpl_vars['formAccountSettings'] = $formAccountSettings;
        $this->context->smarty->assign($tpl_vars);
        $this->content = $this->context->smarty->fetch($this->getTemplatePath() . 'accountSettings.tpl');
        $this->context->smarty->assign('content', $this->content);
    }

    public function initAccountSettingsBlock()
    {
        $this->fields_form['form']['form'] = array(
            'legend' => array(
                'title' => $this->l('Account settings'),
                'icon' => 'icon-cogs',
            ),
            'input' => $this->getAccountSettingsFields(),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right button'
            )
        );
        $values = array(
            'dna_payment_title' => Configuration::get('DNA_PAYMENT_TITLE'),
            'dna_payment_description' => Configuration::get('DNA_PAYMENT_DESCRIPTION'),
            'dna_merchant_client_id' => Configuration::get('DNA_MERCHANT_CLIENT_ID'),
            'dna_merchant_client_secret' => Configuration::get('DNA_MERCHANT_CLIENT_SECRET'),
            'dna_merchant_terminal_id' => Configuration::get('DNA_MERCHANT_TERMINAL_ID'),
            'dna_payment_test_mode' => (boolean)Configuration::get('DNA_PAYMENT_TEST_MODE'),
            'dna_merchant_test_client_id' => Configuration::get('DNA_MERCHANT_TEST_CLIENT_ID'),
            'dna_merchant_test_client_secret' => Configuration::get('DNA_MERCHANT_TEST_CLIENT_SECRET'),
            'dna_merchant_test_terminal_id' => Configuration::get('DNA_MERCHANT_TEST_TERMINAL_ID'),
            'dna_payment_create_order_after_successful_payment' => (boolean)Configuration::get('DNA_PAYMENT_CREATE_ORDER_AFTER_SUCCESSFUL_PAYMENT'),
            'dna_payment_integration_type' => Configuration::get('DNA_PAYMENT_INTEGRATION_TYPE'),
            'dna_payment_back_link' => Configuration::get('DNA_PAYMENT_BACK_LINK'),
            'dna_payment_failure_back_link' => Configuration::get('DNA_PAYMENT_FAILURE_BACK_LINK'),
            'dna_payment_gateway_order_description' => Configuration::get('DNA_PAYMENT_GATEWAY_ORDER_DESCRIPTION'),
        );
        $this->tpl_form_vars = array_merge($this->tpl_form_vars, $values);
    }


    public function renderForm($fields_form = null)
    {
        if ($fields_form === null) {
            $fields_form = $this->fields_form;
        }
        $helper = new HelperForm();
        $helper->token = Tools::getAdminTokenLite($this->controller_name);
        $helper->currentIndex = AdminController::$currentIndex;
        $helper->submit_action = $this->controller_name . '_config';
        $default_lang = (int)\Configuration::get('PS_LANG_DEFAULT');
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;
        $helper->tpl_vars = array(
            'fields_value' => $this->tpl_form_vars,
            'id_language' => $this->context->language->id,
        );
        return $helper->generateForm($fields_form);
    }

    public function getAccountSettingsFields()
    {
        return array(
            array(
                'col' => 6,
                'type' => 'text',
                'desc' => 'This controls the title which the user sees during checkout.',
                'name' => 'dna_payment_title',
                'label' => $this->module->l('Title'),
                'required' => true

            ),
            array(
                'col' => 6,
                'type' => 'text',
                'desc' => 'This controls the description which the user sees during checkout.',
                'name' => 'dna_payment_description',
                'label' => $this->module->l('Description'),
                'required' => true
            ),
            array(
                'col' => 6,
                'type' => 'text',
                'name' => 'dna_merchant_client_id',
                'label' => $this->module->l('Client ID'),
                'required' => false
            ),
            array(
                'col' => 6,
                'type' => 'text',
                'name' => 'dna_merchant_client_secret',
                'label' => $this->module->l('Client Secret'),
                'required' => false
            ),
            array(
                'col' => 6,
                'type' => 'text',
                'name' => 'dna_merchant_terminal_id',
                'label' => $this->module->l('Terminal ID'),
                'required' => false
            ),
            array(
                'col' => 6,
                'type' => 'switch',
                'name' => 'dna_payment_test_mode',
                'label' => $this->module->l('Test mode'),
                'is_bool' => true,
                'values' => array(
                    array(
                        'value' => true,
                        'label' => $this->module->l('Active')
                    ),
                    array(
                        'value' => false,
                        'label' => $this->module->l('Inactive')
                    )
                ),
                'required' => false
            ),
            array(
                'col' => 6,
                'type' => 'text',
                'name' => 'dna_merchant_test_client_id',
                'label' => $this->module->l('Test Client ID'),
                'required' => false
            ),
            array(
                'col' => 6,
                'type' => 'text',
                'name' => 'dna_merchant_test_client_secret',
                'label' => $this->module->l('Test Client Secret'),
                'required' => false
            ),
            array(
                'col' => 6,
                'type' => 'text',
                'name' => 'dna_merchant_test_terminal_id',
                'label' => $this->module->l('Test Terminal ID'),
                'required' => false
            ),
            array(
                'col' => 6,
                'type' => 'switch',
                'name' => 'dna_payment_create_order_after_successful_payment',
                'label' => $this->module->l('Create an order only after a successful payment'),
                'desc' =>
                    $this->module->l('Selecting “Yes” ensures that an order is created and the shopping cart is emptied ONLY after the payment has been successfully processed.') .
                    '<br/><br/>' .
                    $this->module->l('Selecting “No” ensures that an order is created and the shopping cart is emptied after the payment has been processed with ANY status.'),
                'required' => false,
                'values' => array(
                    array(
                        'value' => true,
                        'label' => $this->module->l('Yes')
                    ),
                    array(
                        'value' => false,
                        'label' => $this->module->l('No')
                    )
                )
            ),
            array(
                'col' => 6,
                'type' => 'select',
                'name' => 'dna_payment_integration_type',
                'label' => $this->module->l('Payment form integration type'),
                'required' => false,
                'options' => array(
                    'id' => 'value',
                    'name' => 'label',
                    'query' => [
                        [ 'value' => 'hosted', 'label' => $this->module->l('Full Redirect') ],
                        [ 'value' => 'embedded', 'label' => $this->module->l('iFrame LightBox') ]
                    ]
                )
            ),
            array(
                'col' => 6,
                'type' => 'text',
                'desc' => 'URL for success page.',
                'name' => 'dna_payment_back_link',
                'label' => $this->module->l('Back Link'),
                'required' => false
            ),
            array(
                'col' => 6,
                'type' => 'text',
                'desc' => 'URL for failure page.',
                'name' => 'dna_payment_failure_back_link',
                'label' => $this->module->l('Failure Back Link'),
                'required' => false
            ),
            array(
                'col' => 6,
                'type' => 'text',
                'name' => 'dna_payment_gateway_order_description',
                'label' => $this->module->l('Gateway order description'),
                'required' => false
            ),
        );
    }

    public function postProcess()
    {

        if ((bool)Tools::isSubmit($this->controller_name . '_config')) {
            if ($this->saveForm()) {
                $this->displayInformation($this->module->l('Settings successful updated'));
            }
        }
        parent::postProcess();
    }

    public function saveForm()
    {
        $result = true;

        foreach (Tools::getAllValues() as $fieldName => $fieldValue) {
            if (in_array($fieldName, $this->parametres)) {
                $error_msg = $this->validateInput($fieldName, $fieldValue);
                if (!!$error_msg) {
                    $this->displayWarning($error_msg);
                }
                $result &= Configuration::updateValue(Tools::strtoupper($fieldName), pSQL($fieldValue));
            }
        }

        return $result;
    }

    public function validateInput($fieldName, $fieldValue) {
        $isTestMode = Tools::getAllValues()[strtolower('DNA_PAYMENT_TEST_MODE')];

        if (strtoupper($fieldName) == 'DNA_PAYMENT_TITLE') {
            if(!$fieldValue) {
                return $this->module->l('Title is required');
            }
        }

        if (strtoupper($fieldName) == 'DNA_PAYMENT_DESCRIPTION') {
            if(!$fieldValue) {
                return $this->module->l('Description is required');
            }
        }

        if (strtoupper($fieldName) == 'DNA_MERCHANT_CLIENT_ID') {
            if(!$fieldValue && !$isTestMode) {
                return $this->module->l('Client ID is required');
            }
        }

        if (strtoupper($fieldName) == 'DNA_MERCHANT_CLIENT_SECRET') {
            if(!$fieldValue && !$isTestMode) {
                return $this->module->l('Client Secret is required');
            }
        }

        if (strtoupper($fieldName) == 'DNA_MERCHANT_TERMINAL_ID') {
            if(!$fieldValue && !$isTestMode) {
                return $this->module->l('Terminal ID is required');
            }
        }

        if($isTestMode) {
            if (strtoupper($fieldName) == 'DNA_MERCHANT_TEST_CLIENT_ID') {
                if(!$fieldValue) {
                    return $this->module->l('Test Client ID is required');
                }
            }

            if (strtoupper($fieldName) == 'DNA_MERCHANT_TEST_CLIENT_SECRET') {
                if(!$fieldValue) {
                    return $this->module->l('Test Client Secret is required');
                }
            }

            if (strtoupper($fieldName) == 'DNA_MERCHANT_TEST_TERMINAL_ID') {
                if(!$fieldValue) {
                    return $this->module->l('Test Terminal ID is required');
                }
            }
        }

        return false;
    }
}