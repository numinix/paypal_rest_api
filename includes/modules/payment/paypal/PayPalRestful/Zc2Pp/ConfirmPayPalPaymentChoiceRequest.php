<?php
/**
 * A class to create a payload to confirm the payment choice for the specified payment-type
 * for the PayPalRestful (paypalr) Payment Module
 *
 * @copyright Copyright 2023 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: lat9 2023 Nov 16 Modified in v2.0.0 $
 */
namespace PayPalRestful\Zc2Pp;

class ConfirmPayPalPaymentChoiceRequest
{
    /**
     * The request to be submitted to a v2/orders/{id}/confirm-payment-choice PayPal endpoint.
     */
    protected $request;

    // -----
    // Constructor.  Creates the payload for a PayPal payment-choice confirmation request.
    //
    public function __construct(string $webhook_name, \order $order)
    {
        // -----
        // Determine the site's payment-preferrence, one of:
        //
        // - UNRESTRICTED ................. Any type of payment (including eChecks).
        // - IMMEDIATE_PAYMENT_REQUIRED ... Accepts only immediate payment from the customer.
        //     For example, credit card, PayPal balance, or instant ACH. Ensures that at the time of capture,
        //     the payment does not have the PENDING status.
        //
        $payment_preference = (MODULE_PAYMENT_PAYPALR_ALLOWEDPAYMENT === 'Any') ? 'UNRESTRICTED' : 'IMMEDIATE_PAYMENT_REQUIRED';

        // -----
        // Determine the shipping-preference, one of:
        //
        // - GET_FROM_FILE .......... The customer can choose one of their PayPal-registered addresses for the shipping.
        // - NO_SHIPPING ............ Indicates that the order is 'digital' (aka 'virtual') and no shipping is required.
        // - SET_PROVIDED_ADDRESS ... PayPal uses the address the customer has chosent, no modification is allowed.
        //
        $shipping_preference = ($order->content_type === 'virtual') ? 'NO_SHIPPING' : 'SET_PROVIDED_ADDRESS';

        // -----
        // The brand-name supplied to PayPal (appears on PayPal-sent invoices to the
        // customer) is either the configured value or the store's defined name.
        //
        $brand_name = (MODULE_PAYMENT_PAYPALR_BRANDNAME !== '') ? MODULE_PAYMENT_PAYPALR_BRANDNAME : STORE_NAME;

        $this->request = [
            'paypal' => [
                'name' => [
                    'given_name' => $order->billing['firstname'],
                    'surname' => $order->billing['lastname'],
                ],
                'email_address' => $order->customer['email_address'],
                'experience_context' => [
                    'payment_method_preference' => $payment_preference,
                    'brand_name' => $brand_name,
//                    'locale' => 'en-US',
                    'landing_page' => 'NO_PREFERENCE',  //- LOGIN, GUEST_CHECKOUT or NO_PREFERENCE
                    'shipping_preference' => $shipping_preference,    //- GET_FROM_FILE (allows shipping address change on PayPal), NO_SHIPPING, SET_PROVIDED_ADDRESS (customer can't change)
                    'user_action' => 'CONTINUE',  //- PAY_NOW or CONTINUE
                    'return_url' => $webhook_name . '?op=return',
                    'cancel_url' => $webhook_name . '?op=cancel',
                ],
            ],
        ];
    }

    public function get(): array
    {
        return $this->request;
    }
}
