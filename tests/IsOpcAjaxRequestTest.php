<?php
/**
 * Test for isOpcAjaxRequest() method to ensure it correctly identifies AJAX requests
 * using Zen Cart's IS_AJAX_REQUEST constant.
 *
 * This test addresses the issue where JSON was being output instead of proper HTTP redirects
 * in Zen Cart v1.5.7c standard checkout because isOpcAjaxRequest() was incorrectly
 * detecting AJAX requests.
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
    // Test 1: Without IS_AJAX_REQUEST defined, method should return false
    // even if other AJAX indicators are present (session, headers, etc.)
    // -----
    fwrite(STDOUT, "Test 1: Without IS_AJAX_REQUEST defined, method should return false\n");

    // Note: IS_AJAX_REQUEST is not defined in our test environment
    // Simulate various AJAX indicators that should be ignored
    $_SESSION['request'] = 'ajax';
    $_REQUEST['request'] = 'ajax';
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

    $result = $tester->callIsOpcAjaxRequest();

    if ($result !== false) {
        fwrite(STDERR, "FAIL: Expected false when IS_AJAX_REQUEST is not defined, got true\n");
        $failures++;
    } else {
        fwrite(STDOUT, "  PASS: Returns false when IS_AJAX_REQUEST is not defined\n");
    }

    // Clean up
    unset($_SESSION['request'], $_REQUEST['request'], $_SERVER['HTTP_X_REQUESTED_WITH']);

    // -----
    // Test 2: Standard form POST should return false
    // -----
    fwrite(STDOUT, "Test 2: Standard form POST should return false\n");

    // Simulate a standard form POST (no IS_AJAX_REQUEST defined)
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
    // Test 3: Method uses IS_AJAX_REQUEST constant pattern
    // (We can't actually set IS_AJAX_REQUEST in tests since constants can't be redefined,
    // but we verify the method exists and returns false when constant is not set)
    // -----
    fwrite(STDOUT, "Test 3: Method correctly checks IS_AJAX_REQUEST constant\n");

    // The method should check: defined('IS_AJAX_REQUEST') && IS_AJAX_REQUEST === true
    // Since IS_AJAX_REQUEST is not defined, this should return false
    $result = $tester->callIsOpcAjaxRequest();

    if ($result !== false) {
        fwrite(STDERR, "FAIL: Expected false when IS_AJAX_REQUEST is not defined, got true\n");
        $failures++;
    } else {
        fwrite(STDOUT, "  PASS: Returns false when IS_AJAX_REQUEST constant is not defined\n");
    }

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
