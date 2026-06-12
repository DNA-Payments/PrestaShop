<?php
/**
 * Upgrade to 1.6.0 (PrestaShop 9 compatibility).
 *
 * Migrates existing installations (e.g. PS8 -> PS9) so both module tables use
 * the utf8mb4 charset. Fresh installs already create the tables as utf8mb4;
 * this script only fixes tables created by older versions.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @param Dnapayments $module
 *
 * @return bool
 */
function upgrade_module_1_6_0($module)
{
    $db = Db::getInstance();
    $tables = [
        _DB_PREFIX_ . 'dnapayments_transactions',
        _DB_PREFIX_ . 'dnapayments_account_cards',
    ];

    foreach ($tables as $table) {
        // Skip tables that do not exist (defensive: account_cards may be absent
        // on very old installs).
        $exists = (bool) $db->getValue(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "' . pSQL($table) . '"'
        );

        if (!$exists) {
            continue;
        }

        $sql = 'ALTER TABLE `' . bqSQL($table) . '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci';

        if (!$db->execute($sql)) {
            PrestaShopLogger::addLog(
                'DNA Payments upgrade 1.6.0: failed to convert ' . $table . ' to utf8mb4',
                3
            );

            return false;
        }
    }

    // Idempotency: enforce one transaction row per cart with a UNIQUE key, so
    // concurrent/duplicate webhooks cannot create duplicate transactions.
    $txnTable = _DB_PREFIX_ . 'dnapayments_transactions';
    $txnExists = (bool) $db->getValue(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "' . pSQL($txnTable) . '"'
    );

    if ($txnExists) {
        $hasKey = (bool) $db->getValue(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = "' . pSQL($txnTable) . '"
               AND INDEX_NAME = "uniq_id_cart"'
        );

        if (!$hasKey) {
            // De-duplicate first (keep the most recent row per cart), otherwise
            // the ALTER would fail on existing duplicates.
            $db->execute(
                'DELETE t1 FROM `' . bqSQL($txnTable) . '` t1
                 INNER JOIN `' . bqSQL($txnTable) . '` t2
                   ON t1.id_cart = t2.id_cart AND t1.id < t2.id'
            );

            if (!$db->execute('ALTER TABLE `' . bqSQL($txnTable) . '` ADD UNIQUE KEY `uniq_id_cart` (`id_cart`)')) {
                PrestaShopLogger::addLog(
                    'DNA Payments upgrade 1.6.0: failed to add uniq_id_cart index',
                    3
                );

                return false;
            }
        }
    }

    return true;
}
