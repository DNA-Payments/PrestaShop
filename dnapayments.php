<?php

error_reporting(E_ALL);
ini_set('display_errors', 'On');

define('DNA_PAYMENT_METHOD_CODE', 'dnapayments');
define('DNA_ROOT_URL', dirname(__FILE__));
define('DNA_VERSION', '1.0.0');

if (!defined('_PS_VERSION_')) {
    exit;
}

class Dnapayments extends PaymentModule
{
    public $address;
    public $extra_mail_vars;
    public $context;
    public $moduleConfigs = array();

    public function __construct()
    {
        $this->loadFiles();
        $this->name = DNA_PAYMENT_METHOD_CODE;
        $this->tab = 'payments_gateways';
        $this->version = DNA_VERSION;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->author = 'DNA Payments';
        $this->controllers = array( 'order', 'orderManager', 'orderFailureResult');
        $this->need_instance = 1;
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->is_configurable = true;
        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('DNA Payments');
        $this->description = $this->l('Card Payment - Powered by DNA Payments');
        $this->module_link = $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
        $this->moduleConfigs = array(
            'DNA_PAYMENT_TITLE' => 'Visa / Mastercard / American Express / Diners Club / Other',
            'DNA_PAYMENT_DESCRIPTION' => 'Card payment method',
            'DNA_MERCHANT_CLIENT_ID' => '',
            'DNA_MERCHANT_CLIENT_SECRET' => '',
            'DNA_MERCHANT_TERMINAL_ID' => '',
            'DNA_PAYMENT_TEST_MODE' => 0,
            'DNA_MERCHANT_TEST_CLIENT_ID' => '',
            'DNA_MERCHANT_TEST_CLIENT_SECRET' => '',
            'DNA_MERCHANT_TEST_TERMINAL_ID' => '',
            'DNA_PAYMENT_BACK_LINK' => '',
            'DNA_PAYMENT_FAILURE_BACK_LINK' => '',
            'DNA_PAYMENT_GATEWAY_ORDER_DESCRIPTION' => 'Pay with your credit card via our payment gateway'
        );
    }

    /**
     * List of admin tabs used in this Module
     */
    public $moduleAdminControllers = array(
        array(
            'name' => array(
                'en' => 'DNA Payment',
            ),
            'class_name' => 'AdminParentDnaConfiguration',
            'parent_class_name' => 'SELL',
            'visible' => false,
            'icon' => 'payment'
        ),
        array(
            'name' => array(
                'en' => 'Configuration'
            ),
            'class_name' => 'AdminConfigurationController',
            'parent_class_name' => 'AdminParentDnaConfiguration',
            'visible' => false,
        ),
        array(
            'name' => array(
                'en' => 'Account settings'
            ),
            'class_name' => 'AdminDnaAccountSettings',
            'parent_class_name' => 'AdminConfigurationController',
            'visible' => true
        )
    );

    /**
     * List of hooks used in this Module
     */
    public $hooks = array(
        'paymentOptions',
        'actionEmailSendBefore'
    );


    /**
     * Load the configuration form
     *
     * @return mixed
     * @throws Exception
     */
     public function getContent() {
         return Tools::redirectAdmin($this->context->link->getAdminLink('AdminDnaAccountSettings'));
     }


    /**
     * Load files
     *
     * @return void
     */
    public function loadFiles()
    {
        require_once DNA_ROOT_URL . '/install/ModuleInstaller.php';
        require_once DNA_ROOT_URL . '/includes/model/Transaction.php';
    }

    /**
     *
     * Reset Module only if merchant choose to keep data on modal
     *
     * @return bool
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function reset()
    {
        $installer = new ModuleInstaller($this);

        return $installer->reset($this);
    }

    public function install()
    {

        $installer = new ModuleInstaller($this);

        $isPhpVersionCompliant = false;
        try {
            $isPhpVersionCompliant = $installer->checkPhpVersion();
        } catch (\Exception $e) {
            $this->_errors[] = Tools::displayError($e->getMessage());
        }

        if (($isPhpVersionCompliant && parent::install() && $installer->install()) == false) {
            return false;
        }

        $shops = Shop::getShops();

        foreach ($this->moduleConfigs as $key => $value) {
            if (Shop::isFeatureActive()) {
                foreach ($shops as $shop) {
                    if (!Configuration::updateValue($key, $value, false, null, (int)$shop['id_shop'])) {
                        return false;
                    }
                }
            } else {
                if (!Configuration::updateValue($key, $value)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function uninstall()
    {
        $installer = new ModuleInstaller($this);

        if (parent::uninstall() == false) {
            return false;
        }

        if ($installer->uninstall() == false) {
            return false;
        }

        return true;
    }

    public function hookActionEmailSendBefore($params) {
        if($params['cart'] && $params['cart']->id) {
            $id = $params['cart']->id;
            $order = Order::getOrderByCartId((int)($id));
            $order_details = new Order((int)($order));

            if($params['template'] === 'order_conf' && (int)$order_details->current_state === (int)Configuration::get('DNA_OS_AWAITING_PAYMENT')){
                return false;
            }
        }
        return true;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        return [
            $this->getRedirectPaymentOption()
        ];
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getRedirectPaymentOption()
    {

        $externalOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $this->context->smarty->assign(array(
            'description' => Configuration::get('DNA_PAYMENT_DESCRIPTION')
        ));
        $externalOption->setCallToActionText($this->l(Configuration::get('DNA_PAYMENT_TITLE')))
                       ->setForm($this->generateForm())
                       ->setAdditionalInformation($this->context->smarty->fetch( DNA_ROOT_URL.'/views/templates/front/payment_infos.tpl'))
                       ->setLogo(Media::getMediaPath(DNA_ROOT_URL.'/logo_small.png'));

        return $externalOption;
    }

    protected function generateForm()
    {
        $this->context->smarty->assign(array(
            'order_url' => $this->context->link->getModuleLink($this->name, 'order'),
            'terminal_id' => Configuration::get('DNA_MERCHANT_TERMINAL_ID'),
            'test_mode' => (boolean)Configuration::get('DNA_PAYMENT_TEST_MODE'),
            'test_terminal_id' => Configuration::get('DNA_MERCHANT_TEST_TERMINAL_ID'),
            'gateway_order_description' => Configuration::get('DNA_PAYMENT_GATEWAY_ORDER_DESCRIPTION'),
            'postLink' => $this->context->link->getModuleLink($this->name, 'orderManager', array(
                    'action' => 'confirmOrder'
            )),
            'failurePostLink' => $this->context->link->getModuleLink($this->name, 'orderManager', array(
                'action' => 'cancelOrder'
            )),
            'failureBackLink' => Configuration::get('DNA_PAYMENT_FAILURE_BACK_LINK') ? $this->getBaseUrl().Configuration::get('DNA_PAYMENT_FAILURE_BACK_LINK') : $this->context->link->getModuleLink($this->name, 'orderFailureResult')
        ));

        return $this->context->smarty->fetch( DNA_ROOT_URL.'/views/templates/front/payment_form.tpl');
    }

    public function getBaseUrl() {
        return _PS_BASE_URL_.__PS_BASE_URI__;
    }
}
