<?php
/**
 * Numinix PayPal ISU Installer - Version 1.1.0
 *
 * Removes the deprecated Numinix PPCP Environment configuration option.
 * The onboarding environment is now selected per request instead of via
 * a stored configuration toggle.
 *
 * Version updates are handled by init_numinix_paypal_isu.php after each
 * installer is executed.
 */

if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

global $db, $messageStack;

$configKey = 'NUMINIX_PPCP_ENVIRONMENT';

$lookup = $db->Execute(
    "SELECT configuration_id"
    . " FROM " . TABLE_CONFIGURATION
    . " WHERE configuration_key = '" . zen_db_input($configKey) . "'"
    . " LIMIT 1"
);

if ($lookup && !$lookup->EOF) {
    $db->Execute(
        "DELETE FROM " . TABLE_CONFIGURATION
        . " WHERE configuration_key = '" . zen_db_input($configKey) . "'"
        . " LIMIT 1"
    );

    if (isset($messageStack) && is_object($messageStack)) {
        $messageStack->add('Removed deprecated Numinix PPCP Environment setting.', 'warning');
    }
}
