# Address Lookup Framework

The One Page Responsive Checkout now includes an optional address lookup framework that can be enabled for the registration step. The feature adds a button next to the postal/zip code field; clicking the button sends the postal code to the configured provider and displays the returned address suggestions. Selecting a suggestion populates the registration form automatically.

When the built-in GetAddress drop-in is installed the checkout now serves the bundled `jquery.getAddress-3.0.4.min.js` script locally. This removes the dependency on the unreliable `getaddress-cdn.azureedge.net` host and prevents the checkout JavaScript from stalling while the browser waits for that CDN to timeout.

## Enabling the feature

1. Configure the provider via **Admin > Configuration > One Page Responsive Checkout > Advanced**. Set **Address Lookup Provider** to the desired provider key (`getaddress` is the default) and supply any required credentials in **Address Lookup API Key**. Zen Cart exposes each configuration entry as a constant automatically—defining `OPRC_ADDRESS_LOOKUP_PROVIDER` or `OPRC_ADDRESS_LOOKUP_API_KEY` in code will override the values you set in the dashboard. Create an `includes/extra_datafiles` override only if you explicitly want to manage those settings in version control.
2. Clear template caches if required. When the provider is enabled the lookup button and result panel appear automatically on the checkout registration form.

If `OPRC_ADDRESS_LOOKUP_PROVIDER` is left blank the lookup UI remains hidden and the form behaves as before.

## Creating a provider

Providers encapsulate the logic needed to call an external address lookup service. You can add providers without modifying core plugin files by dropping a PHP file inside `includes/modules/oprc_address_lookup/providers/`. The file must return an array describing the provider and optionally define its class implementation. The array keys are:

- `key` (string, required): unique identifier used by `OPRC_ADDRESS_LOOKUP_PROVIDER`.
- `title` (string, optional): a human readable title shown with the results list.
- `class` (string, optional): the class to instantiate. The class should implement `OPRC_Address_Lookup_Provider` (or extend `OPRC_Address_Lookup_AbstractProvider`).
- `file` (string, optional): absolute path to the file containing the provider class. Defaults to the definition file itself.
- `factory` (callable, optional): callback that returns an instance of the provider. Use this when construction requires additional setup.

### Provider skeleton

```php
<?php
use GuzzleHttp\\Client; // example dependency

class MyCompany_AddressLookup_Provider extends OPRC_Address_Lookup_AbstractProvider
{
    public function lookup($postalCode, array $context = [])
    {
        $client = new Client();
        $response = $client->get('https://example.test/lookup', [
            'query' => [
                'postal_code' => $postalCode,
                'country' => isset($context['zone_country_id']) ? $context['zone_country_id'] : null,
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true);

        $suggestions = [];
        foreach ($data['addresses'] as $address) {
            $suggestions[] = [
                'label' => $address['formatted'],
                'fields' => [
                    'street_address' => $address['line1'],
                    'suburb' => $address['line2'],
                    'city' => $address['city'],
                    'state' => $address['state'],
                    'postcode' => $address['postal_code'],
                    'zone_country_id' => $address['country_id'],
                ],
            ];
        }

        return $suggestions;
    }
}

return [
    'key' => 'mycompany',
    'title' => 'MyCompany Lookup',
    'class' => 'MyCompany_AddressLookup_Provider',
    'file' => __FILE__,
];
```

Each suggestion must return a `label` (displayed to the customer) and a `fields` array keyed by the Zen Cart form field names (`street_address`, `city`, `state`, `postcode`, `zone_id`, `zone_country_id`, etc.). When a customer selects a suggestion the framework fills in each matching field and triggers the corresponding change events.

### Registering providers programmatically

Alternatively, providers can be registered from any bootstrap file (for example `includes/extra_configures/your_module.php`) using `oprc_register_address_lookup_provider()`:

```php
oprc_register_address_lookup_provider('remote_api', [
    'title' => 'Remote API',
    'factory' => function(array $definition) {
        return new RemoteApi_AddressLookup_Provider($definition);
    },
]);
```

Use this approach when your module needs to compute paths dynamically or when you prefer to keep provider classes outside the default directory.

## AJAX endpoint

Client-side requests are handled by `ajax/oprc_address_lookup.php`. The endpoint expects:

- `postal_code` (string, required)
- `context` (array, optional): additional form values (country, state, etc.) supplied automatically by the front-end.

The endpoint responds with JSON containing:

```json
{
  "success": true,
  "addresses": [
    {
      "label": "123 Sample Street, City, 99999",
      "fields": { "street_address": "123 Sample Street", "postcode": "99999" }
    }
  ],
  "provider": { "key": "example", "title": "Example Local Provider" }
}
```

If the request fails `success` is `false` and `message` explains the failure.

## Front-end behaviour

- The lookup button only renders when a provider is configured.
- Results are limited to the first `OPRC_ADDRESS_LOOKUP_MAX_RESULTS` matches (default: 10).
- Selecting a suggestion fills the registration form and displays a confirmation message beneath the postal code field.

This framework allows you to integrate any third-party address lookup service by adding a provider class without touching the core plugin files.
