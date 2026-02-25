<?php
/**
 * Observer class to inject saved cards as top-level payment modules.
 *
 * This observer watches for the payment module list being built during checkout
 * and expands each saved card into a separate top-level payment option.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license   https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

use Zencart\Traits\ObserverManager;

require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/ppacAutoload.php';
if (!trait_exists('Zencart\\Traits\\ObserverManager')) {
    require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypal/PayPalRestful/Compatibility/ObserverManager.php';
}

class zcObserverPaypalrestfulSavedCards
{
    use ObserverManager;

    /**
     * Constructor - attach to relevant checkout events.
     */
    public function __construct()
    {
        // Only proceed if the saved card module is enabled
        if (!defined('MODULE_PAYMENT_PAYPALAC_SAVEDCARD_STATUS') || MODULE_PAYMENT_PAYPALAC_SAVEDCARD_STATUS !== 'True') {
            return;
        }

        // Check if vault is enabled
        if (!defined('MODULE_PAYMENT_PAYPALAC_ENABLE_VAULT') || MODULE_PAYMENT_PAYPALAC_ENABLE_VAULT !== 'True') {
            return;
        }

        // Attach to the payment modules selection notification
        $this->attach($this, [
            'NOTIFY_PAYMENT_MODULES_GET_SELECTION',
        ]);
    }

    /**
     * Handle payment module selection expansion.
     *
     * When the payment modules are gathered for display, this observer expands
     * the paypalac_savedcard module into multiple entries - one for each saved card.
     *
     * @param object $class
     * @param string $eventID
     * @param array  $params
     * @param array  &$selection
     */
    public function updateNotifyPaymentModulesGetSelection(&$class, $eventID, $params, &$selection): void
    {
        // Only process if we have a selection array and the customer is logged in
        if (!is_array($selection) || empty($selection)) {
            return;
        }

        if (!isset($_SESSION['customer_id']) || $_SESSION['customer_id'] <= 0) {
            return;
        }

        // Find the paypalac_savedcard entry in the selection
        $savedCardIndex = null;
        $originalSortOrder = 0;
        foreach ($selection as $index => $module) {
            if (isset($module['id']) && strpos($module['id'], 'paypalac_savedcard') === 0) {
                $savedCardIndex = $index;
                break;
            }
        }

        // If no saved card module found, nothing to do
        if ($savedCardIndex === null) {
            return;
        }

        // Get the saved card module instance to retrieve all cards
        global $paypalac_savedcard;
        if (!isset($paypalac_savedcard) || !is_object($paypalac_savedcard)) {
            // Try to instantiate it
            if (!class_exists('paypalac_savedcard')) {
                require_once DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/paypalac_savedcard.php';
            }
            $paypalac_savedcard = new paypalac_savedcard();
        }

        // Get all saved card selections
        $cardSelections = $paypalac_savedcard->getSelections();

        // If no cards or only one card, leave as is
        if (count($cardSelections) <= 1) {
            return;
        }

        // Remove the original entry
        array_splice($selection, $savedCardIndex, 1);

        // Insert all card selections at the original position
        foreach (array_reverse($cardSelections) as $cardSelection) {
            array_splice($selection, $savedCardIndex, 0, [$cardSelection]);
        }
    }
}
