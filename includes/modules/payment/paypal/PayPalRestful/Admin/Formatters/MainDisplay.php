<?php
/**
 * A class that provides the main PayPal request history table for a given order
 * in the Zen Cart admin placed with the PayPal Restful payment module.
 *
 * @copyright Copyright 2023 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: lat9 2023 Nov 16 Modified in v2.0.0 $
 */
namespace PayPalRestful\Admin\Formatters;

use PayPalRestful\Common\Logger;

class MainDisplay
{
    protected string $mainDisplay = '';
    
    protected array $settledFunds = [
        'currency' => '',
        'value' => 0,
        'fee' => 0,
        'exchange_rate' => 0,
    ];

    protected string $modals = '';

    protected static $tableFields = [
        ['name' => MODULE_PAYMENT_PAYPALR_TXN_TYPE, 'field' => 'txn_type', 'align' => 'center'],
        ['name' => MODULE_PAYMENT_PAYPALR_TXN_ID, 'field' => 'txn_id', 'align' => 'center'],
        ['name' => MODULE_PAYMENT_PAYPALR_PARENT_TXN_ID, 'field' => 'parent_txn_id', 'align' => 'center'],
        ['name' => MODULE_PAYMENT_PAYPALR_PAYMENT_TYPE, 'field' => 'payment_type'],
        ['name' => MODULE_PAYMENT_PAYPALR_NAME_EMAIL, 'field' => 'payer_email'],
        ['name' => MODULE_PAYMENT_PAYPALR_PAYER_ID, 'field' => 'payer_id', 'align' => 'center'],
        ['name' => MODULE_PAYMENT_PAYPALR_PAYER_STATUS, 'field' => 'payer_status', 'align' => 'center'],
        ['name' => MODULE_PAYMENT_PAYPALR_CURRENCY_HDR, 'field' => 'mc_currency', 'align' => 'center'],
        ['name' => MODULE_PAYMENT_PAYPALR_GROSS_AMOUNT, 'field' => 'mc_gross', 'align' => 'right'],
        ['name' => MODULE_PAYMENT_PAYPALR_PAYMENT_FEE, 'field' => 'payment_fee', 'align' => 'right'],
        ['name' => MODULE_PAYMENT_PAYPALR_EXCHANGE_RATE, 'field' => 'exchange_rate', 'align' => 'right'],
    ];

    public function __construct(array $paypal_db_txns, array $paypal_status_response)
    {
        $this->mainDisplay =
            "<hr>\n" .
            "<table class=\"table\">\n" .
            "  <tbody>\n";

        $this->mainDisplay .= $this->buildTableHeader();

        $this->mainDisplay .= $this->buildTableData($paypal_db_txns);

        $this->mainDisplay .=
            "  </tbody>\n" .
            "</table><hr>\n";

        $this->mainDisplay .= $this->modals;
    }

    public function get(): string
    {
        return $this->mainDisplay;
    }

    protected function buildTableHeader(): string
    {
        $header =
            "<tr class=\"dataTableHeadingRow\">\n";

        foreach (self::$tableFields as $next_field) {
            $align_class = (isset($next_field['align'])) ? " text-{$next_field['align']}" : '';
            $header .=
                "  <th class=\"dataTableHeadingContent$align_class\">" . rtrim($next_field['name'], ':') . "</th>\n";
        }

        $header .=
            '  <th class="dataTableHeadingContent text-right">' . MODULE_PAYMENT_PAYPALR_ACTION . "</th>\n";
            "</tr>\n";

        return $header;
    }

    protected function buildTableData(array $paypal_db_txns): string
    {
        $last_capture_index = false;
        $last_auth_index = false;
        $last_refund_index = false;

        $data = '';
        $txn_index = -1;
        foreach ($paypal_db_txns as $next_txn) {
            $txn_index++;

            $data .=
                "<tr class=\"dataTableRow\">\n";

            foreach (self::$tableFields as $next_field) {
                // -----
                // Special case for 'payer_email' field, append (if not empty) the 'first_name'
                // and 'last_name' preceded by a <br>.
                //
                $value = $next_txn[$next_field['field']];
                if ($next_field['field'] === 'payer_email') {
                    $first_name = $next_txn['first_name'];
                    $last_name = $next_txn['last_name'];
                    if (($first_name . $last_name) !== '') {
                        $value = $first_name . ' ' . $last_name . '<br>' . $value;
                    }
                }

                if (empty($value) || $value === '0001-01-01 00:00:00') {
                    $value = '&mdash;';
                }
                $align_class = (isset($next_field['align'])) ? " text-{$next_field['align']}" : '';
                $data .=
                    "  <td class=\"dataTableContent$align_class\">$value</td>\n";
            }

            // -----
            // Determine possible actions for a PayPal transaction; buttons for transactions
            // that are currently authorized, captured, refunded or voided will be handled
            // at the bottom of this loop.
            //
            $action_buttons = '';
            switch ($next_txn['txn_type']) {
                case 'CREATE':
                    $action_buttons = '<button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#detailsModal">' . MODULE_PAYMENT_PAYPALR_ACTION_DETAILS . '</button>';
                    $this->modals .= $this->createDetailsModal($next_txn);
                    break;

                case 'CAPTURE':
                    $last_capture_index = $txn_index;
                    break;

                case 'AUTHORIZE':
                    $last_auth_index = $txn_index;
                    break;

                case 'REFUND':
                    $last_refund_index = $txn_index;
                    break;

                default:
                    break;
            }

            $data .=
                '  <td class="dataTableContent text-right">' . $action_buttons . "</td>\n";
                "</tr>\n";
        }

        return $data;
    }

    protected function createDetailsModal(array $create_fields): string
    {
        $modal_body =
            '<div class="row">
                <div class="col-md-6">
                    <h5>' . MODULE_PAYMENT_PAYPALR_BUYER_INFO . '</h5>
                    <div class="form-horizontal">';

        $address_elements = [
            'address_name' => MODULE_PAYMENT_PAYPALR_ADDRESS_NAME,
            'address_street' => MODULE_PAYMENT_PAYPALR_ADDRESS_STREET,
            'address_city' => MODULE_PAYMENT_PAYPALR_ADDRESS_CITY,
            'address_state' => MODULE_PAYMENT_PAYPALR_ADDRESS_STATE,
            'address_zip' => MODULE_PAYMENT_PAYPALR_ADDRESS_ZIP,
            'address_country' => MODULE_PAYMENT_PAYPALR_ADDRESS_COUNTRY,
        ];
        foreach ($address_elements as $field_name => $label) {
            $value = $create_fields[$field_name];
            if ($field_name === 'address_name') {
                $value .= ' (' . MODULE_PAYMENT_PAYPALR_ADDRESS . ' ' . $create_fields['address_status'] . ')';
            }
            $modal_body .=
                        '<div class="form-group form-group-sm">
                            <label class="control-label col-sm-2">' . $label . ' </label>
                            <div class="col-sm-10">
                                <p class="form-control-static">' . zen_output_string_protected($value) . '</p>
                            </div>
                        </div>';
        }

        $modal_body .=
                    '</div>
                </div>
                <div class="col-md-6">
                    <h5>' . MODULE_PAYMENT_PAYPALR_SELLER_INFO . '</h5>
                    <div class="form-horizontal">';

        $seller_elements = [
            'business' => 'Business:',
            'receiver_email' => 'Email:',
            'receiver_id' => 'Merchant ID:',
        ];
        foreach ($seller_elements as $field_name => $label) {
            $modal_body .=
                        '<div class="form-group form-group-sm">
                            <label class="control-label col-sm-2">' . $label . ' </label>
                            <div class="col-sm-10">
                                <p class="form-control-static">' . zen_output_string_protected($create_fields[$field_name]) . '</p>
                            </div>
                        </div>';
        }
        $modal_body .=
                    '</div>
                </div>
            </div>';

        return $this->createModal('details', MODULE_PAYMENT_PAYPALR_DETAILS_TITLE, $modal_body);
    }

    protected function createModal(string $modal_id, string $modal_title, string $modal_body): string
    {
        return
            '<div id="' . $modal_id . 'Modal" class="modal fade">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h4 class="modal-title text-center">' . $modal_title . '</h4>
                        </div>
                        <div class="modal-body">' . $modal_body . '</div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        </div>
                   </div>
              </div>
        </div>';
    }
}
