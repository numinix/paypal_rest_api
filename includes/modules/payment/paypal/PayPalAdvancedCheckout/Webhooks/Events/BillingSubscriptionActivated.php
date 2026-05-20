<?php
/**
 * PayPal Advanced Checkout Webhooks: subscription activation event.
 *
 * Fires when a customer-approved subscription transitions to the ACTIVE
 * state at PayPal. NCRS uses the "wait_webhook" flow for paypalac-managed
 * subscriptions: the Zen Cart order is recorded in a pending state at
 * checkout, then this handler flips the local subscription row to ACTIVE
 * and updates the order status so the customer's account shows the
 * subscription as live. The first recurring payment lands separately via
 * PAYMENT.SALE.COMPLETED.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license   https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace PayPalAdvancedCheckout\Webhooks\Events;

use PayPalAdvancedCheckout\Common\SubscriptionManager;
use PayPalAdvancedCheckout\Webhooks\WebhookHandlerContract;

class BillingSubscriptionActivated extends WebhookHandlerContract
{
    /** @var array */
    protected $eventsHandled = [
        'BILLING.SUBSCRIPTION.ACTIVATED',
    ];

    public function action(): void
    {
        $remoteId = (string)($this->data['resource']['id'] ?? '');
        if ($remoteId === '') {
            $this->log->write('BILLING.SUBSCRIPTION.ACTIVATED: missing resource.id; nothing to do.');
            return;
        }

        $existing = SubscriptionManager::findByRemoteId($remoteId);
        if ($existing === null) {
            $this->log->write("BILLING.SUBSCRIPTION.ACTIVATED: no local subscription row for $remoteId; ignoring.");
            return;
        }

        $extras = [
            'next_billing_date' => $this->extractDate($this->data['resource']['billing_info']['next_billing_time'] ?? null),
        ];

        $updated = SubscriptionManager::updateStatusByRemoteId($remoteId, SubscriptionManager::STATUS_ACTIVE, $extras);

        $this->log->write(
            "BILLING.SUBSCRIPTION.ACTIVATED: remote $remoteId -> "
            . ($updated ? 'ACTIVE' : 'no update applied')
            . ' (local id ' . ($existing['paypal_subscription_id'] ?? '?') . ').'
        );

        $this->maybeMarkOrderPaid((int)($existing['orders_id'] ?? 0));
    }

    /**
     * Update the linked Zen Cart order's status when the subscription becomes
     * active. Uses MODULE_PAYMENT_PAYPALAC_ORDER_STATUS_ID if configured;
     * otherwise leaves the order untouched (admin can set a status manually).
     */
    protected function maybeMarkOrderPaid(int $ordersId): void
    {
        if ($ordersId <= 0) {
            return;
        }

        if (!defined('MODULE_PAYMENT_PAYPALAC_ORDER_STATUS_ID')) {
            return;
        }

        $statusId = (int)MODULE_PAYMENT_PAYPALAC_ORDER_STATUS_ID;
        if ($statusId <= 0) {
            return;
        }

        global $db;

        $now = date('Y-m-d H:i:s');
        $db->Execute(
            "UPDATE " . TABLE_ORDERS
            . " SET orders_status = " . $statusId . ", last_modified = '" . zen_db_input($now) . "'"
            . " WHERE orders_id = " . (int)$ordersId
        );

        $comment = 'PayPal subscription activated (webhook).';
        $statusSql = [
            'orders_id' => (int)$ordersId,
            'orders_status_id' => $statusId,
            'date_added' => $now,
            'customer_notified' => 0,
            'comments' => $comment,
        ];
        \zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $statusSql);
    }

    protected function extractDate(?string $iso): ?string
    {
        if (!is_string($iso) || $iso === '') {
            return null;
        }
        $ts = strtotime($iso);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d', $ts);
    }
}
