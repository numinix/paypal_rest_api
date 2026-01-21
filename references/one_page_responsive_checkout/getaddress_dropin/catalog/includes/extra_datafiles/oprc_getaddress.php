<?php
/**
 * Configuration placeholder for the GetAddress address lookup provider.
 *
 * Copy this file to your store and set any provider-specific constants (for example, by defining
 * OPRC_GETADDRESS_API_KEY) if you prefer to manage configuration in code.
 */
if (!defined('OPRC_GETADDRESS_API_KEY') && defined('OPRC_ADDRESS_LOOKUP_API_KEY')) {
    define('OPRC_GETADDRESS_API_KEY', OPRC_ADDRESS_LOOKUP_API_KEY);
}

if (!defined('OPRC_GETADDRESS_ENDPOINT')) {
    define('OPRC_GETADDRESS_ENDPOINT', 'https://api.getaddress.io');
}
// eof
