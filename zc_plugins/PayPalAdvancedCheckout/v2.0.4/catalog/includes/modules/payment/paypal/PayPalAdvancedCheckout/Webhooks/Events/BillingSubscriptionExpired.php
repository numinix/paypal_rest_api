<?php
/**
 * PayPal Advanced Checkout Webhooks: subscription expiration event.
 *
 * Fires when a subscription has billed all of its configured total_cycles
 * and reaches the EXPIRED state at PayPal. The local row is flipped to
 * "expired" so the admin can distinguish naturally-ended subscriptions
 * from cancelled ones.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license   https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace PayPalAdvancedCheckout\Webhooks\Events;

use PayPalAdvancedCheckout\Common\SubscriptionManager;
use PayPalAdvancedCheckout\Webhooks\WebhookHandlerContract;

class BillingSubscriptionExpired extends WebhookHandlerContract
{
    /** @var array */
    protected $eventsHandled = [
        'BILLING.SUBSCRIPTION.EXPIRED',
    ];

    public function action(): void
    {
        $remoteId = (string)($this->data['resource']['id'] ?? '');
        if ($remoteId === '') {
            $this->log->write('BILLING.SUBSCRIPTION.EXPIRED: missing resource.id; nothing to do.');
            return;
        }

        $existing = SubscriptionManager::findByRemoteId($remoteId);
        if ($existing === null) {
            $this->log->write("BILLING.SUBSCRIPTION.EXPIRED: no local subscription row for $remoteId; ignoring.");
            return;
        }

        $updated = SubscriptionManager::updateStatusByRemoteId($remoteId, 'expired');

        $this->log->write(
            "BILLING.SUBSCRIPTION.EXPIRED: remote $remoteId -> "
            . ($updated ? 'expired' : 'no update applied')
            . ' (local id ' . ($existing['paypal_subscription_id'] ?? '?') . ').'
        );
    }
}
