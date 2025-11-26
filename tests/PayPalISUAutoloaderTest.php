<?php
/**
 * Test to verify that paypal_isu.core.php autoloader configuration:
 * 1. Does NOT load messageStack class for Zen Cart 2.0+
 * 2. Does NOT instantiate messageStack for Zen Cart 2.0+
 * 3. Still loads messageStack for Zen Cart 1.5.x
 *
 * This test validates the fix for the fatal error:
 * "Call to a member function get_template_dir() on null in message_stack.php"
 * which occurs when messageStack is instantiated without the template object.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
declare(strict_types=1);

namespace {
    if (!defined('DIR_FS_CATALOG')) {
        define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
    }
}

namespace {
    /**
     * Test that verifies messageStack is NOT loaded in Zen Cart 2.0+ config
     */
    function testMessageStackNotLoadedForZenCart2(): bool
    {
        $passed = true;

        $coreFile = DIR_FS_CATALOG . 'numinix.com/includes/auto_loaders/paypal_isu.core.php';
        $content = file_get_contents($coreFile);

        // Find the Zen Cart 2.0+ section (starts with version_compare check for >= 2.0.0)
        $zenCart2Start = strpos($content, "version_compare(\$zcVersionMajor . '.' . \$zcVersionMinor, '2.0.0', '>=')");
        if ($zenCart2Start === false) {
            fwrite(STDERR, "FAIL: Could not find Zen Cart 2.0+ version check\n");
            return false;
        }

        // Find the else block that starts the 1.5.x section
        $elsePos = strpos($content, '} else {', $zenCart2Start);
        if ($elsePos === false) {
            fwrite(STDERR, "FAIL: Could not find else block for 1.5.x section\n");
            return false;
        }

        // Extract only the Zen Cart 2.0+ section
        $zenCart2Section = substr($content, $zenCart2Start, $elsePos - $zenCart2Start);

        // Check 1: Verify messageStack class is NOT loaded in 2.0+ section
        if (strpos($zenCart2Section, "'loadFile' => 'message_stack.php'") === false) {
            fwrite(STDOUT, "✓ Zen Cart 2.0+ section does NOT load message_stack.php class\n");
        } else {
            fwrite(STDERR, "FAIL: Zen Cart 2.0+ section should NOT load message_stack.php class\n");
            $passed = false;
        }

        // Check 2: Verify messageStack is NOT instantiated in 2.0+ section
        if (strpos($zenCart2Section, "'className' => 'messageStack'") === false) {
            fwrite(STDOUT, "✓ Zen Cart 2.0+ section does NOT instantiate messageStack\n");
        } else {
            fwrite(STDERR, "FAIL: Zen Cart 2.0+ section should NOT instantiate messageStack\n");
            $passed = false;
        }

        // Check 3: Verify explanatory comment exists about why messageStack is not loaded
        if (strpos($zenCart2Section, 'messageStack') !== false && 
            (strpos($zenCart2Section, 'intentionally not loaded') !== false || 
             strpos($zenCart2Section, 'intentionally omitted') !== false)) {
            fwrite(STDOUT, "✓ Explanatory comment about messageStack omission exists\n");
        } else {
            fwrite(STDERR, "FAIL: Should have explanatory comment about messageStack omission\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that verifies messageStack IS still loaded in Zen Cart 1.5.x config
     */
    function testMessageStackLoadedForZenCart15x(): bool
    {
        $passed = true;

        $coreFile = DIR_FS_CATALOG . 'numinix.com/includes/auto_loaders/paypal_isu.core.php';
        $content = file_get_contents($coreFile);

        // Find the else block that starts the 1.5.x section
        $elsePos = strpos($content, '} else {');
        if ($elsePos === false) {
            fwrite(STDERR, "FAIL: Could not find else block for 1.5.x section\n");
            return false;
        }

        // Extract only the Zen Cart 1.5.x section
        $zenCart15xSection = substr($content, $elsePos);

        // Check 1: Verify messageStack class IS loaded in 1.5.x section
        if (strpos($zenCart15xSection, "'loadFile' => 'message_stack.php'") !== false) {
            fwrite(STDOUT, "✓ Zen Cart 1.5.x section loads message_stack.php class\n");
        } else {
            fwrite(STDERR, "FAIL: Zen Cart 1.5.x section should load message_stack.php class\n");
            $passed = false;
        }

        // Check 2: Verify messageStack IS instantiated in 1.5.x section
        if (strpos($zenCart15xSection, "'className' => 'messageStack'") !== false) {
            fwrite(STDOUT, "✓ Zen Cart 1.5.x section instantiates messageStack\n");
        } else {
            fwrite(STDERR, "FAIL: Zen Cart 1.5.x section should instantiate messageStack\n");
            $passed = false;
        }

        // Check 3: Verify 1.5.x section also loads template_func (which messageStack depends on)
        if (strpos($zenCart15xSection, "'loadFile' => 'template_func.php'") !== false) {
            fwrite(STDOUT, "✓ Zen Cart 1.5.x section loads template_func.php (messageStack dependency)\n");
        } else {
            fwrite(STDERR, "FAIL: Zen Cart 1.5.x section should load template_func.php for messageStack\n");
            $passed = false;
        }

        // Check 4: Verify 1.5.x section loads init_templates.php
        if (strpos($zenCart15xSection, "'loadFile' => 'init_templates.php'") !== false) {
            fwrite(STDOUT, "✓ Zen Cart 1.5.x section loads init_templates.php\n");
        } else {
            fwrite(STDERR, "FAIL: Zen Cart 1.5.x section should load init_templates.php\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that essential classes are still loaded for Zen Cart 2.0+
     */
    function testEssentialClassesLoadedForZenCart2(): bool
    {
        $passed = true;

        $coreFile = DIR_FS_CATALOG . 'numinix.com/includes/auto_loaders/paypal_isu.core.php';
        $content = file_get_contents($coreFile);

        // Find the Zen Cart 2.0+ section
        $zenCart2Start = strpos($content, "version_compare(\$zcVersionMajor . '.' . \$zcVersionMinor, '2.0.0', '>=')");
        $elsePos = strpos($content, '} else {', $zenCart2Start);
        $zenCart2Section = substr($content, $zenCart2Start, $elsePos - $zenCart2Start);

        $essentialClasses = [
            'class.notifier.php' => 'notifier',
            'currencies.php' => 'currencies',
            'shopping_cart.php' => 'shoppingCart',
        ];

        foreach ($essentialClasses as $file => $desc) {
            if (strpos($zenCart2Section, "'loadFile' => '$file'") !== false) {
                fwrite(STDOUT, "✓ Zen Cart 2.0+ section still loads $file ($desc)\n");
            } else {
                fwrite(STDERR, "FAIL: Zen Cart 2.0+ section should still load $file ($desc)\n");
                $passed = false;
            }
        }

        return $passed;
    }

    // Run the tests
    $failures = 0;

    fwrite(STDOUT, "Test 1: Verifying messageStack is NOT loaded for Zen Cart 2.0+...\n");
    if (testMessageStackNotLoadedForZenCart2()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 2: Verifying messageStack IS still loaded for Zen Cart 1.5.x...\n");
    if (testMessageStackLoadedForZenCart15x()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 3: Verifying essential classes are still loaded for Zen Cart 2.0+...\n");
    if (testEssentialClassesLoadedForZenCart2()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    // Summary
    if ($failures > 0) {
        fwrite(STDERR, "\n✗ FAILED: $failures test(s) failed\n");
        exit(1);
    } else {
        fwrite(STDOUT, "\n✓ All PayPal ISU autoloader tests passed!\n");
        fwrite(STDOUT, "\nFix applied:\n");
        fwrite(STDOUT, "- Removed messageStack class loading and instantiation from Zen Cart 2.0+\n");
        fwrite(STDOUT, "  configuration because the API endpoint doesn't use messageStack and\n");
        fwrite(STDOUT, "  loading it causes a fatal error due to missing \$template object.\n");
        fwrite(STDOUT, "- Zen Cart 1.5.x configuration still loads messageStack as it properly\n");
        fwrite(STDOUT, "  initializes the template dependencies.\n");
        exit(0);
    }
}
