<?php
$groupId = isset($configuration_group_id) ? (int)$configuration_group_id : 0;
if ($groupId <= 0) {
    $groupLookup = $db->Execute("SELECT configuration_group_id FROM " . TABLE_CONFIGURATION_GROUP . " WHERE configuration_group_title = 'One Page Responsive Checkout' LIMIT 1");
    if (!$groupLookup->EOF) {
        $groupId = (int)$groupLookup->fields['configuration_group_id'];
    }
}

if ($groupId > 0 && !defined('OPRC_ORDER_COMMENTS_STATUS')) {
    $sql = "INSERT INTO " . TABLE_CONFIGURATION .
        " (configuration_title, configuration_tab, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) " .
        "VALUES (:title:, 'Layout', 'OPRC_ORDER_COMMENTS_STATUS', 'true', :description:, :groupId:, 42, NOW(), NOW(), NULL, 'zen_cfg_select_option(array(\"true\", \"false\"),')";
    $sql = $db->bindVars($sql, ':title:', 'Include Order Comments Section', 'string');
    $sql = $db->bindVars($sql, ':description:', 'Display the order comments section on the checkout page.', 'string');
    $sql = $db->bindVars($sql, ':groupId:', (int)$groupId, 'integer');
    $db->Execute($sql);
}
