<?php
/**
 * Smoke checks for encapsulated packaging + installer purge list.
 */
$root = dirname(__DIR__);
$plugin = $root . '/zc_plugins/PayPalAdvancedCheckout/v2.0.0';
$errors = [];

foreach ([
    'manifest.php',
    'Installer/ScriptedInstaller.php',
    'Installer/assets/root/ppac_paths.php',
    'Installer/assets/root/ppac_listener.php',
    'catalog/includes/modules/payment/paypalac.php',
    'catalog/includes/modules/payment/paypal/ppacAutoload.php',
] as $rel) {
    if (!is_file($plugin . '/' . $rel)) {
        $errors[] = "Missing $rel";
    }
}

$manifest = require $plugin . '/manifest.php';
if (empty($manifest['removesUnencapsulatedVersion'])) {
    $errors[] = 'manifest must set removesUnencapsulatedVersion';
}
if (($manifest['pluginKey'] ?? '') !== 'PayPalAdvancedCheckout') {
    $errors[] = 'pluginKey mismatch';
}
if (($manifest['pluginVersion'] ?? '') !== 'v2.0.0') {
    $errors[] = 'pluginVersion mismatch';
}

$installer = file_get_contents($plugin . '/Installer/ScriptedInstaller.php');
foreach (['function purgeOldFiles', 'function deployCatalogEntrypoints', 'ppac_wallet.php', 'PayPalAdvancedCheckout'] as $needle) {
    if (strpos($installer, $needle) === false) {
        $errors[] = "Installer missing $needle";
    }
}

$paypalac = file_get_contents($plugin . '/catalog/includes/modules/payment/paypalac.php');
if (strpos($paypalac, 'function manageRootDirectoryFiles') === false) {
    $errors[] = 'paypalac missing manageRootDirectoryFiles';
}
if (strpos($paypalac, 'function readNonEmptyRootEntrypointSource') === false) {
    $errors[] = 'paypalac missing readNonEmptyRootEntrypointSource empty-source guard';
}
if (strpos($paypalac, 'function writeRootEntrypointAtomically') === false) {
    $errors[] = 'paypalac missing writeRootEntrypointAtomically';
}
if (strpos($paypalac, "CURRENT_VERSION = '2.0.0'") === false) {
    $errors[] = 'paypalac CURRENT_VERSION should be 2.0.0';
}
if (strpos($installer, 'function readNonEmptyAssetContents') === false) {
    $errors[] = 'Installer missing readNonEmptyAssetContents empty-source guard';
}

if ($errors) {
    fwrite(STDERR, "FAIL\n" . implode("\n", $errors) . "\n");
    exit(1);
}
echo "OK encapsulated packaging smoke checks passed\n";
