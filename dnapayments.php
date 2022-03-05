<?php

error_reporting(E_ERROR);
ini_set('display_errors', 'On');

define('DNA_PAYMENT_METHOD_CODE', 'dnapayments');
define('DNA_ROOT_URL', dirname(__FILE__));
define('DNA_VERSION', '1.4.1');
define('DNA_ORDER_PREFIX', 'PS_');

require_once DNA_ROOT_URL.'/vendor/autoload.php';
require_once DNA_ROOT_URL.'/includes/ConfigStore.php';
require_once DNA_ROOT_URL.'/classes/DnapaymentsTransaction.php';
require_once DNA_ROOT_URL.'/classes/DnapaymentsAccountCard.php';
require_once DNA_ROOT_URL.'/classes/DnapaymentsHelper.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

class Dnapayments extends PaymentModule
{
    public $moduleConfigs = array();

    /** @var DnapaymentsHelper */
    public $helper;

    public function __construct()
    {
        $this->helper = DnapaymentsHelper::getInstance($this);
        $this->loadFiles();
        $this->name = DNA_PAYMENT_METHOD_CODE;
        $this->tab = 'payments_gateways';
        $this->version = DNA_VERSION;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->author = 'DNA Payments';
        $this->controllers = array( 'order', 'confirm', 'orderFailureResult');
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
            'DNA_PAYMENT_CREATE_ORDER_AFTER_SUCCESSFUL_PAYMENT' => 1,
            'DNA_PAYMENT_CARD_VAULT_ENABLED' => 0,
            'DNA_PAYMENT_TRANSACTION_TYPE' => 'default',
            'DNA_PAYMENT_INTEGRATION_TYPE' => 'hosted',
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
        'actionEmailSendBefore',
        'actionOrderStatusUpdate',
        'displayAdminOrderMainBottom'
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

        $table_name = _DB_PREFIX_."dnapayments_transactions";

        $createSql = "CREATE TABLE IF NOT EXISTS `".$table_name."` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `status` int(2) NOT NULL,
            `id_customer` int(11) NOT NULL,
            `id_cart` int(11) NOT NULL,
            `dnaOrderId` varchar(100) NOT NULL,
            `id_order` int(11) NOT NULL,
            `rrn` varchar(50) NOT NULL,
            `paypal_status` varchar(50) NOT NULL,
            `paypal_capture_status` varchar(50) NOT NULL,
            `paypal_capture_status_reason` varchar(255) NOT NULL,
            `payment_method` varchar(50) NOT NULL,
            `id_transaction` varchar(100) NOT NULL,
            `amount` float NOT NULL,
            `currency` varchar(8) NOT NULL,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE="._MYSQL_ENGINE_." DEFAULT CHARSET=utf8;";

        if (!Db::getInstance()->execute($createSql)) {
            return false;
        }

        $checkSql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '".$table_name."' AND column_name = 'paypal_status';";
        $hasColumn = ((int) Db::getInstance()->getValue($checkSql)) > 0;

        if (!$hasColumn) {
            $alterSql = "ALTER TABLE `".$table_name."`
                ADD COLUMN `rrn` varchar(50) NOT NULL,
                ADD COLUMN `paypal_status` varchar(50) NOT NULL,
                ADD COLUMN `paypal_capture_status` varchar(50) NOT NULL,
                ADD COLUMN `paypal_capture_status_reason` varchar(255) NOT NULL,
                ADD COLUMN `payment_method` varchar(50) NOT NULL;
            ";

            if (!Db::getInstance()->execute($alterSql)) {
                return false;
            }
        }

        return $this->createAccountCardTable();
    }

    public function createAccountCardTable()
    {
        $table_name = _DB_PREFIX_."dnapayments_account_cards";

        $createSql = "CREATE TABLE IF NOT EXISTS `".$table_name."` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `accountId` varchar(100) NOT NULL,
            `cardTokenId` varchar(100) NOT NULL,
            `cardPanStarred` varchar(100) NOT NULL,
            `cardSchemeId` varchar(100) NOT NULL,
            `cardSchemeName` varchar(100) NOT NULL,
            `cardAlias` varchar(100) NOT NULL,
            `cardExpiryDate` varchar(10) NOT NULL,
            `cardholderName` varchar(100) NOT NULL,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE="._MYSQL_ENGINE_." DEFAULT CHARSET=utf8;";

        if (!Db::getInstance()->execute($createSql)) {
            return false;
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
        // return Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'dnapayments_transactions`');
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
        $cart = $this->context->cart;
        $account_id = $cart->id_customer;
        $cards = [];

        if ($this->helper->configStore->dna_payment_card_vault_enabled) {
            $cards = DnapaymentsAccountCard::getAccountCards($account_id);
        }
        
        $this->context->smarty->assign(array(
            'cards' => json_encode($cards),
            'order_url' => $this->context->link->getModuleLink($this->name, 'order'),
            'terminal_id' => Configuration::get('DNA_MERCHANT_TERMINAL_ID'),
            'test_mode' => (boolean)Configuration::get('DNA_PAYMENT_TEST_MODE'),
            'test_terminal_id' => Configuration::get('DNA_MERCHANT_TEST_TERMINAL_ID'),
            'transaction_type' => Configuration::get('DNA_PAYMENT_TRANSACTION_TYPE'),
            'integration_type' => Configuration::get('DNA_PAYMENT_INTEGRATION_TYPE')
        ));

        return $this->context->smarty->fetch( DNA_ROOT_URL.'/views/templates/front/payment_form.tpl');
    }

    public function getBaseUrl() {
        return _PS_BASE_URL_.__PS_BASE_URI__;
    }

    /** Display last order information on Admin > Orders > Order */
    public function hookDisplayAdminOrderMainBottom($params)
    {
        $id_order = (int)$params['id_order'];

        $data = Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'dnapayments_transactions`
        WHERE `id_cart` = (SELECT `id_cart` FROM `'._DB_PREFIX_.'orders` WHERE `id_order` = "'.$id_order.'")');
        if (!$data) {
            return false;
        } else {
            /** Link the info to query */
            return $this->buildOrderMessage($data);
        }
    }

    /** Hook for order status update action */
    public function hookActionOrderStatusUpdate($params)
    {
        $status = $params['newOrderStatus'] ? (int)$params['newOrderStatus']->id : false;
        $order_id = $params['id_order'];

        $transaction = new DnapaymentsTransaction();
        $transaction->getDnapaymentsTransactionByOrderId($order_id);

        $order = new Order($order_id);
        $dnaPayment = $this->helper->dnaPayment;
        $configStore = $this->helper->configStore;

        if (
            !$status ||
            !$transaction->isExists() ||
            !Validate::isLoadedObject($order) ||
            !$configStore::isDNAPaymentOrder($order)
        ) {
            return;
        }
        
        $data = [
            'client_id' => $configStore->client_id,
            'client_secret' => $configStore->client_secret,
            'terminal' => $configStore->terminal_id,
            'invoiceId' => strval($transaction->dnaOrderId),
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'transaction_id' => $transaction->id_transaction
        ];

        $paypalCaptureStatus = $transaction->paypal_capture_status;
        $isNotValidPayPalStatus = $transaction->payment_method === 'paypal' && !$this->helper->isValidStatusPayPalStatus($transaction);

        $result = null;
        $errorText = '';

        switch ($status) {
            case (int)Configuration::get('PS_OS_PAYMENT'):
                if ($transaction->status == (int)Configuration::get('DNA_OS_WAITING_CAPTURE')) {
                    if ($isNotValidPayPalStatus) {
                        $errorText = sprintf( 'DNA Paypal payment could not be captured with status: %s', $paypalCaptureStatus);
                    } else {
                        try {
                            $result = $dnaPayment->charge($data);
                        } catch (Exception $e) {
                            PrestaShopLogger::addLog($e->getMessage(), 3);
                        }
                    }
                }
                break;
            case (int)Configuration::get('PS_OS_REFUND'):
                if ($transaction->status == (int)Configuration::get('PS_OS_PAYMENT')) {
                    if ($isNotValidPayPalStatus) {
                        $errorText = sprintf( 'DNA Paypal payment could not be refund with status: %s', $paypalCaptureStatus);
                    } else {
                        $result = $dnaPayment->refund($data);
                    }
                }
                break;
            case (int)Configuration::get('PS_OS_CANCELED'):
                if ($transaction->status == (int)Configuration::get('DNA_OS_WAITING_CAPTURE')) {
                    if ($isNotValidPayPalStatus) {
                        $errorText = sprintf( 'DNA Paypal payment could not be cancel with status: %s', $paypalCaptureStatus);
                    } else {
                        $result = $dnaPayment->cancel($data);
                    }
                }
                break;
        }

        if (strlen($errorText) > 0) {
            $orderMessage = new Message();
            $orderMessage->id_order = $transaction->id_order;
            $orderMessage->message = $errorText;
            $orderMessage->private = true;
            $orderMessage->save();
        }

        if (!empty($result) && $result['success']) {
            $transaction->status = $status;
            $transaction->id_transaction = $result['id'];
            $transaction->save();
        }
    }

    /** Load template with assigned data */
    public function buildOrderMessage($data)
    {
        $this->context->smarty->assign('data', $data);
        return $this->display(__FILE__, 'views/templates/admin/payment_message.tpl');
    }
}
