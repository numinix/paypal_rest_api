<?php
// lang.braintree.php
// This file dynamically loads the language definitions for the checkout process.
// It first checks for a template override file named lang.checkout_process.php,
// and if it doesn't exist, falls back to the master file checkout_process.php.

$language_page_directory = DIR_WS_LANGUAGES . $_SESSION['language'] . '/';

// Determine the template override file path, if $template_dir is set.
$template_override = '';
if (isset($template_dir) && !empty($template_dir)) {
    $template_override = $language_page_directory . $template_dir . '/lang.' . FILENAME_CHECKOUT_PROCESS . '.php';
}

$master_file = $language_page_directory . FILENAME_CHECKOUT_PROCESS . '.php';

if (!empty($template_override) && file_exists($template_override)) {
    $define = include_once($template_override);
} elseif (file_exists($master_file)) {
    $define = include_once($master_file);
} else {
    $define = []; // Fallback to an empty array if neither file exists.
}

return $define;