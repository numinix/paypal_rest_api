<?php
/**
 * A class that provides the overall layout of "Additional Handling Methods" when
 * an order in the Zen Cart admin placed with the PayPal Restful payment module.
 *
 * @copyright Copyright 2023 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: lat9 2023 Nov 16 Modified in v2.0.0 $
 */
namespace PayPalRestful\Admin;

use PayPalRestful\Admin\GetPayPalOrderTransactions;
use PayPalRestful\Admin\Formatters\MainDisplay;
use PayPalRestful\Api\PayPalRestfulApi;

class AdminMain
{
    protected PayPalRestfulApi $ppr;

    protected string $adminNotifications = '';

    public function __construct(string $module_name, string $module_version, int $oID, PayPalRestfulApi $ppr)
    {
        $this->ppr = $ppr;

        // -----
        // Retrieve the PayPal transactions currently registered in the database
        // for subsequent display.
        //
        $ppr_txns = new GetPayPalOrderTransactions($module_name, $module_version, $oID, $ppr);
        $paypal_db_txns = $ppr_txns->getDatabaseTxns();
        if (count($paypal_db_txns) === 0) {
            $this->adminNotifications =
                '<div class="alert alert-warning text-center">' .
                    '<strong>' . MODULE_PAYMENT_PAYPALR_NO_RECORDS_FOUND . '</strong>' .
                '</div>';
            return;
        }

        // -----
        // Synchronize with PayPal, just in case a transaction was updated in the management
        // console (not via the Zen Cart admin processing).
        //
        $ppr_txns->syncPaypalTxns();

        // -----
        // When the database transactions are retrieved, messages are created if
        // the PayPal synchronization resulted in new records being added.
        //
        $this->adminNotifications = $ppr_txns->getMessages();

        // -----
        // Format the main notification table displayed, showing the basic flow of
        // transactions for this order.
        //
        $main_display = new MainDisplay($paypal_db_txns);
        $this->adminNotifications .= $main_display->get();
    }

    public function get()
    {
        return $this->adminNotifications;
    }
}
