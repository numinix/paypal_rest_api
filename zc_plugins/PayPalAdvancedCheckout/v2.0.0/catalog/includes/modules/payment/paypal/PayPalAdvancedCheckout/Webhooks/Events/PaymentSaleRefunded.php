<?php
/**
 * PayPal Advanced Checkout Webhooks: subscription cycle refund event.
 *
 * Fires when a previously-completed subscription sale is refunded (either by
 * the merchant via PayPal's portal, by the store admin via paypalac's
 * AdminMain refund button, or programmatically via the API). We update our
 * paypal table to mirror the refund so the admin orders page shows the
 * refunded amount, and bump the order's status to the configured "refunded"
 * status when the refund is total.
 *
 * Webhook payload fields used:
 *   - resource.id                    = refund id
 *   - resource.parent_payment / resource.sale_id
 *                                    = the sale this refund applies to
 *   - resource.amount.{total,currency}
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license   https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace PayPalAdvancedCheckout\Webhooks\Events;

use PayPalAdvancedCheckout\Webhooks\WebhookHandlerContract;

class PaymentSaleRefunded extends WebhookHandlerContract
{
    /** @var array */
    protected $eventsHandled = [
        'PAYMENT.SALE.REFUNDED',
    ];

    public function action(): void
    {
        global $db;

        $resource = $this->data['resource'] ?? [];
        if (!is_array($resource)) {
            $this->log->write('PAYMENT.SALE.REFUNDED: resource missing or malformed; nothing to do.');
            return;
        }

        $refundId = trim((string)($resource['id'] ?? ''));
        $saleId   = trim((string)($resource['sale_id']
            ?? $resource['parent_payment']
            ?? $this->extractSaleIdFromLinks($resource['links'] ?? [])
            ?? ''
        ));

        if ($saleId === '') {
            $this->log->write(
                'PAYMENT.SALE.REFUNDED: could not determine which sale was refunded '
                . '(no sale_id, parent_payment, or up-link); skipping.'
            );
            return;
        }

        $refundAmount   = (float)($resource['amount']['total'] ?? 0);
        $refundCurrency = trim((string)($resource['amount']['currency'] ?? 'USD'));

        // Locate the original CAPTURE row written by PaymentSaleCompleted so
        // we know which Zen Cart order this refund affects.
        $capture = $db->Execute(
            "SELECT order_id, mc_gross FROM " . TABLE_PAYPAL
            . " WHERE txn_id = '" . zen_db_input($saleId) . "' AND txn_type = 'CAPTURE' LIMIT 1"
        );
        if (!is_object($capture) || $capture->EOF) {
            $this->log->write(
                "PAYMENT.SALE.REFUNDED: sale $saleId not found in TABLE_PAYPAL; cannot record refund."
            );
            return;
        }

        $orderId   = (int)$capture->fields['order_id'];
        $saleGross = (float)$capture->fields['mc_gross'];

        // Skip if this exact refund is already recorded.
        $existing = $db->Execute(
            "SELECT paypal_ipn_id FROM " . TABLE_PAYPAL
            . " WHERE txn_id = '" . zen_db_input($refundId) . "' AND txn_type = 'REFUND' LIMIT 1"
        );
        if (is_object($existing) && !$existing->EOF) {
            $this->log->write("PAYMENT.SALE.REFUNDED: refund $refundId already recorded; skipping.");
            return;
        }

        // If an admin-initiated refund crashed mid-bookkeeping and we backfilled
        // a placeholder row (txn_id prefixed BACKFILL_REFUND_), upgrade that row
        // in place to the real refund id rather than inserting a duplicate. We
        // match on parent_txn_id (sale id) + amount because the placeholder
        // shares those values verbatim.
        $placeholder = $db->Execute(
            "SELECT paypal_ipn_id FROM " . TABLE_PAYPAL
            . " WHERE txn_type = 'REFUND'"
            . " AND parent_txn_id = '" . zen_db_input($saleId) . "'"
            . " AND txn_id LIKE 'BACKFILL_REFUND_%'"
            . " AND ABS(mc_gross - " . sprintf('%.4f', $refundAmount) . ") < 0.005"
            . " LIMIT 1"
        );
        if (is_object($placeholder) && !$placeholder->EOF) {
            $oldIpn = (int)$placeholder->fields['paypal_ipn_id'];
            $db->Execute(
                "UPDATE " . TABLE_PAYPAL
                . " SET txn_id = '" . zen_db_input($refundId) . "',"
                . " payment_status = 'REFUNDED',"
                . " last_modified = '" . zen_db_input(date('Y-m-d H:i:s')) . "'"
                . " WHERE paypal_ipn_id = " . $oldIpn
            );
            $this->log->write(
                "PAYMENT.SALE.REFUNDED: upgraded backfill placeholder (ipn=$oldIpn) to "
                . "real refund_id $refundId for sale $saleId."
            );
            return;
        }

        $row = [
            'order_id'        => $orderId,
            'txn_type'        => 'REFUND',
            'txn_id'          => $refundId,
            'parent_txn_id'   => $saleId,
            'payment_type'    => 'paypal_subscription',
            'payment_status'  => 'REFUNDED',
            'pending_reason'  => '',
            'invoice'         => '',
            'mc_currency'     => $refundCurrency,
            'mc_gross'        => $refundAmount,
            'mc_fee'          => 0,
            'payer_email'     => '',
            'payer_id'        => '',
            'receiver_email'  => '',
            'receiver_id'     => '',
            'last_modified'   => date('Y-m-d H:i:s'),
            'date_added'      => date('Y-m-d H:i:s'),
            'memo'            => json_encode([
                'source'  => $this->data['event_type'] ?? 'PAYMENT.SALE.REFUNDED',
                'sale_id' => $saleId,
            ]),
        ];
        \zen_db_perform(TABLE_PAYPAL, $row);

        // Adjust the order's status when the refund covers the whole sale.
        $isFullRefund = ($refundAmount >= $saleGross - 0.005);
        if ($isFullRefund) {
            $refundedStatusId = defined('MODULE_PAYMENT_PAYPALAC_REFUNDED_STATUS_ID')
                ? (int)MODULE_PAYMENT_PAYPALAC_REFUNDED_STATUS_ID
                : 1;
            if ($refundedStatusId <= 0) {
                $refundedStatusId = 1;
            }
            $now = date('Y-m-d H:i:s');
            $db->Execute(
                "UPDATE " . TABLE_ORDERS
                . " SET orders_status = " . $refundedStatusId . ", last_modified = '" . zen_db_input($now) . "'"
                . " WHERE orders_id = " . (int)$orderId
            );
            \zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, [
                'orders_id'         => $orderId,
                'orders_status_id'  => $refundedStatusId,
                'date_added'        => $now,
                'customer_notified' => 0,
                'comments'          => "PayPal subscription sale refunded in full. Refund: $refundId. Sale: $saleId. Amount: $refundAmount $refundCurrency.",
            ]);
        } else {
            \zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, [
                'orders_id'         => $orderId,
                'orders_status_id'  => -1,
                'date_added'        => date('Y-m-d H:i:s'),
                'customer_notified' => 0,
                'comments'          => "PayPal subscription sale partially refunded. Refund: $refundId. Sale: $saleId. Amount: $refundAmount $refundCurrency.",
            ]);
        }

        $this->log->write(
            "PAYMENT.SALE.REFUNDED: recorded refund $refundId of $refundAmount $refundCurrency "
            . "against sale $saleId / order #$orderId (full_refund=" . ($isFullRefund ? 'yes' : 'no') . ")."
        );
    }

    /**
     * Some PayPal payloads only carry the sale link in the resource.links
     * array (rel='up'). Pull the sale id out of that URL as a last resort.
     */
    protected function extractSaleIdFromLinks(array $links): ?string
    {
        foreach ($links as $link) {
            $rel = strtolower((string)($link['rel'] ?? ''));
            if ($rel === 'up') {
                $href = (string)($link['href'] ?? '');
                if (preg_match('#/payments/sale/([^/]+)#', $href, $m)) {
                    return $m[1];
                }
                if (preg_match('#/payments/captures/([^/]+)#', $href, $m)) {
                    return $m[1];
                }
            }
        }
        return null;
    }
}
