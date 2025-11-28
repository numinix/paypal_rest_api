<?php
/**
 * Test to verify that installer files properly delegate version management to init_numinix_paypal_isu.php.
 *
 * This test validates that installer files do NOT have standalone version update blocks that run
 * independently of the main initialization file. Such standalone version updates can cause the
 * version number to be updated even if the installer's primary operation (e.g., table creation) fails,
 * preventing the installer from running again on subsequent page loads.
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
     * Get all installer files from the numinix_paypal_isu directory
     */
    function getInstallerFiles(): array
    {
        $installersPath = DIR_FS_CATALOG . 'numinix.com/management/includes/installers/numinix_paypal_isu';
        if (!is_dir($installersPath)) {
            return [];
        }
        
        $files = glob($installersPath . '/*.php');
        return is_array($files) ? $files : [];
    }

    /**
     * Test that installer files do NOT have standalone version UPDATE blocks at the end.
     *
     * Standalone version updates are problematic because:
     * 1. The init file already handles version updates after each installer runs
     * 2. Standalone updates can run even if the installer's main operation fails
     * 3. This prevents the installer from running again on subsequent page loads
     *
     * The pattern we're looking for is a standalone block that:
     * - Gets the configuration_group_id
     * - Then directly updates NUMINIX_PPCP_VERSION
     * - Without being part of a larger configuration values loop
     */
    function testNoStandaloneVersionUpdates(): bool
    {
        $passed = true;
        $installerFiles = getInstallerFiles();

        if (empty($installerFiles)) {
            fwrite(STDERR, "FAIL: No installer files found\n");
            return false;
        }

        fwrite(STDOUT, "Checking " . count($installerFiles) . " installer files...\n");

        foreach ($installerFiles as $file) {
            $content = file_get_contents($file);
            $basename = basename($file);

            // Pattern to detect standalone version update blocks:
            // These are blocks that:
            // 1. Update the version outside of a configuration values loop
            // 2. Have their own version number hardcoded
            // 3. Run independently after other operations
            //
            // Example problematic pattern:
            // $versionKey = 'NUMINIX_PPCP_VERSION';
            // $newVersion = '1.0.6';
            // ... UPDATE ... WHERE configuration_key = ... $versionKey ...

            // Check for patterns like: $newVersion = '1.0.x' followed by UPDATE
            $hasStandaloneVersionVar = (bool) preg_match('/\$newVersion\s*=\s*[\'"]\d+\.\d+\.\d+[\'"]/', $content);
            $hasVersionKeyUpdate = strpos($content, '$versionKey') !== false 
                && strpos($content, '"UPDATE "') !== false
                && strpos($content, 'TABLE_CONFIGURATION') !== false
                && strpos($content, "WHERE configuration_key = '\" . zen_db_input(\$versionKey)") !== false;

            // Also check for direct version updates with hardcoded key
            // Use a more specific pattern to avoid false positives
            $hasDirectVersionUpdate = (bool) preg_match(
                '/\$db->Execute\s*\(\s*["\']UPDATE\b.*\bTABLE_CONFIGURATION\b.*\bconfiguration_value\b.*\bNUMINIX_PPCP_VERSION\b/',
                $content
            );

            // Check if this pattern is part of a configurationValues loop (which is OK)
            // vs. a standalone block (which is problematic)
            $hasConfigValuesLoop = strpos($content, '$configurationValues') !== false
                && strpos($content, 'foreach ($configurationValues') !== false;

            // If we have a standalone version update pattern AND it's NOT part of a config loop
            if (($hasStandaloneVersionVar && $hasVersionKeyUpdate) || $hasDirectVersionUpdate) {
                // It's OK if it's part of a configurationValues loop
                if ($hasConfigValuesLoop) {
                    fwrite(STDOUT, "✓ $basename: Version in configurationValues loop (OK)\n");
                } else {
                    fwrite(STDERR, "FAIL: $basename has a standalone version update block\n");
                    fwrite(STDERR, "      This should be removed - init_numinix_paypal_isu.php handles version updates\n");
                    $passed = false;
                }
            } else {
                fwrite(STDOUT, "✓ $basename: No standalone version update\n");
            }
        }

        return $passed;
    }

    /**
     * Test that the init file properly handles version updates after each installer runs
     */
    function testInitFileHandlesVersionUpdates(): bool
    {
        $passed = true;
        
        $initFile = DIR_FS_CATALOG . 'numinix.com/management/includes/init_includes/init_numinix_paypal_isu.php';
        
        if (!file_exists($initFile)) {
            fwrite(STDERR, "FAIL: Init file does not exist\n");
            return false;
        }

        $content = file_get_contents($initFile);

        // Check that the init file updates version after including each installer
        if (strpos($content, 'include $installerFile') !== false) {
            fwrite(STDOUT, "✓ Init file includes installer files\n");
        } else {
            fwrite(STDERR, "FAIL: Init file should include installer files\n");
            $passed = false;
        }

        // Check that version is updated after each installer runs
        // The init file uses concatenated SQL: "UPDATE " . TABLE_CONFIGURATION
        if (strpos($content, '"UPDATE "') !== false 
            && strpos($content, 'TABLE_CONFIGURATION') !== false
            && strpos($content, '$versionKey') !== false) {
            fwrite(STDOUT, "✓ Init file updates version after installers run\n");
        } else {
            fwrite(STDERR, "FAIL: Init file should update version after installers run\n");
            $passed = false;
        }

        // Check that version is set to installer version
        if (strpos($content, '$currentVersion = $installerVersion') !== false) {
            fwrite(STDOUT, "✓ Init file tracks current version from installer filename\n");
        } else {
            fwrite(STDERR, "FAIL: Init file should track version from installer filename\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that 1_0_6.php specifically no longer has standalone version update
     */
    function testInstaller106NoStandaloneVersionUpdate(): bool
    {
        $passed = true;

        $installerFile = DIR_FS_CATALOG . 'numinix.com/management/includes/installers/numinix_paypal_isu/1_0_6.php';
        
        if (!file_exists($installerFile)) {
            fwrite(STDERR, "FAIL: 1_0_6.php does not exist\n");
            return false;
        }

        $content = file_get_contents($installerFile);
        
        // Extract expected version from filename (e.g., "1_0_6.php" -> "1.0.6")
        $expectedVersion = str_replace('_', '.', basename($installerFile, '.php'));

        // Check that 1_0_6.php does NOT have $newVersion variable with its version
        if (strpos($content, "\$newVersion = '" . $expectedVersion . "'") !== false) {
            fwrite(STDERR, "FAIL: 1_0_6.php should NOT have \$newVersion = '" . $expectedVersion . "'\n");
            $passed = false;
        } else {
            fwrite(STDOUT, "✓ 1_0_6.php does not have hardcoded version variable\n");
        }

        // Check that it does NOT have the version update SQL block
        // Use a more specific pattern to avoid false positives
        if (preg_match('/\$db->Execute\s*\(\s*["\']UPDATE\b.*\bTABLE_CONFIGURATION\b.*\bNUMINIX_PPCP_VERSION\b/', $content)) {
            fwrite(STDERR, "FAIL: 1_0_6.php should NOT have version UPDATE SQL\n");
            $passed = false;
        } else {
            fwrite(STDOUT, "✓ 1_0_6.php does not have version UPDATE SQL\n");
        }

        // Check that it HAS a comment explaining version handling
        if (strpos($content, 'init_numinix_paypal_isu.php') !== false 
            || strpos($content, 'Version number updates are handled automatically') !== false) {
            fwrite(STDOUT, "✓ 1_0_6.php has comment explaining version handling\n");
        } else {
            fwrite(STDERR, "WARNING: 1_0_6.php should have comment explaining version handling\n");
        }

        // Check that it STILL creates the table
        if (strpos($content, 'CREATE TABLE IF NOT EXISTS') !== false) {
            fwrite(STDOUT, "✓ 1_0_6.php still creates the tracking table\n");
        } else {
            fwrite(STDERR, "FAIL: 1_0_6.php should still create the tracking table\n");
            $passed = false;
        }

        return $passed;
    }

    // Run the tests
    $failures = 0;

    fwrite(STDOUT, "Test 1: Verifying no standalone version updates in installer files...\n");
    if (testNoStandaloneVersionUpdates()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 2: Verifying init file handles version updates...\n");
    if (testInitFileHandlesVersionUpdates()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 3: Verifying 1_0_6.php specifically has no standalone version update...\n");
    if (testInstaller106NoStandaloneVersionUpdate()) {
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
        fwrite(STDOUT, "\n✓ All installer version handling tests passed!\n");
        fwrite(STDOUT, "\nKey points:\n");
        fwrite(STDOUT, "1. init_numinix_paypal_isu.php is responsible for updating version numbers\n");
        fwrite(STDOUT, "2. Installer files should NOT have standalone version update blocks\n");
        fwrite(STDOUT, "3. Version updates after installer includes ensures the version reflects successful installation\n");
        exit(0);
    }
}
