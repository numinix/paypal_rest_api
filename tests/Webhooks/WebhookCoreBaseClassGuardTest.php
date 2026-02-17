<?php
declare(strict_types=1);

/**
 * Test to verify that webhook.core.php guards against loading core Zen Cart
 * classes whose parent class (base) may not be available.
 *
 * When the Zen Cart storefront stack isn't fully loaded (e.g. webhook
 * endpoints), core classes like shoppingCart and currencies extend 'base'
 * which may not yet exist. The autoloader must check for 'base' before
 * attempting to load these core files, falling back to compatibility shims.
 *
 * @copyright Copyright 2003-2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace {
    fwrite(STDOUT, "=== Webhook Core Base-Class Guard Test ===\n");
    fwrite(STDOUT, "Verifying that webhook.core.php checks for 'base' class before loading core classes...\n\n");

    $failures = 0;

    $webhookCoreFile = dirname(__DIR__, 2) . '/includes/auto_loaders/webhook.core.php';
    if (!file_exists($webhookCoreFile)) {
        fwrite(STDERR, "✗ webhook.core.php not found at: $webhookCoreFile\n");
        exit(1);
    }

    $content = file_get_contents($webhookCoreFile);

    // Test 1: shoppingCart loading must check for class_exists('base')
    fwrite(STDOUT, "Test 1: shoppingCart core-class loading guards against missing 'base' class...\n");

    // Find the block that loads shopping_cart.php from the core classes directory
    if (preg_match("/DIR_WS_CLASSES\s*\.\s*'shopping_cart\.php'/", $content)) {
        // The line that loads the core shopping_cart.php must be guarded by a base class check
        if (!preg_match("/class_exists\s*\(\s*'base'.*\).*is_file\s*\(\s*\\\$shoppingCartClass\s*\)/s", $content)) {
            fwrite(STDERR, "✗ Core shopping_cart.php loading is not guarded by class_exists('base') check\n");
            $failures++;
        } else {
            fwrite(STDOUT, "✓ Core shopping_cart.php loading is guarded by class_exists('base') check\n");
        }
    } else {
        fwrite(STDOUT, "✓ (No core shopping_cart.php loading found — using compatibility shim only)\n");
    }

    // Test 2: currencies loading must check for class_exists('base')
    fwrite(STDOUT, "\nTest 2: currencies core-class loading guards against missing 'base' class...\n");

    if (preg_match("/DIR_WS_CLASSES\s*\.\s*'currencies\.php'/", $content)) {
        if (!preg_match("/class_exists\s*\(\s*'base'.*\).*is_file\s*\(\s*\\\$currenciesClass\s*\)/s", $content)) {
            fwrite(STDERR, "✗ Core currencies.php loading is not guarded by class_exists('base') check\n");
            $failures++;
        } else {
            fwrite(STDOUT, "✓ Core currencies.php loading is guarded by class_exists('base') check\n");
        }
    } else {
        fwrite(STDOUT, "✓ (No core currencies.php loading found — using compatibility shim only)\n");
    }

    // Test 3: Compatibility shim fallbacks still exist
    fwrite(STDOUT, "\nTest 3: Compatibility shim fallbacks are still present...\n");

    if (strpos($content, 'Compatibility/ShoppingCart.php') === false) {
        fwrite(STDERR, "✗ ShoppingCart compatibility shim fallback is missing\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ ShoppingCart compatibility shim fallback is present\n");
    }

    if (strpos($content, 'Compatibility/Currencies.php') === false) {
        fwrite(STDERR, "✗ Currencies compatibility shim fallback is missing\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ Currencies compatibility shim fallback is present\n");
    }

    fwrite(STDOUT, "\n");

    if ($failures > 0) {
        fwrite(STDERR, "FAILED: $failures test(s) failed.\n");
        exit(1);
    }

    fwrite(STDOUT, "All tests passed.\n");
    exit(0);
}
