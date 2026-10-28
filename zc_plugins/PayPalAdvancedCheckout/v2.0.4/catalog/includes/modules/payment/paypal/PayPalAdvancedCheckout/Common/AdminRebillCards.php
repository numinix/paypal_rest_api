<?php
/**
 * Merge customer saved cards with PayPal vault rows for admin rebill.
 *
 * Checkout and My Account only list vault rows the customer chose to save
 * (visible = 1). Admin rebill also charges vault rows stored from a card
 * checkout when the customer left that box unchecked (visible = 0).
 *
 * @copyright Copyright 2026 Numinix
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace PayPalAdvancedCheckout\Common;

class AdminRebillCards
{
    /**
     * @param list<array<string,mixed>> $savedCards Saved-card rows that already include effective_vault_id
     * @param list<array<string,mixed>> $vaultRows  paypal_vault rows
     * @return list<array<string,mixed>>
     */
    public static function merge(array $savedCards, array $vaultRows, ?string $todayYm = null): array
    {
        if ($todayYm === null || preg_match('/^\d{4}-\d{2}$/', $todayYm) !== 1) {
            $todayYm = date('Y-m');
        }

        $merged = [];
        $seen = [];

        foreach ($savedCards as $card) {
            if (!is_array($card)) {
                continue;
            }

            $vaultId = trim((string)($card['effective_vault_id'] ?? $card['vault_id'] ?? ''));
            if ($vaultId === '') {
                continue;
            }

            $savedId = (int)($card['saved_credit_card_id'] ?? 0);
            if ($savedId <= 0) {
                continue;
            }

            $card['effective_vault_id'] = $vaultId;
            $card['rebill_target'] = 'scc:' . $savedId;
            if (!array_key_exists('customer_visible', $card)) {
                $card['customer_visible'] = 1;
            }
            $merged[] = $card;
            $seen[$vaultId] = true;
        }

        foreach ($vaultRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $status = strtoupper(trim((string)($row['status'] ?? '')));
            if (!in_array($status, ['ACTIVE', 'APPROVED', 'VAULTED'], true)) {
                continue;
            }

            $vaultId = trim((string)($row['vault_id'] ?? ''));
            $paypalVaultId = (int)($row['paypal_vault_id'] ?? 0);
            if ($vaultId === '' || $paypalVaultId <= 0) {
                continue;
            }

            if (isset($seen[$vaultId])) {
                if ((int)($row['visible'] ?? 1) !== 1) {
                    foreach ($merged as $index => $existing) {
                        if (($existing['effective_vault_id'] ?? '') === $vaultId) {
                            $merged[$index]['customer_visible'] = 0;
                        }
                    }
                }
                continue;
            }

            $expiry = trim((string)($row['expiry'] ?? ''));
            if (self::isExpired($expiry, $todayYm)) {
                continue;
            }

            [$month, $year] = self::splitExpiry($expiry);
            $visible = (int)($row['visible'] ?? 0) === 1 ? 1 : 0;
            $brand = trim((string)($row['brand'] ?? ''));
            if ($brand === '') {
                $brand = trim((string)($row['card_type'] ?? ''));
            }
            if ($brand === '') {
                $brand = 'Card';
            }

            $lastDigits = preg_replace('/\D+/', '', (string)($row['last_digits'] ?? ''));
            if (!is_string($lastDigits)) {
                $lastDigits = '';
            }
            if (strlen($lastDigits) > 4) {
                $lastDigits = substr($lastDigits, -4);
            }

            $merged[] = [
                'saved_credit_card_id' => 0,
                'paypal_vault_id' => $paypalVaultId,
                'customers_id' => (int)($row['customers_id'] ?? 0),
                'type' => $brand,
                'last_digits' => $lastDigits,
                'expiry_month' => $month,
                'expiry_year' => $year,
                'holder_name' => (string)($row['cardholder_name'] ?? ''),
                'vault_id' => $vaultId,
                'effective_vault_id' => $vaultId,
                'customer_visible' => $visible,
                'rebill_target' => 'vault:' . $paypalVaultId,
                'orders_id' => (int)($row['orders_id'] ?? 0),
            ];
            $seen[$vaultId] = true;
        }

        return $merged;
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function splitExpiry(string $expiry): array
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $expiry, $matches) === 1) {
            return [$matches[2], $matches[1]];
        }

        return ['', ''];
    }

    private static function isExpired(string $expiry, string $todayYm): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $expiry, $matches) !== 1) {
            return false;
        }

        return $matches[1] . '-' . $matches[2] < $todayYm;
    }
}
