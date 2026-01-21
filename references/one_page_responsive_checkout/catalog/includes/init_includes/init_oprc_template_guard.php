<?php
/**
 * Ensure the global template object is instantiated before language loading occurs.
 *
 * @package initSystem
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

if (!isset($template) || !is_object($template)) {
    if (!class_exists('template_func')) {
        $templateClassPath = DIR_WS_CLASSES . 'template_func.php';
        if (!file_exists($templateClassPath)) {
            return;
        }
        require_once $templateClassPath;
    }

    $template = new template_func();
}
