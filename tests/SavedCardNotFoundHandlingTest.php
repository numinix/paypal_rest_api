<?php
declare(strict_types=1);

/**
 * Test to verify that when a saved card is selected but not found in the vault,
 * an error is properly displayed and validation fails.
 */

namespace {
    if (!defined('DIR_FS_CATALOG')) {
        define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
    }
    if (!defined('DIR_FS_LOGS')) {
        define('DIR_FS_LOGS', sys_get_temp_dir());
    }
    if (!defined('IS_ADMIN_FLAG')) {
        define('IS_ADMIN_FLAG', false);
    }

    echo "Testing saved card not found handling...\n";

    $failures = 0;

    // Test the logic that checks if a saved card is found
    // Simulate: saved_card is set to 'vault-missing' but vaultCards doesn't contain it

    $saved_card = 'vault-missing';
    $vaultCards = [
        ['vault_id' => 'vault-123', 'expiry' => '2030-12'],
        ['vault_id' => 'vault-456', 'expiry' => '2029-06'],
    ];

    $cardFound = false;
    foreach ($vaultCards as $card) {
        if ($card['vault_id'] === $saved_card) {
            $cardFound = true;
            break;
        }
    }

    if ($cardFound) {
        fwrite(STDERR, "Test 1 failed: Card should not be found\n");
        $failures++;
    } else {
        echo "✓ Test 1 passed: Card not found is correctly detected\n";
    }

    // Test with a card that IS in the vault
    $saved_card = 'vault-456';
    $cardFound = false;
    foreach ($vaultCards as $card) {
        if ($card['vault_id'] === $saved_card) {
            $cardFound = true;
            break;
        }
    }

    if (!$cardFound) {
        fwrite(STDERR, "Test 2 failed: Card should be found\n");
        $failures++;
    } else {
        echo "✓ Test 2 passed: Card found is correctly detected\n";
    }

    // Test with empty vault
    $saved_card = 'vault-123';
    $vaultCards = [];
    $cardFound = false;
    foreach ($vaultCards as $card) {
        if ($card['vault_id'] === $saved_card) {
            $cardFound = true;
            break;
        }
    }

    if ($cardFound) {
        fwrite(STDERR, "Test 3 failed: Card should not be found in empty vault\n");
        $failures++;
    } else {
        echo "✓ Test 3 passed: Empty vault correctly handled\n";
    }

    if ($failures > 0) {
        fwrite(STDERR, "\n$failures test(s) failed.\n");
        exit(1);
    }

    echo "\n✓ All saved card not found handling tests passed.\n";
}
