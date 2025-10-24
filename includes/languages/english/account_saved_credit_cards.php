<?php
/**
 * Backwards compatibility language loader for the saved credit cards account page.
 *
 * Zen Cart v1.5.8+ automatically loads the lang.account_saved_credit_cards.php file. This
 * shim loads that file and defines the associated constants for earlier Zen Cart versions
 * without emitting duplicate-definition warnings on newer installations.
 */

if (defined('NAVBAR_TITLE_1')) {
    return;
}

if (!function_exists('accountSavedCardsLanguageGetTemplateOverrideDirectory')) {
    function accountSavedCardsLanguageGetTemplateOverrideDirectory()
    {
        $templateDirectory = null;
        if (defined('DIR_WS_TEMPLATE')) {
            $templateDirectory = basename(rtrim((string)DIR_WS_TEMPLATE, '/'));
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

$languageDirectory = __DIR__ . '/';

$languageFiles = [];
$templateDirectory = accountSavedCardsLanguageGetTemplateOverrideDirectory();
if ($templateDirectory !== null) {
    $languageFiles[] = $languageDirectory . $templateDirectory . '/lang.account_saved_credit_cards.php';
}
$languageFiles[] = $languageDirectory . 'lang.account_saved_credit_cards.php';

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
