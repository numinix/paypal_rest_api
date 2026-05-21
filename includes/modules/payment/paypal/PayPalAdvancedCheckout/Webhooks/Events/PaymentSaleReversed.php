<?php
/**
 * PayPal Advanced Checkout Webhooks: subscription cycle reversal event.
 *
 * Fires on chargebacks and other PayPal-initiated reversals of a previously
 * completed sale. The semantics for our local bookkeeping are the same as
 * a refund -- the merchant no longer holds the funds for that sale -- so
 * we delegate to the same handler. The orders status comment will note
 * the reversal so the admin can take any necessary follow-up action
 * (e.g. fulfillment hold, customer outreach).
 *
 * WebhookController dispatches by class name; this thin subclass exists
 * solely so PAYMENT.SALE.REVERSED resolves to a registered handler. The
 * actual logic lives in PaymentSaleRefunded.
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license   https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace PayPalAdvancedCheckout\Webhooks\Events;

class PaymentSaleReversed extends PaymentSaleRefunded
{
    /** @var array */
    protected $eventsHandled = [
        'PAYMENT.SALE.REVERSED',
    ];
}
