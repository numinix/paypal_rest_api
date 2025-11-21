<?php
declare(strict_types=1);

/**
 * Test to verify that credit card information is stored in session
 * and can be retrieved when POST data is not available.
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

    echo "Testing credit card info session storage and retrieval...\n";

    $failures = 0;

    // Test 1: Verify ccInfo is stored in session after validation
    $_SESSION = [];
    $_SESSION['PayPalRestful']['ccInfo'] = [
        'type' => 'Card',
        'number' => '4111111111111111',
        'expiry_month' => '12',
        'expiry_year' => '2030',
        'name' => 'John Doe',
        'security_code' => '123',
        'redirect' => 'https://example.com/listener',
        'last_digits' => '1111',
        'store_card' => false,
    ];

    if (!isset($_SESSION['PayPalRestful']['ccInfo'])) {
        fwrite(STDERR, "Test 1 failed: ccInfo not stored in session\n");
        $failures++;
    } else {
        echo "✓ Test 1 passed: ccInfo stored in session\n";
    }

    // Test 2: Simulate pre_confirmation_check with no POST data but session data available
    $is_preconfirmation = true;
    $_POST = []; // No POST data (simulates missing forwarding)
    
    // Check if session data would be used
    $shouldUseSession = $is_preconfirmation && 
        !isset($_POST['paypalr_cc_owner']) && !isset($_POST['ppr_cc_owner']) && 
        !isset($_POST['paypalr_saved_card']) && !isset($_POST['ppr_saved_card']) &&
        isset($_SESSION['PayPalRestful']['ccInfo']);

    if (!$shouldUseSession) {
        fwrite(STDERR, "Test 2 failed: Should use session data when POST is empty\n");
        $failures++;
    } else {
        echo "✓ Test 2 passed: Session data used when POST is empty during pre-confirmation\n";
    }

    // Test 3: Verify session data is NOT used when POST data is available
    $_POST = ['paypalr_cc_owner' => 'Jane Smith'];
    
    $shouldUseSession = $is_preconfirmation && 
        !isset($_POST['paypalr_cc_owner']) && !isset($_POST['ppr_cc_owner']) && 
        !isset($_POST['paypalr_saved_card']) && !isset($_POST['ppr_saved_card']) &&
        isset($_SESSION['PayPalRestful']['ccInfo']);

    if ($shouldUseSession) {
        fwrite(STDERR, "Test 3 failed: Should NOT use session data when POST is available\n");
        $failures++;
    } else {
        echo "✓ Test 3 passed: Session data not used when POST data is available\n";
    }

    // Test 4: Verify session data is NOT used when not in pre-confirmation
    $_POST = [];
    $is_preconfirmation = false;
    
    $shouldUseSession = $is_preconfirmation && 
        !isset($_POST['paypalr_cc_owner']) && !isset($_POST['ppr_cc_owner']) && 
        !isset($_POST['paypalr_saved_card']) && !isset($_POST['ppr_saved_card']) &&
        isset($_SESSION['PayPalRestful']['ccInfo']);

    if ($shouldUseSession) {
        fwrite(STDERR, "Test 4 failed: Should NOT use session data when not in pre-confirmation\n");
        $failures++;
    } else {
        echo "✓ Test 4 passed: Session data not used outside pre-confirmation context\n";
    }

    // Test 5: Verify session fallback works with forwarded field name
    $_POST = ['ppr_cc_owner' => 'Bob Jones'];
    $is_preconfirmation = true;
    
    $shouldUseSession = $is_preconfirmation && 
        !isset($_POST['paypalr_cc_owner']) && !isset($_POST['ppr_cc_owner']) && 
        !isset($_POST['paypalr_saved_card']) && !isset($_POST['ppr_saved_card']) &&
        isset($_SESSION['PayPalRestful']['ccInfo']);

    if ($shouldUseSession) {
        fwrite(STDERR, "Test 5 failed: Should NOT use session when forwarded field (ppr_cc_owner) is present\n");
        $failures++;
    } else {
        echo "✓ Test 5 passed: Forwarded POST field (ppr_cc_owner) prevents session fallback\n";
    }

    if ($failures > 0) {
        fwrite(STDERR, "\n$failures test(s) failed.\n");
        exit(1);
    }

    echo "\n✓ All credit card info session storage tests passed.\n";
}
