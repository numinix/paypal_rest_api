<?php
/**
 * paypalr.php payment module class for the PayPal Advanced Checkout payment method
 *
 * @copyright Copyright 2023-2025 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 *
 * Last updated: v1.3.2
 */

/**
 * Load the shared payment class.
 * The actual paypalr class definition is in paypal_common.php to allow
 * other payment modules (googlepay, applepay, venmo) to extend it without
 * causing Zen Cart's language file auto-loading to fail.
 */
require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/paypal_common.php');
