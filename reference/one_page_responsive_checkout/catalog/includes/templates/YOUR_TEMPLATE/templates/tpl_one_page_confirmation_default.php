<?php
/**
 * Page Template
 *
 * Loaded automatically by index.php?main_page=oprc_confirmation.<br />
 * Displays final checkout details, cart, payment and shipping info details.
 *
 * @package templateSystem
 * @copyright Copyright 2003-2006 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: tpl_oprc_confirmation_default.php 3 2012-07-08 21:11:34Z numinix $
 */
?>
<?php
  if (!$_SESSION['sendto']) {
    $columns = "2";
  } 
?>
<div class="centerColumn nmx-wrapper nmx nmx-plugin nmx-plugin--oprc nmx-cf" id="checkoutConfirmDefault">
  <div id="onePageCheckoutContent" class="cf">

<?php 
     /*
 if (OPRC_ORDER_STEPS == 'true') {
        require($template->get_template_dir('tpl_modules_oprc_order_steps.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_order_steps.php');  
      } 
*/
    ?>

    <h1 id="checkoutConfirmDefaultHeading"><?php echo HEADING_TITLE; ?></h1>
    <div id="messageStackErrors"><?php if ($messageStack->size('redemptions') > 0) echo $messageStack->output('redemptions'); ?><?php if ($messageStack->size('checkout_confirmation') > 0) echo $messageStack->output('checkout_confirmation'); ?><?php if ($messageStack->size('checkout') > 0) echo $messageStack->output('checkout'); ?><?php  if ($flagAnyOutOfStock) { ?><?php if (STOCK_ALLOW_CHECKOUT != 'true') {  ?><div class="messageStackError"><?php echo OUT_OF_STOCK_CANT_CHECKOUT; ?></div><?php } //endif STOCK_ALLOW_CHECKOUT ?><?php } //endif flagAnyOutOfStock ?></div> 
    <div id="column1<?php echo $columns; ?>">
      <div id="column2<?php echo $columns; ?>" class="nmx-row">
        <div id="oprc_column1<?php echo $columns; ?>" class="nmx-col-4">
          
          <!-- panel -->
          <div class="nmx-panel">
            
            <!-- panel head -->
            <div class="nmx-panel-head" id="checkoutConfirmDefaultBillingAddress">
              <?php echo HEADING_BILLING_ADDRESS; ?>
              <?php if (!$flagDisablePaymentAddressChange) { ?>
                <?php echo '<a class="nmx-head-link--small" href="' . zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL') . '">' . BUTTON_EDIT_SMALL_ALT . '</a>'; ?>
              <?php } ?>
            </div>
            <!-- end panel head -->

            <!-- panel body -->
            <div class="nmx-panel-body cf">
            
              <div class="nmx-box">
                <address><?php echo zen_address_format($order->billing['format_id'], $order->billing, 1, ' ', '<br />'); ?></address>
              </div>

              <?php
                $class =& $_SESSION['payment'];
              ?>
              <div class="nmx-box">
                <span id="checkoutConfirmDefaultPayment"><strong><?php echo HEADING_PAYMENT_METHOD; ?></strong> <?php echo $GLOBALS[$class]->title; ?></span> 
              </div>

              <div class="nmx-box">
                <?php
                  if (is_array($payment_modules->modules)) {
                    if ($confirmation = $payment_modules->confirmation()) {
                ?>
                  <div class="important"><?php echo $confirmation['title']; ?></div>
                <?php
                    }
                ?>
                  <div class="important">
                    <?php
                          for ($i=0, $n=sizeof($confirmation['fields']); $i<$n; $i++) {
                    ?>
                      <div class="back"><?php echo $confirmation['fields'][$i]['title']; ?></div>
                      <div ><?php echo $confirmation['fields'][$i]['field']; ?></div>
                    <?php
                         }
                    ?>
                  </div>
                <?php
                  }
                ?>
              </div>
            </div>
            <!-- end panel body -->
          
          </div>
          <!-- end panel -->

        </div>

      <?php
        if ($_SESSION['sendto'] != false) {
      ?>
        <div id="oprc_column2" class="nmx-col-4">
          
          <!-- panel -->
          <div class="nmx-panel">
            
            <!-- panel head -->
            <div class="nmx-panel-head" id="checkoutConfirmDefaultShippingAddress">
              <?php echo HEADING_DELIVERY_ADDRESS; ?>
              <?php echo '<a class="nmx-head-link--small" href="' . $editShippingButtonLink . '">' . BUTTON_EDIT_SMALL_ALT . '</a>'; ?>
            </div>
            <!-- end panel head -->

            <!-- panel body -->
            <div class="nmx-panel-body cf">

              <div class="nmx-box">
                <address><?php echo zen_address_format($order->delivery['format_id'], $order->delivery, 1, ' ', '<br />'); ?></address>
              </div>

              <?php
                  if ($order->info['shipping_method']) {
              ?>
                <div class="nmx-box">
                  <span id="checkoutConfirmDefaultShipment">
                    <strong><?php echo HEADING_SHIPPING_METHOD; ?></strong> <?php echo $order->info['shipping_method']; ?>
                  </span>
                </div>
              <?php
                  }
              ?>
            </div>
            <!-- end panel body -->
          
          </div>
          <!-- end panel -->

        </div>
      <?php
        }
      ?>
      <?php
      // always show comments
      //  if ($order->info['comments']) {
      ?>
        <div id="oprc_column3<?php echo $columns; ?>" class="nmx-col-4">
          
          <!-- panel -->
          <div class="nmx-panel">

            <!-- panel head -->
            <div class="nmx-panel-head" id="checkoutConfirmDefaultHeadingComments">
              <?php echo HEADING_ORDER_COMMENTS; ?>
              <?php echo  '<a class="nmx-head-link--small" href="' . zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL') . '">' . BUTTON_EDIT_SMALL_ALT . '</a>'; ?>
            </div>
            <!-- end panel head -->

            <!-- panel body -->
            <div class="nmx-panel-body cf">

              <div class="nmx-box">
                
                <div><?php echo (empty($order->info['comments']) ? NO_COMMENTS_TEXT : nl2br(zen_output_string_protected($order->info['comments'])) . zen_draw_hidden_field('comments', $order->info['comments'])); ?></div>
                
              </div>
              
              <div class="nmx-box">

                <h3><?php echo HEADING_PRODUCTS; ?> <?php echo '<a href="' . zen_href_link(FILENAME_SHOPPING_CART, '', 'NONSSL') . '">' . BUTTON_EDIT_SMALL_ALT . '</a>'; ?></h3>
                <div id="shopBagWrapper">
                        
                  <div class="nmx-box">
                    <table class="table--mini-cart">
                      <?php // now loop thru all products to display quantity and price ?>
                      <?php for ($i=0, $n=sizeof($order->products); $i<$n; $i++) { ?>
                      <?php $thumbnail = zen_get_products_image($order->products[$i]['id'], 40, 42); ?>
                      <tr>
                        <td class="nmx-cart-img"><?php echo $thumbnail; ?></td>
                        <td class="nmx-cart-details"><?php echo $order->products[$i]['qty']; ?> x <?php echo $order->products[$i]['name']; ?>
                          <?php  echo $stock_check[$i]; ?>
                          <?php // if there are attributes, loop thru them and display one per line
                              if (isset($order->products[$i]['attributes']) && sizeof($order->products[$i]['attributes']) > 0 ) {
                              //echo '<ul class="cartAttribsList">';
                                for ($j=0, $n2=sizeof($order->products[$i]['attributes']); $j<$n2; $j++) {
                          ?>
                                <!--li--><?php echo '<br />' . $order->products[$i]['attributes'][$j]['option'] . ': ' . nl2br(zen_output_string_protected($order->products[$i]['attributes'][$j]['value'])); ?><!--/li-->
                          <?php
                                } // end loop
                                //echo '</ul>';
                              } // endif attribute-info
                          ?>
                          </td>
                        <td class="nmx-tar nmx-cart-total">
                        <?php echo $currencies->display_price($order->products[$i]['final_price'], $order->products[$i]['tax'], $order->products[$i]['qty']);
                        if ($order->products[$i]['onetime_charges'] != 0 ) echo '<br /> ' . $currencies->display_price($order->products[$i]['onetime_charges'], $order->products[$i]['tax'], 1);
                            ?>
                        </td>
                      </tr>
                      <?php  }  // end for loopthru all products 
                      ?>
                    </table>
                  </div>
                  <?php
                    if (MODULE_ORDER_TOTAL_INSTALLED) {
                      //$order_totals = $order_total_modules->process();
                  ?>
                    <div id="orderTotals" class="nmx-box">
                      <table border="0">
                        <?php $order_total_modules->output(); ?>
                      </table>
                    </div>
                  <?php
                    }
                  ?>
                </div>

                <?php
                  echo zen_draw_form('checkout_confirmation', $form_action_url, 'post', 'id="checkout_confirmation"');
                  if (OPRC_ONE_PAGE == 'true') {
                    echo zen_draw_hidden_field('onePageStatus', 'on', 'class="hiddenField"') . zen_draw_hidden_field('email_pref_html', 'email_format', 'class="hiddenField"');
                  }
                  if (is_array($payment_modules->modules)) {
                    echo $payment_modules->process_button();
                  }
                ?>
                  <div class="nmx-buttons nmx-tar cf">
                      <?php echo (OPRC_CSS_BUTTONS == 'false' ? zen_image_submit(BUTTON_IMAGE_CONFIRM_ORDER, BUTTON_CONFIRM_ORDER_ALT, 'name="btn_submit" id="btn_submit"') : zenCssButton(BUTTON_IMAGE_CONFIRM_ORDER, BUTTON_CONFIRM_ORDER_ALT, 'submit', 'button_confirm_checkout')) ;?>
                  </div>
                </form>
                

              </div>

            </div>
            <!-- end panel body -->
            
          </div>
          <!-- end panel -->
          
        </div>
        <!-- end third column -->
        
      </div>
      <!-- end row -->
      
    </div>
    <!-- end column1 -->

  </div>
</div>
