<?php
require 'includes/application_top.php';

require DIR_WS_MODULES . 'payment/paypal/pprAutoload.php';

$op = $_GET['op'] ?? '';
if ($op === 'return') {
    require DIR_WS_MODULES . 'payment/paypal/PayPalRestfulApi.php';

    $enable_debug = true;
    $ppr = new PayPalRestfulApi('Sandbox', 'Aanp2cAgmLRbVOgxbjra_ua5MgTTMfKbbHzXyjfY_eP-3hERiQDrVe1gGpzbKchdnKxcRX_AtFAPE4ot', 'EF_NnoOjN46yhkbjwb3D3kcQHuDbIHC_3r7xxVSmpCboyi_CBLzrq2i-G39w_PxDwtEY4OHYdYWjhYs8', $enable_debug);
    
    echo 'In webhook, processing return.<br>';
    $capture = $ppr->captureOrder($_GET['token']);
    echo nl2br(json_encode($capture, JSON_PRETTY_PRINT));
}

require DIR_WS_INCLUDES . 'application_bottom.php';
