<?php
declare(strict_types=1);

/**
 * Test to verify skip_next_payment functionality for subscriptions
 *
 * This test ensures that:
 * 1. The skip_next_payment method calculates and updates the next billing date
 * 2. The admin UI provides the skip action
 * 3. No flag is used - date is updated immediately
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

echo "Running Subscription Skip Next Payment Test...\n\n";

$basePath = dirname(__DIR__);

// Test 1: Verify skip_next_payment method exists and calculates dates
echo "Test 1: Checking for skip_next_payment method with date calculation...\n";
$savedCardRecurringFile = $basePath . '/includes/classes/paypalSavedCardRecurring.php';
if (file_exists($savedCardRecurringFile)) {
    $content = file_get_contents($savedCardRecurringFile);
    if (strpos($content, 'function skip_next_payment') !== false &&
        strpos($content, 'DateInterval') !== false &&
        strpos($content, 'update_payment_info') !== false) {
        echo "✓ paypalSavedCardRecurring has skip_next_payment method with date calculation\n\n";
    } else {
        echo "✗ paypalSavedCardRecurring missing complete skip_next_payment implementation\n\n";
        exit(1);
    }
} else {
    echo "✗ paypalSavedCardRecurring.php not found\n\n";
    exit(1);
}

// Test 2: Verify no skip flag logic in schema managers
echo "Test 2: Verifying skip flag removed from schema managers...\n";
$savedCardManagerFile = $basePath . '/includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/SavedCreditCardsManager.php';
$subscriptionManagerFile = $basePath . '/includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/SubscriptionManager.php';

if (file_exists($savedCardManagerFile) && file_exists($subscriptionManagerFile)) {
    $savedCardContent = file_get_contents($savedCardManagerFile);
    $subscriptionContent = file_get_contents($subscriptionManagerFile);
    
    if (strpos($savedCardContent, 'ensureSkipNextPaymentColumn') === false && 
        strpos($subscriptionContent, 'ensureSkipNextPaymentColumn') === false) {
        echo "✓ Skip flag columns removed from schema managers\n\n";
    } else {
        echo "✗ Skip flag logic still present in schema managers\n\n";
        exit(1);
    }
} else {
    echo "✗ Schema manager files not found\n\n";
    exit(1);
}

// Test 3: Verify cron file has no skip flag logic
echo "Test 3: Checking cron file has no skip flag logic...\n";
$cronFile = $basePath . '/cron/paypalac_saved_card_recurring.php';
if (file_exists($cronFile)) {
    $content = file_get_contents($cronFile);
    if (strpos($content, 'skip_next_payment') === false) {
        echo "✓ Cron file has no skip flag logic (skip handled in admin)\n\n";
    } else {
        echo "✗ Cron file still contains skip flag logic\n\n";
        exit(1);
    }
} else {
    echo "✗ Cron file not found\n\n";
    exit(1);
}

// Test 4: Verify admin files have skip action with immediate date update
echo "Test 4: Checking admin files for skip action with date calculation...\n";
$savedCardAdminFile = $basePath . '/admin/paypalac_saved_card_recurring.php';
$subscriptionsAdminFile = $basePath . '/admin/paypalac_subscriptions.php';

if (file_exists($savedCardAdminFile) && file_exists($subscriptionsAdminFile)) {
    $savedCardContent = file_get_contents($savedCardAdminFile);
    $subscriptionsContent = file_get_contents($subscriptionsAdminFile);
    
    $hasSavedCardAction = strpos($savedCardContent, "case 'skip_next_payment':") !== false;
    $hasSavedCardButton = strpos($savedCardContent, 'Skip Next') !== false;
    
    $hasSubscriptionsAction = strpos($subscriptionsContent, "if (\$action === 'skip_next_payment')") !== false;
    $hasSubscriptionsButton = strpos($subscriptionsContent, 'Skip Next') !== false;
    $hasSubscriptionsCalc = strpos($subscriptionsContent, 'DateInterval') !== false;
    $hasPayPalAPIUpdate = strpos($subscriptionsContent, 'updateProfile') !== false;
    
    if ($hasSavedCardAction && $hasSavedCardButton && $hasSubscriptionsAction && 
        $hasSubscriptionsButton && $hasSubscriptionsCalc && $hasPayPalAPIUpdate) {
        echo "✓ Both admin files have skip_next_payment action with date calculation and PayPal API integration\n\n";
    } else {
        if (!$hasSavedCardAction || !$hasSavedCardButton) {
            echo "✗ paypalac_saved_card_recurring.php missing skip action or button\n";
        }
        if (!$hasSubscriptionsAction || !$hasSubscriptionsButton || !$hasSubscriptionsCalc) {
            echo "✗ paypalac_subscriptions.php missing skip action, button, or date calculation\n";
        }
        if (!$hasPayPalAPIUpdate) {
            echo "✗ paypalac_subscriptions.php missing PayPal API integration for vault subscriptions\n";
        }
        echo "\n";
        exit(1);
    }
} else {
    echo "✗ Admin files not found\n\n";
    exit(1);
}

// Test 5: Verify security and validation in skip method
echo "Test 5: Checking security and validation in skip method...\n";
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
echo "- Skip method with date calculation: ✓\n";
echo "- No skip flag in schema: ✓\n";
echo "- No skip logic in cron: ✓\n";
echo "- Admin UI (saved cards): ✓\n";
echo "- Admin UI (vault subscriptions): ✓\n";
echo "- Security and validation: ✓\n";
