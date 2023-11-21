<?php
/*
See https://developer.paypal.com/api/nvp-soap/set-express-checkout-nvp/ for NVP operations
*/
require 'includes/application_top.php';

require DIR_WS_MODULES . 'payment/paypal/PayPalRestfulApi.php';

$enable_debug = true;
$ppr = new PayPalRestfulApi('Sandbox', 'Aanp2cAgmLRbVOgxbjra_ua5MgTTMfKbbHzXyjfY_eP-3hERiQDrVe1gGpzbKchdnKxcRX_AtFAPE4ot', 'EF_NnoOjN46yhkbjwb3D3kcQHuDbIHC_3r7xxVSmpCboyi_CBLzrq2i-G39w_PxDwtEY4OHYdYWjhYs8', $enable_debug);

echo 'Instantiated ...<br>';

//$valid_credentials = $ppr->validatePayPalCredentials('Aanp2cAgmLRbVOgxbjra_ua5MgTTMfKbbHzXyjfY_eP-3hERiQDrVe1gGpzbKchdnKxcRX_AtFAPE4ot', 'EF_NnoOjN46yhkbjwb3D3kcQHuDbIHC_3r7xxVSmpCboyi_CBLzrq2i-G39w_PxDwtEY4OHYdYWjhYs8');

//echo 'The credentials are ' . (($valid_credentials === false) ? 'not ' : '') . 'valid.';

$order_request = [
    'intent' => 'CAPTURE',
    'purchase_units' => [
        [
            'amount' => [
                'currency_code' => 'USD',
                'value' => '10.00',
            ],
        ],
    ],
];
$order_request_update = [
    'intent' => 'CAPTURE',
    'purchase_units' => [
        [
            'amount' => [
                'currency_code' => 'USD',
                'value' => '15.00',
            ],
        ],
    ],
];
echo json_encode($ppr->orderDiffRecursive($order_request['purchase_units'][0], $order_request_update['purchase_units'][0]), JSON_PRETTY_PRINT) . '<br><br>';

$response = $ppr->createOrder($order_request);

if ($response === false) {
    echo 'createOrder failed: ' . json_encode($ppr->getErrorInfo(), JSON_PRETTY_PRINT);
} else {
    $paypal_id = $response['id'];
    $paypal_order_status = $response['status'];
    echo "createOrder successful.  id = $paypal_id, status = $paypal_order_status<br>";
    $order_status = $ppr->getOrderStatus($paypal_id);
    if ($response === false) {
        echo 'getOrderStatus failed. ' . json_encode($ppr->getErrorInfo(), JSON_PRETTY_PRINT);
    } else {
        echo 'getOrderStatus successful.<br>';
        $confirm_source = $ppr->confirmPaymentSource($paypal_id,
            [
                'paypal' => [
                    'name' => [
                        'given_name' => 'John',
                        'surname' => 'Doe',
                    ],
                    'email_address' => 'sb-4i47ph18250301@personal.example.com',
                    'experience_context' => [
                        "payment_method_preference" => "IMMEDIATE_PAYMENT_REQUIRED",
                        "brand_name" => "EXAMPLE INC",
                        "locale" => "en-US",
                        "landing_page" => "NO_PREFERENCE",  //- LOGIN, GUEST_CHECKOUT or NO_PREFERENCE
                        "shipping_preference" => "SET_PROVIDED_ADDRESS",
                        "user_action" => "CONTINUE",  //- PAY_NOW or CONTINUE
                        "return_url" => HTTP_SERVER . DIR_WS_CATALOG . 'ppr_webhook_main.php?op=return',
                        "cancel_url" => HTTP_SERVER . DIR_WS_CATALOG . 'ppr_webhook_main.php?op=cancel',
                    ],
                ],
            ]
        );
        echo json_encode($confirm_source, JSON_PRETTY_PRINT) . '<br>';
//        $order_update = $ppr->updateOrder($paypal_id, $order_request, $order_request_update);
//        if ($order_update === false) {
//            echo 'updateOrder failed. ' . json_encode($ppr->getErrorInfo(), JSON_PRETTY_PRINT);
//        } else {
 //           echo 'updateOrder successful.<br>' . json_encode($order_update, JSON_PRETTY_PRINT) . '<br>';
//            $order_update = $ppr->getOrderStatus($paypal_id);
 //           echo json_encode($order_update, JSON_PRETTY_PRINT) . '<br>';
        if ($confirm_source !== false) {

            $action_link = '';
            foreach ($confirm_source['links'] as $next_link) {
                if ($next_link['rel'] === 'payer-action') {
                    $action_link = $next_link['href'];
                    $approve_method = $next_link['method'];
                }
            }
            if ($action_link === '') {
                echo 'No payer-action link!';
            } else {
?>
<a href="<?php echo $action_link; ?>">PayPal</a>
<?php
//                $response = $ppr->sendToUrl($action_link);
//                echo "URL ($action_link) response:<br>" . json_encode($response, JSON_PRETTY_PRINT) . '<br>';

//                $order_capture = $ppr->captureOrder($paypal_id);
//                echo json_encode($order_capture, JSON_PRETTY_PRINT) . '<br>';
            }
//        }
//        }
        }
    }
}

//$ppr->close();

require DIR_WS_INCLUDES . 'application_bottom.php';

/*
curl -v -X POST "https://api-m.sandbox.paypal.com/v1/oauth2/token" -u "Aanp2cAgmLRbVOgxbjra_ua5MgTTMfKbbHzXyjfY_eP-3hERiQDrVe1gGpzbKchdnKxcRX_AtFAPE4ot:EF_NnoOjN46yhkbjwb3D3kcQHuDbIHC_3r7xxVSmpCboyi_CBLzrq2i-G39w_PxDwtEY4OHYdYWjhYs8" -H "Content-Type: application/x-www-form-urlencoded" -d "grant_type=client_credentials"
      
*/