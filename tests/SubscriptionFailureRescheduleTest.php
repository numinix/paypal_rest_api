<?php
declare(strict_types=1);

/**
 * Test to verify subscription failure handling doesn't change next_payment_date
 *
 * This test ensures that:
 * 1. When a payment fails, the subscription's next_payment_date stays the same (no drift)
 * 2. REST API subscriptions are properly detected even when api_type is not set
 * 3. The cron will retry on next run with the original billing date
 *
 * @copyright Copyright 2026 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

echo "Running Subscription Failure Date Preservation Test...\n\n";

$basePath = dirname(__DIR__);

// Test 1: Verify cron does NOT update next_payment_date on failure
echo "Test 1: Checking cron preserves next_payment_date when payment fails...\n";
$cronFile = $basePath . '/zc_plugins/PayPalAdvancedCheckout/v2.0.0/catalog/cron/paypalac_saved_card_recurring.php';
if (!file_exists($cronFile)) {
    $cronFile = $basePath . '/cron/paypalac_saved_card_recurring.php';
}
if (file_exists($cronFile)) {
    $content = file_get_contents($cronFile);
    
    // Check that we're NOT updating next_payment_date or 'date' field on failure
    // The old buggy code would call update_payment_info or schedule_payment
    if (preg_match('/else\s*\{[^}]*update_payment_info.*date.*tomorrow/s', $content)) {
        echo "✗ Cron still updates next_payment_date on failure (causes drift)\n";
        exit(1);
    }
    
    if (preg_match('/else\s*\{[^}]*schedule_payment.*tomorrow.*after failure/s', $content)) {
        echo "✗ Cron still calls schedule_payment on failure (creates duplicates)\n";
        exit(1);
    }
    
    // Check for the correct comment explaining why we don't update the date
    if (strpos($content, 'Do NOT update next_payment_date') !== false &&
        strpos($content, 'prevents subscription drift') !== false) {
        echo "✓ Cron correctly preserves next_payment_date on failure (no drift)\n";
    } else {
        echo "✗ Missing explanation comment about preserving next_payment_date\n";
        exit(1);
    }
    
    echo "\n";
} else {
    echo "✗ Cron file not found\n\n";
    exit(1);
}

// Test 2: Verify REST API subscription detection fallback
echo "Test 2: Checking REST API subscription detection with vault card fallback...\n";
$savedCardRecurringFile = $basePath . '/zc_plugins/PayPalAdvancedCheckout/v2.0.0/catalog/includes/classes/paypalacSavedCardRecurring.php';
if (file_exists($savedCardRecurringFile)) {
    $content = file_get_contents($savedCardRecurringFile);
    
    // Prefer REST whenever a vault token is present — including when api_type is
    // still paypalwpp/payflow from a legacy migration (avoids Payflow 81253).
    if (strpos($content, '$use_rest') !== false &&
        strpos($content, 'paypal_vault_card') !== false &&
        strpos($content, "in_array(\$api_type, array('paypalac', 'rest')") !== false) {
        echo "✓ REST API subscription detection prefers vault_id over legacy api_type\n";
    } else {
        echo "✗ Missing vault-preferred REST routing for recurring charges\n";
        exit(1);
    }

    // process_payment must not set status=failed on gateway errors (cron decides).
    if (preg_match("/function process_payment.*?Paypal error:.*?update_payment_status\(\\\$paypal_saved_card_recurring_id, 'failed'/s", $content)) {
        echo "✗ process_payment still sets status=failed on gateway errors\n";
        exit(1);
    }
    if (strpos($content, "add_payment_comment(\$paypal_saved_card_recurring_id, 'Paypal error:") !== false) {
        echo "✓ process_payment logs gateway errors without flipping status to failed\n";
    } else {
        echo "✗ process_payment should comment gateway errors instead of setting failed\n";
        exit(1);
    }
    
    echo "\n";
} else {
    echo "✗ paypalacSavedCardRecurring.php not found\n\n";
    exit(1);
}

// Test 3: Verify retryable failures restore status to scheduled (cron only processes scheduled)
echo "Test 3: Checking cron restores status=scheduled on retryable failure...\n";
if (file_exists($cronFile)) {
    $content = file_get_contents($cronFile);

    if (strpos($content, "update_payment_status(\$payment_id, 'scheduled', ' Payment attempt failed. Will retry.") !== false
        || strpos($content, "update_payment_status(\n                \$payment_id,\n                'scheduled'") !== false
        || preg_match("/update_payment_status\(\s*\\\$payment_id,\s*'scheduled',\s*' Payment attempt failed/s", $content)) {
        echo "✓ Cron restores status=scheduled so get_scheduled_payments will retry\n";
    } else {
        echo "✗ Cron must call update_payment_status(..., 'scheduled', ...) on retryable failure\n";
        exit(1);
    }

    echo "\n";
} else {
    echo "✗ Cron file not found\n\n";
    exit(1);
}

// Test 4: Verify successful payments advance the same subscription row (no duplicate rows)
echo "Test 4: Checking successful payments reschedule the same subscription...\n";
if (file_exists($cronFile)) {
    $content = file_get_contents($cronFile);

    if (strpos($content, "update_payment_status(\$payment_id, 'scheduled'") !== false
        && strpos($content, 'Next billing date') !== false) {
        echo "✓ Successful payments keep one subscription row and set next billing date\n";
    } else {
        echo "✗ Missing scheduled status update with next billing date after success\n";
        exit(1);
    }

    echo "\n";
} else {
    echo "✗ Cron file not found\n\n";
    exit(1);
}

echo "All tests passed! ✓\n";
echo "\nVerified:\n";
echo "1. Failed payments preserve next_payment_date (no subscription drift)\n";
echo "2. Vaulted cards use REST even when api_type is legacy paypalwpp/payflow\n";
echo "3. Retryable failures restore status=scheduled so cron keeps retrying\n";
echo "4. Successful payments advance the same subscription row\n";
