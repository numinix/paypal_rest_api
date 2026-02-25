<?php
declare(strict_types=1);

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
        define('MODULE_PAYMENT_PAYPALAC_SERVER', 'live');
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

    $failures = 0;

    $tests = [
        'live fallback uses configured credentials' => function () {
            $api = new PayPalAdvancedCheckoutApi('', '', '');
            return [
                'environment' => $api->getEnvironmentType(),
                'client_id' => getPrivateProperty($api, 'clientId'),
                'client_secret' => getPrivateProperty($api, 'clientSecret'),
            ];
        },
        'sandbox fallback uses configured credentials' => function () {
            $api = new PayPalAdvancedCheckoutApi('sandbox', '', '');
            return [
                'environment' => $api->getEnvironmentType(),
                'client_id' => getPrivateProperty($api, 'clientId'),
                'client_secret' => getPrivateProperty($api, 'clientSecret'),
            ];
        },
        'explicit credentials override configuration' => function () {
            $api = new PayPalAdvancedCheckoutApi('live', 'ProvidedId', 'ProvidedSecret');
            return [
                'environment' => $api->getEnvironmentType(),
                'client_id' => getPrivateProperty($api, 'clientId'),
                'client_secret' => getPrivateProperty($api, 'clientSecret'),
            ];
        },
    ];

    foreach ($tests as $description => $test) {
        try {
            $result = $test();
        } catch (\Throwable $e) {
            fwrite(STDERR, sprintf("%s threw %s: %s\n", $description, $e::class, $e->getMessage()));
            $failures++;
            continue;
        }

        switch ($description) {
            case 'live fallback uses configured credentials':
                if ($result['environment'] !== 'live' || $result['client_id'] !== 'LiveClientId' || $result['client_secret'] !== 'LiveClientSecret') {
                    fwrite(STDERR, sprintf("%s failed. Result: %s\n", $description, json_encode($result)));
                    $failures++;
                }
                break;
            case 'sandbox fallback uses configured credentials':
                if ($result['environment'] !== 'sandbox' || $result['client_id'] !== 'SandboxClientId' || $result['client_secret'] !== 'SandboxClientSecret') {
                    fwrite(STDERR, sprintf("%s failed. Result: %s\n", $description, json_encode($result)));
                    $failures++;
                }
                break;
            case 'explicit credentials override configuration':
                if ($result['environment'] !== 'live' || $result['client_id'] !== 'ProvidedId' || $result['client_secret'] !== 'ProvidedSecret') {
                    fwrite(STDERR, sprintf("%s failed. Result: %s\n", $description, json_encode($result)));
                    $failures++;
                }
                break;
        }
    }

    if ($failures > 0) {
        exit(1);
    }

    fwrite(STDOUT, "PayPalAdvancedCheckoutApi constructor tests passed.\n");
}

namespace {
    function getPrivateProperty(object $object, string $property)
    {
        $reflection = new \ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        if ($prop->isPublic() === false) {
            $prop->setAccessible(true);
        }
        return $prop->getValue($object);
    }
}
