<?php
/**
 * Provides a lightweight autoloader that makes the core Zen Cart language
 * class available when the paypalr entry points are executed in isolation.
 *
 * PayPal webhook deliveries can invoke ppr_webhook.php without the rest of the
 * storefront bootstrap. Some hosting environments omit the stock
 * includes/classes/language.php file from the deployment that services the
 * webhook endpoint, which previously resulted in a fatal error. This shim
 * attempts to load the storefront's language class when it exists and falls
 * back to a narrow stub that supplies the behaviour required by the webhook
 * handler.
 */

namespace PayPalRestful\Compatibility;

final class LanguageAutoloader
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        // Prepend the autoloader so that we have the first opportunity to
        // satisfy the language class before PHP reports a fatal error.
        spl_autoload_register([self::class, 'autoload'], true, true);
        self::$registered = true;
    }

    /**
     * Attempt to load the Zen Cart language class or provide the paypalr stub
     * when the storefront's class is unavailable.
     */
    private static function autoload(string $class): void
    {
        if (strcasecmp($class, 'language') !== 0) {
            return;
        }

        $languageFile = self::resolveLanguageClassPath();
        if ($languageFile !== null) {
            require_once $languageFile;
            return;
        }

        require_once __DIR__ . '/LanguageStub.php';
    }

    private static function resolveLanguageClassPath(): ?string
    {
        $catalogRoot = self::catalogRoot();
        $languageFile = $catalogRoot . 'includes/classes/language.php';

        if (is_file($languageFile)) {
            return $languageFile;
        }

        return null;
    }

    private static function catalogRoot(): string
    {
        if (defined('DIR_FS_CATALOG') && DIR_FS_CATALOG !== '') {
            return rtrim((string) DIR_FS_CATALOG, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }

        return rtrim(dirname(__DIR__, 6), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
}
