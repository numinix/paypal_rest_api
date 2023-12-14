<?php
/**
 * A class to 'convert' a Zen Cart order to a PayPal order-creation request payload
 * for the PayPalRestful (paypalr) Payment Module
 *
 * @copyright Copyright 2023 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: lat9 2023 Nov 16 Modified in v2.0.0 $
 */
namespace PayPalRestful\Zc2Pp;

use PayPalRestful\Common\ErrorInfo;
use PayPalRestful\Common\Logger;
use PayPalRestful\Zc2Pp\Address;
use PayPalRestful\Zc2Pp\Amount;
use PayPalRestful\Zc2Pp\Name;

// -----
// Create a PayPal order request, as documented here: https://developer.paypal.com/docs/api/orders/v2/#orders_create
//
class CreatePayPalOrderRequest extends ErrorInfo
{
    /**
     * Debug interface, shared with the PayPalRestfulApi class.
     */
    protected Logger $log; //- An instance of the Logger class, logs debug tracing information.

    /**
     * Local "Amount" class; it's got the to-be-used currency for the PayPal order
     * stashed in a static variable!
     */
    protected Amount $amount;
    
    /**
     * The currency-code in which the PayPal order is to be 'built'.
     */
    protected string $paypalCurrencyCode;

    /**
     * The request to be submitted to a v2/orders/create PayPal endpoint.
     */
    protected array $request;

    /**
     * The items' pricing 'breakdown' elements, gathered by getItems and
     * and subsequently ussed by getOrderTotals.
     */
    protected array $itemBreakdown = [
        'handling' => 0,        //- aka one-time charges
        'item_total' => 0,
        'item_tax_total' => 0,
        'all_products_virtual' => true,
    ];

    // -----
    // Constructor.  "Converts" a Zen Cart order into an PayPal /orders/create object.
    //
    public function __construct(string $ppr_type, \order $order, array $cc_info)
    {
        $this->log = new Logger();

        global $currencies;
        $this->amount = new Amount($order->info['currency']);
        $this->paypalCurrencyCode = $this->amount->getDefaultCurrencyCode();

        $this->log->write("CreatePayPalOrderRequest::__construct($ppr_type, ...) starts ...");

        $this->request = [
            'intent' => (MODULE_PAYMENT_PAYPALR_TRANSACTION_MODE === 'Final Sale') ? 'CAPTURE' : 'AUTHORIZE',
            'purchase_units' => [
                [
                    'invoice_id' =>
                        'PPR-' .
                        date('YmdHis') . '-' .
                        $_SESSION['customer_id'] . '-' .
                        substr($_SESSION['customer_first_name'], 0, 3) . substr($_SESSION['customer_last_name'], 0, 3) . '-' .
                        bin2hex(random_bytes(4)),
                ],
            ],
        ];
        $this->request['purchase_units'][0]['items'] = $this->getItems($order->products);
        $this->request['purchase_units'][0]['amount'] = $this->getOrderAmountAndBreakdown($order);

        // -----
        // The 'shipping' element is included *only if* the order's got one or more
        // physical items to be shipped.
        //
        if ($this->itemBreakdown['all_products_virtual'] === false) {
            $this->request['purchase_units'][0]['shipping'] = $this->getShipping($order);
        }

        if ($this->countItems() === 0) {
            unset($this->request['purchase_units'][0]['items']);
        }

        // -----
        // If this is a request to pay for the order using a credit card, add
        // the 'card' payment source to the order-creation request.  Note that
        // without a 'payment_source', the source defaults to 'paypal'.
        //
        if ($ppr_type === 'card') {
            $this->request['payment_source']['card'] = $this->buildCardPaymentSource($order, $cc_info);
        }

        $this->log->write("\nCreatePayPalOrderRequest::__construct($ppr_type, ...) finished, request:\n" . Logger::logJSON($this->request));
    }

    public function get()
    {
        return $this->request;
    }

    protected function getItems(array $order_products): array
    {
        $item_errors = false;
        $items = [];
        foreach ($order_products as $next_product) {
            // -----
            // Grab the product's 'id' and 'name', for use in any message logs that might arise.
            //
            $products_id = $next_product['id'];
            $name = $next_product['name'];

            // -----
            // PayPal supports *only* integer-quanties in the order's item list,
            // so if any quantity is not an integer value, the items' array
            // can't be included in the PayPal order request.  Noting that this
            // will be an issue for sites that sell fabric or cheeses, for instance.
            //
            $quantity = (string)$next_product['qty'];
            if (ctype_digit($quantity) === false) {
                $item_errors = true;
                $this->log->write("!**-> getItems: Product #$products_id ($name) has a non-integer quantity ($quantity); item details cannot be included.");
                continue;
            }
            
            // -----
            // For the item list to be included, all items must have names that are at least
            // 1-character long.
            //
            if ($name === '') {
                $item_errors = true;
                $this->log->write("!**-> getItems: Product #$products_id ($name) is empty; item details cannot be included.");
                continue;
            }

            // -----
            // Rather than dealing with divide-by-zero issues if there's no tax, since the
            // tax is represented as a percent, e.g. '5.75' for a 5.75% tax, simply multiply
            // the tax value by 1/100 (0.01) to arrive at the percentage.
            //
            $tax_rate = $next_product['tax'] * 0.01;
            $products_price = $this->getRateConvertedValue($next_product['final_price']);
            $product_is_physical = ($next_product['products_virtual'] !== 1);
            $item = [
                'name' => substr($name, 0, 127),
                'quantity' => $quantity,
                'category' => ($product_is_physical === true) ? 'PHYSICAL_GOODS' : 'DIGITAL_GOODS',
                'unit_amount' => $this->amount->setValue($products_price),
                'tax' => $this->amount->setValue($products_price * $tax_rate),
            ];

            // -----
            // If the product is physical, indicate as such for use by the getShipping method.
            //
            if ($product_is_physical === true) {
                $this->itemBreakdown['all_products_virtual'] = false;
            }

            // -----
            // Unfortunately, PayPal has no concept of one-time charges for a product.  They'll be
            // summed up and will be noted in the PayPal order as a 'handling fee'.
            //
            $this->itemBreakdown['handling'] += $next_product['onetime_charges'] * $tax_rate;

            // -----
            // Add the current item to the items' array.
            //
            $items[] = $item;
        }

        return ($item_errors === true) ? [] : $items;
    }

    protected function getOrderAmountAndBreakdown($order): array
    {
        $amount = $this->setRateConvertedValue($order->info['total']);
        if ($this->countItems() === 0) {
            return $amount;
        }

        $item_total = 0;
        $item_tax_total = 0;
        $handling_total = $this->itemBreakdown['handling'];
        $insurance_total = 0;
        $shipping_discount_total = 0;
        $discount_total = 0;
        foreach ($this->request['purchase_units'][0]['items'] as $next_item) {
            $item_total += $next_item['quantity'] * $next_item['unit_amount']['value'];
            $item_tax_total += $next_item['quantity'] * $next_item['tax']['value'];
        }
        $breakdown = [
            'item_total' => $this->amount->setValue($item_total),
            'shipping' => $this->setRateConvertedValue($order->info['shipping_cost'] + $order->info['shipping_tax']),
            'tax_total' => $this->amount->setValue($item_tax_total),
        ];

        if ($handling_total > 0) {
            $breakdown['handling'] = $this->setRateConvertedValue($handling_total);
        }
        if ($insurance_total > 0) {
            $breakdown['insurance'] = $this->setRateConvertedValue($insurance_total);
        }
        if ($shipping_discount_total > 0) {
            $breakdown['shipping_discount'] = $setRateConvertedValue($shipping_discount_total);
        }
        if ($discount_total > 0) {
            $breakdown['discount'] = $this->setRateConvertedValue($discount_total);
        }
        $amount['breakdown'] = $breakdown;
        return $amount;
    }

    protected function setRateConvertedValue($value)
    {
        return $this->amount->setValue($this->getRateConvertedValue($value));
    }

    // -----
    // Gets the shipping element of a to-be-created order.  Note that this method
    // is not called (!) when the order's virtual!
    //
    protected function getShipping($order): array
    {
        global $order;

        $is_storepickup = (strpos($order->info['shipping_module_code'], 'storepickup') === 0);

        return [
            'type' => ($is_storepickup === true) ? 'PICKUP_IN_PERSON' : 'SHIPPING',
            'name' => Name::get($order->delivery),
            'address' => Address::get($order->delivery),
        ];
    }

    protected function countItems()
    {
        return count($this->request['purchase_units'][0]['items']);
    }

    protected function getRateConvertedValue($value)
    {
        global $currencies;

        return number_format((float)$currencies->rateAdjusted($value, true, $this->paypalCurrencyCode), 2, '.', '');
    }

    protected function buildCardPaymentSource(\order $order, array $cc_info): array
    {
        return [
            'name' => $cc_info['name'],
            'number' => $cc_info['number'],
            'security_code' => $cc_info['security_code'],
            'expiry' => $cc_info['expiry_year'] . '-' . $cc_info['expiry_month'],
            'billing_address' => Address::get($order->billing),
            'experience_context' => [
                'return_url' => $cc_info['webhook'] . '?op=3ds_challenge_return',
                'cancel_url' => $cc_info['webhook'] . '?op=3ds_challenge_cancel',
            ],
        ];
    }
}
