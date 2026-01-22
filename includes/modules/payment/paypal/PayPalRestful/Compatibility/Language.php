<?php
/**
 * Compatibility language loader for the PayPalRestful (paypalr) payment module.
 *
 * @copyright Copyright 2023-2025 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 *
 * Last updated: v1.3.0
 */

namespace PayPalRestful\Compatibility;

class Language
{
    /** @var array */
    protected static $loadedModules = [];
    public static function load(string $moduleCode = 'paypalr'): void
    {
        if (isset(self::$loadedModules[$moduleCode])) {
            return;
        }
        self::$loadedModules[$moduleCode] = true;

        $language = self::determineLanguage();
        foreach (self::getLanguageDirectories($language) as $languageBase) {
            self::includeLanguageFiles($languageBase, $moduleCode);
        }
    }

    protected static function determineLanguage(): string
    {
        $fallback = 'english';
        if (defined('IS_ADMIN_FLAG') && IS_ADMIN_FLAG === true) {
            return $_SESSION['admin_language'] ?? $fallback;
        }

        return $_SESSION['language'] ?? $fallback;
    }

    protected static function getLanguageDirectories(string $language): array
    {
        $directories = [];
        if (defined('DIR_FS_CATALOG') && defined('DIR_WS_LANGUAGES')) {
            $directories[] = DIR_FS_CATALOG . DIR_WS_LANGUAGES . $language . '/';
        }
        if (defined('DIR_FS_ADMIN') && defined('DIR_WS_LANGUAGES')) {
            $directories[] = DIR_FS_ADMIN . DIR_WS_LANGUAGES . $language . '/';
        }

        $directories = array_filter($directories, static function ($directory) {
            return is_dir($directory);
        });

        return array_values(array_unique($directories));
    }

    protected static function includeLanguageFiles(string $languageBase, string $moduleCode): void
    {
        $moduleDirectory = $languageBase . 'modules/payment/';
        if (is_dir($moduleDirectory)) {
            foreach (self::getModuleLanguagePaths($moduleDirectory, $moduleCode) as $moduleFile) {
                self::includeAndDefine($moduleFile);
            }
        }

        $extraDefinitions = $languageBase . 'extra_definitions/';
        if (is_dir($extraDefinitions)) {
            foreach (self::getExtraDefinitionPaths($extraDefinitions) as $extraFile) {
                self::includeAndDefine($extraFile);
            }
        }
    }

    protected static function getModuleLanguagePaths(string $directory, string $moduleCode): array
    {
        $paths = [];
        $templateDirectory = self::getTemplateOverrideDirectory();

        $filenames = [
            "lang.$moduleCode.php",
            'lang.paypalr_shared.php',
            'lang.paypalr.php',
        ];

        foreach ($filenames as $filename) {
            if ($templateDirectory !== null) {
                $paths[] = $directory . $templateDirectory . '/' . $filename;
            }
            $paths[] = $directory . $filename;
        }

        return array_filter($paths, static function ($file) {
            return is_file($file);
        });
    }

    protected static function getExtraDefinitionPaths(string $directory): array
    {
        $files = [
            'lang.paypalr_redirect_listener_definitions.php',
        ];

        $paths = [];
        foreach ($files as $filename) {
            $paths[] = $directory . $filename;
        }

        return array_filter($paths, static function ($file) {
            return is_file($file);
        });
    }

    protected static function includeAndDefine(string $file): void
    {
        $definitions = include $file;
        if (is_array($definitions)) {
            foreach ($definitions as $constant => $value) {
                if (!defined($constant)) {
                    define($constant, $value);
                }
            }
        }
    }

    protected static function getTemplateOverrideDirectory(): ?string
    {
        $templateDirectory = null;
        if (defined('DIR_WS_TEMPLATE')) {
            $templateDirectory = basename(rtrim(DIR_WS_TEMPLATE, '/'));
        } elseif (defined('TEMPLATE_DIR')) {
            $templateDirectory = basename((string)TEMPLATE_DIR);
        } elseif (!empty($_SESSION['tplDir'])) {
            $templateDirectory = basename((string)$_SESSION['tplDir']);
        }

        if ($templateDirectory === null || $templateDirectory === '' || $templateDirectory === '.' || $templateDirectory === '..') {
            return null;
        }

        return $templateDirectory;
    }
}
