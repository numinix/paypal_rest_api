<?php
/**
 * PayPal Advanced Checkout (paypalr) module upgrade handler.
 *
 * Handles the upgrade process for PayPal payment modules when the
 * "Upgrade" button is clicked in the admin panel. This script
 * triggers the module's tableCheckup() method which applies all
 * incremental version updates.
 */

$autoloaderPath = dirname(__DIR__) . '/includes/modules/payment/paypal/PayPalRestful/Compatibility/LanguageAutoloader.php';
if (is_file($autoloaderPath)) {
    require_once $autoloaderPath;
    \PayPalRestful\Compatibility\LanguageAutoloader::register();
}

require 'includes/application_top.php';

if (function_exists('zen_admin_check_login')) {
    $paypalrAdminLoggedIn = zen_admin_check_login();
} else {
    $paypalrAdminLoggedIn = (int)($_SESSION['admin_id'] ?? 0) > 0;
}

if (!$paypalrAdminLoggedIn) {
    zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
}

$languageFile = DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/paypalr.php';
if (file_exists($languageFile)) {
    include $languageFile;
}

$module_code = trim((string)($_GET['module'] ?? ''));
$action = strtolower(trim((string)($_GET['action'] ?? '')));

// Validate module code
$valid_modules = ['paypalr', 'paypalr_applepay', 'paypalr_googlepay', 'paypalr_venmo', 'paypalr_savedcard'];
if (!in_array($module_code, $valid_modules, true)) {
    paypalr_upgrade_message(
        'Invalid module specified for upgrade.',
        'error'
    );
    paypalr_upgrade_redirect_to_modules();
}

if ($action === 'upgrade') {
    // Load the payment module
    $module_file = DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/' . $module_code . '.php';
    if (!file_exists($module_file)) {
        paypalr_upgrade_message(
            'Module file not found: ' . $module_code,
            'error'
        );
        paypalr_upgrade_redirect_to_modules();
    }

    require_once $module_file;
    
    // Instantiate the module
    $module = new $module_code();
    
    // Get current and new versions
    $current_version = defined('MODULE_PAYMENT_' . strtoupper($module_code) . '_VERSION') 
        ? constant('MODULE_PAYMENT_' . strtoupper($module_code) . '_VERSION') 
        : '0.0.0';
    $new_version = $module->getCurrentVersion();
    
    // Trigger the upgrade by calling tableCheckup
    // tableCheckup is a protected method, but it's called in the constructor when IS_ADMIN_FLAG is true
    // Since we already instantiated the module with IS_ADMIN_FLAG = true, tableCheckup has already run
    // So we just need to refresh the module to trigger it again
    
    // For modules that don't auto-run tableCheckup in constructor, we need another approach
    // Let's use reflection to call the protected method
    try {
        $reflection = new ReflectionClass($module);
        if ($reflection->hasMethod('tableCheckup')) {
            $method = $reflection->getMethod('tableCheckup');
            $method->setAccessible(true);
            $method->invoke($module);
        }
        
        paypalr_upgrade_message(
            sprintf(
                'Successfully upgraded %s from version %s to %s',
                $module_code,
                $current_version,
                $new_version
            ),
            'success'
        );
    } catch (Exception $e) {
        paypalr_upgrade_message(
            'Upgrade failed: ' . $e->getMessage(),
            'error'
        );
    }
} else {
    paypalr_upgrade_message(
        'Invalid action specified.',
        'error'
    );
}

paypalr_upgrade_redirect_to_modules();

function paypalr_upgrade_message(string $message, string $type = 'warning'): void
{
    global $messageStack;

    if (!isset($messageStack) || !is_object($messageStack)) {
        return;
    }

    $messageStack->add_session($message, $type);
}

function paypalr_upgrade_redirect_to_modules(): void
{
    zen_redirect(zen_href_link(FILENAME_MODULES, 'set=payment&module=' . ($_GET['module'] ?? 'paypalr'), 'NONSSL'));
    exit;
}

// In case we ever need to render an upgrade confirmation page (not currently used)
function paypalr_upgrade_render_page(): void
{
    ?>
    <!doctype html>
    <html <?php echo defined('HTML_PARAMS') ? HTML_PARAMS : 'lang="en"'; ?>>
    <head>
        <?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
        <link rel="stylesheet" href="../includes/modules/payment/paypal/PayPalRestful/numinix_admin.css">
    </head>
    <body>
    <?php require DIR_WS_INCLUDES . 'header.php'; ?>
    <div class="nmx-module">
        <div class="nmx-container">
            <div class="nmx-container-header">
                <h1>Module Upgrade</h1>
            </div>
            <div class="nmx-panel">
                <div class="nmx-panel-heading">
                    <div class="nmx-panel-title">Upgrade Complete</div>
                </div>
                <div class="nmx-panel-body">
                    <p>The module has been successfully upgraded.</p>
                    <div class="nmx-btn-container">
                        <a href="<?php echo zen_href_link(FILENAME_MODULES, 'set=payment'); ?>" class="nmx-btn nmx-btn-primary">Return to Payment Modules</a>
                    </div>
                </div>
            </div>
            <div class="nmx-footer">
                <a href="https://www.numinix.com" target="_blank" rel="noopener noreferrer" class="nmx-footer-logo">
                    <img src="images/numinix_logo.png" alt="Numinix">
                </a>
            </div>
        </div>
    </div>
    <?php require DIR_WS_INCLUDES . 'footer.php'; ?>
    </body>
    </html>
    <?php
}
