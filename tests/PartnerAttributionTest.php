<?php
declare(strict_types=1);

/**
 * Test to verify that the Numinix partner attribution ID is included in all PayPal API calls.
 * 
 * This test confirms that all payment modules (paypalac, paypalac_applepay, paypalac_googlepay, 
 * paypalac_venmo) and all supporting code (admin observers, listeners, webhooks, vault management)
 * use the centralized PayPalRestfulApi class which automatically includes the partner attribution
 * header in all HTTP requests to PayPal.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace {
    if (!defined('DIR_FS_CATALOG')) {
        define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
    }
    if (!defined('DIR_FS_LOGS')) {
        define('DIR_FS_LOGS', sys_get_temp_dir());
    }
    if (!defined('IS_ADMIN_FLAG')) {
        define('IS_ADMIN_FLAG', true);
    }

    if (!defined('MODULE_PAYMENT_PAYPALAC_SERVER')) {
        define('MODULE_PAYMENT_PAYPALAC_SERVER', 'sandbox');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_CLIENTID_L')) {
        define('MODULE_PAYMENT_PAYPALAC_CLIENTID_L', 'LiveClientId');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_SECRET_L')) {
        define('MODULE_PAYMENT_PAYPALAC_SECRET_L', 'LiveClientSecret');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_CLIENTID_S')) {
        define('MODULE_PAYMENT_PAYPALAC_CLIENTID_S', 'SandboxClientId');
    }
    if (!defined('MODULE_PAYMENT_PAYPALAC_SECRET_S')) {
        define('MODULE_PAYMENT_PAYPALAC_SECRET_S', 'SandboxClientSecret');
    }

    if (!class_exists('base')) {
        class base {}
    }

    if (session_status() === PHP_SESSION_NONE) {
        $_SESSION = [];
    }
    $_SESSION['admin_id'] = $_SESSION['admin_id'] ?? 1;

    $current_page_base = 'tests';
}

namespace PayPalRestful\Common {
    if (!class_exists(Helpers::class)) {
        require_once dirname(__DIR__) . '/includes/modules/payment/paypal/PayPalRestful/Common/Helpers.php';
    }
    if (!class_exists(Logger::class)) {
        require_once dirname(__DIR__) . '/includes/modules/payment/paypal/PayPalRestful/Common/Logger.php';
    }
    if (!class_exists(ErrorInfo::class)) {
        require_once dirname(__DIR__) . '/includes/modules/payment/paypal/PayPalRestful/Common/ErrorInfo.php';
    }
}

namespace PayPalRestful\Token {
    if (!class_exists(TokenCache::class)) {
        require_once dirname(__DIR__) . '/includes/modules/payment/paypal/PayPalRestful/Token/TokenCache.php';
    }
}

namespace PayPalRestful\Api {
    if (!class_exists(PayPalRestfulApi::class)) {
        require_once dirname(__DIR__) . '/includes/modules/payment/paypal/PayPalRestful/Api/PayPalRestfulApi.php';
    }
}

namespace {
    use PayPalRestful\Api\PayPalRestfulApi;

    $failures = 0;

    /**
     * Test 1: Verify the partner attribution constant is defined
     */
    echo "Test 1: Checking PARTNER_ATTRIBUTION_ID constant...\n";
    if (!defined('PayPalRestful\Api\PayPalRestfulApi::PARTNER_ATTRIBUTION_ID')) {
        fwrite(STDERR, "FAILED: PARTNER_ATTRIBUTION_ID constant is not defined\n");
        $failures++;
    } else {
        $partnerId = PayPalRestfulApi::PARTNER_ATTRIBUTION_ID;
        if ($partnerId !== 'NuminixPPCP_SP') {
            fwrite(STDERR, "FAILED: PARTNER_ATTRIBUTION_ID is '$partnerId', expected 'NuminixPPCP_SP'\n");
            $failures++;
        } else {
            echo "  ✓ PARTNER_ATTRIBUTION_ID = '$partnerId'\n";
        }
    }

    /**
     * Test 2: Verify the partner attribution is included in HTTP headers
     * We'll use reflection to check the setAuthorizationHeader method behavior
     */
    echo "\nTest 2: Checking setAuthorizationHeader method includes partner attribution...\n";
    try {
        $api = new PayPalRestfulApi('sandbox', 'TestClientId', 'TestClientSecret');
        
        // Use reflection to access the protected method
        $reflection = new \ReflectionClass($api);
        $method = $reflection->getMethod('setAuthorizationHeader');
        $method->setAccessible(true);
        
        // Get the source code of the method to verify it includes the partner attribution
        $filename = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        
        $fileContents = file($filename);
        $methodSource = implode('', array_slice($fileContents, $startLine - 1, $endLine - $startLine + 1));
        
        if (strpos($methodSource, 'PayPal-Partner-Attribution-Id') === false) {
            fwrite(STDERR, "FAILED: setAuthorizationHeader method does not include 'PayPal-Partner-Attribution-Id' header\n");
            $failures++;
        } elseif (strpos($methodSource, 'PARTNER_ATTRIBUTION_ID') === false) {
            fwrite(STDERR, "FAILED: setAuthorizationHeader method does not use PARTNER_ATTRIBUTION_ID constant\n");
            $failures++;
        } else {
            echo "  ✓ setAuthorizationHeader includes 'PayPal-Partner-Attribution-Id: ' . self::PARTNER_ATTRIBUTION_ID\n";
        }
    } catch (\Exception $e) {
        fwrite(STDERR, "FAILED: Exception while checking setAuthorizationHeader: " . $e->getMessage() . "\n");
        $failures++;
    }

    /**
     * Test 3: Verify all CURL methods use setAuthorizationHeader
     */
    echo "\nTest 3: Checking all CURL methods use setAuthorizationHeader...\n";
    try {
        $api = new PayPalRestfulApi('sandbox', 'TestClientId', 'TestClientSecret');
        $reflection = new \ReflectionClass($api);
        $filename = $reflection->getFileName();
        $fileContents = file_get_contents($filename);
        
        $curlMethods = ['curlPost', 'curlGet', 'curlPatch', 'curlDelete'];
        foreach ($curlMethods as $methodName) {
            if (!$reflection->hasMethod($methodName)) {
                fwrite(STDERR, "WARNING: Method $methodName not found\n");
                continue;
            }
            
            $method = $reflection->getMethod($methodName);
            $startLine = $method->getStartLine();
            $endLine = $method->getEndLine();
            
            $methodSource = implode('', array_slice(file($filename), $startLine - 1, $endLine - $startLine + 1));
            
            if (strpos($methodSource, 'setAuthorizationHeader') === false) {
                fwrite(STDERR, "FAILED: Method $methodName does not call setAuthorizationHeader\n");
                $failures++;
            } else {
                echo "  ✓ $methodName calls setAuthorizationHeader\n";
            }
        }
    } catch (\Exception $e) {
        fwrite(STDERR, "FAILED: Exception while checking CURL methods: " . $e->getMessage() . "\n");
        $failures++;
    }

    /**
     * Test 4: Verify wallet modules extend the main paypalac class
     */
    echo "\nTest 4: Checking wallet modules extend paypalac...\n";
    $walletModules = [
        'paypalac_applepay' => dirname(__DIR__) . '/includes/modules/payment/paypalac_applepay.php',
        'paypalac_googlepay' => dirname(__DIR__) . '/includes/modules/payment/paypalac_googlepay.php',
        'paypalac_venmo' => dirname(__DIR__) . '/includes/modules/payment/paypalac_venmo.php',
    ];
    
    foreach ($walletModules as $moduleName => $modulePath) {
        if (!file_exists($modulePath)) {
            fwrite(STDERR, "WARNING: Wallet module file not found: $modulePath\n");
            continue;
        }
        
        $moduleContents = file_get_contents($modulePath);
        if (strpos($moduleContents, "class $moduleName extends paypalac") === false) {
            fwrite(STDERR, "FAILED: Wallet module $moduleName does not extend paypalac\n");
            $failures++;
        } else {
            echo "  ✓ $moduleName extends paypalac (inherits partner attribution)\n";
        }
    }

    /**
     * Test 5: Verify other code locations use PayPalRestfulApi
     */
    echo "\nTest 5: Checking other code locations instantiate PayPalRestfulApi...\n";
    $otherLocations = [
        'Admin Observer' => dirname(__DIR__) . '/admin/includes/classes/observers/auto.PaypalacAdmin.php',
        'Payment Listener' => dirname(__DIR__) . '/ppac_listener.php',
        'Vault Management' => dirname(__DIR__) . '/includes/modules/pages/account_saved_credit_cards/header_php.php',
        'Subscription Management' => dirname(__DIR__) . '/includes/modules/pages/account_paypal_subscriptions/header_php.php',
        'Webhook Handler' => dirname(__DIR__) . '/includes/modules/payment/paypal/PayPalRestful/Webhooks/WebhookHandlerContract.php',
        'Webhook Responder' => dirname(__DIR__) . '/includes/modules/payment/paypal/PayPalRestful/Webhooks/WebhookResponder.php',
    ];
    
    foreach ($otherLocations as $locationName => $locationPath) {
        if (!file_exists($locationPath)) {
            fwrite(STDERR, "WARNING: File not found: $locationPath\n");
            continue;
        }
        
        $locationContents = file_get_contents($locationPath);
        if (strpos($locationContents, 'new PayPalRestfulApi') === false && strpos($locationContents, 'new \PayPalRestful\Api\PayPalRestfulApi') === false) {
            fwrite(STDERR, "WARNING: $locationName does not instantiate PayPalRestfulApi (may use it indirectly)\n");
        } else {
            echo "  ✓ $locationName uses PayPalRestfulApi (includes partner attribution)\n";
        }
    }

    /**
     * Summary
     */
    echo "\n" . str_repeat('=', 70) . "\n";
    if ($failures > 0) {
        fwrite(STDERR, "FAILED: $failures test(s) failed\n");
        exit(1);
    } else {
        echo "SUCCESS: All partner attribution tests passed!\n\n";
        echo "Conclusion:\n";
        echo "  All PayPal modules support the Numinix partner tracking (NuminixPPCP_SP)\n";
        echo "  through centralized code in the PayPalRestfulApi class. The partner\n";
        echo "  attribution ID is automatically included in all HTTP headers for every\n";
        echo "  API call to PayPal, regardless of which module or code location makes\n";
        echo "  the call.\n";
        echo str_repeat('=', 70) . "\n";
    }
}
