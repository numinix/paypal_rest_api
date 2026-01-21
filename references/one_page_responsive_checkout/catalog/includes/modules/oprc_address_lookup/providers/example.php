<?php
class OPRC_Address_Lookup_Example_Provider extends OPRC_Address_Lookup_AbstractProvider
{
    public function lookup($postalCode, array $context = [])
    {
        $postalCode = strtoupper(trim($postalCode));
        if ($postalCode === '') {
            return [];
        }

        $city = isset($context['city']) ? $context['city'] : 'Sample City';
        $state = isset($context['state']) ? $context['state'] : '';
        $country = isset($context['zone_country_id']) ? $context['zone_country_id'] : '';

        $addresses = [];
        $addresses[] = [
            'label' => sprintf('123 Example Street, %s %s %s', $city, $state, $postalCode),
            'fields' => [
                'street_address' => '123 Example Street',
                'city' => $city,
                'state' => $state,
                'postcode' => $postalCode,
                'zone_country_id' => $country
            ]
        ];

        $addresses[] = [
            'label' => sprintf('Unit 5, 98 Sample Road, %s %s %s', $city, $state, $postalCode),
            'fields' => [
                'street_address' => 'Unit 5, 98 Sample Road',
                'city' => $city,
                'state' => $state,
                'postcode' => $postalCode,
                'zone_country_id' => $country
            ]
        ];

        return $addresses;
    }
}

return [
    'key' => 'example',
    'title' => 'Example Local Provider',
    'class' => 'OPRC_Address_Lookup_Example_Provider',
    'file' => __FILE__
];
// eof
