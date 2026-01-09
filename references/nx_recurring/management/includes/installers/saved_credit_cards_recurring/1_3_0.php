<?php
/**
 * Register the Active Subscriptions report in the admin menu.
 */

global $db, $messageStack, $configuration_group_id;

if (!defined('TABLE_ADMIN_PAGES')) {
    $tablePrefix = defined('DB_PREFIX') ? DB_PREFIX : '';
    define('TABLE_ADMIN_PAGES', $tablePrefix . 'admin_pages');
}

if (!defined('BOX_TOOLS_ACTIVE_SUBSCRIPTIONS_REPORT')) {
    define('BOX_TOOLS_ACTIVE_SUBSCRIPTIONS_REPORT', 'Active Subscriptions Report');
}

if (!defined('FILENAME_ACTIVE_SUBSCRIPTIONS_REPORT')) {
    define('FILENAME_ACTIVE_SUBSCRIPTIONS_REPORT', 'active_subscriptions_report');
}

if (!function_exists('asr_installer_escape')) {
    function asr_installer_escape($value)
    {
        if (function_exists('zen_db_input')) {
            return zen_db_input($value);
        }

        return addslashes($value);
    }
}

$pageKey = 'toolsActiveSubscriptionsReport';
$pageName = 'BOX_TOOLS_ACTIVE_SUBSCRIPTIONS_REPORT';
$pageFile = 'FILENAME_ACTIVE_SUBSCRIPTIONS_REPORT';

$pageExists = false;
if (function_exists('zen_page_key_exists')) {
    $pageExists = zen_page_key_exists($pageKey);
} else {
    $check = $db->Execute(
        "SELECT page_key FROM " . TABLE_ADMIN_PAGES . " WHERE page_key = '" . asr_installer_escape($pageKey) . "' LIMIT 1;"
    );
    $pageExists = ($check && $check->RecordCount() > 0);
}

if (!$pageExists) {
    if (function_exists('zen_register_admin_page')) {
        zen_register_admin_page(
            $pageKey,
            $pageName,
            $pageFile,
            '',
            'tools',
            'Y',
            (int) $configuration_group_id
        );
    } else {
        $db->Execute(
            "INSERT IGNORE INTO " . TABLE_ADMIN_PAGES
            . " (page_key, language_key, main_page, page_params, menu_key, display_on_menu, sort_order) VALUES ('"
            . asr_installer_escape($pageKey) . "', '"
            . asr_installer_escape($pageName) . "', '"
            . asr_installer_escape($pageFile) . "', '', 'tools', 'Y', "
            . (int) $configuration_group_id . ");"
        );
    }

    if (isset($messageStack) && is_object($messageStack)) {
        $messageStack->add('Active Subscriptions Report has been added to the Tools menu.', 'success');
    }
}
