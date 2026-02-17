<?php
declare(strict_types=1);

/**
 * Test to verify that webhook.core.php uses proper Zen Cart autoloader
 * entries (autoType => 'class') for loading core class files and their
 * compatibility shim fallbacks, rather than manual require_once calls.
 *
 * The standard Zen Cart autoloader pattern loads class files from
 * DIR_WS_CLASSES at breakpoint 0, letting the InitSystem handle file
 * existence checks and load ordering. Compatibility shims are loaded
 * after each core class via classPath so that the shim's class_exists()
 * guard can detect whether the core class was loaded successfully.
 *
 * @copyright Copyright 2003-2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace {
    fwrite(STDOUT, "=== Webhook Core Autoloader Structure Test ===\n");
    fwrite(STDOUT, "Verifying that webhook.core.php uses proper autoLoadConfig entries...\n\n");

    $failures = 0;

    $webhookCoreFile = dirname(__DIR__, 2) . '/includes/auto_loaders/webhook.core.php';
    if (!file_exists($webhookCoreFile)) {
        fwrite(STDERR, "✗ webhook.core.php not found at: $webhookCoreFile\n");
        exit(1);
    }

    $content = file_get_contents($webhookCoreFile);

    // ---------------------------------------------------------------
    // Test 1: No manual require_once calls to load core or shim classes
    // ---------------------------------------------------------------
    fwrite(STDOUT, "Test 1: No manual require_once calls outside the autoloader system...\n");

    // Match require_once that reference Compatibility/ or DIR_WS_CLASSES outside of autoLoadConfig
    // (i.e. raw require_once calls that bypass the autoloader)
    if (preg_match('/^\s*require_once\b/m', $content)) {
        fwrite(STDERR, "✗ Found manual require_once calls — classes should be loaded via autoLoadConfig entries\n");
        $failures++;
    } else {
        fwrite(STDOUT, "✓ No manual require_once calls found\n");
    }

    // ---------------------------------------------------------------
    // Test 2: shopping_cart.php loaded via autoType => 'class'
    // ---------------------------------------------------------------
    fwrite(STDOUT, "\nTest 2: shopping_cart.php loaded via autoType => 'class' entry...\n");

    if (preg_match("/'autoType'\s*=>\s*'class'.*'loadFile'\s*=>\s*'shopping_cart\.php'/s", $content)) {
        fwrite(STDOUT, "✓ shopping_cart.php is loaded via autoType => 'class'\n");
    } else {
        fwrite(STDERR, "✗ shopping_cart.php is not loaded via autoType => 'class'\n");
        $failures++;
    }

    // ---------------------------------------------------------------
    // Test 3: currencies.php loaded via autoType => 'class'
    // ---------------------------------------------------------------
    fwrite(STDOUT, "\nTest 3: currencies.php loaded via autoType => 'class' entry...\n");

    if (preg_match("/'autoType'\s*=>\s*'class'.*'loadFile'\s*=>\s*'currencies\.php'/s", $content)) {
        fwrite(STDOUT, "✓ currencies.php is loaded via autoType => 'class'\n");
    } else {
        fwrite(STDERR, "✗ currencies.php is not loaded via autoType => 'class'\n");
        $failures++;
    }

    // ---------------------------------------------------------------
    // Test 4: class.base.php loaded via autoType => 'class'
    // ---------------------------------------------------------------
    fwrite(STDOUT, "\nTest 4: class.base.php loaded via autoType => 'class' for 1.5.x support...\n");

    if (preg_match("/'autoType'\s*=>\s*'class'.*'loadFile'\s*=>\s*'class\.base\.php'/s", $content)) {
        fwrite(STDOUT, "✓ class.base.php is loaded via autoType => 'class'\n");
    } else {
        fwrite(STDERR, "✗ class.base.php is not loaded via autoType => 'class'\n");
        $failures++;
    }

    // ---------------------------------------------------------------
    // Test 5: Compatibility shim fallbacks are registered via classPath
    // ---------------------------------------------------------------
    fwrite(STDOUT, "\nTest 5: Compatibility shim fallbacks are registered via classPath entries...\n");

    $shims = [
        'ShoppingCart.php' => 'shoppingCart',
        'Currencies.php' => 'currencies',
        'LegacyNotifier.php' => 'notifier',
        'ZcDate.php' => 'zcDate',
        'Sniffer.php' => 'sniffer',
        'Cache.php' => 'cache',
        'MessageStack.php' => 'messageStack',
        'TemplateFunc.php' => 'template_func',
        'Order.php' => 'order',
    ];

    foreach ($shims as $shimFile => $className) {
        // Look for an autoLoadConfig entry that loads the shim with classPath
        $pattern = "/'loadFile'\s*=>\s*'" . preg_quote($shimFile, '/') . "'.*'classPath'/s";
        if (preg_match($pattern, $content)) {
            fwrite(STDOUT, "  ✓ $shimFile compatibility shim registered with classPath\n");
        } else {
            fwrite(STDERR, "  ✗ $shimFile compatibility shim NOT registered with classPath\n");
            $failures++;
        }
    }

    // ---------------------------------------------------------------
    // Test 6: base class is loaded before shopping_cart (ordering check)
    // ---------------------------------------------------------------
    fwrite(STDOUT, "\nTest 6: class.base.php appears before shopping_cart.php in breakpoint 0...\n");

    $basePos = strpos($content, "'class.base.php'");
    $cartPos = strpos($content, "'shopping_cart.php'");
    if ($basePos !== false && $cartPos !== false && $basePos < $cartPos) {
        fwrite(STDOUT, "✓ class.base.php is loaded before shopping_cart.php\n");
    } elseif ($basePos === false) {
        fwrite(STDERR, "✗ class.base.php not found in file\n");
        $failures++;
    } else {
        fwrite(STDERR, "✗ class.base.php appears after shopping_cart.php (incorrect order)\n");
        $failures++;
    }

    // ---------------------------------------------------------------
    // Test 7: classInstantiate entries still present for all classes
    // ---------------------------------------------------------------
    fwrite(STDOUT, "\nTest 7: classInstantiate entries present for runtime objects...\n");

    $instantiations = [
        'notifier' => 'zco_notifier',
        'zcDate' => 'zcDate',
        'sniffer' => 'sniffer',
        'shoppingCart' => 'cart',
        'currencies' => 'currencies',
        'template_func' => 'template',
        'messageStack' => 'messageStack',
    ];

    foreach ($instantiations as $className => $objectName) {
        $pattern = "/'className'\s*=>\s*'" . preg_quote($className, '/') . "'.*'objectName'\s*=>\s*'" . preg_quote($objectName, '/') . "'/s";
        if (preg_match($pattern, $content)) {
            fwrite(STDOUT, "  ✓ $className => \$$objectName classInstantiate entry found\n");
        } else {
            fwrite(STDERR, "  ✗ $className => \$$objectName classInstantiate entry MISSING\n");
            $failures++;
        }
    }

    fwrite(STDOUT, "\n");

    if ($failures > 0) {
        fwrite(STDERR, "FAILED: $failures test(s) failed.\n");
        exit(1);
    }

    fwrite(STDOUT, "All tests passed.\n");
    exit(0);
}
