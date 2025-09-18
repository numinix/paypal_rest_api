<?php
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

    class PaypalrTestDouble extends \paypalr
    {
        public function __construct()
        {
        }

        public function determineRedirect(string $current_page_base, array $postVars): string
        {
            return $this->determinePayerActionRedirectPage($current_page_base, $postVars);
        }
    }

    $tests = [
        'plain checkout confirmation' => [
            'expected' => 'checkout_confirmation',
            'current_page_base' => 'checkout_payment',
            'postVars' => ['main_page' => 'checkout_confirmation'],
        ],
        'plain checkout success with php suffix' => [
            'expected' => 'checkout_success',
            'current_page_base' => 'checkout_payment',
            'postVars' => ['main_page' => 'checkout_success.php'],
        ],
        'nested index main_page query' => [
            'expected' => 'one_page_confirmation',
            'current_page_base' => 'checkout_payment',
            'postVars' => ['main_page' => 'index.php?main_page=one_page_confirmation'],
        ],
        'absolute url main_page query' => [
            'expected' => 'checkout_success',
            'current_page_base' => 'checkout_payment',
            'postVars' => ['main_page' => 'https://example.com/index.php?main_page=checkout_success'],
        ],
        'empty nested main page falls back' => [
            'expected' => 'checkout_payment',
            'current_page_base' => 'checkout_payment',
            'postVars' => ['main_page' => 'index.php?main_page='],
        ],
    ];

    $tester = new PaypalrTestDouble();
    $failures = 0;

    foreach ($tests as $description => $test) {
        $result = $tester->determineRedirect($test['current_page_base'], $test['postVars']);
        if ($result !== $test['expected']) {
            fwrite(STDERR, sprintf(
                "%s failed: expected '%s', got '%s'\n",
                $description,
                $test['expected'],
                $result
            ));
            $failures++;
        }
    }

    if ($failures > 0) {
        exit(1);
    }

    fwrite(STDOUT, "All tests passed.\n");
}
