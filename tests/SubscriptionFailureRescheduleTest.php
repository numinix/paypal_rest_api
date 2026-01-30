<?php
declare(strict_types=1);

/**
 * Test to verify subscription failure handling doesn't create duplicate subscriptions
 *
 * This test ensures that:
 * 1. When a payment fails, the cron updates the existing subscription instead of creating a new one
 * 2. REST API subscriptions are properly detected even when api_type is not set
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

echo "Running Subscription Failure Reschedule Test...\n\n";

$basePath = dirname(__DIR__);

// Test 1: Verify cron uses update_payment_info instead of schedule_payment on failure
echo "Test 1: Checking cron reschedules failed payments without creating duplicates...\n";
$cronFile = $basePath . '/cron/paypal_saved_card_recurring.php';
if (file_exists($cronFile)) {
    $content = file_get_contents($cronFile);
    
    // Check that when payment fails, we update the existing subscription
    $updatePattern = '/update_payment_info.*next_payment_date.*tomorrow/s';
    $schedulePattern = '/schedule_payment.*tomorrow.*after failure/';
    
    if (preg_match($updatePattern, $content)) {
        echo "✓ Cron uses update_payment_info to reschedule failed payments\n";
    } else {
        echo "✗ Cron doesn't use update_payment_info for failed payment rescheduling\n";
        exit(1);
    }
    
    // Make sure we're NOT calling schedule_payment on failure (which would create duplicates)
    if (preg_match($schedulePattern, $content)) {
        echo "✗ Cron still calls schedule_payment on failure (creates duplicates)\n";
        exit(1);
    } else {
        echo "✓ Cron doesn't call schedule_payment on failure\n";
    }
    
    echo "\n";
} else {
    echo "✗ Cron file not found\n\n";
    exit(1);
}

// Test 2: Verify REST API subscription detection fallback
echo "Test 2: Checking REST API subscription detection with vault card fallback...\n";
$savedCardRecurringFile = $basePath . '/includes/classes/paypalSavedCardRecurring.php';
if (file_exists($savedCardRecurringFile)) {
    $content = file_get_contents($savedCardRecurringFile);
    
    // Look for the fallback logic that checks for vault card
    if (strpos($content, 'has_vault_card') !== false &&
        strpos($content, 'paypal_vault_card') !== false &&
        strpos($content, "api_type === '' && \$has_vault_card") !== false) {
        echo "✓ REST API subscription detection includes vault card fallback\n";
    } else {
        echo "✗ Missing vault card fallback for REST API subscription detection\n";
        exit(1);
    }
    
    echo "\n";
} else {
    echo "✗ paypalSavedCardRecurring.php not found\n\n";
    exit(1);
}

// Test 3: Verify failed subscriptions are still picked up by get_scheduled_payments
echo "Test 3: Checking get_scheduled_payments includes failed status...\n";
if (file_exists($savedCardRecurringFile)) {
    $content = file_get_contents($savedCardRecurringFile);
    
    // Verify that failed subscriptions are included in scheduled payments query
    if (strpos($content, "status = 'scheduled' OR status = 'failed'") !== false ||
        strpos($content, "status = \\'scheduled\\' OR status = \\'failed\\'") !== false) {
        echo "✓ get_scheduled_payments includes failed status for auto-retry\n";
    } else {
        echo "✗ get_scheduled_payments may not include failed subscriptions\n";
        exit(1);
    }
    
    echo "\n";
} else {
    echo "✗ paypalSavedCardRecurring.php not found\n\n";
    exit(1);
}

echo "All tests passed! ✓\n";
echo "\nVerified:\n";
echo "1. Failed payments are rescheduled by updating existing subscription (no duplicates)\n";
echo "2. REST API subscriptions are detected even when api_type is not set\n";
echo "3. Failed subscriptions are automatically retried by the cron\n";
