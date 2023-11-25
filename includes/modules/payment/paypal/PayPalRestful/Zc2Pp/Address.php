<?php
/**
 * A ZenCart-to-PayPal conversion class for Address types used by the PayPalRestful (paypalr) Payment Module
 *
 * @copyright Copyright 2023 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: lat9 2023 Nov 16 Modified in v2.0.0 $
 */
namespace PayPalRestful\Zc2Pp;

use PayPalRestful\Api\Data\CountryCodes;

class Address
{
    public static function get(array $order_address): array
    {
        return [
            'address_line_1' => $order_address['street_address'],
            'address_line_2' => $order_address['suburb'],
            'admin_area_2' => $order_address['city'],
            'admin_area_1' => (!empty($order_address['state_code'])) ? $order_address['state_code'] : $order_address['state'],
            'postal_code' => $order_address['postcode'],
            'country_code' => CountryCodes::convertCountryCode($order_address['country']['iso_code_2']),
        ];
    }
}
