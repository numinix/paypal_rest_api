<?php
/**
 * @copyright Copyright 2023-2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id:  $
 *
 *  Last updated: v1.2.0
 *
 */

global $psr4Autoloader;
$psr4Autoloader->addPrefix('PayPalAdvancedCheckout\Admin', DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalAdvancedCheckout/Admin');
$psr4Autoloader->addPrefix('PayPalAdvancedCheckout\Admin\Formatters', DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalAdvancedCheckout/Admin/Formatters');
$psr4Autoloader->addPrefix('PayPalAdvancedCheckout\Compatibility', DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalAdvancedCheckout/Compatibility');
$psr4Autoloader->addPrefix('PayPalAdvancedCheckout\Api', DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalAdvancedCheckout/Api');
$psr4Autoloader->addPrefix('PayPalAdvancedCheckout\Api\Data', DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalAdvancedCheckout/Api/Data');
$psr4Autoloader->addPrefix('PayPalAdvancedCheckout\Common', DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalAdvancedCheckout/Common');
$psr4Autoloader->addPrefix('PayPalAdvancedCheckout\Token', DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalAdvancedCheckout/Token');
$psr4Autoloader->addPrefix('PayPalAdvancedCheckout\Webhooks', DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalAdvancedCheckout/Webhooks');
$psr4Autoloader->addPrefix('PayPalAdvancedCheckout\Zc2Pp', DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalAdvancedCheckout/Zc2Pp');
