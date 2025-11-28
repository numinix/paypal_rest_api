<?php
/**
 * Test for isOpcAjaxRequest() method to ensure it correctly identifies OPC AJAX requests
 * and does not incorrectly return true for standard 3-page checkout.
 *
 * This test addresses the issue where JSON was being output instead of proper HTTP redirects
 * in Zen Cart v1.5.7c standard checkout because isOpcAjaxRequest() was returning true
 * when it should return false.
 */
declare(strict_types=1);

namespace {
    class base {}

    if (!defined('DIR_FS_CATALOG')) {
        define('DIR_FS_CATALOG', dirname(__DIR__) . '/');
    }

    if (!defined('DIR_WS_MODULES')) {
        define('DIR_WS_MODULES', 'includes/modules/');
    }

    $psr4Autoloader = new class {
        public function addPrefix(string $prefix, string $path): void
        {
            // Intentionally left blank for test isolation.
        }
    };
}

namespace PayPalRestful\Compatibility {
    class Language
    {
        public static function load(): void
        {
            // Test stub.
        }
    }
}

namespace PayPalRestful\Api {
    class PayPalRestfulApi
    {
        public const STATUS_APPROVED = 'APPROVED';
        public const STATUS_COMPLETED = 'COMPLETED';
        public const STATUS_CAPTURED = 'CAPTURED';
    }
}

namespace PayPalRestful\Api\Data {
    class CountryCodes
    {
    }
}

namespace PayPalRestful\Common {
    class ErrorInfo
    {
    }

    class Helpers
    {
    }

    class Logger
    {
        public function write(string $message): void
        {
            // Test stub.
        }
    }
}

namespace PayPalRestful\Admin {
    class AdminMain
    {
    }

    class DoAuthorization
    {
    }

    class DoCapture
    {
    }

    class DoRefund
    {
    }

    class DoVoid
    {
    }

    class GetPayPalOrderTransactions
    {
    }
}

namespace PayPalRestful\Zc2Pp {
    class Amount
    {
    }

    class ConfirmPayPalPaymentChoiceRequest
    {
    }

    class CreatePayPalOrderRequest
    {
    }
}

namespace {
    require_once dirname(__DIR__) . '/includes/modules/payment/paypalr.php';

    /**
     * Test double that exposes the protected isOpcAjaxRequest method
     */
    class PaypalrOpcAjaxTestDouble extends \paypalr
    {
        public function __construct()
        {
            // Empty constructor for testing
        }

        public function callIsOpcAjaxRequest(): bool
        {
            return $this->isOpcAjaxRequest();
        }
    }

    $failures = 0;
    $tester = new PaypalrOpcAjaxTestDouble();

    // -----
    // Test 1: Without OPC installed (FILENAME_CHECKOUT_ONE_CONFIRMATION not defined),
    // isOpcAjaxRequest should return false even if AJAX indicators are present
    // -----
    fwrite(STDOUT, "Test 1: Without OPC installed, method should return false\n");

    // Note: We cannot undefine constants in PHP, so we rely on the fact that
    // FILENAME_CHECKOUT_ONE_CONFIRMATION is not defined in our test environment

    if (!defined('FILENAME_CHECKOUT_ONE_CONFIRMATION')) {
        // Simulate AJAX request indicators
        $_SESSION['request'] = 'ajax';
        $_REQUEST['request'] = 'ajax';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        $result = $tester->callIsOpcAjaxRequest();

        if ($result !== false) {
            fwrite(STDERR, "FAIL: Expected false when OPC is not installed, got true\n");
            $failures++;
        } else {
            fwrite(STDOUT, "  PASS: Returns false when OPC is not installed\n");
        }

        // Clean up
        unset($_SESSION['request'], $_REQUEST['request'], $_SERVER['HTTP_X_REQUESTED_WITH']);
    } else {
        fwrite(STDERR, "SKIP: Cannot test without OPC because FILENAME_CHECKOUT_ONE_CONFIRMATION is defined\n");
    }

    // -----
    // Test 2: Without any AJAX indicators, method should return false
    // -----
    fwrite(STDOUT, "Test 2: Without AJAX indicators, method should return false\n");

    // Clear any AJAX indicators
    unset($_SESSION['request'], $_REQUEST['request'], $_SERVER['HTTP_X_REQUESTED_WITH']);

    $result = $tester->callIsOpcAjaxRequest();

    if ($result !== false) {
        fwrite(STDERR, "FAIL: Expected false without AJAX indicators, got true\n");
        $failures++;
    } else {
        fwrite(STDOUT, "  PASS: Returns false without AJAX indicators\n");
    }

    // -----
    // Test 3: Standard form POST (no AJAX indicators) should return false
    // -----
    fwrite(STDOUT, "Test 3: Standard form POST should return false\n");

    // Simulate a standard form POST
    $_POST = ['payment' => 'paypalr', 'submit' => 'Continue'];
    $_REQUEST = $_POST;

    $result = $tester->callIsOpcAjaxRequest();

    if ($result !== false) {
        fwrite(STDERR, "FAIL: Expected false for standard form POST, got true\n");
        $failures++;
    } else {
        fwrite(STDOUT, "  PASS: Returns false for standard form POST\n");
    }

    // Clean up
    $_POST = [];
    $_REQUEST = [];

    // -----
    // Summary
    // -----
    if ($failures > 0) {
        fwrite(STDERR, "\n$failures test(s) failed.\n");
        exit(1);
    }

    fwrite(STDOUT, "\nAll tests passed.\n");
    exit(0);
}
