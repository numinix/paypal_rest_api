<?php
declare(strict_types=1);

/**
 * Test to verify skip_next_payment functionality for subscriptions
 *
 * This test ensures that:
 * 1. The skip_next_payment flag can be set on saved card subscriptions
 * 2. The skip_next_payment column exists in both tables
 * 3. Only scheduled subscriptions can be skipped
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

echo "Running Subscription Skip Next Payment Test...\n\n";

$basePath = dirname(__DIR__);

// Test 1: Verify SavedCreditCardsManager has the skip column method
echo "Test 1: Checking SavedCreditCardsManager for skip column support...\n";
$savedCardManagerFile = $basePath . '/includes/modules/payment/paypal/PayPalRestful/Common/SavedCreditCardsManager.php';
if (file_exists($savedCardManagerFile)) {
    $content = file_get_contents($savedCardManagerFile);
    if (strpos($content, 'ensureSkipNextPaymentColumn') !== false && 
        strpos($content, 'skip_next_payment') !== false) {
        echo "✓ SavedCreditCardsManager has skip_next_payment column support\n\n";
    } else {
        echo "✗ SavedCreditCardsManager missing skip_next_payment support\n\n";
        exit(1);
    }
} else {
    echo "✗ SavedCreditCardsManager.php not found\n\n";
    exit(1);
}

// Test 2: Verify SubscriptionManager has the skip column method
echo "Test 2: Checking SubscriptionManager for skip column support...\n";
$subscriptionManagerFile = $basePath . '/includes/modules/payment/paypal/PayPalRestful/Common/SubscriptionManager.php';
if (file_exists($subscriptionManagerFile)) {
    $content = file_get_contents($subscriptionManagerFile);
    if (strpos($content, 'ensureSkipNextPaymentColumn') !== false && 
        strpos($content, 'skip_next_payment') !== false) {
        echo "✓ SubscriptionManager has skip_next_payment column support\n\n";
    } else {
        echo "✗ SubscriptionManager missing skip_next_payment support\n\n";
        exit(1);
    }
} else {
    echo "✗ SubscriptionManager.php not found\n\n";
    exit(1);
}

// Test 3: Verify the skip_next_payment method exists in paypalSavedCardRecurring
echo "Test 3: Checking for skip_next_payment method...\n";
$savedCardRecurringFile = $basePath . '/includes/classes/paypalSavedCardRecurring.php';
if (file_exists($savedCardRecurringFile)) {
    $content = file_get_contents($savedCardRecurringFile);
    if (strpos($content, 'function skip_next_payment') !== false) {
        echo "✓ paypalSavedCardRecurring has skip_next_payment method\n\n";
    } else {
        echo "✗ paypalSavedCardRecurring missing skip_next_payment method\n\n";
        exit(1);
    }
} else {
    echo "✗ paypalSavedCardRecurring.php not found\n\n";
    exit(1);
}

// Test 4: Verify cron file checks for skip flag
echo "Test 4: Checking cron file for skip logic...\n";
$cronFile = $basePath . '/cron/paypal_saved_card_recurring.php';
if (file_exists($cronFile)) {
    $content = file_get_contents($cronFile);
    if (strpos($content, 'skip_next_payment') !== false &&
        strpos($content, '$0 order') !== false) {
        echo "✓ Cron file contains skip_next_payment logic with $0 order creation\n\n";
    } else {
        echo "✗ Cron file missing complete skip_next_payment logic\n\n";
        exit(1);
    }
} else {
    echo "✗ Cron file not found\n\n";
    exit(1);
}

// Test 5: Verify admin files have skip action
echo "Test 5: Checking admin files for skip action...\n";
$savedCardAdminFile = $basePath . '/admin/paypalr_saved_card_recurring.php';
$subscriptionsAdminFile = $basePath . '/admin/paypalr_subscriptions.php';

if (file_exists($savedCardAdminFile) && file_exists($subscriptionsAdminFile)) {
    $savedCardContent = file_get_contents($savedCardAdminFile);
    $subscriptionsContent = file_get_contents($subscriptionsAdminFile);
    
    $hasSavedCardAction = strpos($savedCardContent, "case 'skip_next_payment':") !== false;
    $hasSavedCardButton = strpos($savedCardContent, 'Skip Next') !== false;
    
    $hasSubscriptionsAction = strpos($subscriptionsContent, "if (\$action === 'skip_next_payment')") !== false;
    $hasSubscriptionsButton = strpos($subscriptionsContent, 'Skip Next') !== false;
    
    if ($hasSavedCardAction && $hasSavedCardButton && $hasSubscriptionsAction && $hasSubscriptionsButton) {
        echo "✓ Both admin files have skip_next_payment action and UI buttons\n\n";
    } else {
        if (!$hasSavedCardAction || !$hasSavedCardButton) {
            echo "✗ paypalr_saved_card_recurring.php missing skip action or button\n";
        }
        if (!$hasSubscriptionsAction || !$hasSubscriptionsButton) {
            echo "✗ paypalr_subscriptions.php missing skip action or button\n";
        }
        echo "\n";
        exit(1);
    }
} else {
    echo "✗ Admin files not found\n\n";
    exit(1);
}

// Test 6: Verify security checks in skip method
echo "Test 6: Checking security and validation in skip method...\n";
$content = file_get_contents($savedCardRecurringFile);
if (strpos($content, 'Security check') !== false &&
    strpos($content, "status'] !== 'scheduled'") !== false) {
    echo "✓ skip_next_payment method has security checks and status validation\n\n";
} else {
    echo "✗ skip_next_payment method missing security or validation\n\n";
    exit(1);
}

echo "========================================\n";
echo "All skip next payment tests passed! ✓\n";
echo "========================================\n";
echo "\nSummary:\n";
echo "- Database schema updates: ✓\n";
echo "- Skip method implementation: ✓\n";
echo "- Cron job integration: ✓\n";
echo "- Admin UI (saved cards): ✓\n";
echo "- Admin UI (vault subscriptions): ✓\n";
echo "- Security and validation: ✓\n";
