<?php
/**
 * Test: Pay Later Confirm Order parity with PayPal, plus server confirmation.
 *
 * Background (ticket 615108 / redlinestands.com):
 * - Pay Later should look and act like the PayPal row: visible native radio,
 *   branded button to the right, no "PayPal Pay Later" text label. Confirm
 *   Order (or the button) starts hosted approval, then checkout continues.
 * - Pay Later is approved through PayPal's hosted popup (paypal.Buttons()),
 *   so processWalletConfirmation() must skip confirm-payment-source and
 *   re-check live order status.
 */

$jsFile = __DIR__ . '/../zc_plugins/PayPalAdvancedCheckout/v2.0.0/catalog/includes/modules/payment/paypal/PayPalAdvancedCheckout/jquery.paypalac.paylater.js';
$phpFile = __DIR__ . '/../zc_plugins/PayPalAdvancedCheckout/v2.0.0/catalog/includes/modules/payment/paypal/paypal_common.php';
$moduleFile = __DIR__ . '/../zc_plugins/PayPalAdvancedCheckout/v2.0.0/catalog/includes/modules/payment/paypalac_paylater.php';

$testPassed = true;
$errors = [];

$jsContent = file_get_contents($jsFile);
$phpContent = file_get_contents($phpFile);
$moduleContent = file_get_contents($moduleFile);

// Test 1: hideModuleRadio() is only used when the method is ineligible.
$hideModuleRadioCallCount = preg_match_all('/\bhideModuleRadio\s*\(\s*\)\s*;/', $jsContent);
if ($hideModuleRadioCallCount !== 1) {
    $testPassed = false;
    $errors[] = "hideModuleRadio() should be invoked once, from hidePaymentMethodContainer(), so the radio stays visible like PayPal during normal rendering (found {$hideModuleRadioCallCount} call sites).";
} else {
    echo "✓ Pay Later JS keeps the radio visible during normal rendering (hideModuleRadio only when ineligible)\n";
}

if (!preg_match('/function hidePaymentMethodContainer\s*\([^)]*\)\s*\{[\s\S]*?\bhideModuleRadio\s*\(\s*\)\s*;/', $jsContent)) {
    $testPassed = false;
    $errors[] = "hideModuleRadio() must still run from hidePaymentMethodContainer() when Pay Later is unavailable.";
} else {
    echo "✓ hideModuleRadio() still hides the radio when Pay Later is ineligible\n";
}

if (strpos($jsContent, 'paypalac-wallet-radio-hidden-control') === false) {
    $testPassed = false;
    $errors[] = "hideModuleRadio() must mark the theme custom-radio wrapper so label::before is hidden when the method is ineligible.";
} else {
    echo "✓ Pay Later JS marks the custom-radio wrapper when hiding an ineligible method\n";
}

// Test 2: Confirm Order intercept matches the PayPal checkout process via redirect.
if (strpos($jsContent, 'function wrapSubmitCheckout') === false
    || strpos($jsContent, 'function startPayLaterConfirmOrderRedirect') === false
    || strpos($jsContent, 'function interceptPaylaterCheckoutSubmit') === false
    || strpos($jsContent, 'confirm_redirect') === false
) {
    $testPassed = false;
    $errors[] = "Pay Later JS should intercept Confirm Order and redirect to PayPal Pay Later approval (confirm_redirect), matching the PayPal Confirm Order process.";
} else {
    echo "✓ Pay Later JS intercepts Confirm Order and starts a Pay Later approval redirect\n";
}

if (strpos($jsContent, 'function serializeCheckoutPostsForPayLater') === false
    || strpos($jsContent, 'checkout_posts') === false
) {
    $testPassed = false;
    $errors[] = "Pay Later Confirm Order must serialize checkout form fields as checkout_posts so ppac_listener can resume OPC after PayPal return.";
} else {
    echo "✓ Pay Later Confirm Order sends checkout_posts with the redirect request\n";
}

if (strpos($jsContent, "shape: 'pill'") === false) {
    $testPassed = false;
    $errors[] = "Pay Later button should use pill shape to match the PayPal branded button.";
} else {
    echo "✓ Pay Later button uses pill shape\n";
}

if (strpos($jsContent, 'Keep the mock radio button visible') !== false) {
    $testPassed = false;
    $errors[] = "Stale comment claiming the radio is intentionally kept visible should be removed/updated.";
} else {
    echo "✓ Stale 'keep radio visible' comment is not present\n";
}

// Test 3: selection() is radio + button only (no text label), like paypalac.
if (preg_match('/\$selectionLabel\s*\.\s*[\'"]\s*[\'"]?\s*\.\s*\$buttonContainer/', $moduleContent)
    || preg_match("/'module'\s*=>\s*\$selectionLabel/", $moduleContent)
) {
    $testPassed = false;
    $errors[] = "selection() should render only the Pay Later button (no text label), matching paypalac::selection().";
} else {
    echo "✓ Pay Later selection() does not prepend label text next to the button\n";
}

// Test 4: paypal_common.php has a dedicated 'paylater' branch in
// processWalletConfirmation().
if (!preg_match('/if\s*\(\$walletType\s*===\s*[\'"]paylater[\'"]\)\s*\{/', $phpContent, $matches, PREG_OFFSET_CAPTURE)) {
    $testPassed = false;
    $errors[] = "processWalletConfirmation() should have a dedicated branch for \$walletType === 'paylater'.";
} else {
    $branchStart = $matches[0][1];

    $branchEnd = strpos($phpContent, 'return;', $branchStart);
    $branchBody = $branchEnd !== false
        ? substr($phpContent, $branchStart, $branchEnd - $branchStart)
        : substr($phpContent, $branchStart, 2000);

    if (strpos($branchBody, '->confirmPaymentSource(') !== false) {
        $testPassed = false;
        $errors[] = "The paylater branch should not call confirmPaymentSource(); Pay Later orders are already approved via the hosted popup by the time onApprove fires.";
    } else {
        echo "✓ Pay Later branch does not call confirmPaymentSource()\n";
    }

    if (strpos($branchBody, 'getOrderStatus') === false) {
        $testPassed = false;
        $errors[] = "The paylater branch should call getOrderStatus() to verify the order before proceeding.";
    } else {
        echo "✓ Pay Later branch calls getOrderStatus() to verify the order\n";
    }

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

$paylaterBranchPos = strpos($phpContent, "if (\$walletType === 'paylater')");
$genericConfirmPos = strpos($phpContent, '$confirm_response = $this->paymentModule->ppr->confirmPaymentSource(');
if ($paylaterBranchPos === false || $genericConfirmPos === false || $paylaterBranchPos >= $genericConfirmPos) {
    $testPassed = false;
    $errors[] = "The paylater branch must appear (and return) before the generic confirmPaymentSource fallthrough used by other wallet types.";
} else {
    echo "✓ Pay Later branch is checked before the generic confirmPaymentSource fallthrough\n";
}

// Test 5: Confirm Order create uses payment_source.paypal (Orders v2 rejects paylater).
$createRequestFile = __DIR__ . '/../zc_plugins/PayPalAdvancedCheckout/v2.0.0/catalog/includes/modules/payment/paypal/PayPalAdvancedCheckout/Zc2Pp/CreatePayPalOrderRequest.php';
$createRequestContent = file_get_contents($createRequestFile);
if ($createRequestContent === false
    || strpos($createRequestContent, "['payment_source']['paypal'] = \$this->buildPayLaterRedirectPaymentSource") === false
    || strpos($createRequestContent, "['payment_source']['paylater']") !== false
) {
    $testPassed = false;
    $errors[] = "Confirm Order create must use payment_source.paypal via buildPayLaterRedirectPaymentSource (not payment_source.paylater).";
} else {
    echo "✓ Confirm Order create uses payment_source.paypal (Orders v2 compatible)\n";
}

if (strpos($phpContent, 'fundingSource=paylater') === false) {
    $testPassed = false;
    $errors[] = "Approve URL for Pay Later Confirm Order redirect should append fundingSource=paylater.";
} else {
    echo "✓ Pay Later Confirm Order approve URL appends fundingSource=paylater\n";
}

// Test 5b: Confirm Order must persist browser checkout form fields (not empty $_POST).
$walletEndpoint = file_get_contents(__DIR__ . '/../zc_plugins/PayPalAdvancedCheckout/v2.0.0/Installer/assets/root/ppac_wallet.php');
if ($walletEndpoint === false
    || strpos($walletEndpoint, 'checkout_posts') === false
    || strpos($walletEndpoint, 'ajaxCreatePayLaterConfirmRedirect($checkoutPosts)') === false
) {
    $testPassed = false;
    $errors[] = "ppac_wallet.php must parse checkout_posts and pass them into ajaxCreatePayLaterConfirmRedirect().";
} else {
    echo "✓ ppac_wallet.php forwards checkout_posts into Confirm Order redirect\n";
}

if (strpos($moduleContent, 'function storePayLaterPayerAction(string $approveUrl, array $checkoutPosts') === false
    || strpos($moduleContent, "\$savedPosts['paypalac_paylater_status'] = 'approved'") === false
) {
    $testPassed = false;
    $errors[] = "storePayLaterPayerAction must accept checkout_posts and mark paylater status approved for the return form.";
} else {
    echo "✓ storePayLaterPayerAction persists checkout_posts and approved paylater status\n";
}

if (strpos($moduleContent, '$keepConfirmed') === false
    || strpos($moduleContent, "(\$sessionOrder['payment_source'] ?? '') === 'paylater'") === false
) {
    $testPassed = false;
    $errors[] = "selection() must preserve wallet_payment_confirmed after a successful Pay Later return.";
} else {
    echo "✓ selection() preserves wallet_payment_confirmed after Pay Later return\n";
}

// Test 6: Buttons ineligibility must not hide Confirm Order radio; overlay uses OPRC/mower message.
if (strpos($jsContent, 'function hidePayLaterButtonOnly') === false
    || strpos($jsContent, 'keeping radio for Confirm Order') === false
) {
    $testPassed = false;
    $errors[] = "Pay Later JS should hide only the Buttons widget when ineligible so Confirm Order remains available.";
} else {
    echo "✓ Buttons ineligibility keeps the Pay Later radio for Confirm Order\n";
}

if (strpos($jsContent, 'function showCheckoutProcessingOverlay') === false
    || strpos($jsContent, 'blockPage') === false
    || strpos($jsContent, 'oprcProcessingText') === false
) {
    $testPassed = false;
    $errors[] = "Pay Later Confirm Order should show the OPRC/mower processing message (blockPage/oprcProcessingText), not an empty blockUI.";
} else {
    echo "✓ Confirm Order uses OPRC/mower processing overlay messaging\n";
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
