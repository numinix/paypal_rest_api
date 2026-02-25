<?php
/**
 * Vault card observer for PayPal Advanced Checkout.
 *
 * Saves vaulted payment tokens after order creation to support all checkout systems
 * (standard 3-page checkout, One-Page Checkout, and other custom checkout flows).
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license   https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

use PayPalAdvancedCheckout\Common\VaultManager;
use Zencart\Traits\ObserverManager;

require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/ppacAutoload.php';
if (!trait_exists('Zencart\\Traits\\ObserverManager')) {
    require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalAdvancedCheckout/Compatibility/ObserverManager.php';
}

class zcObserverPaypaladvcheckoutVault
{
    use ObserverManager;

    /** @var array<int,bool> */
    protected array $processedOrders = [];

    public function __construct()
    {
        // -----
        // If the base paypalac payment-module isn't installed, nothing further to do here.
        // The observer is needed as long as any PayPal payment module is enabled.
        //
        if (!defined('MODULE_PAYMENT_PAYPALAC_VERSION')) {
            return;
        }

        // -----
        // Check if at least one PayPal payment module is enabled
        //
        $anyModuleEnabled = (
            (defined('MODULE_PAYMENT_PAYPALAC_STATUS') && MODULE_PAYMENT_PAYPALAC_STATUS === 'True') ||
            (defined('MODULE_PAYMENT_PAYPALAC_CREDITCARD_STATUS') && MODULE_PAYMENT_PAYPALAC_CREDITCARD_STATUS === 'True') ||
            (defined('MODULE_PAYMENT_PAYPALAC_APPLEPAY_STATUS') && MODULE_PAYMENT_PAYPALAC_APPLEPAY_STATUS === 'True') ||
            (defined('MODULE_PAYMENT_PAYPALAC_GOOGLEPAY_STATUS') && MODULE_PAYMENT_PAYPALAC_GOOGLEPAY_STATUS === 'True') ||
            (defined('MODULE_PAYMENT_PAYPALAC_VENMO_STATUS') && MODULE_PAYMENT_PAYPALAC_VENMO_STATUS === 'True') ||
            (defined('MODULE_PAYMENT_PAYPALAC_SAVEDCARD_STATUS') && MODULE_PAYMENT_PAYPALAC_SAVEDCARD_STATUS === 'True')
        );

        if (!$anyModuleEnabled) {
            return;
        }

        $this->attach($this, ['NOTIFY_CHECKOUT_PROCESS_AFTER_ORDER_CREATE_ADD_PRODUCTS']);
    }

    /**
     * Process vault card data after order products have been created.
     *
     * This observer fires after the order has been fully created and all products
     * have been added. It retrieves vault card data stored in the session during
     * payment processing and saves it to the database.
     */
    public function updateNotifyCheckoutProcessAfterOrderCreateAddProducts(&$class, $eventID, $params): void
    {
        $ordersId = (int)($_SESSION['order_number_created'] ?? 0);
        if ($ordersId <= 0 || isset($this->processedOrders[$ordersId])) {
            return;
        }

        // Check if there's vault data to process
        if (!isset($_SESSION['PayPalAdvancedCheckout']['VaultCardData'])) {
            return;
        }

        $this->processedOrders[$ordersId] = true;

        $vaultCardData = $_SESSION['PayPalAdvancedCheckout']['VaultCardData'];
        
        // Ensure we have the required data
        if (!is_array($vaultCardData) || empty($vaultCardData['card_source'])) {
            return;
        }

        global $db;

        // Get customer ID from the order
        $orderInfo = $db->Execute(
            "SELECT customers_id
               FROM " . TABLE_ORDERS . "
              WHERE orders_id = " . $ordersId . "
              LIMIT 1"
        );

        if ($orderInfo->EOF) {
            return;
        }

        $customersId = (int)$orderInfo->fields['customers_id'];
        if ($customersId <= 0) {
            return;
        }

        // Save the vault card data
        $cardSource = $vaultCardData['card_source'];
        $visible = $vaultCardData['visible'] ?? true;  // Default to visible for backward compatibility
        $storedVault = VaultManager::saveVaultedCard($customersId, $ordersId, $cardSource, $visible);
        
        if ($storedVault !== null) {
            // Notify other observers that a vault card was saved
            $this->notify('NOTIFY_PAYPALAC_VAULT_CARD_SAVED', $storedVault);
        }

        // Clean up the session data
        unset($_SESSION['PayPalAdvancedCheckout']['VaultCardData']);
    }

    /**
     * Ensure notify() method is available for compatibility across Zen Cart versions.
     */
    public function notify(
        $eventID,
        $param1 = [],
        &$param2 = null,
        &$param3 = null,
        &$param4 = null,
        &$param5 = null,
        &$param6 = null,
        &$param7 = null,
        &$param8 = null,
        &$param9 = null
    ) {
        // Check if the newer EventDto notifier is available (ZC 2.0+)
        if (class_exists('\\Zencart\\Events\\EventDto')) {
            $eventDispatcher = \Zencart\Events\EventDto::getInstance();

            if (method_exists($eventDispatcher, 'notify')) {
                $eventDispatcher->notify(
                    $eventID,
                    $param1,
                    $param2,
                    $param3,
                    $param4,
                    $param5,
                    $param6,
                    $param7,
                    $param8,
                    $param9
                );
                return;
            }

            if (method_exists($eventDispatcher, 'dispatch')) {
                $eventDispatcher->dispatch(
                    $eventID,
                    $param1,
                    $param2,
                    $param3,
                    $param4,
                    $param5,
                    $param6,
                    $param7,
                    $param8,
                    $param9
                );
                return;
            }
        }

        // Fall back to the legacy $zco_notifier (ZC 1.5.x)
        global $zco_notifier;
        if (is_object($zco_notifier) && method_exists($zco_notifier, 'notify')) {
            $zco_notifier->notify(
                $eventID,
                $param1,
                $param2,
                $param3,
                $param4,
                $param5,
                $param6,
                $param7,
                $param8,
                $param9
            );
        }
    }
}
