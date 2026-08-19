<?php
/**
 * Test: Pay Later server-side confirmation fix and dead radio-button UI fix.
 *
 * Background (ticket 615108 / redlinestands.com):
 * - The customer reported that selecting the "PayPal Pay Later" radio button
 *   did nothing -- only clicking the yellow PayPal-rendered button worked,
 *   which was confusing. The native radio was never actually hidden during
 *   normal rendering even though hideModuleRadio()/CSS support already
 *   existed (same latent gap Google Pay/Apple Pay had before their fix).
 * - Separately, the customer still saw "We were unable to confirm your Pay
 *   Later payment with PayPal" even on an eligible cart amount. Pay Later is
 *   approved through PayPal's own hosted popup (paypal.Buttons()), so by the
 *   time onApprove fires the order is already APPROVED; the shared
 *   processWalletConfirmation() was nonetheless calling confirm-payment-source
 *   (a call meant only for orders confirmed without a payment_source, e.g.
 *   card-only multi-step flows), which PayPal was rejecting.
 *
 * This matches the exact class of bug already fixed for Google Pay via
 * client-side confirmation (see GooglePayClientSideConfirmationTest.php);
 * Pay Later instead skips confirm-payment-source server-side and just
 * re-checks the live order status, since no client "confirmed" flag exists
 * for the plain Buttons() flow.
 */

$jsFile = __DIR__ . '/../includes/modules/payment/paypal/PayPalAdvancedCheckout/jquery.paypalac.paylater.js';
$phpFile = __DIR__ . '/../includes/modules/payment/paypal/paypal_common.php';

$testPassed = true;
$errors = [];

$jsContent = file_get_contents($jsFile);
$phpContent = file_get_contents($phpFile);

// Test 1: hideModuleRadio() is actually invoked during normal rendering,
// not just defined. (Definition + at least one real call site.)
$hideModuleRadioCallCount = preg_match_all('/\bhideModuleRadio\s*\(\s*\)\s*;/', $jsContent);
if ($hideModuleRadioCallCount < 2) {
    $testPassed = false;
    $errors[] = "hideModuleRadio() should be invoked outside of hidePaymentMethodContainer() so the dead radio is hidden during normal rendering, not just when the button is unavailable.";
} else {
    echo "✓ Pay Later JS invokes hideModuleRadio() during normal rendering (found {$hideModuleRadioCallCount} call sites)\n";
}

// Test 2: The label text is NOT hidden by default (only the radio), so the
// shopper still sees "PayPal Pay Later" for context next to the button.
if (strpos($jsContent, 'Keep the mock radio button visible') !== false) {
    $testPassed = false;
    $errors[] = "Stale comment claiming the radio is intentionally kept visible should be removed/updated.";
} else {
    echo "✓ Stale 'keep radio visible' comment has been updated\n";
}

// Test 3: paypal_common.php has a dedicated 'paylater' branch in
// processWalletConfirmation().
if (!preg_match('/if\s*\(\$walletType\s*===\s*[\'"]paylater[\'"]\)\s*\{/', $phpContent, $matches, PREG_OFFSET_CAPTURE)) {
    $testPassed = false;
    $errors[] = "processWalletConfirmation() should have a dedicated branch for \$walletType === 'paylater'.";
} else {
    $branchStart = $matches[0][1];

    // Isolate roughly the paylater branch body up to its "return;" so we can
    // check it doesn't call confirmPaymentSource but does call getOrderStatus.
    $branchEnd = strpos($phpContent, 'return;', $branchStart);
    $branchBody = $branchEnd !== false
        ? substr($phpContent, $branchStart, $branchEnd - $branchStart)
        : substr($phpContent, $branchStart, 2000);

    // Test 4: Pay Later branch must NOT call confirmPaymentSource (look for
    // the actual method call, not just the word appearing in a log message).
    if (strpos($branchBody, '->confirmPaymentSource(') !== false) {
        $testPassed = false;
        $errors[] = "The paylater branch should not call confirmPaymentSource(); Pay Later orders are already approved via the hosted popup by the time onApprove fires.";
    } else {
        echo "✓ Pay Later branch does not call confirmPaymentSource()\n";
    }

    // Test 5: Pay Later branch must call getOrderStatus to re-verify the order.
    if (strpos($branchBody, 'getOrderStatus') === false) {
        $testPassed = false;
        $errors[] = "The paylater branch should call getOrderStatus() to verify the order before proceeding.";
    } else {
        echo "✓ Pay Later branch calls getOrderStatus() to verify the order\n";
    }

    // Test 6: Pay Later branch must still surface confirm_failed on a hard
    // failure, and mark wallet_payment_confirmed on success.
    if (strpos($branchBody, "errorMessages['confirm_failed']") === false) {
        $testPassed = false;
        $errors[] = "The paylater branch should still redirect with confirm_failed if getOrderStatus() fails outright.";
    } else {
        echo "✓ Pay Later branch still reports confirm_failed if getOrderStatus() fails\n";
    }

    if (strpos($branchBody, "['wallet_payment_confirmed'] = true") === false) {
        $testPassed = false;
        $errors[] = "The paylater branch should mark wallet_payment_confirmed on success, matching other wallet types.";
    } else {
        echo "✓ Pay Later branch marks wallet_payment_confirmed on success\n";
    }
}

// Test 7: The new paylater branch must run before the generic
// confirmPaymentSource fallthrough (order matters -- PHP returns early).
$paylaterBranchPos = strpos($phpContent, "if (\$walletType === 'paylater')");
$genericConfirmPos = strpos($phpContent, '$confirm_response = $this->paymentModule->ppr->confirmPaymentSource(');
if ($paylaterBranchPos === false || $genericConfirmPos === false || $paylaterBranchPos >= $genericConfirmPos) {
    $testPassed = false;
    $errors[] = "The paylater branch must appear (and return) before the generic confirmPaymentSource fallthrough used by other wallet types.";
} else {
    echo "✓ Pay Later branch is checked before the generic confirmPaymentSource fallthrough\n";
}

echo "\n";
if ($testPassed) {
    echo "All tests passed! ✓\n";
    exit(0);
}

echo "Tests failed:\n";
foreach ($errors as $error) {
    echo "  ✗ {$error}\n";
}
exit(1);
