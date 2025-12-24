
    <!-- bof modified for STRIN: Alert for backorders in cart -->
    <?php if (isset($_SESSION['backorders']) && zen_not_null($_SESSION['backorders'])) : ?>
      <span class="backorders-in-cart-alert messageStackCaution">This cart contains back ordered items. </span>
    <?php endif; ?>
    <!-- eof modified for STRIN: Alert for backorders in cart -->
    <!-- order total -->
    <div id="orderTotal">
      
      <!--EOF Shopping Bag Contents-->  
      <div id="shopBagWrapper">
        
        <!-- shopping cart -->
        <div id="cartProducts" class="nmx-box">
          <?php // now loop thru all products to display quantity and price ?>
          <?php
            $colspan = 3;
            $flagAnyOutOfStock = false;  
            for ($i=0; $i < sizeof($order->products); $i++) {
              if (STOCK_CHECK == 'true') {
                $attributes = array();
                if (isset($order->products[$i]['attributes']) && sizeof($order->products[$i]['attributes']) > 0) {
                  foreach ($order->products[$i]['attributes'] as $attribute) {
                    $attributes[$attribute['option_id']] = $attribute['value_id'];
                  }
                }
                $flagStockCheck = zen_check_stock((int)$order->products[$i]['id'], $order->products[$i]['qty'], $attributes);
                if ($flagStockCheck == true) {
                  $flagAnyOutOfStock = true;
                }
                $stockAvailable = zen_get_products_stock($order->products[$i]['id'], $attributes);
                if (is_array($stockAvailable)) $stockAvailable = $stockAvailable['quantity']; // NPVIM
              }
              $thumbnail = zen_get_products_image($order->products[$i]['id'], IMAGE_SHOPPING_CART_WIDTH, IMAGE_SHOPPING_CART_HEIGHT);
              $customUrl = $_SESSION[$order->products[$i]['id'] . 'meta']['products_customily_meta']['previewiconUrl'] ?? '';

              if ($customUrl === '' && isset($order->products[$i]['attributes']) && is_array($order->products[$i]['attributes'])) {
                foreach ($order->products[$i]['attributes'] as $attribute) {
                  if (!isset($attribute['option_id']) || !isset($attribute['value_id'])) {
                    continue;
                  }

                  $attributes_image = $db->Execute("SELECT attributes_image
                                                    FROM " . TABLE_PRODUCTS_ATTRIBUTES . "
                                                    WHERE products_id = " . (int)$order->products[$i]['id'] . "
                                                    AND options_id = " . (int)$attribute['option_id'] . "
                                                    AND options_values_id = " . (int)$attribute['value_id'] . "
                                                    LIMIT 1;");

                  if ($attributes_image->RecordCount() > 0 && $attributes_image->fields['attributes_image'] != '') {
                    $attributeImageFile = $attributes_image->fields['attributes_image'];
                    $attributeThumbnail = zen_image(DIR_WS_IMAGES . $attributeImageFile, $order->products[$i]['name'], IMAGE_SHOPPING_CART_WIDTH, IMAGE_SHOPPING_CART_HEIGHT);

                    if (strpos($attributeThumbnail, $attributeImageFile) !== false) {
                      $thumbnail = $attributeThumbnail;
                      break;
                    }
                  }
                }
              }

          // bof modified for STRIN: custom code for backorders
          $backorder_status = 0;
          if (isset($_SESSION['backorders'])) {
            if (in_array($order->products[$i]['id'], $_SESSION['backorders'])) {
              $backorder_status = 1;
            }
          }
          // eof modified for STRIN: custom code for backorders

          ?>
          <div class="product <?php echo (($flagStockCheck && $order->products[$i]['qty'] > $stockAvailable) || $backorder_status == 1 ? ' backorder' : ''); // modified for STRING: Alert for backorders in cart ?>">
            <?php if(OPRC_CHECKOUT_SHOPPING_CART_DISPLAY_DEFAULT == "partially expanded") { ?>
            <span class="nmx-accordion-title js-accordion-title">
              <span class="nmx-caac--details">
                <span>
                  <?php echo $order->products[$i]['name'] . (($flagStockCheck && $order->products[$i]['qty'] > $stockAvailable) ? '' : ''); ?>
                </span>
                <?php
                // bof modified for STRIN: custom code for backorders
                if (($flagStockCheck && $order->products[$i]['qty'] > $stockAvailable) || $backorder_status == 1 ) {
                  $will_ship = $stockAvailable > 0 ? $stockAvailable : 0;
                  $will_back = $stockAvailable > 0 ? $order->products[$i]['qty'] - $stockAvailable : $order->products[$i]['qty'];
                  echo '<div class="msg-backorder alert">' . $will_ship . ' will ship. ' . $will_back . ' will backorder.</div>';
                }
                // bof modified for STRIN: custom code for backorders
                ?>
              </span>
              <span class="nmx-caac--total">
                <?php echo $order->products[$i]['qty']; ?> x <?php echo $currencies->display_price($order->products[$i]['final_price'], $order->products[$i]['tax'], $order->products[$i]['qty']); ?>
              </span>
            </span>
            <?php } ?>
            <table class="table--borderless table--mini-cart">
              <tr>
                <td class="nmx-cart-img">
                  <?php echo $thumbnail; ?>
                </td>
                <td class="nmx-cart-details">
                  <?php if(OPRC_CHECKOUT_SHOPPING_CART_DISPLAY_DEFAULT == "fully expanded") { ?>
                    <?php echo $order->products[$i]['name'] . (($flagStockCheck && $order->products[$i]['qty'] > $stockAvailable) ? '' : ''); ?>
                  
                    <?php
                    // bof modified for STRIN: custom code for backorders
                    if (($flagStockCheck && $order->products[$i]['qty'] > $stockAvailable) || $backorder_status == 1 ) {
                      $will_ship = $stockAvailable > 0 ? $stockAvailable : 0;
                      $will_back = $stockAvailable > 0 ? $order->products[$i]['qty'] - $stockAvailable : $order->products[$i]['qty'];
                      echo '<div class="msg-backorder alert">' . $will_ship . ' will ship. ' . $will_back . ' will backorder.</div>';
                    }
                    // bof modified for STRIN: custom code for backorders
                    ?>
                  <?php } ?>
                  <?php // if there are attributes, loop thru them and display one per line
                     if (isset($order->products[$i]['attributes']) && sizeof($order->products[$i]['attributes']) > 0 ) {
                       for ($j=0; $j < sizeof($order->products[$i]['attributes']); $j++) {
                         if (zen_values_name($order->products[$i]['attributes'][$j]['value_id']) != 'TEXT') {
                           echo '<div class="bagOption">' . $order->products[$i]['attributes'][$j]['option'] . ': ' . nl2br(zen_output_string_protected($order->products[$i]['attributes'][$j]['value'])) . '</div>';
                         }
                       } // end loop
                     } // endif attribute-info
                  ?>
                  <?php if(OPRC_CHECKOUT_SHOPPING_CART_DISPLAY_DEFAULT == "partially expanded") { ?>
                  <a class="removeProduct remove-product" href="<?php echo zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, 'action=remove_product&product_id=' . $order->products[$i]['id'], 'SSL'); ?>">
                      <?php echo BUTTON_REMOVE_OPRC_REMOVE_CHECKOUT; ?>
                    </a>
                  <?php } ?>
                </td>
                <td class="nmx-tar nmx-cart-total">
                  <?php if(OPRC_CHECKOUT_SHOPPING_CART_DISPLAY_DEFAULT == "fully expanded") { ?>
                    <?php echo $order->products[$i]['qty']; ?> x <?php echo $currencies->display_price($order->products[$i]['final_price'], $order->products[$i]['tax'], $order->products[$i]['qty']); ?>
                    <br>
                    <a class="removeProduct remove-product" href="<?php echo zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, 'action=remove_product&product_id=' . $order->products[$i]['id'], 'SSL'); ?>">
                      <?php echo BUTTON_REMOVE_OPRC_REMOVE_CHECKOUT; ?>
                    </a>
                  <?php } ?>
                   
                </td>
              </tr>
            </table>
          </div>
          <?php
              }
            if ($flagAnyOutOfStock) {
              echo '<div id="bo-wrapper"><em class="backorder alert" id="bo-em">' . TEXT_BACKORDERED . '</em></div>';
            }
          ?>

        </div>
        <!-- end shopping cart -->

        <?php
          if (MODULE_ORDER_TOTAL_INSTALLED) {
            if (!isset($_SESSION['customer_id'])) {         
              global $order;
              if( !is_object($order) ){
                require_once(DIR_WS_CLASSES . 'order.php');
                $order = new order;
              }
              if( isset($_SESSION['ac_shipping_estimator_order']) ){
                $order->info['shipping_module_code'] = $_SESSION['ac_shipping_estimator_order']['info']['shipping_module_code'];
                $order->info['shipping_method'] = $_SESSION['ac_shipping_estimator_order']['info']['shipping_method'];
                $order->info['shipping_cost'] = $_SESSION['ac_shipping_estimator_order']['info']['shipping_cost'];
                $order->delivery = $_SESSION['ac_shipping_estimator_order']['delivery'];
              }
                                     
              require_once(DIR_WS_CLASSES . 'order_total.php');
              $order_total_modules = new order_total;
              $order_totals = $order_total_modules->process();
            }
            
            //update order object with the session shipping info to display correctly in the ot_shipping box
            if(is_object($GLOBALS['ot_shipping']) && is_object($order) && isset($_SESSION['shipping']) && zen_not_null($_SESSION['shipping'])){
                
                $order->info['shipping_method'] = $_SESSION['shipping']['title'];
                $order->info['shipping_module_code'] = $_SESSION['shipping']['id'];
                $order->info['shipping_cost'] = $_SESSION['shipping']['cost'];
                unset($GLOBALS['ot_shipping']->output);
                $GLOBALS['ot_shipping']->process();
                
            }
        ?>
        
        <div id="orderTotals" class="nmx-box">
          <table class="table--ordertotal">
            <?php $order_total_modules->output(); ?>
          </table>
        </div>
        <?php
          }
        ?>

        <?php if (!isset($_SESSION['customer_id'])) { ?>
        <!-- BOF #shipHandling -->
        <div id="shipHandling" class="nmx-box"><?php echo TEXT_ORDER_TOTAL_DISCLAIMER; ?></div>
        <!-- EOF #shipHandling -->
        <?php } ?>

      </div>
      <!--EOF #shopBagWrapper-->

    </div>
    <!-- end order total -->

  
