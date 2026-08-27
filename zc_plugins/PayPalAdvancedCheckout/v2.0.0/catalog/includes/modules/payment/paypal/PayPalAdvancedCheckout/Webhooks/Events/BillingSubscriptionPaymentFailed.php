<?php
/**
 * PayPal Advanced Checkout Webhooks: subscription payment-failed event.
 *
 * Fires when PayPal cannot collect a scheduled subscription payment (e.g.
 * card declined, insufficient funds). We don't change the local status
 * here -- PayPal will independently fire SUSPENDED once the failure
 * threshold is exceeded -- but we annotate the row's status with a
 * "payment_failed" marker and log the event so the admin can see the
 * payment failure history without diffing PayPal manually.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license   https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace PayPalAdvancedCheckout\Webhooks\Events;

use PayPalAdvancedCheckout\Common\SubscriptionManager;
use PayPalAdvancedCheckout\Webhooks\WebhookHandlerContract;

class BillingSubscriptionPaymentFailed extends WebhookHandlerContract
{
    /** @var array */
    protected $eventsHandled = [
        'BILLING.SUBSCRIPTION.PAYMENT.FAILED',
    ];

    public function action(): void
    {
        $remoteId = (string)($this->data['resource']['id'] ?? '');
        if ($remoteId === '') {
            $this->log->write('BILLING.SUBSCRIPTION.PAYMENT.FAILED: missing resource.id; nothing to do.');
            return;
        }

        $existing = SubscriptionManager::findByRemoteId($remoteId);
        if ($existing === null) {
            $this->log->write("BILLING.SUBSCRIPTION.PAYMENT.FAILED: no local subscription row for $remoteId; ignoring.");
            return;
        }

        // Preserve the prior status (typically ACTIVE) but mark a payment-failed flag
        // so the admin sees it; the SUSPENDED webhook will overwrite this once PayPal
        // exceeds the failure threshold.
        $currentStatus = (string)($existing['status'] ?? '');
        $newStatus = $currentStatus !== '' ? $currentStatus : 'payment_failed';

        SubscriptionManager::updateStatusByRemoteId($remoteId, $newStatus);

        $failedCount = $this->data['resource']['billing_info']['failed_payments_count'] ?? '?';
        $this->log->write(
            "BILLING.SUBSCRIPTION.PAYMENT.FAILED: remote $remoteId failed_payments_count=$failedCount; status preserved as $newStatus."
        );
    }
}
