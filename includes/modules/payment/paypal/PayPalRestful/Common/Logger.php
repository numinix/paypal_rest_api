<?php
/**
 * Debug logging class for the PayPalRestful (paypalr) Payment Module
 *
 * @copyright Copyright 2023-2024 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: lat9 2023 Nov 16 Modified in v2.0.0 $
 *
 * Last updated: v1.0.0
 */

namespace PayPalRestful\Common;

class Logger
{
    /**
     * Static variables associated with interface logging;
     *
     * @debugLogFile string
     * @debug bool
     */
    protected static bool $debug = false;
    protected static string $debugLogFile;

    // -----
    // Class constructor.
    //
    public function __construct()
    {
        // -----
        // Using the same log-file name for each page-load.  If it's already set,
        // simply return.
        //
        if (isset(self::$debugLogFile)) {
            return;
        }

        if (IS_ADMIN_FLAG === false) {
            $logfile_suffix = 'c-' . ($_SESSION['customer_id'] ?? 'na') . '-' . substr($_SESSION['customer_first_name'] ?? 'na', 0, 3) . substr($_SESSION['customer_last_name'] ?? 'na', 0, 3);
        } else {
            $logfile_suffix = 'adm-a' . $_SESSION['admin_id'];
            global $order;
            if (isset($order)) {
                $logfile_suffix .= '-o' . $order->info['order_id'];
            }
        }
        self::$debugLogFile = DIR_FS_LOGS . '/paypalr-' . $logfile_suffix . '-' . date('Ymd') . '.log';
    }

    public function enableDebug()
    {
        self::$debug = true;
    }
    public function disableDebug()
    {
        self::$debug = false;
    }

    // -----
    // Format pretty-printed JSON for the debug-log, removing any HTTP Header
    // information (present in the CURL options) and/or the actual access-token.
    //
    // Also remove unneeded return values that will just 'clutter up' the logged information,
    // unless requested to keep them.
    //
    public static function logJSON($data, bool $keep_links = false): string
    {
        if (is_array($data)) {
            unset(
                $data[CURLOPT_HTTPHEADER],
                $data['access_token'],
                $data['scope']
            );
            if ($keep_links === false) {
                unset(
                    $data['links'],
                    $data['purchase_units'][0]['payments']['authorizations']['links'],
                    $data['purchase_units'][0]['payments']['captures']['links'],
                    $data['purchase_units'][0]['payments']['refunds']['links']
                );
            }
            foreach (['authorizations', 'captures', 'refunds'] as $next_payment_type) {
                if (!isset($data['purchase_units'][0]['payments'][$next_payment_type])) {
                    continue;
                }
                for ($i = 0, $n = count($data['purchase_units'][0]['payments'][$next_payment_type]); $i < $n; $i++) {
                    unset($data['purchase_units'][0]['payments'][$next_payment_type][$i]['links']);
                }
            }
        }
        return json_encode($data, JSON_PRETTY_PRINT);
    }

    public function write(string $message, bool $include_timestamp = false, string $include_separator = '')
    {
        global $current_page_base;

        if (self::$debug === true) {
            $timestamp = ($include_timestamp === false) ? '' : ("\n" . date('Y-m-d H:i:s: ') . "($current_page_base) ");
            $separator = '';
            $separator_before = '';
            $separator_after = '';
            if ($include_separator !== '') {
                $separator = "************************************************";
                if ($include_separator === 'before') {
                    $separator_before = (strpos($message, "\n") === 0) ? "\n$separator" : "\n$separator\n";
                } else {
                    $separator_after = (substr($message, -1) === "\n") ? "$separator\n" : "\n$separator\n";
                }
            }
            error_log($separator_before . $timestamp . $message . $separator_after, 3, self::$debugLogFile);
        }
    }
}
