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
}
