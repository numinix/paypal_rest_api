<?php
/**
 * Test to verify wallet payment modules instantiate PayPalAdvancedCheckoutApi with correct parameter order.
 * This test was added to prevent regression of the bug where modules passed parameters in wrong order,
 * causing authentication failures with "expired-token error".
 *
 * @see https://github.com/numinix/paypal_rest_api/issues/[issue-number]
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

    // Define required constants for the test
    if (!defined('MODULE_PAYMENT_PAYPALAC_SERVER')) {
        define('MODULE_PAYMENT_PAYPALAC_SERVER', 'sandbox');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_CLIENTID_L')) {
        define('MODULE_PAYMENT_PAYPALAC_CLIENTID_L', 'LiveClientId123');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_SECRET_L')) {
        define('MODULE_PAYMENT_PAYPALAC_SECRET_L', 'LiveSecret456');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_CLIENTID_S')) {
        define('MODULE_PAYMENT_PAYPALAC_CLIENTID_S', 'SandboxClientId789');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_SECRET_S')) {
        define('MODULE_PAYMENT_PAYPALAC_SECRET_S', 'SandboxSecret012');
    }

    if (session_status() === PHP_SESSION_NONE) {
        $_SESSION = [];
    }
}

namespace PayPalAdvancedCheckout\Common {
    if (!class_exists(Helpers::class)) {
        require_once dirname(__DIR__) . '/includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/Helpers.php';
    }
    if (!class_exists(Logger::class)) {
        require_once dirname(__DIR__) . '/includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/Logger.php';
    }
    if (!class_exists(ErrorInfo::class)) {
        require_once dirname(__DIR__) . '/includes/modules/payment/paypal/PayPalAdvancedCheckout/Common/ErrorInfo.php';
    }
}

namespace PayPalAdvancedCheckout\Token {
    if (!class_exists(TokenCache::class)) {
        require_once dirname(__DIR__) . '/includes/modules/payment/paypal/PayPalAdvancedCheckout/Token/TokenCache.php';
    }
}

namespace PayPalAdvancedCheckout\Api {
    if (!class_exists(PayPalAdvancedCheckoutApi::class)) {
        require_once dirname(__DIR__) . '/includes/modules/payment/paypal/PayPalAdvancedCheckout/Api/PayPalAdvancedCheckoutApi.php';
    }
}

namespace {
    use PayPalAdvancedCheckout\Api\PayPalAdvancedCheckoutApi;

    /**
     * Helper function to extract private property values using reflection
     */
    function getPrivateProperty(object $object, string $property)
    {
        $reflection = new \ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        if ($prop->isPublic() === false) {
            $prop->setAccessible(true);
        }
        return $prop->getValue($object);
    }

    /**
     * Test that wallet modules use correct parameter order when instantiating PayPalAdvancedCheckoutApi
     */
    function testWalletModuleConstructorParameterOrder(): array
    {
        $results = [];
        
        // Test each wallet module's getPayPalAdvancedCheckoutApi method indirectly
        // by checking the pattern used in the source files
        $modules = [
            'paypalac_creditcard' => 'includes/modules/payment/paypalac_creditcard.php',
            'paypalac_applepay' => 'includes/modules/payment/paypalac_applepay.php',
            'paypalac_googlepay' => 'includes/modules/payment/paypalac_googlepay.php',
            'paypalac_venmo' => 'includes/modules/payment/paypalac_venmo.php',
        ];

        foreach ($modules as $moduleName => $filePath) {
            $fullPath = DIR_FS_CATALOG . $filePath;
            if (!file_exists($fullPath)) {
                $results[$moduleName] = ['status' => 'SKIP', 'reason' => 'File not found'];
                continue;
            }

            $content = file_get_contents($fullPath);
            
            // Check for the correct pattern: new PayPalAdvancedCheckoutApi(MODULE_PAYMENT_PAYPALAC_SERVER, $client_id, $secret)
            $correctPattern = '/new\s+PayPalAdvancedCheckoutApi\s*\(\s*MODULE_PAYMENT_PAYPALAC_SERVER\s*,\s*\$client_id\s*,\s*\$secret\s*\)/s';
            
            // Check for the INCORRECT pattern: new PayPalAdvancedCheckoutApi($client_id, $secret, MODULE_PAYMENT_PAYPALAC_SERVER, ...)
            $incorrectPattern = '/new\s+PayPalAdvancedCheckoutApi\s*\(\s*\$client_id\s*,\s*\$secret\s*,\s*MODULE_PAYMENT_PAYPALAC_SERVER/s';
            
            if (preg_match($correctPattern, $content)) {
                $results[$moduleName] = ['status' => 'PASS', 'reason' => 'Uses correct parameter order'];
            } elseif (preg_match($incorrectPattern, $content)) {
                $results[$moduleName] = ['status' => 'FAIL', 'reason' => 'Uses INCORRECT parameter order - will cause authentication failures!'];
            } else {
                $results[$moduleName] = ['status' => 'WARN', 'reason' => 'Could not detect PayPalAdvancedCheckoutApi instantiation pattern'];
            }
        }

        return $results;
    }

    /**
     * Verify that the correct parameter order produces the expected internal state
     */
    function testCorrectParameterOrderInternals(): bool
    {
        // Test with sandbox environment
        $api = new PayPalAdvancedCheckoutApi('sandbox', 'TestClientId', 'TestSecret');
        
        $environment = $api->getEnvironmentType();
        $clientId = getPrivateProperty($api, 'clientId');
        $secret = getPrivateProperty($api, 'clientSecret');
        
        if ($environment !== 'sandbox') {
            fwrite(STDERR, "FAIL: Expected environment 'sandbox', got '$environment'\n");
            return false;
        }
        if ($clientId !== 'TestClientId') {
            fwrite(STDERR, "FAIL: Expected clientId 'TestClientId', got '$clientId'\n");
            return false;
        }
        if ($secret !== 'TestSecret') {
            fwrite(STDERR, "FAIL: Expected clientSecret 'TestSecret', got '$secret'\n");
            return false;
        }
        
        return true;
    }

    // Run the tests
    $failures = 0;
    
    // Test 1: Verify constructor internals work correctly
    fwrite(STDOUT, "Test 1: Verifying PayPalAdvancedCheckoutApi constructor parameter order...\n");
    if (testCorrectParameterOrderInternals()) {
        fwrite(STDOUT, "  ✓ Constructor correctly assigns parameters\n");
    } else {
        fwrite(STDERR, "  ✗ Constructor test failed\n");
        $failures++;
    }
    
    // Test 2: Check all wallet modules use correct parameter order
    fwrite(STDOUT, "\nTest 2: Checking wallet modules use correct parameter order...\n");
    $moduleResults = testWalletModuleConstructorParameterOrder();
    
    foreach ($moduleResults as $module => $result) {
        $status = $result['status'];
        $reason = $result['reason'];
        
        if ($status === 'PASS') {
            fwrite(STDOUT, "  ✓ $module: $reason\n");
        } elseif ($status === 'FAIL') {
            fwrite(STDERR, "  ✗ $module: $reason\n");
            $failures++;
        } elseif ($status === 'WARN') {
            fwrite(STDOUT, "  ⚠ $module: $reason\n");
        } else {
            fwrite(STDOUT, "  - $module: $reason\n");
        }
    }
    
    // Summary
    if ($failures > 0) {
        fwrite(STDERR, "\n✗ FAILED: $failures test(s) failed\n");
        exit(1);
    } else {
        fwrite(STDOUT, "\n✓ All wallet module constructor tests passed!\n");
        exit(0);
    }
}
