<?php
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

global $db, $messageStack;

$isInstallAction = (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' &&
    isset($_GET['action'], $_GET['set'], $_POST['module']) &&
    $_GET['action'] === 'install' &&
    $_GET['set'] === 'payment' &&
    $_POST['module'] === 'braintree_paypal'
);

$isModuleInstalled = false;
if (isset($db) && is_object($db)) {
    $installedCheck = $db->Execute(
        "SELECT configuration_key FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_BRAINTREE_PAYPAL_STATUS' LIMIT 1"
    );
    $isModuleInstalled = (is_object($installedCheck) && isset($installedCheck->EOF) && !$installedCheck->EOF);
}

if (!$isModuleInstalled && !$isInstallAction) {
    return;
}

$module_constant = 'MODULE_PAYMENT_BRAINTREE_PAYPAL_VERSION';
$module_installer_directory = DIR_FS_ADMIN . 'includes/installers/braintree_paypal';
$module_name = "Braintree PayPal";
$zencart_com_plugin_id = 0; // from zencart.com plugins - Leave Zero not to check
$configuration_group_id = 6; // Module Configuration
// Just change the stuff above... Nothing down here should need to change

$current_version = '0.0.0';
if ($isModuleInstalled || $isInstallAction) {
    $versionCheck = $db->Execute(
        "SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = '" . $module_constant . "' LIMIT 1"
    );
    if (is_object($versionCheck) && isset($versionCheck->EOF) && !$versionCheck->EOF) {
        $current_version = $versionCheck->fields['configuration_value'];
    } else {
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES
                    ('Version', '" . $module_constant . "', '0.0.0', 'Version installed:', " . $configuration_group_id . ", 0, NOW(), NOW(), NULL, NULL);");
    }
}

// Get the list of installer files and filter to version-numbered PHP files only
$installers = scandir($module_installer_directory);
$installers = array_filter($installers, function($file) {
    return preg_match('/^\d+(_\d+)*\.php$/', $file);
});

// Proper semantic version sorting
usort($installers, function($a, $b) {
    return version_compare(str_replace('.php', '', str_replace('_', '.', $a)),
                           str_replace('.php', '', str_replace('_', '.', $b)));
});

// Determine newest version correctly after proper sorting
$newest_version = str_replace('_', '.', str_replace('.php', '', end($installers)));

if (version_compare($newest_version, $current_version) > 0) {
    foreach ($installers as $installer) {
        $installer_version = str_replace('_', '.', substr($installer, 0, -4));
        if (version_compare($installer_version, $current_version) > 0) {
            include($module_installer_directory . '/' . $installer);
            $current_version = $installer_version;
            $db->Execute("UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = '" . $current_version . "' WHERE configuration_key = '" . $module_constant . "' LIMIT 1;");
            $messageStack->add("Installed " . $module_name . " v" . $current_version, 'success');
        }
    }
}

if (!function_exists('plugin_version_check_for_updates')) {
    function plugin_version_check_for_updates($fileid = 0, $version_string_to_check = '') {
        if ($fileid == 0) {
            return FALSE;
        }
        $new_version_available = FALSE;
        $lookup_index = 0;
        $url = 'http://www.zen-cart.com/downloads.php?do=versioncheck' . '&id=' . (int) $fileid;
        $data = json_decode(file_get_contents($url), true);
        // compare versions
        if (version_compare($data[$lookup_index]['latest_plugin_version'], $version_string_to_check) > 0) {
            $new_version_available = TRUE;
        }
        // check whether present ZC version is compatible with the latest available plugin version
        if (!in_array('v' . PROJECT_VERSION_MAJOR . '.' . PROJECT_VERSION_MINOR, $data[$lookup_index]['zcversions'])) {
            $new_version_available = FALSE;
        }
        if ($version_string_to_check == true) {
            return $data[$lookup_index];
        } else {
            return FALSE;
        }
    }
}

// Version Checking
if ($zencart_com_plugin_id != 0) {
    if (isset($_GET['gID']) && $_GET['gID'] == $configuration_group_id) {
        $new_version_details = plugin_version_check_for_updates($zencart_com_plugin_id, $current_version);
        if ($new_version_details != FALSE) {
            $messageStack->add("Version " . $new_version_details['latest_plugin_version'] . " of " . $new_version_details['title'] . ' is available at <a href="' . $new_version_details['link'] . '" target="_blank">[Details]</a>', 'caution');
        }
    }
}

