<?php
/**
 * PayPal Advanced Checkout: Subscription cycle order factory.
 *
 * When a PayPal-managed subscription bills the customer for a cycle (the
 * activation cycle or any renewal), PayPal sends a PAYMENT.SALE.COMPLETED
 * webhook. This class is responsible for creating a Zen Cart order that
 * represents that single cycle, so admins can see the cycle in the orders
 * grid, void/refund the cycle's payment, and have it appear on the
 * customer's account "My Orders" page the same way one-off paypalac orders
 * do. Cloning the original order (placed at checkout when the subscription
 * was approved) keeps the customer record, billing/shipping snapshot,
 * products list, totals, and status history aligned with the customer's
 * intent at sign-up time, with the order_total swapped for the cycle's
 * billed amount.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license   https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace PayPalAdvancedCheckout\Common;

use PayPalAdvancedCheckout\Common\Logger;

class SubscriptionCycleOrderFactory
{
    /** @var Logger|null */
    protected $log;

    public function __construct(?Logger $log = null)
    {
        $this->log = $log;
    }

    /**
     * Clone $originalOrderId into a new Zen Cart order representing a single
     * subscription cycle's billing. Returns the new orders_id, or 0 on failure.
     *
     * The clone reuses the original order's customer/billing/shipping/products
     * verbatim (the cycle should be billing for the same line item) but
     * overrides:
     *   - order_total to the cycle's amount (some plans have setup fees /
     *     trial pricing that differ from the recurring amount)
     *   - orders_status to MODULE_PAYMENT_PAYPALAC_ORDER_STATUS_ID (paid)
     *   - date_purchased / last_modified to now
     *   - payment_method/payment_module_code to 'paypalac' so admin tools
     *     resolve the right payment-module class
     *
     * @param int    $originalOrderId    The orders_id of the order placed when
     *                                   the customer first approved the
     *                                   subscription at PayPal.
     * @param float  $cycleAmount        Currency-major amount actually billed
     *                                   by PayPal for this cycle.
     * @param string $cycleCurrencyCode  ISO 4217 currency code reported in
     *                                   the webhook (e.g. "USD").
     * @param string $saleId             PayPal sale id (resource.id from the
     *                                   PAYMENT.SALE.COMPLETED event), used
     *                                   for the transaction comment.
     * @param string $remoteSubscriptionId I-XXXXX subscription id at PayPal,
     *                                   surfaced in the order's status
     *                                   history for traceability.
     */
    public function cloneOrderForCycle(
        int $originalOrderId,
        float $cycleAmount,
        string $cycleCurrencyCode,
        string $saleId,
        string $remoteSubscriptionId
    ): int {
        global $db;

        if ($originalOrderId <= 0) {
            $this->logMessage("cloneOrderForCycle: invalid originalOrderId ($originalOrderId).");
            return 0;
        }

        $original = $db->Execute("SELECT * FROM " . TABLE_ORDERS . " WHERE orders_id = " . $originalOrderId . " LIMIT 1");
        if (!is_object($original) || $original->EOF) {
            $this->logMessage("cloneOrderForCycle: original order #$originalOrderId not found.");
            return 0;
        }

        $now = date('Y-m-d H:i:s');
        $paidStatusId = defined('MODULE_PAYMENT_PAYPALAC_ORDER_STATUS_ID') ? (int)MODULE_PAYMENT_PAYPALAC_ORDER_STATUS_ID : 2;
        if ($paidStatusId <= 0) {
            $paidStatusId = 2;
        }

        // Build the new orders row by copying the original then overlaying
        // cycle-specific fields. Using zen_db_perform keeps the column list
        // tolerant of schema additions over time.
        $newOrderColumns = $original->fields;
        unset($newOrderColumns['orders_id']);

        $newOrderColumns['payment_module_code'] = 'paypalac';
        if (empty($newOrderColumns['payment_method']) || stripos((string)$newOrderColumns['payment_method'], 'paypal') === false) {
            $newOrderColumns['payment_method'] = 'PayPal Advanced Checkout (Subscription)';
        }
        $newOrderColumns['orders_status']  = $paidStatusId;
        $newOrderColumns['date_purchased'] = $now;
        $newOrderColumns['last_modified']  = $now;
        $newOrderColumns['order_total']    = $cycleAmount;
        $newOrderColumns['currency']       = $cycleCurrencyCode !== '' ? $cycleCurrencyCode : ($newOrderColumns['currency'] ?? 'USD');

        \zen_db_perform(TABLE_ORDERS, $newOrderColumns);
        $newOrderId = (int)$db->insert_ID();
        if ($newOrderId <= 0) {
            $this->logMessage("cloneOrderForCycle: failed to INSERT new orders row for original #$originalOrderId.");
            return 0;
        }

        $this->logMessage(
            "cloneOrderForCycle: created cycle order #$newOrderId cloned from "
            . "#$originalOrderId (cycle amount=$cycleAmount $cycleCurrencyCode, "
            . "sale=$saleId, subscription=$remoteSubscriptionId)."
        );

        $this->cloneOrdersProducts($originalOrderId, $newOrderId);
        $this->cloneOrdersTotalForCycle($originalOrderId, $newOrderId, $cycleAmount, $newOrderColumns['currency']);
        $this->writeStatusHistory(
            $newOrderId,
            $paidStatusId,
            "PayPal subscription cycle billed. Subscription: $remoteSubscriptionId. Sale: $saleId. Cloned from order #$originalOrderId."
        );

        return $newOrderId;
    }

    /**
     * Record the sale into the paypal table as a CAPTURE so that paypalac's
     * admin_notification panel on the orders page shows it and the existing
     * refund flow (refundCapture against /v2/payments/captures/{id}/refund)
     * can act on it. We use 'CAPTURE' (rather than 'SALE') because the
     * existing AdminMain/DoRefund classes key off CAPTURE rows; modern
     * PayPal subscriptions back v2 captures under the hood so the sale id
     * is a valid capture id at /v2/payments/captures/{id}.
     */
    public function recordCaptureForCycle(
        int $newOrderId,
        string $saleId,
        float $amount,
        string $currencyCode,
        string $remoteSubscriptionId,
        ?string $payerEmail,
        ?string $payerId
    ): void {
        global $db;

        if ($newOrderId <= 0 || $saleId === '') {
            return;
        }

        $existing = $db->Execute(
            "SELECT paypal_ipn_id FROM " . TABLE_PAYPAL
            . " WHERE order_id = " . (int)$newOrderId
            . " AND txn_id = '" . zen_db_input($saleId) . "'"
            . " AND txn_type = 'CAPTURE' LIMIT 1"
        );
        if (is_object($existing) && !$existing->EOF) {
            $this->logMessage(
                "recordCaptureForCycle: capture $saleId already recorded for order #$newOrderId; skipping insert."
            );
            return;
        }

        // module_name MUST be 'paypalac' so the existing admin_notification
        // pipeline picks this row up; GetPayPalOrderTransactions filters with
        // module_name IN ('paypalac', 'paypalr') and silently drops anything
        // without a matching module_name, which means the order edit page
        // shows no captures and therefore no Refund button. Same reason we
        // populate payment_date with a real timestamp -- the MainDisplay
        // formatter renders payment_date next to each row and a zero-date
        // makes it look like the capture never landed.
        $now = date('Y-m-d H:i:s');
        $row = [
            'order_id'              => (int)$newOrderId,
            'module_name'           => 'paypalac',
            'txn_type'              => 'CAPTURE',
            'txn_id'                => $saleId,
            'parent_txn_id'         => $remoteSubscriptionId,
            'payment_type'          => 'paypal_subscription',
            'payment_status'        => 'COMPLETED',
            'pending_reason'        => '',
            'invoice'               => '',
            'mc_currency'           => $currencyCode,
            'mc_gross'              => $amount,
            'mc_fee'                => 0,
            'payer_email'           => (string)$payerEmail,
            'payer_id'              => (string)$payerId,
            'payer_business_name'   => '',
            'receiver_email'        => '',
            'receiver_id'           => '',
            'payment_date'          => $now,
            'last_modified'         => $now,
            'date_added'            => $now,
            'memo'                  => json_encode([
                'paypal_subscription_remote_id' => $remoteSubscriptionId,
                'sale_id'                       => $saleId,
                'source'                        => 'PAYMENT.SALE.COMPLETED webhook',
            ]),
        ];

        \zen_db_perform(TABLE_PAYPAL, $row);
        $this->logMessage(
            "recordCaptureForCycle: recorded CAPTURE $saleId on order #$newOrderId "
            . "(amount=$amount $currencyCode, subscription=$remoteSubscriptionId)."
        );
    }

    /**
     * Mark the original order paid and append a status history entry noting
     * the activation-cycle sale. Used when the cycle being billed is the
     * activation cycle (the order is still in "Pending Subscription
     * Activation"); we don't need a clone in that case -- the original
     * order is the activation cycle.
     */
    public function markOriginalActivationCyclePaid(
        int $originalOrderId,
        string $saleId,
        float $amount,
        string $currencyCode,
        string $remoteSubscriptionId
    ): void {
        global $db;

        if ($originalOrderId <= 0) {
            return;
        }

        $paidStatusId = defined('MODULE_PAYMENT_PAYPALAC_ORDER_STATUS_ID') ? (int)MODULE_PAYMENT_PAYPALAC_ORDER_STATUS_ID : 2;
        if ($paidStatusId <= 0) {
            $paidStatusId = 2;
        }

        $now = date('Y-m-d H:i:s');
        $db->Execute(
            "UPDATE " . TABLE_ORDERS
            . " SET orders_status = " . (int)$paidStatusId . ", last_modified = '" . zen_db_input($now) . "'"
            . " WHERE orders_id = " . (int)$originalOrderId
        );
        $this->writeStatusHistory(
            $originalOrderId,
            $paidStatusId,
            "PayPal subscription activated and first cycle billed. Subscription: $remoteSubscriptionId. Sale: $saleId. Amount: $amount $currencyCode."
        );
        $this->logMessage(
            "markOriginalActivationCyclePaid: order #$originalOrderId set to status $paidStatusId for activation cycle of $remoteSubscriptionId."
        );
    }

    protected function cloneOrdersProducts(int $sourceOrderId, int $targetOrderId): void
    {
        global $db;

        $products = $db->Execute("SELECT * FROM " . TABLE_ORDERS_PRODUCTS . " WHERE orders_id = " . $sourceOrderId);
        while (is_object($products) && !$products->EOF) {
            $row = $products->fields;
            $oldOrdersProductsId = (int)$row['orders_products_id'];
            unset($row['orders_products_id']);
            $row['orders_id'] = $targetOrderId;
            \zen_db_perform(TABLE_ORDERS_PRODUCTS, $row);
            $newOrdersProductsId = (int)$db->insert_ID();

            // Carry attribute selections forward so the cycle order is a
            // faithful copy (subscription configuration values like Billing
            // Period live here too).
            $attrs = $db->Execute(
                "SELECT * FROM " . TABLE_ORDERS_PRODUCTS_ATTRIBUTES
                . " WHERE orders_products_id = " . $oldOrdersProductsId
            );
            while (is_object($attrs) && !$attrs->EOF) {
                $attrRow = $attrs->fields;
                unset($attrRow['orders_products_attributes_id']);
                $attrRow['orders_id'] = $targetOrderId;
                $attrRow['orders_products_id'] = $newOrdersProductsId;
                \zen_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $attrRow);
                $attrs->MoveNext();
            }

            $products->MoveNext();
        }
    }

    /**
     * Build a minimal orders_total set for the cycle order:
     *   1. Subtotal (the cycle amount)
     *   2. Total    (the cycle amount)
     * The original order may carry shipping/tax/discounts that don't apply
     * to subsequent cycle billings (PayPal handles taxes inside its plan).
     */
    protected function cloneOrdersTotalForCycle(
        int $sourceOrderId,
        int $targetOrderId,
        float $cycleAmount,
        string $currencyCode
    ): void {
        $rows = [
            [
                'orders_id'      => $targetOrderId,
                'title'          => 'Sub-Total:',
                'text'           => '$' . number_format($cycleAmount, 2),
                'value'          => $cycleAmount,
                'class'          => 'ot_subtotal',
                'sort_order'     => 1,
            ],
            [
                'orders_id'      => $targetOrderId,
                'title'          => 'Total:',
                'text'           => '<b>$' . number_format($cycleAmount, 2) . '</b>',
                'value'          => $cycleAmount,
                'class'          => 'ot_total',
                'sort_order'     => 99,
            ],
        ];
        foreach ($rows as $r) {
            \zen_db_perform(TABLE_ORDERS_TOTAL, $r);
        }
    }

    protected function writeStatusHistory(int $orderId, int $statusId, string $comment): void
    {
        $sql = [
            'orders_id'         => $orderId,
            'orders_status_id'  => $statusId,
            'date_added'        => date('Y-m-d H:i:s'),
            'customer_notified' => 0,
            'comments'          => $comment,
        ];
        \zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql);
    }

    protected function logMessage(string $message): void
    {
        if ($this->log instanceof Logger) {
            $this->log->write($message);
        }
    }
}
