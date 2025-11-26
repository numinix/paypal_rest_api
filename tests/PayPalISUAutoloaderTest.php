<?php
/**
 * Test to verify that paypal_isu.core.php autoloader configuration:
 * 1. Loads only minimal required components for Zen Cart 2.0+
 * 2. Does NOT load unnecessary classes that could cause errors
 * 3. Still loads full configuration for Zen Cart 1.5.x
 *
 * This test validates the optimized minimal bootstrap for the PayPal ISU API.
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
     * Helper function to extract Zen Cart 2.0+ section from the config file
     */
    function getZenCart2Section(): string
    {
        $coreFile = DIR_FS_CATALOG . 'numinix.com/includes/auto_loaders/paypal_isu.core.php';
        $content = file_get_contents($coreFile);
        
        $zenCart2Start = strpos($content, "version_compare(\$zcVersionMajor . '.' . \$zcVersionMinor, '2.0.0', '>=')");
        if ($zenCart2Start === false) {
            return '';
        }
        
        $elsePos = strpos($content, '} else {', $zenCart2Start);
        if ($elsePos === false) {
            return '';
        }
        
        return substr($content, $zenCart2Start, $elsePos - $zenCart2Start);
    }

    /**
     * Helper function to extract Zen Cart 1.5.x section from the config file
     */
    function getZenCart15xSection(): string
    {
        $coreFile = DIR_FS_CATALOG . 'numinix.com/includes/auto_loaders/paypal_isu.core.php';
        $content = file_get_contents($coreFile);
        
        $elsePos = strpos($content, '} else {');
        if ($elsePos === false) {
            return '';
        }
        
        return substr($content, $elsePos);
    }

    /**
     * Test that unnecessary classes are NOT loaded in Zen Cart 2.0+ config
     */
    function testUnnecessaryClassesNotLoadedForZenCart2(): bool
    {
        $passed = true;
        $zenCart2Section = getZenCart2Section();

        // Classes that should NOT be loaded (not needed by API and could cause errors)
        $unnecessaryClasses = [
            'message_stack.php' => 'messageStack (causes fatal error without $template)',
            'currencies.php' => 'currencies (not used by API)',
            'shopping_cart.php' => 'shoppingCart (not used by API)',
            'language.php' => 'language (not used by API)',
            'zcDate.php' => 'zcDate (not used by API)',
        ];

        foreach ($unnecessaryClasses as $file => $desc) {
            if (strpos($zenCart2Section, "'loadFile' => '$file'") === false) {
                fwrite(STDOUT, "✓ Zen Cart 2.0+ section does NOT load $file ($desc)\n");
            } else {
                fwrite(STDERR, "FAIL: Zen Cart 2.0+ section should NOT load $file ($desc)\n");
                $passed = false;
            }
        }

        return $passed;
    }

    /**
     * Test that unnecessary class instantiations are NOT in Zen Cart 2.0+ config
     */
    function testUnnecessaryInstantiationsNotInZenCart2(): bool
    {
        $passed = true;
        $zenCart2Section = getZenCart2Section();

        // Class instantiations that should NOT be present
        $unnecessaryInstantiations = [
            'messageStack' => 'messageStack (causes fatal error)',
            'currencies' => 'currencies (not used by API)',
            'shoppingCart' => 'shoppingCart (not used by API)',
            'zcDate' => 'zcDate (not used by API)',
        ];

        foreach ($unnecessaryInstantiations as $className => $desc) {
            if (strpos($zenCart2Section, "'className' => '$className'") === false) {
                fwrite(STDOUT, "✓ Zen Cart 2.0+ section does NOT instantiate $className ($desc)\n");
            } else {
                fwrite(STDERR, "FAIL: Zen Cart 2.0+ section should NOT instantiate $className ($desc)\n");
                $passed = false;
            }
        }

        return $passed;
    }

    /**
     * Test that essential classes ARE loaded for Zen Cart 2.0+
     */
    function testEssentialClassesLoadedForZenCart2(): bool
    {
        $passed = true;
        $zenCart2Section = getZenCart2Section();

        // Essential class that must be loaded
        if (strpos($zenCart2Section, "'loadFile' => 'class.notifier.php'") !== false) {
            fwrite(STDOUT, "✓ Zen Cart 2.0+ section loads class.notifier.php (required for event notifications)\n");
        } else {
            fwrite(STDERR, "FAIL: Zen Cart 2.0+ section should load class.notifier.php\n");
            $passed = false;
        }

        // Notifier must be instantiated
        if (strpos($zenCart2Section, "'className' => 'notifier'") !== false) {
            fwrite(STDOUT, "✓ Zen Cart 2.0+ section instantiates notifier (required for \$zco_notifier)\n");
        } else {
            fwrite(STDERR, "FAIL: Zen Cart 2.0+ section should instantiate notifier\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that essential init scripts ARE loaded for Zen Cart 2.0+
     */
    function testEssentialInitScriptsLoadedForZenCart2(): bool
    {
        $passed = true;
        $zenCart2Section = getZenCart2Section();

        // Essential init scripts that must be loaded
        $essentialScripts = [
            'init_db_config_read.php' => 'database configuration (required for $db access)',
            'init_general_funcs.php' => 'general functions (provides zen_get_configuration_key_value)',
            'init_sessions.php' => 'session handling (required for $_SESSION)',
        ];

        foreach ($essentialScripts as $script => $desc) {
            if (strpos($zenCart2Section, "'loadFile' => '$script'") !== false) {
                fwrite(STDOUT, "✓ Zen Cart 2.0+ section loads $script ($desc)\n");
            } else {
                fwrite(STDERR, "FAIL: Zen Cart 2.0+ section should load $script ($desc)\n");
                $passed = false;
            }
        }

        return $passed;
    }

    /**
     * Test that unnecessary init scripts are NOT loaded for Zen Cart 2.0+
     */
    function testUnnecessaryInitScriptsNotLoadedForZenCart2(): bool
    {
        $passed = true;
        $zenCart2Section = getZenCart2Section();

        // Init scripts that should NOT be loaded (not needed by API)
        $unnecessaryScripts = [
            'init_non_db_settings.php' => 'non-DB settings (not required for API)',
            'init_sanitize.php' => 'sanitization (not required for API)',
            'init_languages.php' => 'languages (not used by API)',
            'init_currencies.php' => 'currencies (not used by API)',
            'init_customer_auth.php' => 'customer auth (not required for this API)',
        ];

        foreach ($unnecessaryScripts as $script => $desc) {
            if (strpos($zenCart2Section, "'loadFile' => '$script'") === false) {
                fwrite(STDOUT, "✓ Zen Cart 2.0+ section does NOT load $script ($desc)\n");
            } else {
                fwrite(STDERR, "FAIL: Zen Cart 2.0+ section should NOT load $script ($desc)\n");
                $passed = false;
            }
        }

        return $passed;
    }

    /**
     * Test that messageStack IS still loaded in Zen Cart 1.5.x config
     */
    function testMessageStackLoadedForZenCart15x(): bool
    {
        $passed = true;
        $zenCart15xSection = getZenCart15xSection();

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
     * Test that documentation comment exists in the 2.0+ section
     */
    function testDocumentationCommentExists(): bool
    {
        $passed = true;
        $zenCart2Section = getZenCart2Section();

        // Verify comprehensive comment exists explaining what's omitted
        if (strpos($zenCart2Section, 'minimal configuration') !== false ||
            strpos($zenCart2Section, 'strictly required') !== false) {
            fwrite(STDOUT, "✓ Zen Cart 2.0+ section has documentation explaining minimal config\n");
        } else {
            fwrite(STDERR, "FAIL: Zen Cart 2.0+ section should have documentation about minimal config\n");
            $passed = false;
        }

        // Check for explanations of what's omitted
        if (strpos($zenCart2Section, 'not needed') !== false ||
            strpos($zenCart2Section, 'not used by API') !== false) {
            fwrite(STDOUT, "✓ Zen Cart 2.0+ section documents what's intentionally omitted\n");
        } else {
            fwrite(STDERR, "FAIL: Zen Cart 2.0+ section should document what's intentionally omitted\n");
            $passed = false;
        }

        return $passed;
    }

    // Run the tests
    $failures = 0;

    fwrite(STDOUT, "Test 1: Verifying unnecessary classes are NOT loaded for Zen Cart 2.0+...\n");
    if (testUnnecessaryClassesNotLoadedForZenCart2()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 2: Verifying unnecessary class instantiations are NOT in Zen Cart 2.0+...\n");
    if (testUnnecessaryInstantiationsNotInZenCart2()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 3: Verifying essential classes ARE loaded for Zen Cart 2.0+...\n");
    if (testEssentialClassesLoadedForZenCart2()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 4: Verifying essential init scripts ARE loaded for Zen Cart 2.0+...\n");
    if (testEssentialInitScriptsLoadedForZenCart2()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 5: Verifying unnecessary init scripts are NOT loaded for Zen Cart 2.0+...\n");
    if (testUnnecessaryInitScriptsNotLoadedForZenCart2()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 6: Verifying messageStack IS still loaded for Zen Cart 1.5.x...\n");
    if (testMessageStackLoadedForZenCart15x()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 7: Verifying documentation comment exists...\n");
    if (testDocumentationCommentExists()) {
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
        fwrite(STDOUT, "\nOptimization applied:\n");
        fwrite(STDOUT, "- Zen Cart 2.0+ now loads only minimal required components:\n");
        fwrite(STDOUT, "  * class.notifier.php + notifier instantiation (for event notifications)\n");
        fwrite(STDOUT, "  * init_db_config_read.php (for database access)\n");
        fwrite(STDOUT, "  * init_general_funcs.php (for helper functions)\n");
        fwrite(STDOUT, "  * init_sessions.php (for session management)\n");
        fwrite(STDOUT, "- Removed unnecessary classes that could cause errors or are unused:\n");
        fwrite(STDOUT, "  * messageStack (fatal error without \$template)\n");
        fwrite(STDOUT, "  * currencies, shoppingCart, zcDate, language (not used by API)\n");
        fwrite(STDOUT, "- Zen Cart 1.5.x configuration unchanged (full bootstrap for compatibility)\n");
        exit(0);
    }
}
