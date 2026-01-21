<?php
$quotes = isset($quotes) && is_array($quotes) ? $quotes : [];
$shippingModules = isset($shipping_modules) ? $shipping_modules : null;

if (!function_exists('oprc_prepare_delivery_updates_for_quotes')) {
    return;
}

$deliveryData = oprc_prepare_delivery_updates_for_quotes($quotes, $shippingModules);
$renderedUpdates = isset($deliveryData['rendered_updates']) && is_array($deliveryData['rendered_updates'])
    ? $deliveryData['rendered_updates']
    : [];

foreach ($renderedUpdates as $identifier => $html) {
    echo '<div class="oprc-estimate" data-id="' . htmlspecialchars($identifier, ENT_QUOTES, 'UTF-8') . '">';
    echo $html;
    echo '</div>';
}
