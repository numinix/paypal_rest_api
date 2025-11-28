<?php
/**
 * Test to verify that the Partner Referrals API uses the v2 endpoint.
 *
 * This test validates the fix for the issue where the code was using v1 API
 * endpoint (/v1/customer/partner-referrals) but sending v2 API schema fields
 * (capabilities, legal_consents, contact_information), causing PayPal to
 * reject the request with "MALFORMED_REQUEST_JSON" error.
 *
 * The fix changes the endpoint from v1 to v2 to match the payload schema.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
declare(strict_types=1);

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

    if (session_status() === PHP_SESSION_NONE) {
        $_SESSION = [];
    }

    /**
     * Test that the createPartnerReferral method uses v2 API endpoint
     */
    function testPartnerReferralsUsesV2Endpoint(): bool
    {
        $passed = true;

        $serviceFile = DIR_FS_CATALOG . 'numinix.com/management/includes/classes/Numinix/PaypalIsu/SignupLinkService.php';
        $content = file_get_contents($serviceFile);

        // Check 1: The code uses v2 endpoint for partner-referrals
        if (strpos($content, "/v2/customer/partner-referrals") !== false) {
            fwrite(STDOUT, "✓ SignupLinkService uses v2 partner-referrals endpoint\n");
        } else {
            fwrite(STDERR, "FAIL: SignupLinkService should use v2 partner-referrals endpoint\n");
            $passed = false;
        }

        // Check 2: The code does NOT use v1 endpoint for partner-referrals
        // (except in comments or documentation)
        $lines = explode("\n", $content);
        $foundV1InCode = false;
        foreach ($lines as $lineNum => $line) {
            // Skip comment lines
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '*') || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '/*')) {
                continue;
            }
            // Check for v1 endpoint in actual code
            if (strpos($line, "/v1/customer/partner-referrals") !== false) {
                fwrite(STDERR, "FAIL: Found v1 partner-referrals endpoint in code at line " . ($lineNum + 1) . "\n");
                $foundV1InCode = true;
            }
        }
        
        if (!$foundV1InCode) {
            fwrite(STDOUT, "✓ No v1 partner-referrals endpoint found in code\n");
        } else {
            $passed = false;
        }

        return $passed;
    }

    /**
     * Test that the payload includes v2-specific fields
     */
    function testPayloadUsesV2SchemaFields(): bool
    {
        $passed = true;

        $serviceFile = DIR_FS_CATALOG . 'numinix.com/management/includes/classes/Numinix/PaypalIsu/SignupLinkService.php';
        $content = file_get_contents($serviceFile);

        // Check for v2 API fields in the buildPayload method
        // Note: 'capabilities' is optional and only accepts specific values (APPLE_PAY, GOOGLE_PAY, etc.)
        // The 'features' field inside 'third_party_details' is where PAYMENT, REFUND, PARTNER_FEE belong
        $v2Fields = [
            'features' => "API payload includes 'features' field in third_party_details (v2 schema)",
            'legal_consents' => "API payload includes 'legal_consents' field (v2 schema)",
            'contact_information' => "API payload includes 'contact_information' field (v2 schema)",
            'business_entity' => "API payload includes 'business_entity' field (v2 schema)",
            'business_information' => "API payload includes 'business_information' field (v2 schema)",
        ];

        foreach ($v2Fields as $field => $message) {
            if (strpos($content, "'$field'") !== false || strpos($content, "\"$field\"") !== false) {
                fwrite(STDOUT, "✓ $message\n");
            } else {
                fwrite(STDERR, "FAIL: $message\n");
                $passed = false;
            }
        }

        return $passed;
    }

    /**
     * Test that the NuminixPaypalOnboardingService also uses v2 for referral lookups
     */
    function testOnboardingServiceUsesV2Endpoints(): bool
    {
        $passed = true;

        $serviceFile = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/class.numinix_paypal_onboarding_service.php';
        $content = file_get_contents($serviceFile);

        // Check 1: Referral lookup uses v2 endpoint
        if (strpos($content, "/v2/customer/partner-referrals/") !== false) {
            fwrite(STDOUT, "✓ OnboardingService uses v2 for partner-referrals lookup\n");
        } else {
            fwrite(STDERR, "FAIL: OnboardingService should use v2 for partner-referrals lookup\n");
            $passed = false;
        }

        // Check 2: Marketplace integrations use v1 endpoint (this is correct - different API)
        if (strpos($content, "/v1/customer/partners/marketplace/merchant-integrations") !== false) {
            fwrite(STDOUT, "✓ OnboardingService uses v1 for marketplace integrations (correct API)\n");
        } else {
            fwrite(STDOUT, "  Note: Marketplace integrations endpoint not found or different\n");
        }

        return $passed;
    }

    /**
     * Test that the API version consistency is maintained
     */
    function testApiVersionConsistency(): bool
    {
        $passed = true;

        $signupService = DIR_FS_CATALOG . 'numinix.com/management/includes/classes/Numinix/PaypalIsu/SignupLinkService.php';
        $onboardingService = DIR_FS_CATALOG . 'numinix.com/includes/modules/pages/paypal_signup/class.numinix_paypal_onboarding_service.php';

        $signupContent = file_get_contents($signupService);
        $onboardingContent = file_get_contents($onboardingService);

        // Both services should use v2 for partner-referrals operations
        $signupUsesV2 = strpos($signupContent, "/v2/customer/partner-referrals") !== false;
        $onboardingUsesV2 = strpos($onboardingContent, "/v2/customer/partner-referrals/") !== false;

        if ($signupUsesV2 && $onboardingUsesV2) {
            fwrite(STDOUT, "✓ Both services use consistent v2 API for partner-referrals\n");
        } else {
            fwrite(STDERR, "FAIL: Services should use consistent API versions\n");
            if (!$signupUsesV2) {
                fwrite(STDERR, "  - SignupLinkService not using v2\n");
            }
            if (!$onboardingUsesV2) {
                fwrite(STDERR, "  - OnboardingService not using v2\n");
            }
            $passed = false;
        }

        return $passed;
    }

    // Run the tests
    $failures = 0;

    fwrite(STDOUT, "Test 1: Verifying SignupLinkService uses v2 partner-referrals endpoint...\n");
    if (testPartnerReferralsUsesV2Endpoint()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 2: Verifying payload uses v2 API schema fields...\n");
    if (testPayloadUsesV2SchemaFields()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 3: Verifying OnboardingService uses v2 endpoints...\n");
    if (testOnboardingServiceUsesV2Endpoints()) {
        fwrite(STDOUT, "  ✓ Test passed\n\n");
    } else {
        fwrite(STDERR, "  ✗ Test failed\n\n");
        $failures++;
    }

    fwrite(STDOUT, "Test 4: Verifying API version consistency across services...\n");
    if (testApiVersionConsistency()) {
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
        fwrite(STDOUT, "\n✓ All Partner Referrals API version tests passed!\n");
        fwrite(STDOUT, "\nFix applied:\n");
        fwrite(STDOUT, "The SignupLinkService now uses the v2 Partner Referrals API endpoint\n");
        fwrite(STDOUT, "(/v2/customer/partner-referrals) instead of v1, which matches the v2\n");
        fwrite(STDOUT, "schema fields used in the payload (capabilities, legal_consents,\n");
        fwrite(STDOUT, "contact_information, business_entity, business_information).\n");
        fwrite(STDOUT, "\nThis resolves the 'MALFORMED_REQUEST_JSON' error that occurred when\n");
        fwrite(STDOUT, "sending v2-style payloads to the v1 API endpoint.\n");
        exit(0);
    }
}
