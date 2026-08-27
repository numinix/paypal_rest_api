<?php
/**
 * @copyright Copyright 2023-2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 *
 * Last updated: v2.0.0
 */

$ppacAutoloadDir = __DIR__ . '/';

global $psr4Autoloader;
$psr4Autoloader->addPrefix('PayPalAdvancedCheckout\Admin', $ppacAutoloadDir . 'PayPalAdvancedCheckout/Admin');
$psr4Autoloader->addPrefix('PayPalAdvancedCheckout\Admin\Formatters', $ppacAutoloadDir . 'PayPalAdvancedCheckout/Admin/Formatters');
$psr4Autoloader->addPrefix('PayPalAdvancedCheckout\Compatibility', $ppacAutoloadDir . 'PayPalAdvancedCheckout/Compatibility');
$psr4Autoloader->addPrefix('PayPalAdvancedCheckout\Api', $ppacAutoloadDir . 'PayPalAdvancedCheckout/Api');
$psr4Autoloader->addPrefix('PayPalAdvancedCheckout\Api\Data', $ppacAutoloadDir . 'PayPalAdvancedCheckout/Api/Data');
$psr4Autoloader->addPrefix('PayPalAdvancedCheckout\Common', $ppacAutoloadDir . 'PayPalAdvancedCheckout/Common');
$psr4Autoloader->addPrefix('PayPalAdvancedCheckout\Token', $ppacAutoloadDir . 'PayPalAdvancedCheckout/Token');
$psr4Autoloader->addPrefix('PayPalAdvancedCheckout\Webhooks', $ppacAutoloadDir . 'PayPalAdvancedCheckout/Webhooks');
$psr4Autoloader->addPrefix('PayPalAdvancedCheckout\Zc2Pp', $ppacAutoloadDir . 'PayPalAdvancedCheckout/Zc2Pp');
