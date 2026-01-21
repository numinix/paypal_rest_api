<?php
$groupId = isset($configuration_group_id) ? (int)$configuration_group_id : 0;
if ($groupId <= 0) {
    $groupLookup = $db->Execute("SELECT configuration_group_id FROM " . TABLE_CONFIGURATION_GROUP . " WHERE configuration_group_title = 'One Page Responsive Checkout' LIMIT 1");
    if (!$groupLookup->EOF) {
        $groupId = (int)$groupLookup->fields['configuration_group_id'];
    }
}

if ($groupId > 0 && !defined('OPRC_CHECKOUT_LOGO_SECURE_PATH')) {
    $db->Execute(
        "INSERT INTO " . TABLE_CONFIGURATION .
        " (configuration_title, configuration_tab, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES " .
        " ('Checkout Logo Secure URL', 'Layout', 'OPRC_CHECKOUT_LOGO_SECURE_PATH', '', 'Enter the full secure (https) URL to the logo image (recommended up to 240px wide by 90px tall) that should display above the Shopping Cart panel. The image scales automatically while preserving its aspect ratio.', " .
        $groupId . ", 32, NOW(), NOW(), NULL, NULL)"
    );
}
