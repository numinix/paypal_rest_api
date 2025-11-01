<?php
/**
 * Compatibility loader for the PayPal subscriptions account page language definitions.
 */

if (defined('NAVBAR_TITLE_1')) {
    return;
}

if (!function_exists('accountPaypalSubscriptionsLanguageGetTemplateDirectory')) {
    function accountPaypalSubscriptionsLanguageGetTemplateDirectory(): ?string
    {
        $templateDirectory = null;
        if (defined('DIR_WS_TEMPLATE')) {
            $templateDirectory = basename(rtrim((string) DIR_WS_TEMPLATE, '/'));
        } elseif (defined('TEMPLATE_DIR')) {
            $templateDirectory = basename((string) TEMPLATE_DIR);
        } elseif (!empty($_SESSION['tplDir'])) {
            $templateDirectory = basename((string) $_SESSION['tplDir']);
        }

        if ($templateDirectory === null || $templateDirectory === '' || $templateDirectory === '.' || $templateDirectory === '..') {
            return null;
        }

        return $templateDirectory;
    }
}

$languageDirectory = __DIR__ . '/';
$languageFiles = [];
$templateDirectory = accountPaypalSubscriptionsLanguageGetTemplateDirectory();
if ($templateDirectory !== null) {
    $languageFiles[] = $languageDirectory . $templateDirectory . '/lang.account_paypal_subscriptions.php';
}
$languageFiles[] = $languageDirectory . 'lang.account_paypal_subscriptions.php';

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
