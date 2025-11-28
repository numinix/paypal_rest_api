<?php
/**
 * Test to verify that the void submit button only disables when form is valid.
 *
 * This test validates the fix for the issue where clicking the CONFIRM button
 * on the void modal without entering the authorization ID would leave the button
 * in a permanent "Please wait..." disabled state.
 *
 * The fix adds a form.checkValidity() check before disabling the button,
 * so users can re-enter the authorization ID and click the button again.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
declare(strict_types=1);

namespace {
    if (!defined('DIR_FS_CATALOG')) {
        define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
    }
    if (!defined('DIR_WS_INCLUDES')) {
        define('DIR_WS_INCLUDES', 'includes/');
    }
    if (!defined('DIR_FS_LOGS')) {
        define('DIR_FS_LOGS', sys_get_temp_dir());
    }
    if (!defined('IS_ADMIN_FLAG')) {
        define('IS_ADMIN_FLAG', false);
    }

    if (session_status() === PHP_SESSION_NONE) {
        $_SESSION = [];
    }

    /**
     * Test that the JavaScript for modal buttons includes form validity check
     */
    function testModalButtonsIncludeFormValidityCheck(): bool
    {
        $passed = true;

        $mainDisplayFile = DIR_FS_CATALOG . DIR_WS_INCLUDES . 'modules/payment/paypal/PayPalRestful/Admin/Formatters/MainDisplay.php';
        $content = file_get_contents($mainDisplayFile);

        // Check 1: Verify the JavaScript includes checkValidity() before disabling
        if (strpos($content, 'checkValidity()') !== false) {
            fwrite(STDOUT, "✓ Modal button script includes form validity check\n");
        } else {
            fwrite(STDERR, "FAIL: Modal button script should include checkValidity() before disabling\n");
            $passed = false;
        }

        // Check 2: Verify the conditional structure: if (form.checkValidity()) { disabled = true }
        if (strpos($content, 'if (event.target.form.checkValidity())') !== false) {
            fwrite(STDOUT, "✓ Button only disables when form is valid\n");
        } else {
            fwrite(STDERR, "FAIL: Button should only disable when form.checkValidity() returns true\n");
            $passed = false;
        }

        // Check 3: Verify the disabled and innerHTML changes are inside the validity check
        if (preg_match('/if\s*\(\s*event\.target\.form\.checkValidity\(\)\s*\)\s*\{[^}]*disabled\s*=\s*true/', $content)) {
            fwrite(STDOUT, "✓ Disabled state is set inside validity check block\n");
        } else {
            fwrite(STDERR, "FAIL: Disabled state should be set inside the validity check block\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that the void modal form has required validation
     */
    function testVoidModalHasRequiredValidation(): bool
    {
        $passed = true;

        $mainDisplayFile = DIR_FS_CATALOG . DIR_WS_INCLUDES . 'modules/payment/paypal/PayPalRestful/Admin/Formatters/MainDisplay.php';
        $content = file_get_contents($mainDisplayFile);

        // Check: Verify the void input has required attribute and pattern
        if (strpos($content, 'pattern="[A-Za-z0-9]{17}" required') !== false) {
            fwrite(STDOUT, "✓ Void authorization ID input has required validation\n");
        } else {
            fwrite(STDERR, "FAIL: Void authorization ID input should have required and pattern validation\n");
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that createModalButtons method generates correct HTML
     */
    function testCreateModalButtonsGeneratesCorrectHtml(): bool
    {
        $passed = true;

        $mainDisplayFile = DIR_FS_CATALOG . DIR_WS_INCLUDES . 'modules/payment/paypal/PayPalRestful/Admin/Formatters/MainDisplay.php';
        $content = file_get_contents($mainDisplayFile);

        // Check 1: Verify addEventListener is used
        if (strpos($content, 'addEventListener("click"') !== false) {
            fwrite(STDOUT, "✓ Submit button has click event listener\n");
        } else {
            fwrite(STDERR, "FAIL: Submit button should have click event listener\n");
            $passed = false;
        }

        // Check 2: Verify setTimeout is used (to allow form processing)
        if (strpos($content, 'setTimeout(() =>') !== false) {
            fwrite(STDOUT, "✓ Event handler uses setTimeout for proper event timing\n");
        } else {
            fwrite(STDERR, "FAIL: Event handler should use setTimeout\n");
            $passed = false;
        }

        // Check 3: Verify the complete structure exists (use regex to be whitespace-tolerant)
        $pattern = '/addEventListener\s*\(\s*["\']click["\']\s*,\s*event\s*=>\s*setTimeout\s*\(\s*\(\s*\)\s*=>\s*\{if\s*\(event\.target\.form\.checkValidity\(\)\)/';
        if (preg_match($pattern, $content)) {
            fwrite(STDOUT, "✓ Complete validation structure is correct\n");
        } else {
            fwrite(STDERR, "FAIL: Complete validation structure is missing or incorrect\n");
            $passed = false;
        }

        return $passed;
    }

    // Run the tests
    $failures = 0;

    fwrite(STDOUT, "Test 1: Verifying modal buttons include form validity check...\n");
    if (testModalButtonsIncludeFormValidityCheck()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 2: Verifying void modal has required validation...\n");
    if (testVoidModalHasRequiredValidation()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 3: Verifying createModalButtons generates correct HTML...\n");
    if (testCreateModalButtonsGeneratesCorrectHtml()) {
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
        fwrite(STDOUT, "\n✓ All void button validation tests passed!\n");
        fwrite(STDOUT, "\nFix summary:\n");
        fwrite(STDOUT, "1. The modal submit button now checks form.checkValidity() before disabling.\n");
        fwrite(STDOUT, "2. If form validation fails (e.g., missing required authorization ID),\n");
        fwrite(STDOUT, "   the button remains enabled so the user can correct and resubmit.\n");
        fwrite(STDOUT, "3. Only when the form is valid will the button be disabled and show 'Please wait...'.\n");
        exit(0);
    }
}
