<?php
declare(strict_types=1);

/**
 * Admin rebill lists customer-saved cards and admin-only vault tokens.
 *
 * Customer checkout continues to use visible = 1. Cards vaulted when the
 * shopper left "save this card" unchecked stay off checkout and still appear
 * here so staff can rebill them.
 */

namespace {
    $failures = 0;

    $classFile = dirname(__DIR__) . '/zc_plugins/PayPalAdvancedCheckout/v2.0.0/catalog/includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/AdminRebillCards.php';
    if (!is_file($classFile)) {
        fwrite(STDERR, "AdminRebillCards.php is missing.\n");
        exit(1);
    }

    require_once $classFile;

    $saved = [
        [
            'saved_credit_card_id' => 44,
            'customers_id' => 9,
            'type' => 'Visa',
            'last_digits' => '1111',
            'expiry_month' => '09',
            'expiry_year' => '2030',
            'vault_id' => 'VAULT-SAVED',
            'effective_vault_id' => 'VAULT-SAVED',
        ],
    ];

    $vaultRows = [
        [
            'paypal_vault_id' => 8,
            'customers_id' => 9,
            'orders_id' => 1005976,
            'vault_id' => 'VAULT-SAVED',
            'status' => 'VAULTED',
            'brand' => 'Visa',
            'last_digits' => '1111',
            'expiry' => '2030-09',
            'visible' => 1,
        ],
        [
            'paypal_vault_id' => 9,
            'customers_id' => 9,
            'orders_id' => 1006001,
            'vault_id' => 'VAULT-ADMIN',
            'status' => 'ACTIVE',
            'brand' => 'Mastercard',
            'last_digits' => '4242',
            'expiry' => '2028-01',
            'cardholder_name' => 'Ben Wunsch',
            'visible' => 0,
        ],
        [
            'paypal_vault_id' => 10,
            'customers_id' => 9,
            'vault_id' => 'VAULT-EXPIRED',
            'status' => 'VAULTED',
            'brand' => 'Visa',
            'last_digits' => '0001',
            'expiry' => '2020-01',
            'visible' => 0,
        ],
        [
            'paypal_vault_id' => 11,
            'customers_id' => 9,
            'vault_id' => 'VAULT-DEAD',
            'status' => 'DELETED',
            'brand' => 'Visa',
            'last_digits' => '0002',
            'expiry' => '2030-01',
            'visible' => 0,
        ],
    ];

    $cards = \PayPalAdvancedCheckout\Common\AdminRebillCards::merge($saved, $vaultRows, '2026-10');

    if (count($cards) !== 2) {
        fwrite(STDERR, 'Expected 2 rebill cards, got ' . count($cards) . ".\n");
        $failures++;
    }

    $byTarget = [];
    foreach ($cards as $card) {
        $byTarget[$card['rebill_target']] = $card;
    }

    if (($byTarget['scc:44']['effective_vault_id'] ?? '') !== 'VAULT-SAVED') {
        fwrite(STDERR, "Customer-saved card was not kept as the charge target.\n");
        $failures++;
    }

    if (!isset($byTarget['vault:9']) || (int)$byTarget['vault:9']['customer_visible'] !== 0) {
        fwrite(STDERR, "Admin-only vault token was not listed.\n");
        $failures++;
    }

    if (($byTarget['vault:9']['last_digits'] ?? '') !== '4242' || ($byTarget['vault:9']['expiry_month'] ?? '') !== '01') {
        fwrite(STDERR, "Admin-only card details were not mapped.\n");
        $failures++;
    }

    foreach ($cards as $card) {
        if (($card['effective_vault_id'] ?? '') === 'VAULT-EXPIRED' || ($card['effective_vault_id'] ?? '') === 'VAULT-DEAD') {
            fwrite(STDERR, "Expired or inactive vault leaked into the rebill list.\n");
            $failures++;
        }
    }

    $checkoutVisible = array_values(array_filter($cards, static function (array $card): bool {
        return (int)($card['customer_visible'] ?? 0) === 1;
    }));
    if (count($checkoutVisible) !== 1 || ($checkoutVisible[0]['rebill_target'] ?? '') !== 'scc:44') {
        fwrite(STDERR, "Only the customer-saved card should be marked visible for checkout.\n");
        $failures++;
    }

    $hiddenSaved = \PayPalAdvancedCheckout\Common\AdminRebillCards::merge($saved, [
        [
            'paypal_vault_id' => 8,
            'customers_id' => 9,
            'vault_id' => 'VAULT-SAVED',
            'status' => 'VAULTED',
            'brand' => 'Visa',
            'last_digits' => '1111',
            'expiry' => '2030-09',
            'visible' => 0,
        ],
    ], '2026-10');
    if (count($hiddenSaved) !== 1 || (int)($hiddenSaved[0]['customer_visible'] ?? 1) !== 0) {
        fwrite(STDERR, "A saved-card row whose vault is hidden must stay admin-only.\n");
        $failures++;
    }

    if ($failures > 0) {
        exit(1);
    }

    fwrite(STDOUT, "Admin rebill card merge test passed.\n");
}
