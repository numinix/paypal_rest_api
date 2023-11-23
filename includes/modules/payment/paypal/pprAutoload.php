<?php
/**
 * @copyright Copyright 2023 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: lat9 2023 Nov 21 Modified in v1.5.8-alpha $
 */
global $psr4Autoloader;

$psr4Autoloader->addPrefix('PayPalRestful\Api', DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/Api');
$psr4Autoloader->addPrefix('PayPalRestful\Api\Data', DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/Api/Data');
$psr4Autoloader->addPrefix('PayPalRestful\Common', DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/Common');
$psr4Autoloader->addPrefix('PayPalRestful\Token', DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/Token');
$psr4Autoloader->addPrefix('PayPalRestful\Zc2Pp', DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/Zc2Pp');
