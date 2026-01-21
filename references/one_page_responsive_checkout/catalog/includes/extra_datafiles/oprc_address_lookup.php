<?php
/**
 * Address lookup helpers for the One Page Responsive Checkout plugin.
 *
 * Configuration values such as OPRC_ADDRESS_LOOKUP_PROVIDER and
 * OPRC_ADDRESS_LOOKUP_MAX_RESULTS are managed via the admin settings. Zen Cart
 * exposes each configuration key as a constant automatically, so defining them
 * here would override the values chosen in the dashboard.
 */
if (!defined('FILENAME_OPRC_ADDRESS_LOOKUP')) {
    define('FILENAME_OPRC_ADDRESS_LOOKUP', 'ajax/oprc_address_lookup.php');
}
// eof
