<?php
if (defined('IS_ADMIN_FLAG') && IS_ADMIN_FLAG === true) {
    return;
}

if (!isset($template) || !is_object($template)) {
    return;
}

if (!function_exists('oprc_address_lookup_manager')) {
    return;
}

$manager = oprc_address_lookup_manager();
if (!is_object($manager) || !method_exists($manager, 'isEnabled') || !$manager->isEnabled()) {
    return;
}

if (!method_exists($manager, 'getProviderKey') || $manager->getProviderKey() !== 'getaddress') {
    return;
}

$scriptPath = $template->get_template_dir(
    'jquery.getAddress-3.0.4.min.js',
    DIR_WS_TEMPLATE,
    $current_page_base,
    'jscript/jquery'
);
if ($scriptPath === '') {
    return;
}

$scriptPath .= '/jquery.getAddress-3.0.4.min.js';
$charset = defined('CHARSET') ? CHARSET : 'UTF-8';
$scriptSrc = htmlspecialchars($scriptPath, ENT_QUOTES, $charset);
?>
<script src="<?php echo $scriptSrc; ?>" defer></script>
