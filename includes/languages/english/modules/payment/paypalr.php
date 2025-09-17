<?php
/**
 * paypalr language definitions loader.
 *
 * This loader provides backwards compatibility for Zen Cart versions prior to v1.5.8a,
 * ensuring that the module's language constants are defined without emitting duplicate
 * definition warnings.
 */

if (!function_exists('paypalrLanguageGetTemplateOverrideDirectory')) {
    function paypalrLanguageGetTemplateOverrideDirectory()
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

if (defined('MODULE_PAYMENT_PAYPALR_TEXT_TITLE')) {
    return;
}

$languageDirectory = __DIR__ . '/';

$languageFiles = [];
$templateDirectory = paypalrLanguageGetTemplateOverrideDirectory();
if ($templateDirectory !== null) {
    $languageFiles[] = $languageDirectory . $templateDirectory . '/lang.paypalr.php';
}
$languageFiles[] = $languageDirectory . 'lang.paypalr.php';

foreach ($languageFiles as $languageFile) {
    if (!is_file($languageFile)) {
        continue;
    }

    $definitions = include $languageFile;
    if (is_array($definitions)) {
        foreach ($definitions as $constant => $value) {
            if (!defined($constant)) {
                define($constant, $value);
            }
        }
    }

    break;
}
