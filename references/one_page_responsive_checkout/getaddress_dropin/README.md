# GetAddress.io Address Lookup Drop-in

This drop-in adds a [GetAddress.io](https://getaddress.io/) provider for the One Page Responsive Checkout address lookup framework. It implements the provider contract used by the checkout plugin so customers can search for addresses by postal code and automatically populate the checkout form.

## Contents

```
catalog/
  includes/
    extra_datafiles/
      oprc_getaddress.php       # Placeholder configuration file
    modules/
      oprc_address_lookup/
        providers/
          getaddress.php        # Provider implementation
```

## Installation

1. Copy the files from this directory into the corresponding locations in your Zen Cart store. Preserve the directory structure so the provider and configuration file land inside `includes/modules/oprc_address_lookup/providers/` and `includes/extra_datafiles/` respectively.
2. Configure your GetAddress.io API key via **Admin > Configuration > One Page Responsive Checkout > Advanced > Address Lookup API Key**. Alternatively, define `OPRC_ADDRESS_LOOKUP_API_KEY` (or the legacy `OPRC_GETADDRESS_API_KEY`) in an `includes/extra_datafiles` override such as `oprc_getaddress.php`. **Do not commit the real key to version control.**
3. (Optional) If you need to use a non-default endpoint (for example, when proxying requests), update the `OPRC_GETADDRESS_ENDPOINT` constant in `includes/extra_datafiles/oprc_getaddress.php`.
4. Ensure `OPRC_ADDRESS_LOOKUP_PROVIDER` is set to `getaddress` (this is now the default value). You can adjust the provider via the same Advanced configuration tab or in an override file.
5. Clear any caches if required by your setup.

Once configured, the checkout registration form will display the address lookup button. Enter a postal code, choose one of the returned suggestions, and the provider will populate the address fields automatically.

### Pagination

By default the drop-in fetches up to 100 addresses per page and keeps requesting additional pages until the service reports that no more results are available. You can override the behaviour by defining `per_page` or `max_pages` in the provider configuration array (for example via `includes/extra_datafiles/oprc_getaddress.php`). `per_page` is clamped between 1 and 100 to satisfy the GetAddress.io API limits, while `max_pages` can be set to a positive integer when you need to cap the number of requests (a value of `0` leaves pagination uncapped).

## Error handling

If the GetAddress.io API returns an error the response message is surfaced in the checkout UI. Verify that:

- The API key is valid and has sufficient lookup credits.
- The store's server can reach `api.getaddress.io` over HTTPS.
- The postal code exists for the selected country.

## Updating the API key

To rotate the API key, update the value stored in the **Address Lookup API Key** configuration field (or adjust the `OPRC_ADDRESS_LOOKUP_API_KEY` constant in your override file) and clear any opcode caches. The change takes effect immediately—no additional deployment steps are required. The provider also continues to honour `OPRC_GETADDRESS_API_KEY` for backward compatibility.
