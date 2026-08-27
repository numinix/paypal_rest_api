<?php
/**
 * Language definitions loader for the PayPal Apple Pay payment module.
 */

if (defined('MODULE_PAYMENT_PAYPALAC_APPLEPAY_TEXT_TITLE')) {
    return;
}

$languageDirectory = __DIR__ . '/';

$languageFiles = [];
if (!function_exists('paypalacApplePayGetTemplateOverrideDirectory')) {
    function paypalacApplePayGetTemplateOverrideDirectory()
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

$templateDirectory = paypalacApplePayGetTemplateOverrideDirectory();
if ($templateDirectory !== null) {
    $languageFiles[] = $languageDirectory . $templateDirectory . '/lang.paypalac_applepay.php';
}
$languageFiles[] = $languageDirectory . 'lang.paypalac_applepay.php';

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
