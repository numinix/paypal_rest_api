<?php
/**
 * Resolve PayPal Advanced Checkout files under zc_plugins (or legacy overlay).
 * Safe to load from catalog-root entrypoints before the PSR-4 autoloader.
 */

if (!function_exists('ppac_find_catalog_includes_file')) {
    /**
     * @param string $relative Path under catalog/includes/, e.g. modules/payment/paypal/ppacAutoload.php
     * @param string|null $catalogRoot Absolute catalog filesystem root; defaults to DIR_FS_CATALOG or __DIR__
     */
    function ppac_find_catalog_includes_file(string $relative, ?string $catalogRoot = null): ?string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if ($catalogRoot === null) {
            if (defined('DIR_FS_CATALOG')) {
                $catalogRoot = DIR_FS_CATALOG;
            } else {
                $catalogRoot = __DIR__;
            }
        }
        $catalogRoot = rtrim(str_replace('\\', '/', $catalogRoot), '/');

        $bestPath = null;
        $bestVersion = '0.0.0';
        $pluginRoot = $catalogRoot . '/zc_plugins/PayPalAdvancedCheckout';
        if (is_dir($pluginRoot)) {
            foreach (glob($pluginRoot . '/v*', GLOB_ONLYDIR) ?: [] as $versionDir) {
                $folder = basename($versionDir);
                if (!preg_match('/^v\d+(?:\.\d+){0,2}$/', $folder)) {
                    continue;
                }
                $candidate = $versionDir . '/catalog/includes/' . $relative;
                if (!is_file($candidate)) {
                    continue;
                }
                $semver = ltrim($folder, 'v');
                if ($bestPath === null || version_compare($semver, $bestVersion, '>')) {
                    $bestVersion = $semver;
                    $bestPath = $candidate;
                }
            }
        }

        if ($bestPath !== null) {
            return $bestPath;
        }

        $legacy = $catalogRoot . '/includes/' . $relative;
        return is_file($legacy) ? $legacy : null;
    }
}

if (!function_exists('ppac_require_catalog_includes_file')) {
    function ppac_require_catalog_includes_file(string $relative, ?string $catalogRoot = null): void
    {
        $path = ppac_find_catalog_includes_file($relative, $catalogRoot);
        if ($path === null) {
            throw new RuntimeException('PayPal Advanced Checkout file not found: ' . $relative);
        }
        require_once $path;
    }
}
