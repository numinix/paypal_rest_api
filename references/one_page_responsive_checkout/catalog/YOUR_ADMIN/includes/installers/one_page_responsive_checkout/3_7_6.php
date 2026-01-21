<?php
$groupId = isset($configuration_group_id) ? (int)$configuration_group_id : 0;
if ($groupId <= 0) {
    $groupLookup = $db->Execute(
        "SELECT configuration_group_id FROM " . TABLE_CONFIGURATION_GROUP .
        " WHERE configuration_group_title = 'One Page Responsive Checkout' LIMIT 1"
    );
    if (!$groupLookup->EOF) {
        $groupId = (int)$groupLookup->fields['configuration_group_id'];
    }
}

if ($groupId > 0) {
    $definitions = array(
        'OPRC_ADDRESS_LOOKUP_PROVIDER' => array(
            'title' => 'Address Lookup Provider',
            'value' => 'getaddress',
            'description' => 'Enter the provider key that should supply address suggestions. Leave blank to disable address lookup.',
            'sort_order' => 55,
        ),
        'OPRC_ADDRESS_LOOKUP_API_KEY' => array(
            'title' => 'Address Lookup API Key',
            'value' => '',
            'description' => 'API key shared by address lookup providers. Configure the key required by the active provider.',
            'sort_order' => 56,
        ),
        'OPRC_ADDRESS_LOOKUP_MAX_RESULTS' => array(
            'title' => 'Address Lookup Max Results',
            'value' => 10,
            'description' => 'Maximum number of address suggestions to display to the customer.',
            'sort_order' => 57,
        ),
    );

    foreach ($definitions as $configurationKey => $definition) {
        $exists = $db->Execute(
            "SELECT configuration_id FROM " . TABLE_CONFIGURATION .
            " WHERE configuration_key = '" . zen_db_input($configurationKey) . "' LIMIT 1"
        );

        if ($exists->EOF) {
            $db->Execute(
                "INSERT INTO " . TABLE_CONFIGURATION .
                " (configuration_title, configuration_tab, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, last_modified, date_added, use_function, set_function) VALUES (" .
                " '" . zen_db_input($definition['title']) . "'," .
                " 'Advanced'," .
                " '" . zen_db_input($configurationKey) . "'," .
                " '" . zen_db_input($definition['value']) . "'," .
                " '" . zen_db_input($definition['description']) . "'," .
                $groupId .
                ", " . (int)$definition['sort_order'] . ", NOW(), NOW(), NULL, NULL)"
            );
        }
    }
}
