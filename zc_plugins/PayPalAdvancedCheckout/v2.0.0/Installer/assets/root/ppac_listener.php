<?php
/**
 * Catalog-root entrypoint for PayPal Advanced Checkout return/cancel redirects.
 *
 * Host antivirus on some stores empties large PHP files uploaded via FTP into
 * the document root. Keep this stub tiny and load the real listener from the
 * plugin/includes tree (which is not subject to that wipe pattern).
 */
declare(strict_types=1);

require_once __DIR__ . '/ppac_paths.php';

ppac_require_catalog_includes_file('modules/payment/paypal/PayPalAdvancedCheckout/ppac_listener.php');
