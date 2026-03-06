<?php
/**
 *  ot_local_sales_tax module
 *
 * Last updated: v3.0.1
 *
 *   By Heather Gardner AKA: LadyHLG
 *   The module should apply tax based on the field you
 *   choose options are Zip Code, City, and Suburb.
 *   It should also compound the tax to whatever zone
 *   taxes you already have set up.  Which means you
 *   can apply multiple taxes to any zone based on
 *   different criteria.
 */
class zcObserverLocalSalesTaxAdmin extends base
{
    protected string $store_tax_basis;
    protected array $localTaxes;

    public function __construct()
    {
        if (!defined('MODULE_ORDER_TOTAL_COUNTY_LOCAL_TAX_STATUS')) {
            return;
        }

        $this->store_tax_basis = MODULE_ORDER_TOTAL_COUNTY_LOCAL_TAX_STORE_TAX_BASIS;

        $this->attach($this, [
            /* From /includes/classes/order.php */
            'NOTIFY_ORDER_CART_EXTERNAL_TAX_RATE_LOOKUP',

            /* From /includes/modules/order_total/ot_shipping.php */
            'NOTIFY_OT_SHIPPING_TAX_CALCS',
        ]);
    }

    public function notify_order_cart_external_tax_rate_lookup(\order &$order, string $e, string $store_tax_basis, array &$products, int &$loop, int &$index, int &$taxCountryId, int &$taxZoneId, &$taxRates): void
    {
        // -----
        // If another observer has already provided the tax-rates' override, those overrides
        // are in effect!
        //
        if ($taxRates !== null) {
            return;
        }

        // -----
        // If EO's product-pricing is to be performed 'manually', these tax calculations
        // don't apply ... unless a product-addition is in-process.
        //
        global $eo;
        if (($_SESSION['eo_price_calculations'] ?? '') !== 'Manual' || (isset($eo) && $eo->productAddInProcess() === true)) {
            $this->getLocalZoneTaxes($order, $taxZoneId, $store_tax_basis);
            if (count($this->localTaxes) === 0) {
                return;
            }

            $tax_class_id = (int)$products[$loop]['tax_class_id'];
            foreach ($this->localTaxes as $taxrec) {
                if ($taxrec['tax_class'] !== $tax_class_id) {
                    continue;
                }

                $apply_local_tax = $this->checkForDataMatch($taxrec['order_data'], $taxrec['matching_data']);
                if (!$apply_local_tax) {
                    continue;
                }

                $order->products[$index]['tax'] ??= 0;
                $order->products[$index]['tax'] += $taxrec['tax'];

                $tax_description = $taxrec['id'] . ' ' . $taxrec['tax'] . '%';
                $taxRates ??= [];
                $taxRates[$tax_description] = $taxrec['tax'];
            }

            if ($taxRates === null) {
                return;
            }

            // -----
            // There were local sales taxes to apply. Include the state's base tax in
            // the calculations as well.
            //
            $order->products[$index]['tax'] += zen_get_tax_rate($products[$loop]['tax_class_id'], $taxCountryId, $taxZoneId);

            $taxRates = array_merge($taxRates, zen_get_multiple_tax_rates($products[$loop]['tax_class_id'], $taxCountryId, $taxZoneId));
            $order->products[$index]['tax_groups'] = $taxRates;
            $order->products[$index]['tax_description'] = implode(' + ', array_keys($taxRates));
        }
    }

    public function notify_ot_shipping_tax_calcs(&$class, string $e, $unused, bool &$external_shipping_tax_handler, int|float &$shipping_tax, string &$shipping_tax_description): void
    {
        if (!isset($this->localTaxes) || count($this->localTaxes) === 0) {
            return;
        }

        $module = (isset($_SESSION['shipping']['id'])) ? substr($_SESSION['shipping']['id'], 0, strpos($_SESSION['shipping']['id'], '_')) : '';
        if (empty($module) || $module === 'free' || !isset($GLOBALS[$module]->tax_class) || $GLOBALS[$module]->tax_class <= 0) {
            return;
        }

        global $order;

        $shipping_tax_class = (int)$GLOBALS[$module]->tax_class;
        $taxRates = null;
        foreach ($this->localTaxes as $taxrec) {
            if ($taxrec['tax_class'] !== $shipping_tax_class || $taxrec['tax_shipping'] !== 'true') {
                continue;
            }

            $apply_local_tax = $this->checkForDataMatch($taxrec['order_data'], $taxrec['matching_data']);
            if (!$apply_local_tax) {
                continue;
            }

            $shipping_tax += $taxrec['tax'];

            $tax_description = $taxrec['id'] . ' ' . $taxrec['tax'] . '%';
            $taxRates ??= [];
            $taxRates[$tax_description] = $taxrec['tax'];
        }

        if ($taxRates === null) {
            return;
        }

        $external_shipping_tax_handler = true;
        $shipping_tax_description = implode(' + ', array_keys($taxRates));
    }

    // -----
    // Convert a string value to either an int or float, depending on
    // the presence of a '.' in the value.
    //
    protected function convertToIntOrFloat(string $value): int|float
    {
        if (strpos($value, '.') === false) {
            return (int)$value;
        }
        return (float)$value;
    }

    protected function getLocalZoneTaxes(\order &$order, int $taxZoneId, string $store_tax_basis): void
    {
        if (isset($this->localTaxes)) {
            return;
        }

        global $db;

        $taxsql =
            "SELECT local_tax_id, zone_id, local_fieldmatch, local_datamatch, local_tax_rate, local_tax_label,
                    local_tax_shipping, local_tax_class_id
               FROM " . TABLE_LOCAL_SALES_TAXES . "
              WHERE zone_id = " . $taxZoneId;

        //get tax rates for field lookup
        $local_taxes = $db->Execute($taxsql);
        $this->localTaxes = [];
        if ($local_taxes->EOF) {
            return;
        }

        foreach ($local_taxes as $next_local) {
            $this->localTaxes[$next_local['local_tax_id']] = [
                'id' => $next_local['local_tax_label'],
                'tax' => $this->convertToIntOrFloat($next_local['local_tax_rate']),
                'match_field' => $next_local['local_fieldmatch'],
                'matching_data' => $next_local['local_datamatch'],
                'tax_shipping' => $next_local['local_tax_shipping'],
                'tax_class' => (int)$next_local['local_tax_class_id'],
                'order_data' => $this->getOrderData($order, $store_tax_basis, $next_local['local_fieldmatch']),
            ];
        }
    }

    protected function getOrderData(\order &$order, string $store_tax_basis, string $taxmatch): string
    {
        $is_store_pickup = str_starts_with($order->info['shipping_module_code'], 'storepickup');
        $ot_local_sales_taxes_basis = ($is_store_pickup === true) ? 'Store Pickup' : $store_tax_basis;

        switch ($ot_local_sales_taxes_basis) {
            case 'Shipping':
                if (empty($order->delivery[$taxmatch])) {
                    $orderdata = $order->billing[$taxmatch];
                } else {
                    $orderdata = $order->delivery[$taxmatch];
                }
                break;

            case 'Billing':
                $orderdata = $order->billing[$taxmatch];
                break;

            case 'Store Pickup':
            default:
                $orderdata = $this->store_tax_basis;
                break;
        }
        return $orderdata;
    }

    protected function checkForDataMatch(string $order_data, string $local_data_list): bool
    {
        $taxapplies = false;

        //Remove the - and plus 4 if the customer entered it with the zip code
        if (str_contains($order_data, '-')) {
            $tmpOD = explode('-', trim($order_data));
            if (ctype_digit($tmpOD[0])) {
                $order_data = $tmpOD[0];
            }
        }

        $listarray = explode(';', $local_data_list);

        //loop through the array to check each item is it a range or single zip
        //ranges are usually used with postcodes
        $order_data = strtolower($order_data);
        foreach ($listarray as $value) {
            $value = strtolower(trim($value));

            //this array item is a range
            if (str_contains($value, '-to-')) {
                //split the range to see if zip falls within
                $rangearray = explode('-to-', $value);
                $lowerrange = trim($rangearray[0]);
                $upperrange = trim($rangearray[1]);

                if ($order_data >= $lowerrange && $order_data <= $upperrange) {
                    $taxapplies = true;
                    break;
                }
            } elseif ($order_data === $value) {
                $taxapplies = true;
                break;
            }
        }
        return $taxapplies;
    }
}
