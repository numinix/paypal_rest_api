<?php
/**
 * Filesystem helpers for encapsulated PayPal Advanced Checkout assets.
 *
 * @copyright Copyright 2026 Numinix
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace PayPalAdvancedCheckout\Common;

class PluginPaths
{
    /**
     * Absolute path to catalog/includes/modules/payment/paypal/PayPalAdvancedCheckout/
     */
    public static function supportDir(): string
    {
        return str_replace('\\', '/', dirname(__DIR__) . '/');
    }

    /**
     * Absolute path to a file under the support directory.
     */
    public static function supportFile(string $relative): string
    {
        return self::supportDir() . ltrim(str_replace('\\', '/', $relative), '/');
    }

    /**
     * Absolute path to catalog/includes/modules/payment/paypal/
     */
    public static function paypalDir(): string
    {
        return str_replace('\\', '/', dirname(self::supportDir()) . '/');
    }

    /**
     * Public catalog-relative URL path for card-brand images deployed by the installer.
     */
    public static function publicImageUrl(string $filename): string
    {
        $images = defined('DIR_WS_IMAGES') ? DIR_WS_IMAGES : 'images/';
        return $images . 'paypalac/' . ltrim($filename, '/');
    }

    /**
     * Read a support-directory asset for inline script/style output.
     */
    public static function readSupportFile(string $relative): string
    {
        $path = self::supportFile($relative);
        if (!is_file($path)) {
            return '';
        }
        $contents = file_get_contents($path);
        return $contents === false ? '' : $contents;
    }

    /**
     * Catalog-relative web URL for a support file under zc_plugins (js/css are
     * allowed by Zen Cart's zc_plugins/.htaccess). Empty when not web-mappable.
     */
    public static function supportWebUrl(string $relative): string
    {
        if (!defined('DIR_FS_CATALOG')) {
            return '';
        }
        $path = str_replace('\\', '/', self::supportFile($relative));
        if (!is_file($path)) {
            return '';
        }
        $root = rtrim(str_replace('\\', '/', DIR_FS_CATALOG), '/');
        if (strpos($path, $root . '/') !== 0) {
            return '';
        }
        $rel = ltrim(substr($path, strlen($root)), '/');
        $prefix = defined('DIR_WS_CATALOG') ? DIR_WS_CATALOG : '';
        return rtrim((string)$prefix, '/') . '/' . $rel;
    }

    /**
     * Versioned stylesheet tag; falls back to inline when no public URL.
     */
    public static function supportStyleTag(string $relative): string
    {
        $path = self::supportFile($relative);
        $url = self::supportWebUrl($relative);
        if ($url !== '' && is_file($path)) {
            return '<link rel="stylesheet" href="' . $url . '?v=' . (int)filemtime($path) . '">';
        }
        $css = self::readSupportFile($relative);
        return $css === '' ? '' : '<style>' . $css . '</style>';
    }

    /**
     * Versioned script tag; falls back to inline when no public URL.
     */
    public static function supportScriptTag(string $relative, bool $defer = true): string
    {
        $path = self::supportFile($relative);
        $url = self::supportWebUrl($relative);
        if ($url !== '' && is_file($path)) {
            return '<script' . ($defer ? ' defer' : '') . ' src="' . $url . '?v=' . (int)filemtime($path) . '"></script>';
        }
        $js = self::readSupportFile($relative);
        return $js === '' ? '' : '<script>' . $js . '</script>';
    }
}
