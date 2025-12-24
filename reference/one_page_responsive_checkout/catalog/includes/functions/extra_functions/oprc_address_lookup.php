<?php
require_once DIR_FS_CATALOG . 'includes/classes/oprc_address_lookup/class.oprc_address_lookup_manager.php';

if (!isset($GLOBALS['OPRC_ADDRESS_LOOKUP_PROVIDER_REGISTRY']) || !is_array($GLOBALS['OPRC_ADDRESS_LOOKUP_PROVIDER_REGISTRY'])) {
    $GLOBALS['OPRC_ADDRESS_LOOKUP_PROVIDER_REGISTRY'] = [];
}

if (!function_exists('oprc_address_lookup_manager')) {
    function oprc_address_lookup_manager()
    {
        return OPRC_Address_Lookup_Manager::instance();
    }
}

if (!function_exists('oprc_register_address_lookup_provider')) {
    /**
     * Registers an address lookup provider definition.
     *
     * @param string $key
     * @param array $definition
     */
    function oprc_register_address_lookup_provider($key, array $definition)
    {
        $providerKey = trim($key);
        if ($providerKey === '') {
            return;
        }

        $definition['key'] = $providerKey;
        $GLOBALS['OPRC_ADDRESS_LOOKUP_PROVIDER_REGISTRY'][$providerKey] = $definition;
    }
}
// eof
