<?php

/**
 * Sends plugin and platform versions to the DNA telemetry API,
 * the same way the WooCommerce and Magento 2 plugins do.
 *
 * Data is sent only when it changed since the last successful send
 * (a hash of the payload is kept in the DNA_ANALYTICS_HASH configuration value).
 */
class DnapaymentsAnalytics
{
    const TELEMETRY_API_URL_PROD = 'https://telemetry-api.dnapayments.com/v1/cms-plugins-versions';
    const TELEMETRY_API_URL_TEST = 'https://test-telemetry-api.dnapayments.com/v1/cms-plugins-versions';
    const REQUEST_TIMEOUT = 10;
    const HASH_CONFIG_KEY = 'DNA_ANALYTICS_HASH';

    /** @var DnapaymentsHelper */
    private $helper;

    public function __construct($helper)
    {
        $this->helper = $helper;
    }

    public function getAnalyticsData()
    {
        $configStore = $this->helper->configStore;

        return array(
            'integrationType' => (string)Configuration::get('DNA_PAYMENT_INTEGRATION_TYPE'),
            'terminalId' => $configStore->terminal_id,
            'phpVersion' => phpversion(),
            'domainName' => !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : Tools::getShopDomain(false),
            'pluginVersion' => DNA_VERSION,
            'cmsPlatformName' => 'PrestaShop',
            'cmsPlatformVersion' => _PS_VERSION_,
        );
    }

    public function sendAnalytics()
    {
        try {
            $configStore = $this->helper->configStore;
            if (empty($configStore->client_id) || empty($configStore->client_secret) || empty($configStore->terminal_id)) {
                return;
            }

            $data = $this->getAnalyticsData();
            $hash = hash('sha256', json_encode($data));

            if ($hash === Configuration::getGlobalValue(self::HASH_CONFIG_KEY)) {
                return;
            }

            // SDK 1.x has no client-token call, so a token is requested the same way as for a payment
            $auth = \DNAPayments\DNAPayments::auth(array(
                'client_id' => $configStore->client_id,
                'client_secret' => $configStore->client_secret,
                'terminal' => $configStore->terminal_id,
                'invoiceId' => '',
                'amount' => 1,
                'currency' => 'GBP',
            ));
            if (empty($auth['access_token'])) {
                throw new Exception('DNA auth token is empty');
            }

            $this->post($auth['access_token'], $data, $configStore->is_test);

            Configuration::updateGlobalValue(self::HASH_CONFIG_KEY, $hash);
        } catch (Exception $e) {
            $this->logError($e);
        } catch (Throwable $e) {
            $this->logError($e);
        }
    }

    private function post($accessToken, array $data, $isTest)
    {
        $response = Requests::post(
            $isTest ? self::TELEMETRY_API_URL_TEST : self::TELEMETRY_API_URL_PROD,
            array(
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ),
            json_encode($data),
            array('timeout' => self::REQUEST_TIMEOUT)
        );

        if ((int)$response->status_code !== 200) {
            throw new Exception('Telemetry API Error [' . $response->status_code . ']: ' . $response->body);
        }
    }

    private function logError($e)
    {
        PrestaShopLogger::addLog('DNA Analytics error: ' . $e->getMessage(), 2, null, 'Module', null, true);
    }
}
