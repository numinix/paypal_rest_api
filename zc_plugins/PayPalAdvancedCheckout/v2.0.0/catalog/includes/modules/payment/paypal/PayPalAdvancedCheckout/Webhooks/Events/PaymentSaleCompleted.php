<?php
/**
 * PayPal Advanced Checkout Webhooks: subscription cycle billing event.
 *
 * Fires every time PayPal successfully bills a customer for a cycle of a
 * PayPal-managed subscription (the activation cycle and each renewal). The
 * resource carries:
 *   - id                  = PayPal sale id for this cycle's payment
 *   - billing_agreement_id = our remote subscription id (I-XXXXXX)
 *   - amount.{currency,total}
 *   - state               = "completed"
 *   - parent_payment      = sometimes empty for subscription sales
 *
 * Strategy (matches what the saved_credit_cards_recurring cron does for
 * Zen Cart-managed subscriptions):
 *   - The activation cycle (subscription was 'pending' locally): mark the
 *     original Zen Cart order paid, record the sale as a CAPTURE row in
 *     TABLE_PAYPAL keyed on the original orders_id. We don't create a new
 *     order here because the original placed-at-checkout order IS the
 *     activation cycle.
 *   - Renewal cycles (subscription was already 'active' locally): clone
 *     the original order into a fresh Zen Cart order whose orders_id is
 *     unique, mark that clone paid, and attach the CAPTURE row to it. Now
 *     each cycle appears in the admin orders grid and is individually
 *     void/refundable via paypalac's existing AdminMain refund flow.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license   https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace PayPalAdvancedCheckout\Webhooks\Events;

use PayPalAdvancedCheckout\Common\SubscriptionCycleOrderFactory;
use PayPalAdvancedCheckout\Common\SubscriptionManager;
use PayPalAdvancedCheckout\Webhooks\WebhookHandlerContract;

class PaymentSaleCompleted extends WebhookHandlerContract
{
    /** @var array */
    protected $eventsHandled = [
        'PAYMENT.SALE.COMPLETED',
    ];

    public function action(): void
    {
        $resource = $this->data['resource'] ?? [];
        if (!is_array($resource)) {
            $this->log->write('PAYMENT.SALE.COMPLETED: resource missing or malformed; nothing to do.');
            return;
        }

        // The billing_agreement_id field is what links a sale back to a
        // subscription. Plain one-off PayPal sales (not subscription related)
        // don't carry this field; ignore those here -- they're handled by
        // PaymentCaptureCompleted on the v2 capture event.
        $remoteSubscriptionId = trim((string)($resource['billing_agreement_id'] ?? ''));
        if ($remoteSubscriptionId === '') {
            $this->log->write('PAYMENT.SALE.COMPLETED: no billing_agreement_id; not a subscription cycle, skipping.');
            return;
        }

        $saleId = trim((string)($resource['id'] ?? ''));
        if ($saleId === '') {
            $this->log->write('PAYMENT.SALE.COMPLETED: missing resource.id (sale id); cannot record.');
            return;
        }

        $amount = (float)($resource['amount']['total'] ?? 0);
        $currencyCode = trim((string)($resource['amount']['currency'] ?? 'USD'));
        $payerEmail = trim((string)($resource['payer']['payer_info']['email'] ?? ($this->data['summary'] ?? '')));
        $payerId = trim((string)($resource['payer']['payer_info']['payer_id'] ?? ''));

        // Find the local subscription row so we know which Zen Cart order
        // this cycle belongs to and whether this is the activation cycle.
        $subscriptionRow = SubscriptionManager::findByRemoteId($remoteSubscriptionId);
        if ($subscriptionRow === null) {
            $this->log->write(
                "PAYMENT.SALE.COMPLETED: no local subscription row for remote id $remoteSubscriptionId; "
                . "sale $saleId ignored. (Was this subscription created outside this store?)"
            );
            return;
        }

        $originalOrderId = (int)($subscriptionRow['orders_id'] ?? 0);
        $localStatus = strtolower((string)($subscriptionRow['status'] ?? ''));
        $localSubscriptionId = (int)($subscriptionRow['paypal_subscription_id'] ?? 0);

        if ($originalOrderId <= 0) {
            $this->log->write(
                "PAYMENT.SALE.COMPLETED: subscription $remoteSubscriptionId has no original orders_id; "
                . "cannot record sale $saleId."
            );
            return;
        }

        $factory = new SubscriptionCycleOrderFactory($this->log);

        // Activation cycle vs renewal: when the local row is still 'pending',
        // this sale represents the activation billing that arrives alongside
        // BILLING.SUBSCRIPTION.ACTIVATED. We attach the CAPTURE to the original
        // order rather than cloning, since the customer's placed-at-checkout
        // order IS the activation cycle.
        $isActivationCycle = ($localStatus === 'pending');

        if ($isActivationCycle) {
            $targetOrderId = $originalOrderId;
            $factory->markOriginalActivationCyclePaid(
                $originalOrderId,
                $saleId,
                $amount,
                $currencyCode,
                $remoteSubscriptionId
            );
        } else {
            $targetOrderId = $factory->cloneOrderForCycle(
                $originalOrderId,
                $amount,
                $currencyCode,
                $saleId,
                $remoteSubscriptionId
            );
            if ($targetOrderId <= 0) {
                $this->log->write(
                    "PAYMENT.SALE.COMPLETED: failed to clone order #$originalOrderId for cycle sale $saleId; "
                    . "falling back to recording the capture against the original order."
                );
                $targetOrderId = $originalOrderId;
            }
        }

        $factory->recordCaptureForCycle(
            $targetOrderId,
            $saleId,
            $amount,
            $currencyCode,
            $remoteSubscriptionId,
            $payerEmail !== '' ? $payerEmail : null,
            $payerId !== '' ? $payerId : null
        );

        // Update the subscription's bookkeeping: next billing date, last
        // payment marker. We deliberately don't flip status here -- ACTIVATED
        // already handled status transitions, and we don't want to suppress
        // a future SUSPENDED webhook from a missed cycle.
        $extras = [
            'next_billing_date' => $this->extractDate($resource['next_payment_date'] ?? null),
        ];
        $statusForUpdate = $localStatus !== '' ? $localStatus : 'active';
        if ($isActivationCycle) {
            $statusForUpdate = 'active';
        }
        SubscriptionManager::updateStatusByRemoteId($remoteSubscriptionId, $statusForUpdate, array_filter($extras));

        $this->log->write(
            "PAYMENT.SALE.COMPLETED: subscription $remoteSubscriptionId (local #$localSubscriptionId) "
            . "sale $saleId recorded against order #$targetOrderId "
            . "(activation_cycle=" . ($isActivationCycle ? 'yes' : 'no')
            . ", amount=$amount $currencyCode)."
        );

        // Surface to the rest of the system in case anything else listens for
        // recurring funds events (e.g. inventory adjustments, drop-shippers,
        // affiliate ledgers).
        global $zco_notifier;
        if (is_object($zco_notifier)) {
            $zco_notifier->notify('NOTIFY_PAYPALAC_SUBSCRIPTION_CYCLE_BILLED', [
                'orders_id' => $targetOrderId,
                'original_orders_id' => $originalOrderId,
                'paypal_subscription_id' => $localSubscriptionId,
                'paypal_subscription_remote_id' => $remoteSubscriptionId,
                'sale_id' => $saleId,
                'amount' => $amount,
                'currency_code' => $currencyCode,
                'is_activation_cycle' => $isActivationCycle,
                'webhook' => $this->data,
            ]);
        }
    }

    protected function extractDate($iso): ?string
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
