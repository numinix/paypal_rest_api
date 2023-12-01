### `paypal` Table Usage and Changes



| Field Name                  | Changes | Usage                                                        |
| --------------------------- | :-----: | ------------------------------------------------------------ |
| paypal_ipn_id               | &mdash; | n/c                                                          |
| order_id                    | &mdash; | n/c                                                          |
| txn_mode                    | &mdash; | Set to the 'intent' associated with the entry.               |
| module_name                 | &mdash; | Set to 'paypalr'                                             |
| module_mode                 | &mdash; | Currently unused                                             |
| reason_code                 | &mdash; | Currently unused                                             |
| payment_type                | &mdash; | `['payment_source']`, first key, e.g. `paypal`.              |
| payment_status              | &mdash; | `['status']`                                                 |
| pending_reason              | &mdash; | Currently unused                                             |
| invoice                     | &mdash; | To-be-assigned                                               |
| mc_currency                 | &mdash; | `['amount']['currency_code']`                                |
| first_name                  | &mdash; | `['payer']['name']['given_name']`                            |
| last_name                   | &mdash; | `['payer']['name']['surname']`                               |
| payer_business_name         | &mdash; | Currently unused                                             |
| address_name<sup>1</sup>    | &mdash; | `['shipping']['name']['full_name']`                          |
| address_street<sup>1</sup>  | &mdash; | `['shipping']['address']['address_line_1']`, with `['shipping']['address']['address_line_2']`, if provided. |
| address_city<sup>1</sup>    | &mdash; | `['shipping']['address']['admin_area_2']`                    |
| address_state<sup>1</sup>   | &mdash; | `['shipping']['address']['admin_area_1']`                    |
| address_zip<sup>1</sup>     | &mdash; | `['shipping']['address']['postal_code']`                     |
| address_country<sup>1</sup> | &mdash; | `['shipping']['address']['country_code']`                    |
| address_status<sup>1</sup>  | &mdash; | To-be-assigned                                               |
| payer_email                 | &mdash; | `['payer']['email_address']`                                 |
| payer_id                    | &mdash; | `['payer']['payer_id']`                                      |
| payer_status                | &mdash; | `['payment_source'][$type]['account_status']`                |
| payment_date                | &mdash; | `['create_time']`, converted using `convertToLocalTimeZone`. |
| business                    | &mdash; | Currently unused                                             |
| receiver_email              | &mdash; | `['payee']['email_address']`                                 |
| receiver_id                 | &mdash; | `['payee']['merchant_id']`                                   |
| txn_id                      | &mdash; | `['id']`                                                     |
| parent_txn_id               | &mdash; | An empty string for the original order, otherwise the `txn_id` of the previous transaction. |
| num_cart_items              | &mdash; | The number of items in the cart when the original order was placed. |
| mc_gross                    | &mdash; |                                                              |

​	
