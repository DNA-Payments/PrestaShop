<?php
require_once DNA_ROOT_URL.'/install/AbstactInstaller.php';

class ModuleInstaller extends AbstactInstaller {
    /**
     * @return bool
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function install()
    {
        return $this->installAdminControllers()
                && $this->registerHooks()
                && $this->installOrderState();
    }


    /**
     * @return bool
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function uninstall()
    {
        return $this->uninstallConfiguration() && $this->uninstallModuleAdminControllers();
    }

    /**
     * Uninstall Configuration (with or without language management)
     *
     * @return bool
     * @throws \PrestaShopDatabaseException
     */
    public function uninstallConfiguration()
    {
        $query = new DbQuery();
        $query->select('name');
        $query->from('configuration');
        $query->where('name LIKE \'' . pSQL(Tools::strtoupper($this->module->name)) . '_%\'');

        $results = Db::getInstance()->executeS($query);

        if (empty($results)) {
            return true;
        }

        $configurationKeys = array_column($results, 'name');

        $result = true;
        foreach ($configurationKeys as $configurationKey) {
            $result &= Configuration::deleteByName($configurationKey);
        }

        return $result;
    }

    public function getHooks()
    {
        return $this->module->hooks;
    }

    /**
     * Get Admin Controllers
     *
     * @return array
     */

    public function getAdminControllers()
    {
        return $this->module->moduleAdminControllers;
    }

}