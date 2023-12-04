<?php
/**
 * A class to create an order-update request payload for the PayPalRestful (paypalr) Payment Module
 *
 * @copyright Copyright 2023 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: lat9 2023 Nov 16 Modified in v2.0.0 $
 */
namespace PayPalRestful\Zc2Pp;

use PayPalRestful\Api\PayPalRestfulApi as PayPalRestfulApi;
use PayPalRestful\Common\ErrorInfo;
use PayPalRestful\Common\Helpers;
use PayPalRestful\Common\Logger;

class UpdatePayPalOrderRequest extends ErrorInfo
{
    /**
     * Debug interface, shared with the PayPalRestfulApi class.
     */
    protected $log; //- An instance of the Logger class, logs debug tracing information.

    /**
     * The request to be submitted to a v2/orders/{id} PayPal endpoint.
     */
    protected $request;

    /**
     * Array, used by the getOrderDifference method, that identifies what operations
     * can be performed on various fields of the order's requested data.  All
     * 'keys' are part of the 'purchase_units' array of that request-data!
     *
     * The 'key' values can be a pseudo-encoding of the an array structure
     * present in the 'purchase_units' array.  For example, the 'shipping.name'
     * key represents the shipping array's sub-element 'name'.
     */
    protected $orderUpdateOperations = [
        'custom_id' => 'replace, add, remove',
        'description' => 'replace, add, remove',
        'shipping.name' => 'replace, add',
        'shipping.address' => 'replace, add',
        'shipping.type' => 'replace, add',
        'soft_descriptor' => 'replace, remove',
        'amount' => 'replace',
        'items' => 'replace, add, remove',
        'invoice_id' => 'replace, add, remove',
    ];

    // -----
    // Constructor.  Creates the payload for a PayPal order-update request.
    //
    public function __construct(array $order_request_update)
    {
        $this->request = [];

        $this->log = new Logger();
        $this->log->write('UpdatePayPalOrderRequest::__construct starts ...');

        if (!isset($_SESSION['PayPalRestful']['Order'])) {
            $this->setError('Order must be created before update', 'no_order');
            return;
        }

        $current_order = $_SESSION['PayPalRestful']['Order'];
        $status = $current_order['status'];
        if ($status !== PayPalRestfulApi::STATUS_CREATED && $status !== PayPalRestfulApi::STATUS_APPROVED) {
            $error_name = ($status === PayPalRestfulApi::STATUS_PAYER_ACTION_REQUIRED) ? 'recreate' : 'status_restriction';
            $this->setError("Can't update, due to order status restriction: '{$current_order['status']}'.", $error_name);
            return;
        }

        $updates = $this->getOrderDifference($order_request_update);
        if (count($updates) === 0) {
            $message = 'Nothing to update:';
        } elseif ($updates[0] !== 'error') {
            $message = 'Updates to order:';
            foreach ($updates as $next_update) {
                $this->request[] = [
                    'op' => $next_update['op'],
                    'path' => "/purchase_units/@reference_id=='default'/{$next_update['path']}",
                    'value' => $next_update['value'],
                ];
            }
        }
        $this->log->write("UpdatePayPalOrderRequest::__construct finished, $message\n" . Logger::logJSON($this->request));
    }

    public function get()
    {
        return $this->request;
    }

    protected function getOrderDifference(array $update): array
    {
        // -----
        // The most recent/current order request sent to PayPal is stashed in
        // the session.
        //
        $current = $_SESSION['PayPalRestful']['Order']['current'];

        // -----
        // Determine *all* differences between a current update and the
        // current PayPal order.  If no differences, return an empty array.
        //
        $purchase_unit_current = $current['purchase_units'][0];
        $purchase_unit_update = $update['purchase_units'][0];
        $order_difference = Helpers::arrayDiffRecursive($purchase_unit_update, $purchase_unit_current);
        if (count($order_difference) === 0) {
            return [];
        }

        $difference = [];
        foreach ($this->orderUpdateOperations as $key => $update_options) {
            $subkey = '';
            if (strpos($key, '.') !== false) {
                [$key, $subkey] = explode('.', $key);
            }

            // -----
            // Remove this valid-to-update key{/subkey} element from the overall orders'
            // differences.  If any differences remain after this loop's processing, then
            // there are updates to the order that are disallowed by PayPal.
            //
            if ($subkey !== '') {
                unset($order_difference[$key][$subkey]);
            } else {
                unset($order_difference[$key]);
            }

            // -----
            // Determine the presence of the current key{/subkey} in the current/to-be-updated
            // order elements.
            //
            $key_subkey_current = $this->issetKeySubkey($key, $subkey, $purchase_unit_current);
            $key_subkey_update = $this->issetKeySubkey($key, $subkey, $purchase_unit_update);

            // -----
            // Is the field *not* present in either the currently-recorded order
            // at PayPal or in the update, nothing further to do for this key/subkey.
            //
            if ($key_subkey_current === false && $key_subkey_update === false) {
                continue;
            }

            // -----
            // Initially, nothing to do for this key/subkey element.
            //
            $op = '';

            // -----
            // If the field is present in both the current and to-be-updated order, check
            // to see if the field's changed.
            //
            if ($key_subkey_current === true && $key_subkey_update === true) {
                if ($subkey !== '') {
                    if ($purchase_unit_current[$key][$subkey] !== $purchase_unit_update[$key][$subkey]) {
                        $op = 'replace';
                        $path = "$key/$subkey";
                        $value = $purchase_unit_update[$key][$subkey];
                    }
                } elseif ($purchase_unit_current[$key] !== $purchase_unit_update[$key]) {
                        $op = 'replace';
                        $path = $key;
                        $value = $purchase_unit_update[$key];
                    }
            // -----
            // Is the field added to the order for an update?
            //
            } elseif ($key_subkey_update === true) {
                $op = 'add';
                if ($subkey !== '') {
                    $path = "$key/$subkey";
                    $value = $purchase_unit_update[$key][$subkey];
                } else {
                    $path = $key;
                    $value = $purchase_unit_update[$key];
                }
            // -----
            // Otherwise, the field was removed from the to-be-updated order.
            //
            } else {
                $op = 'remove';
                if ($subkey !== '') {
                    $path = "$key/$subkey";
                    $value = $purchase_unit_current[$key][$subkey];
                } else {
                    $path = $key;
                    $value = $purchase_unit_current[$key];
                }
            }

            // -----
            // If no change to the current key/subkey was found, continue on
            // to the next key/subkey check.
            //
            if ($op === '') {
                continue;
            }

            // -----
            // The current key/subkey was changed in some manner, make sure that
            // the operation is allowed by PayPal.  If not, return a 'difference'
            // that indicates that the update cannot be applied.
            //
            if (strpos($update_options, $op) === false) {
                $this->setError("  --> $key/$subkey operation '$op' is not supported", 'recreate');
                return ['error'];
            }

            // -----
            // The current key/subkey was changed and it's allowed, note the difference
            // in the to-be-returned difference array.
            //
            $difference[] = [
                'op' => $op,
                'path' => $path,
                'value' => $value,
            ];
        }

        // -----
        // A special case for the order's 'shipping' component.  Since each of the
        // individual elements can be separately updated, it's possible that an empty
        // 'shipping element remains in the differences.  If that's the case, remove
        // if prior to the order-differences' check below.
        //
        if (empty($order_difference['shipping'])) {
            unset($order_difference['shipping']);
        }

        // -----
        // If any elements remain in the orders' overall difference array, then those
        // elements aren't valid-to-update by PayPal.  Note the condition in the PayPal
        // log and the errorInfo; return a 'difference' that indicates that the update
        // cannot be applied.
        //
        if (count($order_difference) !== 0) {
            $this->setError("--> Update disallowed, changed parameters cannot be updated:\n" . Logger::logJSON($order_difference), 'recreate');
            return ['error'];
        }

        return $difference;
    }

    protected function issetKeySubkey(string $key, string $subkey, array $array1): bool
    {
        return ($subkey !== '') ? isset($array1[$key][$subkey]) : isset($array1[$key]);
    }

    protected function setError(string $errMsg, string $error_name)
    {
        $this->setErrorInfo(400, $errMsg, 0, [['name' => $error_name]]);
        $this->request = ['error' => $error_name];
        $this->log->write("UpdatePayPalOrderRequest finished with error: $errMsg; error_name ($error_name)", true, 'after');
    }
}
