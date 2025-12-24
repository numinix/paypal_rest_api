<?php
// Ensure there is always a selected shipping method when quotes are displayed.
if (empty($_SESSION['shipping']) && isset($shipping_modules) && is_object($shipping_modules)) {
    $_SESSION['shipping'] = $shipping_modules->cheapest();
}

if (!isset($quotes) || !is_array($quotes)) {
    $quotes = [];
}

if (!function_exists('oprc_prepare_delivery_updates_for_quotes')) {
    require_once(DIR_FS_CATALOG . 'includes/functions/extra_functions/oprc_functions.php');
}

$quotes = array_values($quotes);
$oprcDeliveryUpdateData = oprc_prepare_delivery_updates_for_quotes($quotes, isset($shipping_modules) ? $shipping_modules : null);
$oprcRenderedDeliveryUpdates = isset($oprcDeliveryUpdateData['rendered_updates']) && is_array($oprcDeliveryUpdateData['rendered_updates'])
    ? $oprcDeliveryUpdateData['rendered_updates']
    : [];

if (zen_count_shipping_modules() > 0) {
    if (isset($free_shipping) && $free_shipping == true) {
        $freeShippingIcon = '';
        if (!empty($quotes) && isset($quotes[0]['icon'])) {
            $freeShippingIcon = $quotes[0]['icon'];
        }
        ?>
        <div id="freeShip" class="nmx-box important">
            <?php echo FREE_SHIPPING_TITLE; ?> <?php echo $freeShippingIcon; ?>
        </div>
        <div class="nmx-box" id="defaultSelected">
            <?php echo sprintf(FREE_SHIPPING_DESCRIPTION, $currencies->format(MODULE_ORDER_TOTAL_SHIPPING_FREE_SHIPPING_OVER)) . zen_draw_hidden_field('shipping', 'free_free'); ?>
        </div>
        <?php
    } else {
        $radio_buttons = 0;
        $renderedShippingOptions = [];
        $quotesCount = count($quotes);
        if ($quotesCount > 0) {
            for ($i = 0; $i < $quotesCount; $i++) {
                $quote = $quotes[$i];

                if (!is_array($quote)) {
                    continue;
                }
                if (OPRC_SHIPPING_INFO == 'true' && isset($quote['info']) && $quote['info'] != '') {
                    $toggleShipping = 'is-clickable';
                } else {
                    $toggleShipping = '';
                }
                ?>
                <div class="shipping-method cf">
                    <h4 class="<?php echo $toggleShipping; ?>">
                        <?php echo isset($quote['module']) ? $quote['module'] : ''; ?>
                        <?php if (isset($quote['icon']) && zen_not_null($quote['icon'])) {
                            echo $quote['icon'];
                        } ?>
                    </h4>
                    <?php
                    if (OPRC_SHIPPING_INFO == 'true' && isset($quote['info']) && $quote['info'] != '') {
                        echo '<p class="information">' . $quote['info'] . '</p>';
                    }
                    ?>
                    <?php
                    if (isset($quote['error'])) {
                        ?>
                        <div><?php echo $quote['error']; ?></div>
                        <?php
                    } else {
                        $quoteMethods = isset($quote['methods']) && is_array($quote['methods']) ? $quote['methods'] : [];
                        $methodsCount = count($quoteMethods);
                        for ($j = 0; $j < $methodsCount; $j++) {
                            $method = $quoteMethods[$j];
                            if (!is_array($method) || !isset($quote['id']) || !isset($method['id'])) {
                                continue;
                            }

                            $shippingOptionId = $quote['id'] . '_' . $method['id'];
                            if (isset($renderedShippingOptions[$shippingOptionId])) {
                                continue;
                            }
                            $renderedShippingOptions[$shippingOptionId] = true;
                            // set the radio button to be checked if it is the method chosen
                            if (isset($_SESSION['shipping']['id']) && $shippingOptionId == $_SESSION['shipping']['id']) {
                                $checked = true;
                            } else {
                                $checked = false;
                            }
                            if (($checked == true) || ($quotesCount == 1 && $methodsCount == 1)) {
                                // perform something
                            }
                            ?>
                            <div class="custom-control custom-radio">
                                <?php
                                $radioFieldAttributes = 'id="ship-' . $quote['id'] . '-' . $method['id'] . '"';
                                echo zen_draw_radio_field('shipping', $shippingOptionId, $checked, $radioFieldAttributes);
                                ?>
                                <label for="<?php echo 'ship-' . $quote['id'] . '-' . $method['id']; ?>">
                                    <?php echo isset($method['title']) ? $method['title'] : ''; ?>
                                    <?php
                                    if (($quotesCount > 1) || ($methodsCount > 1)) {
                                        ?>
                                        <span class="shipping-price"><span>&mdash;</span>
                                            <?php
                                            $tax = isset($quote['tax']) ? $quote['tax'] : 0;
                                            $cost = isset($method['cost']) ? $method['cost'] : 0;
                                            echo $currencies->format(zen_add_tax($cost, $tax));
                                            ?></span>
                                        <?php
                                    } else {
                                        ?>
                                        <span class="shipping-price"><span>&mdash;</span>
                                            <?php
                                            $tax = isset($quote['tax']) ? $quote['tax'] : 0;
                                            $cost = isset($method['cost']) ? $method['cost'] : 0;
                                            echo $currencies->format(zen_add_tax($cost, $tax)) . zen_draw_hidden_field('shipping', $shippingOptionId);
                                            ?></span>
                                        <?php
                                    }
                                    ?>
                                    <?php
                                    $optionDateHtml = isset($oprcRenderedDeliveryUpdates[$shippingOptionId])
                                        ? $oprcRenderedDeliveryUpdates[$shippingOptionId]
                                        : '';

                                    if ($optionDateHtml === '' && isset($oprcRenderedDeliveryUpdates[$quote['id']])) {
                                        $optionDateHtml = $oprcRenderedDeliveryUpdates[$quote['id']];
                                    }

                                    if ($optionDateHtml !== '') {
                                        ?>
                                        <span class="shipping-estimated-date"><?php echo $optionDateHtml; ?></span>
                                        <?php
                                    }
                                    ?>
                                </label>
                            </div>
                            <?php
                            $radio_buttons++;
                        }
                    }
                    ?>
                </div>
                <?php
            }
        }
    }
} elseif ($_SESSION['shipping'] != 'free_free') {
    ?>
    <h3 id="checkoutShippingHeadingMethod"><?php echo TITLE_NO_SHIPPING_AVAILABLE; ?></h3>
    <div id="checkoutShippingContentChoose" class="important"><?php echo TEXT_NO_SHIPPING_AVAILABLE; ?></div>
    <?php
}
?>
