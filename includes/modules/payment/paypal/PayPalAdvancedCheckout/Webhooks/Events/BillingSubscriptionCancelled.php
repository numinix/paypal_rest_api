<?php
/**
 * PayPal Advanced Checkout Webhooks: subscription cancellation event.
 *
 * Fires when a subscription is cancelled at PayPal, either by the customer
 * from inside their PayPal account or by the store admin via the
 * Subscription Manager. Either way we mirror the status into our local
 * record so the admin UI and the recurring cron stay in sync.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license   https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace PayPalAdvancedCheckout\Webhooks\Events;

use PayPalAdvancedCheckout\Common\SubscriptionManager;
use PayPalAdvancedCheckout\Webhooks\WebhookHandlerContract;

class BillingSubscriptionCancelled extends WebhookHandlerContract
{
    /** @var array */
    protected $eventsHandled = [
        'BILLING.SUBSCRIPTION.CANCELLED',
    ];

    public function action(): void
    {
        $remoteId = (string)($this->data['resource']['id'] ?? '');
        if ($remoteId === '') {
            $this->log->write('BILLING.SUBSCRIPTION.CANCELLED: missing resource.id; nothing to do.');
            return;
        }

        $existing = SubscriptionManager::findByRemoteId($remoteId);
        if ($existing === null) {
            $this->log->write("BILLING.SUBSCRIPTION.CANCELLED: no local subscription row for $remoteId; ignoring.");
            return;
        }

        $updated = SubscriptionManager::updateStatusByRemoteId($remoteId, 'cancelled');

        $this->log->write(
            "BILLING.SUBSCRIPTION.CANCELLED: remote $remoteId -> "
            . ($updated ? 'cancelled' : 'no update applied')
            . ' (local id ' . ($existing['paypal_subscription_id'] ?? '?') . ').'
        );
    }
}
