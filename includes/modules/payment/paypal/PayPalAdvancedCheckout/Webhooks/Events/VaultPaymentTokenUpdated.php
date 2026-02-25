<?php
/**
 * PayPal Advanced Checkout Webhooks
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace PayPalAdvancedCheckout\Webhooks\Events;

use PayPalAdvancedCheckout\Common\VaultManager;
use PayPalAdvancedCheckout\Webhooks\WebhookHandlerContract;

class VaultPaymentTokenUpdated extends WebhookHandlerContract
{
    /** @var array */
    protected $eventsHandled = [
        'VAULT.PAYMENT-TOKEN.UPDATED',
    ];
    public function action(): void
    {
        $this->log->write('VAULT.PAYMENT-TOKEN.UPDATED - action() triggered');

        $resource = $this->data['resource'] ?? [];
        if (empty($resource)) {
            $this->log->write('VAULT.PAYMENT-TOKEN.UPDATED - missing resource payload');
            return;
        }

        $vaultId = $resource['id'] ?? '';
        if ($vaultId === '') {
            $this->log->write('VAULT.PAYMENT-TOKEN.UPDATED - missing vault token id');
            return;
        }

        $card = $resource['payment_source']['card'] ?? [];
        if (empty($card)) {
            $this->log->write('VAULT.PAYMENT-TOKEN.UPDATED - missing payment_source.card payload');
            return;
        }

        $normalizedCard = $this->normalizeCardPayload($resource, $card, $vaultId);
        $updatedRecord = VaultManager::updateFromWebhookPayload($normalizedCard);
        if ($updatedRecord === null) {
            $this->log->write('VAULT.PAYMENT-TOKEN.UPDATED - vault record not found for id ' . $vaultId);
            return;
        }

        global $zco_notifier;
        if (isset($zco_notifier) && method_exists($zco_notifier, 'notify')) {
            $zco_notifier->notify('NOTIFY_PAYPALAC_VAULT_CARD_SAVED', $updatedRecord);
        }
    }

    protected function normalizeCardPayload(array $resource, array $card, string $vaultId): array
    {
        $normalized = $card;

        $normalized['type'] = $card['type'] ?? ($card['card_type'] ?? '');
        $normalized['brand'] = $card['brand'] ?? ($card['brand_name'] ?? ($normalized['brand'] ?? ''));
        $normalized['last_digits'] = $card['last_digits'] ?? ($card['number'] ?? ($normalized['last_digits'] ?? ''));
        $normalized['expiry'] = $card['expiry'] ?? ($normalized['expiry'] ?? '');
        $normalized['name'] = $card['name'] ?? ($card['cardholder_name'] ?? ($normalized['name'] ?? ''));
        $normalized['billing_address'] = $card['billing_address'] ?? ($resource['billing_address'] ?? ($normalized['billing_address'] ?? []));

        $customer = $resource['customer'] ?? [];
        $metadata = $resource['metadata'] ?? [];
        $payer = $resource['payer'] ?? [];

        $normalized['vault'] = [
            'id' => $vaultId,
            'status' => $resource['status'] ?? ($card['vault']['status'] ?? ''),
            'create_time' => $resource['create_time'] ?? ($card['vault']['create_time'] ?? null),
            'update_time' => $resource['update_time'] ?? ($card['vault']['update_time'] ?? null),
            'customer' => [
                'id' => $customer['id'] ?? ($resource['customer_id'] ?? ($card['vault']['customer']['id'] ?? ($metadata['customer_id'] ?? ''))),
                'payer_id' => $customer['payer_id'] ?? ($metadata['payer_id'] ?? ($payer['payer_id'] ?? ($card['vault']['customer']['payer_id'] ?? ''))),
            ],
        ];

        if (!empty($metadata)) {
            $normalized['vault']['metadata'] = $metadata;
        }

        return $normalized;
    }
}
