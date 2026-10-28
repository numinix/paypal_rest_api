<?php
/**
 * PayPal Advanced Checkout Webhooks: subscription suspension event.
 *
 * Fires when a subscription enters the SUSPENDED state at PayPal (either
 * via /suspend or after a payment_failure_threshold breach). The customer
 * keeps the subscription record but PayPal will not bill again until the
 * subscription is reactivated.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license   https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace PayPalAdvancedCheckout\Webhooks\Events;

use PayPalAdvancedCheckout\Common\SubscriptionManager;
use PayPalAdvancedCheckout\Webhooks\WebhookHandlerContract;

class BillingSubscriptionSuspended extends WebhookHandlerContract
{
    /** @var array */
    protected $eventsHandled = [
        'BILLING.SUBSCRIPTION.SUSPENDED',
    ];

    public function action(): void
    {
        $remoteId = (string)($this->data['resource']['id'] ?? '');
        if ($remoteId === '') {
            $this->log->write('BILLING.SUBSCRIPTION.SUSPENDED: missing resource.id; nothing to do.');
            return;
        }

        $existing = SubscriptionManager::findByRemoteId($remoteId);
        if ($existing === null) {
            $this->log->write("BILLING.SUBSCRIPTION.SUSPENDED: no local subscription row for $remoteId; ignoring.");
            return;
        }

        $updated = SubscriptionManager::updateStatusByRemoteId($remoteId, 'suspended');

        $this->log->write(
            "BILLING.SUBSCRIPTION.SUSPENDED: remote $remoteId -> "
            . ($updated ? 'suspended' : 'no update applied')
            . ' (local id ' . ($existing['paypal_subscription_id'] ?? '?') . ').'
        );
    }
}
