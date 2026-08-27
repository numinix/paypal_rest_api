<?php
/**
 * PayPalAdvancedCheckout – Scripted Installer
 *
 * Plugin Manager install/upgrade/uninstall for the encapsulated package.
 * Migrating from the classic overlay: removes overlay files if present,
 * redeploys catalog root/ajax/cron entrypoints, and preserves existing
 * MODULE_PAYMENT_PAYPALAC_* (and companion) configuration rows.
 */

use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallerBase;

class ScriptedInstaller extends ScriptedInstallerBase
{
    protected string $pluginDirectory = 'PayPalAdvancedCheckout';

    /** @var bool */
    protected $purgedOverlayFiles = false;

    public function executeInstall()
    {
        if (!$this->assertMinimumZcVersion()) {
            return false;
        }

        if ($this->purgeOldFiles() === false) {
            return false;
        }

        if ($this->deployCatalogEntrypoints() === false) {
            return false;
        }

        $this->deployStableCronEntry();
        $this->deployPublicImages();
        $this->ensureAdminPages();
        $this->logStorefrontEncapsulationCompatibilityNotice();

        return true;
    }

    public function doUpgrade($oldVersion = null): ?bool
    {
        if (
            method_exists(parent::class, 'setVersionDetails')
            && (!isset($this->pluginKey) || !isset($this->version) || !isset($this->pluginDir))
        ) {
            $pluginDir = dirname(__DIR__);
            $version = basename($pluginDir);
            $pluginKey = basename(dirname($pluginDir));
            $this->setVersionDetails([
                'pluginKey' => $pluginKey,
                'pluginDir' => $pluginDir,
                'version' => $version,
                'oldVersion' => (string) ($oldVersion ?? ''),
            ]);
        }

        return (bool) $this->executeUpgrade($oldVersion);
    }

    protected function executeUpgrade($oldVersion = null)
    {
        if ($this->deployCatalogEntrypoints() === false) {
            return false;
        }

        $this->deployStableCronEntry();
        $this->deployPublicImages();
        $this->ensureAdminPages();
        $this->logStorefrontEncapsulationCompatibilityNotice();

        return true;
    }

    public function executeUninstall()
    {
        $modules = [
            'paypalac',
            'paypalac_applepay',
            'paypalac_googlepay',
            'paypalac_venmo',
            'paypalac_creditcard',
            'paypalac_savedcard',
            'paypalac_paylater',
        ];

        foreach ($modules as $code) {
            $statusKey = 'MODULE_PAYMENT_' . strtoupper($code) . '_STATUS';
            if (!defined($statusKey)) {
                continue;
            }

            $moduleFile = dirname(__DIR__) . '/catalog/includes/modules/payment/' . $code . '.php';
            if (!is_file($moduleFile)) {
                continue;
            }

            require_once $moduleFile;
            if (!class_exists($code, false)) {
                continue;
            }

            $module = new $code();
            if (method_exists($module, 'remove')) {
                $module->remove();
            }
        }

        $this->removeCatalogEntrypoints();

        return true;
    }

    protected function assertMinimumZcVersion(): bool
    {
        if (!function_exists('zen_get_zcversion')) {
            return true;
        }

        if (version_compare(zen_get_zcversion(), '2.0.1', '<')) {
            $message = defined('ERROR_UNSUPPORTED_ZC_VERSION')
                ? ERROR_UNSUPPORTED_ZC_VERSION
                : 'Zen Cart 2.0.1 or later is required.';
            $this->errorContainer->addError(0, $message, false, $message);
            return false;
        }

        return true;
    }

    protected function logStorefrontEncapsulationCompatibilityNotice(): void
    {
        if (!defined('PROJECT_VERSION_MAJOR') || !defined('PROJECT_VERSION_MINOR')) {
            return;
        }

        $major = (int) PROJECT_VERSION_MAJOR;
        $minor = (int) PROJECT_VERSION_MINOR;
        if ($major < 2 || ($major === 2 && $minor < 1)) {
            error_log(
                'PayPalAdvancedCheckout: Zen Cart ' . PROJECT_VERSION_MAJOR . '.' . PROJECT_VERSION_MINOR .
                ' detected. Storefront encapsulated plugins require pre-2.1.0 core compatibility patches. ' .
                'See https://docs.zen-cart.com/dev/plugins/encapsulated/'
            );
        }
    }

    protected function deployStableCronEntry(): void
    {
        $source = __DIR__ . '/assets/cron.php';
        if (!is_readable($source)) {
            return;
        }

        $target = dirname(__DIR__, 2) . '/cron.php';
        $contents = (string) file_get_contents($source);
        if ($contents === '') {
            return;
        }

        if (is_file($target) && hash('sha256', (string) file_get_contents($target)) === hash('sha256', $contents)) {
            return;
        }

        @file_put_contents($target, $contents);
    }

    protected function deployPublicImages(): void
    {
        if (!defined('DIR_FS_CATALOG')) {
            return;
        }

        $sourceDir = dirname(__DIR__) . '/catalog/includes/modules/payment/paypal/PayPalAdvancedCheckout/images';
        if (!is_dir($sourceDir)) {
            return;
        }

        $destDir = rtrim(DIR_FS_CATALOG, '/\\') . '/images/paypalac';
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }

        foreach (glob($sourceDir . '/*') ?: [] as $file) {
            if (!is_file($file)) {
                continue;
            }
            @copy($file, $destDir . '/' . basename($file));
        }
    }

    protected function ensureAdminPages(): void
    {
        if (!function_exists('zen_register_admin_page') || !function_exists('zen_page_key_exists')) {
            return;
        }

        $pages = [
            ['paypalacSubscriptions', 'BOX_PAYPALAC_SUBSCRIPTIONS', 'paypalac_subscriptions', '', 'customers', 'Y', 90],
            ['paypalacSavedCardRecurring', 'BOX_PAYPALAC_SAVED_CARD_RECURRING', 'paypalac_saved_card_recurring', '', 'customers', 'Y', 91],
            ['paypalacSubscriptionsReport', 'BOX_PAYPALAC_SUBSCRIPTIONS_REPORT', 'paypalac_subscriptions_report', '', 'reports', 'Y', 90],
            ['paypalacWebhookLogs', 'BOX_PAYPALAC_WEBHOOK_LOGS', 'paypalac_webhook_logs', '', 'tools', 'Y', 90],
        ];

        foreach ($pages as $page) {
            if (!zen_page_key_exists($page[0])) {
                zen_register_admin_page($page[0], $page[1], $page[2], $page[3], $page[4], $page[5], $page[6]);
            }
        }
    }

    protected function deployCatalogEntrypoints(): bool
    {
        if (!defined('DIR_FS_CATALOG')) {
            return true;
        }

        $ok = true;
        $catalog = rtrim(DIR_FS_CATALOG, '/\\') . '/';

        $rootMap = [
            'ppac_paths.php' => 'assets/root/ppac_paths.php',
            'ppac_listener.php' => 'assets/root/ppac_listener.php',
            'ppac_webhook.php' => 'assets/root/ppac_webhook.php',
            'ppac_wallet.php' => 'assets/root/ppac_wallet.php',
            'ppac_add_card.php' => 'assets/root/ppac_add_card.php',
        ];

        foreach ($rootMap as $destName => $relativeSource) {
            if (!$this->copyAsset(__DIR__ . '/' . $relativeSource, $catalog . $destName)) {
                $ok = false;
            }
        }

        $ajaxDir = $catalog . 'ajax';
        if (!is_dir($ajaxDir)) {
            @mkdir($ajaxDir, 0755, true);
        }
        foreach (glob(__DIR__ . '/assets/ajax/*.php') ?: [] as $source) {
            if (!$this->copyAsset($source, $ajaxDir . '/' . basename($source))) {
                $ok = false;
            }
        }

        $cronDir = $catalog . 'cron';
        if (!is_dir($cronDir)) {
            @mkdir($cronDir, 0755, true);
        }
        foreach (glob(__DIR__ . '/assets/cron/paypalac_*.php') ?: [] as $source) {
            if (!$this->copyAsset($source, $cronDir . '/' . basename($source))) {
                $ok = false;
            }
        }

        if ($ok === false) {
            $message = defined('ERROR_PPAC_ROOT_SHIM_DEPLOY')
                ? sprintf(ERROR_PPAC_ROOT_SHIM_DEPLOY, 'root/ajax/cron entrypoints')
                : 'Failed to deploy PayPal Advanced Checkout catalog entrypoints.';
            $this->errorContainer->addError(0, $message, false, $message);
        }

        return $ok;
    }

    protected function removeCatalogEntrypoints(): void
    {
        if (!defined('DIR_FS_CATALOG')) {
            return;
        }

        $catalog = rtrim(DIR_FS_CATALOG, '/\\') . '/';
        foreach (['ppac_paths.php', 'ppac_listener.php', 'ppac_webhook.php', 'ppac_wallet.php', 'ppac_add_card.php', 'ppac_webhook_main.php'] as $file) {
            if (is_file($catalog . $file)) {
                @unlink($catalog . $file);
            }
        }

        foreach (glob($catalog . 'ajax/paypalac_wallet*.php') ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob($catalog . 'cron/paypalac_*.php') ?: [] as $file) {
            @unlink($file);
        }
    }

    protected function copyAsset(string $source, string $dest): bool
    {
        if (!is_readable($source)) {
            return false;
        }

        $contents = file_get_contents($source);
        if ($contents === false) {
            return false;
        }

        $written = @file_put_contents($dest, $contents);
        if ($written === false || !is_file($dest)) {
            return false;
        }

        return filesize($source) === filesize($dest);
    }

    protected function purgeOldFiles(): bool
    {
        $errorOccurred = false;

        $adminFiles = [
            '' => [
                'paypalac_integrated_signup.php',
                'paypalac_saved_card_recurring.php',
                'paypalac_signup.php',
                'paypalac_subscriptions_report.php',
                'paypalac_subscriptions.php',
                'paypalac_upgrade.php',
                'paypalac_webhook_logs.php',
            ],
            'includes/classes/observers/' => [
                'auto.PaypalacAdmin.php',
            ],
            'includes/css/' => [
                'paypalac_integrated_signup.css',
                'paypalac_saved_card_recurring.css',
                'paypalac_signup.css',
                'paypalac_subscriptions.css',
                'paypalac_webhook_logs.css',
            ],
            'includes/extra_datafiles/' => [
                'paypalac_filenames.php',
                'ppac_database_tables.php',
            ],
            'includes/javascript/' => [
                'paypalac_integrated_signup_callback.js',
                'paypalac_integrated_signup_complete.js',
                'paypalac_integrated_signup.js',
                'paypalac_saved_card_recurring.js',
                'paypalac_signup.js',
                'paypalac_subscriptions.js',
            ],
            'includes/languages/english/' => [
                'paypalac_saved_card_recurring.php',
                'paypalac_subscriptions_report.php',
                'paypalac_subscriptions.php',
                'paypalac_webhook_logs.php',
                'extra_definitions/paypalac_admin_names.php',
            ],
        ];

        if (defined('DIR_FS_ADMIN')) {
            foreach ($adminFiles as $dir => $files) {
                foreach ($files as $nextFile) {
                    if ($this->unlinkIfExists(DIR_FS_ADMIN . $dir . $nextFile) === false) {
                        $errorOccurred = true;
                    }
                }
            }
        }

        $catalogFiles = [
            '' => [
                'ppac_listener.php',
                'ppac_webhook.php',
                'ppac_wallet.php',
                'ppac_add_card.php',
                'ppac_webhook_main.php',
            ],
            'ajax/' => [
                'paypalac_wallet.php',
                'paypalac_wallet_checkout.php',
                'paypalac_wallet_clear_cart.php',
            ],
            'cron/' => [
                'paypalac_saved_card_recurring.php',
                'paypalac_remove_expired_cards.php',
                'paypalac_subscription_cancellations.php',
                'paypalac_recurring_reminders.php',
            ],
            'includes/auto_loaders/' => [
                'paypalac_vault_observer.core.php',
                'paypalac_wallet_ajax.core.php',
                'webhook.core.php',
            ],
            'includes/classes/' => [
                'paypalacSavedCardRecurring.php',
            ],
            'includes/classes/observers/' => [
                'auto.paypaladvcheckout.php',
                'auto.paypaladvcheckout_recurring.php',
                'auto.paypaladvcheckout_savedcards.php',
                'auto.paypaladvcheckout_vault.php',
            ],
            'includes/extra_datafiles/' => [
                'ppac_account_paypal_subscriptions_filenames.php',
                'ppac_account_saved_credit_cards_filenames.php',
                'ppac_database_tables.php',
            ],
            'includes/functions/' => [
                'paypalac_functions.php',
            ],
            'includes/functions/extra_functions/' => [
                'paypalac_subscription_functions.php',
            ],
            'includes/languages/english/' => [
                'account_paypal_subscriptions.php',
                'account_saved_credit_cards.php',
                'lang.account_paypal_subscriptions.php',
                'lang.account_saved_credit_cards.php',
                'extra_definitions/lang.paypalac_redirect_listener_definitions.php',
                'modules/payment/lang.paypalac.php',
                'modules/payment/lang.paypalac_applepay.php',
                'modules/payment/lang.paypalac_creditcard.php',
                'modules/payment/lang.paypalac_googlepay.php',
                'modules/payment/lang.paypalac_paylater.php',
                'modules/payment/lang.paypalac_savedcard.php',
                'modules/payment/lang.paypalac_shared.php',
                'modules/payment/lang.paypalac_venmo.php',
                'modules/payment/paypalac.php',
                'modules/payment/paypalac_applepay.php',
                'modules/payment/paypalac_creditcard.php',
                'modules/payment/paypalac_googlepay.php',
                'modules/payment/paypalac_paylater.php',
                'modules/payment/paypalac_savedcard.php',
                'modules/payment/paypalac_venmo.php',
            ],
            'includes/modules/payment/' => [
                'paypalac.php',
                'paypalac_applepay.php',
                'paypalac_creditcard.php',
                'paypalac_googlepay.php',
                'paypalac_paylater.php',
                'paypalac_savedcard.php',
                'paypalac_venmo.php',
                'paypal/ppacAutoload.php',
            ],
            'includes/modules/pages/account_paypal_subscriptions/' => ['*.*'],
            'includes/modules/pages/account_saved_credit_cards/' => ['*.*'],
            'includes/templates/template_default/templates/' => [
                'tpl_modules_paypalac_applepay.php',
                'tpl_modules_paypalac_googlepay.php',
                'tpl_modules_paypalac_product_applepay.php',
                'tpl_modules_paypalac_product_googlepay.php',
                'tpl_modules_paypalac_product_venmo.php',
                'tpl_modules_paypalac_venmo.php',
                'tpl_paypalac_product_info.php',
                'tpl_paypalac_shopping_cart.php',
            ],
        ];

        if (!defined('DIR_FS_CATALOG')) {
            return !$errorOccurred;
        }

        foreach ($catalogFiles as $dir => $files) {
            $currentDir = DIR_FS_CATALOG . $dir;
            foreach ($files as $nextFile) {
                $currentFile = $currentDir . $nextFile;
                if (substr($currentFile, -3) === '*.*') {
                    if ($this->removeDirectoryAndFiles(str_replace('/*.*', '', $currentFile)) === false) {
                        $errorOccurred = true;
                    }
                    continue;
                }
                if ($this->unlinkIfExists($currentFile) === false) {
                    $errorOccurred = true;
                }
            }
        }

        // Remove PPAC namespace tree only (never wipe includes/modules/payment/paypal/).
        $namespaceDir = DIR_FS_CATALOG . 'includes/modules/payment/paypal/PayPalAdvancedCheckout';
        if ($this->removeDirectoryAndFiles($namespaceDir) === false) {
            $errorOccurred = true;
        }

        // Remove PPAC paypal_common.php only when it is the PayPalAdvancedCheckout copy.
        $paypalCommon = DIR_FS_CATALOG . 'includes/modules/payment/paypal/paypal_common.php';
        if (is_file($paypalCommon)) {
            $contents = (string) @file_get_contents($paypalCommon);
            if ($contents !== '' && str_contains($contents, 'PayPalAdvancedCheckout\\')) {
                if ($this->unlinkIfExists($paypalCommon) === false) {
                    $errorOccurred = true;
                }
            }
        }

        // Optional YOUR_TEMPLATE overlay copies (best-effort; template name varies).
        $yourTemplateDir = DIR_FS_CATALOG . 'includes/templates/YOUR_TEMPLATE';
        if (is_dir($yourTemplateDir)) {
            $templateFiles = [
                'auto_loaders/loader_account_paypal_subscriptions.php',
                'auto_loaders/loader_account_saved_credit_cards.php',
                'css/account_paypal_subscriptions.css',
                'css/account_saved_credit_cards.css',
                'templates/tpl_account_paypal_subscriptions_default.php',
                'templates/tpl_account_saved_credit_cards_default.php',
            ];
            foreach ($templateFiles as $rel) {
                $this->unlinkIfExists($yourTemplateDir . '/' . $rel);
            }
        }

        return !$errorOccurred;
    }

    protected function unlinkIfExists(string $currentFile): bool
    {
        if (!file_exists($currentFile)) {
            return true;
        }

        $this->purgedOverlayFiles = true;
        unlink($currentFile);
        if (file_exists($currentFile)) {
            $friendly = str_replace(
                [DIR_FS_ADMIN ?? '', DIR_FS_CATALOG ?? ''],
                ['[admin_directory]/', ''],
                $currentFile
            );
            $message = sprintf(
                defined('ERROR_UNABLE_TO_DELETE_FILE') ? ERROR_UNABLE_TO_DELETE_FILE : 'Failed to delete old file: %s',
                $friendly
            );
            $this->errorContainer->addError(0, $message, false, $message);
            return false;
        }

        return true;
    }

    protected function removeDirectoryAndFiles(string $dirName): bool
    {
        $errorOccurred = false;
        if ($dirName === '.' || $dirName === '..' || !is_dir($dirName)) {
            return true;
        }

        $dirFiles = scandir($dirName);
        if ($dirFiles === false) {
            return true;
        }

        foreach ($dirFiles as $nextFile) {
            if ($nextFile === '.' || $nextFile === '..') {
                continue;
            }

            $nextEntry = $dirName . '/' . $nextFile;
            if (is_file($nextEntry)) {
                if ($this->unlinkIfExists($nextEntry) === false) {
                    $errorOccurred = true;
                }
            } elseif ($this->removeDirectoryAndFiles($nextEntry) === false) {
                $errorOccurred = true;
            }
        }

        if (is_dir($dirName)) {
            @rmdir($dirName);
            if (is_dir($dirName)) {
                $errorOccurred = true;
                $friendly = str_replace(
                    [DIR_FS_ADMIN ?? '', DIR_FS_CATALOG ?? ''],
                    ['[admin_directory]/', ''],
                    $dirName
                );
                $message = sprintf(
                    defined('ERROR_UNABLE_TO_REMOVE_DIR') ? ERROR_UNABLE_TO_REMOVE_DIR : 'Failed to remove old directory: %s',
                    $friendly
                );
                $this->errorContainer->addError(0, $message, false, $message);
            }
        }

        return !$errorOccurred;
    }
}
