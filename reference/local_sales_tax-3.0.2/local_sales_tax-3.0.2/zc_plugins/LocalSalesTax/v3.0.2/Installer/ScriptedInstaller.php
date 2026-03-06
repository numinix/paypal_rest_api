<?php
use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    /**
     * @return bool
     */
    protected function executeInstall()
    {
        if (!$this->purgeOldFiles()) {
            return false;
        }

        global $sniffer;
        zen_define_default('TABLE_LOCAL_SALES_TAXES', DB_PREFIX . 'tax_rates_local');
        if (!$sniffer->table_exists(TABLE_LOCAL_SALES_TAXES)) {
            $this->executeInstallerSql(
                'CREATE TABLE `' . TABLE_LOCAL_SALES_TAXES . '` ( ' .
                    '`local_tax_id` int(11) NOT NULL AUTO_INCREMENT COMMENT \'tax id\', ' .
                    '`zone_id` int(11) DEFAULT NULL COMMENT \'zen cart zone to apply tax\', ' .
                    '`local_fieldmatch` varchar(100) DEFAULT NULL COMMENT \'name of field from delivery table to match\', ' .
                    '`local_datamatch` text COMMENT \'Data to match delievery field\', ' .
                    '`local_tax_rate` decimal(7,4) DEFAULT \'0.0000\' COMMENT \'local tax rate\', ' .
                    '`local_tax_label` varchar(100) DEFAULT NULL COMMENT \'Label for checkout\', ' .
                    '`local_tax_shipping` varchar(5) DEFAULT \'false\' COMMENT \'Apply this tax to shipping\', ' .
                    '`local_tax_class_id` int(1) DEFAULT NULL COMMENT \'Apply to products in what tax class\', ' .
                    'PRIMARY KEY  (`local_tax_id`) ' .
                ')'
            );
        }

        // -----
        // Register the plugin's taxes page tool for the admin menus.
        //
        if (!zen_page_key_exists('localSalesTaxes')) {
            zen_register_admin_page('localSalesTaxes', 'BOX_TAXES_LOCAL_SALES_TAXES', 'FILENAME_LOCAL_SALES_TAXES', '', 'taxes', 'Y');
        }

        return true;
    }

    // -----
    // Note: This (https://github.com/zencart/zencart/pull/6498) Zen Cart PR must
    // be present in the base code or a PHP Fatal error is generated due to the
    // function signature difference.
    //
    protected function executeUpgrade($oldVersion)
    {
    }

    /**
     * @return bool
     */
    protected function executeUninstall()
    {
        zen_deregister_admin_pages('localSalesTaxes');
        $this->executeInstallerSql(
            "DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key LIKE 'MODULE\_ORDER\_TOTAL\_COUNTY\_LOCAL\_TAX\_'"
        );
        return true;
    }

    protected function purgeOldFiles(): bool
    {
        $filesToDelete = [
            DIR_FS_ADMIN . DIR_WS_INCLUDES . 'boxes/extra_boxes/local_sales_tax_taxes_dhtml.php',
            DIR_FS_ADMIN . DIR_WS_INCLUDES . 'auto_loaders/config.local_sales_taxes.php',
            DIR_FS_ADMIN . DIR_WS_INCLUDES . 'classes/observers/auto.local_sales_tax_admin.php',
            DIR_FS_ADMIN . DIR_WS_INCLUDES . 'extra_datafiles/local_sales_taxes.php',
            DIR_FS_ADMIN . DIR_WS_INCLUDES . 'auto_loaders/config.local_sales_taxes.php',
            DIR_FS_ADMIN . DIR_WS_INCLUDES . 'extra_datafiles/local_sales_taxes_filenames.php',
            DIR_FS_ADMIN . DIR_WS_INCLUDES . 'extra_datafiles/local_sales_taxes_database_tables.php',
            DIR_FS_ADMIN . DIR_WS_INCLUDES . 'init_includes/init_local_sales_taxes_install.php',
            DIR_FS_ADMIN . DIR_WS_INCLUDES . 'init_includes/init_local_sales_taxes_uninstall.php',
            DIR_FS_ADMIN . DIR_WS_LANGUAGES . 'english/lang.local_sales_taxes.php',
            DIR_FS_ADMIN . DIR_WS_LANGUAGES . 'english/local_sales_taxes.php',
            DIR_FS_ADMIN . DIR_WS_LANGUAGES . 'english/extra_definitions/lang.local_sales_taxes.php',
            DIR_FS_ADMIN . DIR_WS_LANGUAGES . 'english/extra_definitions/local_sales_taxes.php',
            DIR_FS_ADMIN . 'local_sales_taxes.php',

            DIR_FS_CATALOG . DIR_WS_INCLUDES . 'extra_datafiles/ot_local_sales_taxes_databse_tables.php',
            DIR_FS_CATALOG . DIR_WS_INCLUDES . 'classes/observers/auto.local_sales_tax.php',
            DIR_FS_CATALOG . DIR_WS_INCLUDES . 'extra_datafiles/ot_local_sales_taxes.php',
            DIR_FS_CATALOG . DIR_WS_FUNCTIONS . 'extra_functions/functions_local_sales_taxes.php',
            DIR_FS_CATALOG . DIR_WS_FUNCTIONS . 'extra_functions/functions_local_taxes.php',
            DIR_FS_CATALOG . DIR_WS_LANGUAGES . 'english/modules/order_total/lang.ot_local_sales_taxes.php',
            DIR_FS_CATALOG . DIR_WS_LANGUAGES . 'english/modules/order_total/ot_local_sales_taxes.php',
            DIR_FS_CATALOG . DIR_WS_MODULES . 'order_total/ot_local_sales_taxes.php',
        ];

        $errorOccurred = false;
        foreach ($filesToDelete as $key => $nextFile) {
            if (file_exists($nextFile)) {
                $result = unlink($nextFile);
                if (!$result && file_exists($nextFile)) {
                    $errorOccurred = true;
                    $this->errorContainer->addError(
                        0,
                        sprintf(ERROR_UNABLE_TO_DELETE_FILE, $nextFile),
                        false,
                        // this str_replace has to do DIR_FS_ADMIN before CATALOG because catalog is contained within admin, so results are wrong.
                        // also, '[admin_directory]' is used to obfuscate the admin dir name, in case the user copy/pastes output to a public forum for help.
                        sprintf(ERROR_UNABLE_TO_DELETE_FILE, str_replace([DIR_FS_ADMIN, DIR_FS_CATALOG], ['[admin_directory]/', ''], $nextFile))
                    );
                }
            }
        }
        return !$errorOccurred;
    }
}
