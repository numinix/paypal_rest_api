<?php
/**
 * Auto-load the vault observer so session card tokens persist after order lines exist.
 *
 * Must run before the recurring observer (if loaded at a higher breakpoint) so
 * NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE_ADD_PRODUCTS handlers see paypal_vault rows.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

$autoLoadConfig[201][] = [
    'autoType' => 'class',
    'loadFile' => 'observers/auto.paypaladvcheckout_vault.php',
];

$autoLoadConfig[201][] = [
    'autoType' => 'classInstantiate',
    'className' => 'zcObserverPaypaladvcheckoutVault',
    'objectName' => 'zcObserverPaypaladvcheckoutVault',
];
