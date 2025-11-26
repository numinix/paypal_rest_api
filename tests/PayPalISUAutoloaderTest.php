<?php
/**
 * Test to verify that paypal_isu.core.php autoloader configuration:
 * 1. Loads minimal required components for PayPal ISU API
 * 2. Does NOT load unnecessary classes that could cause errors
 * 3. Includes init_non_db_settings.php to define required constants like TOPMOST_CATEGORY_PARENT_ID
 *
 * This test validates the minimal bootstrap for the PayPal ISU API.
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
     * Helper function to get the full content of the config file
     */
    function getConfigFileContent(): string
    {
        $coreFile = DIR_FS_CATALOG . 'numinix.com/includes/auto_loaders/paypal_isu.core.php';
        return file_get_contents($coreFile);
    }

    /**
     * Test that unnecessary classes are NOT loaded in the config
     */
    function testUnnecessaryClassesNotLoaded(): bool
    {
        $passed = true;
        $content = getConfigFileContent();

        // Classes that should NOT be loaded (not needed by API and could cause errors)
        $unnecessaryClasses = [
            'message_stack.php' => 'messageStack (causes fatal error without $template)',
            'currencies.php' => 'currencies (not used by API)',
            'shopping_cart.php' => 'shoppingCart (not used by API)',
            'language.php' => 'language (not used by API)',
            'zcDate.php' => 'zcDate (not used by API)',
        ];

        foreach ($unnecessaryClasses as $file => $desc) {
            if (strpos($content, "'loadFile' => '$file'") === false) {
                fwrite(STDOUT, "✓ Config does NOT load $file ($desc)\n");
            } else {
                fwrite(STDERR, "FAIL: Config should NOT load $file ($desc)\n");
                $passed = false;
            }
        }

        return $passed;
    }

    /**
     * Test that unnecessary class instantiations are NOT in the config
     */
    function testUnnecessaryInstantiationsNotPresent(): bool
    {
        $passed = true;
        $content = getConfigFileContent();

        // Class instantiations that should NOT be present
        $unnecessaryInstantiations = [
            'messageStack' => 'messageStack (causes fatal error)',
            'currencies' => 'currencies (not used by API)',
            'shoppingCart' => 'shoppingCart (not used by API)',
            'zcDate' => 'zcDate (not used by API)',
        ];

        foreach ($unnecessaryInstantiations as $className => $desc) {
            if (strpos($content, "'className' => '$className'") === false) {
                fwrite(STDOUT, "✓ Config does NOT instantiate $className ($desc)\n");
            } else {
                fwrite(STDERR, "FAIL: Config should NOT instantiate $className ($desc)\n");
                $passed = false;
            }
        }

        return $passed;
    }

    /**
     * Test that essential classes ARE loaded
     */
    function testEssentialClassesLoaded(): bool
    {
        $passed = true;
        $content = getConfigFileContent();

        // Essential class that must be loaded
        if (strpos($content, "'loadFile' => 'class.notifier.php'") !== false) {
            fwrite(STDOUT, "✓ Config loads class.notifier.php (required for event notifications)\n");
        } else {
            fwrite(STDERR, "FAIL: Config should load class.notifier.php\n");
            $passed = false;
        }

        // Notifier must be instantiated
        if (strpos($content, "'className' => 'notifier'") !== false) {
            fwrite(STDOUT, "✓ Config instantiates notifier (required for \$zco_notifier)\n");
        } else {
            fwrite(STDERR, "FAIL: Config should instantiate notifier\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that essential init scripts ARE loaded
     */
    function testEssentialInitScriptsLoaded(): bool
    {
        $passed = true;
        $content = getConfigFileContent();

        // Essential init scripts that must be loaded
        $essentialScripts = [
            'init_db_config_read.php' => 'database configuration (required for $db access)',
            'init_non_db_settings.php' => 'non-DB settings (defines TOPMOST_CATEGORY_PARENT_ID)',
            'init_general_funcs.php' => 'general functions (provides zen_get_configuration_key_value)',
            'init_sessions.php' => 'session handling (required for $_SESSION)',
        ];

        foreach ($essentialScripts as $script => $desc) {
            if (strpos($content, "'loadFile' => '$script'") !== false) {
                fwrite(STDOUT, "✓ Config loads $script ($desc)\n");
            } else {
                fwrite(STDERR, "FAIL: Config should load $script ($desc)\n");
                $passed = false;
            }
        }

        return $passed;
    }

    /**
     * Test that unnecessary init scripts are NOT loaded
     */
    function testUnnecessaryInitScriptsNotLoaded(): bool
    {
        $passed = true;
        $content = getConfigFileContent();

        // Init scripts that should NOT be loaded (not needed by API)
        $unnecessaryScripts = [
            'init_sanitize.php' => 'sanitization (not required for API)',
            'init_languages.php' => 'languages (not used by API)',
            'init_currencies.php' => 'currencies (not used by API)',
            'init_customer_auth.php' => 'customer auth (not required for this API)',
        ];

        foreach ($unnecessaryScripts as $script => $desc) {
            if (strpos($content, "'loadFile' => '$script'") === false) {
                fwrite(STDOUT, "✓ Config does NOT load $script ($desc)\n");
            } else {
                fwrite(STDERR, "FAIL: Config should NOT load $script ($desc)\n");
                $passed = false;
            }
        }

        return $passed;
    }

    /**
     * Test that documentation comment exists
     */
    function testDocumentationCommentExists(): bool
    {
        $passed = true;
        $content = getConfigFileContent();

        // Verify comprehensive comment exists explaining what's omitted
        if (strpos($content, 'minimal configuration') !== false ||
            strpos($content, 'strictly required') !== false) {
            fwrite(STDOUT, "✓ Config has documentation explaining minimal config\n");
        } else {
            fwrite(STDERR, "FAIL: Config should have documentation about minimal config\n");
            $passed = false;
        }

        // Check for explanations of what's omitted
        if (strpos($content, 'not needed') !== false ||
            strpos($content, 'not used by API') !== false) {
            fwrite(STDOUT, "✓ Config documents what's intentionally omitted\n");
        } else {
            fwrite(STDERR, "FAIL: Config should document what's intentionally omitted\n");
            $passed = false;
        }

        return $passed;
    }

    // Run the tests
    $failures = 0;

    fwrite(STDOUT, "Test 1: Verifying unnecessary classes are NOT loaded...\n");
    if (testUnnecessaryClassesNotLoaded()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 2: Verifying unnecessary class instantiations are NOT present...\n");
    if (testUnnecessaryInstantiationsNotPresent()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 3: Verifying essential classes ARE loaded...\n");
    if (testEssentialClassesLoaded()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 4: Verifying essential init scripts ARE loaded...\n");
    if (testEssentialInitScriptsLoaded()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 5: Verifying unnecessary init scripts are NOT loaded...\n");
    if (testUnnecessaryInitScriptsNotLoaded()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 6: Verifying documentation comment exists...\n");
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
        fwrite(STDOUT, "\nMinimal bootstrap configuration includes:\n");
        fwrite(STDOUT, "  * class.notifier.php + notifier instantiation (for event notifications)\n");
        fwrite(STDOUT, "  * init_db_config_read.php (for database access)\n");
        fwrite(STDOUT, "  * init_non_db_settings.php (defines constants like TOPMOST_CATEGORY_PARENT_ID)\n");
        fwrite(STDOUT, "  * init_general_funcs.php (for helper functions)\n");
        fwrite(STDOUT, "  * init_sessions.php (for session management)\n");
        exit(0);
    }
}
