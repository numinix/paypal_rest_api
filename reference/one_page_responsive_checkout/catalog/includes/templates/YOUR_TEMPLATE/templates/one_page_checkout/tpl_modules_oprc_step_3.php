<?php
    // column configuration
    $columns = '';
    if ($_SESSION['cart']->get_content_type() == 'virtual') {
      $columns = '2';
      $widthClass = 'width50';
    } else {
      $widthClass = 'width33';
    }
  ?>
    
  <div id="column1<?php echo $columns; ?>">
    <div id="column2<?php echo $columns; ?>">
      <?php 
      // shipping, payment and confirmation  
      ?>
      <!-- panel -->
      <?php if (OPRC_HIDE_WELCOME != true) { ?>
      <div class="nmx-panel nmx-welcome">
        
        <!-- panel head -->
        <div class="nmx-panel-head">
          <?php echo HEADING_STEP_1; ?>
        </div>
        <!-- end panel head -->

        <!-- panel body -->
        <div class="nmx-panel-body nmx-cf" id="oprcWelcome">
          <?php echo sprintf(HEADING_WELCOME, $_SESSION['customer_first_name']); ?>
        </div>
        <!-- end panel head -->

      </div>
      <?php } ?>
      <!-- end panel -->
      
      <!-- panel -->
      <div class="nmx-panel" id="oprcAddresses">

        <!-- panel head -->
        <div class="nmx-panel-head">
          <?php echo ((OPRC_SHIPPING_ADDRESS == 'false' || $_SESSION['cart']->get_content_type() == 'virtual') ? HEADING_STEP_2_NO_SHIPPING : HEADING_STEP_2); ?><!--EOF .oprcHead-->
        </div>

        <!-- panel body -->
        <div class="nmx-panel-body nmx-cf">
          
          <?php if ($messageStack->size('checkout_address') > 0) echo $messageStack->output('checkout_address'); ?> 

          <!-- row -->
          <div class="nmx-row shipping-billing-addresses nmx-cf">
            
            <!-- col-6 -->
            <div class="nmx-col-6">
              <?php if (($_SESSION['cart']->get_content_type() == 'virtual' && OPRC_FORCE_SHIPPING_ADDRESS_TO_BILLING == 'true') ||OPRC_FORCE_SHIPPING_ADDRESS_TO_BILLING == 'false') {
                require($template->get_template_dir('tpl_modules_revise_billing.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_revise_billing.php');
                } else {
                  echo "<div id='ignoreAddressCheck'></div>";
                } ?>
            </div>
            <!-- end col-6 -->

            <!-- col-6 -->
            <div class="nmx-col-6">
              <?php if ($_SESSION['cart']->get_content_type() != 'virtual') {
                require($template->get_template_dir('tpl_modules_revise_shipping.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_revise_shipping.php');
              } ?>
            </div>
            <!-- end col-6 -->

          </div>
          <!-- end row -->

        </div>
        <!-- end body -->

      </div>
      <!-- end panel -->

      <!-- panel -->
      <div class="nmx-panel">

        <!-- panel head -->
        <div class="nmx-panel-head current">
          <?php echo ((OPRC_SHIPPING_ADDRESS == 'false' || $_SESSION['cart']->get_content_type() == 'virtual') ? HEADING_STEP_3_NO_SHIPPING : HEADING_STEP_3); ?>
        </div>
        <!-- end panel -->

        <!-- panel body -->
        <div class="nmx-panel-body nmx-cf" id="step3">

          <div id="oprc_column2<?php echo $columns; ?>" class="nmx-box <?php echo $widthClass; ?>">
          <?php
          if ($_SESSION['cart']->get_content_type() == 'virtual') {
            require($template->get_template_dir('tpl_modules_oprc_payment.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_payment.php');
          } else {
            require($template->get_template_dir('tpl_modules_oprc_shipping.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_shipping.php');
          }
          if (OPRC_CREDIT_POSITION == 1) {
            require($template->get_template_dir('tpl_modules_oprc_credit.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_credit.php');
          }
          ?>
          </div>

        <?php if ($_SESSION['cart']->get_content_type() != 'virtual') { ?>
          <div id="oprc_column1<?php echo $columns; ?>" class="nmx-box <?php echo $widthClass; ?>">
              <?php require($template->get_template_dir('tpl_modules_oprc_payment.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_payment.php'); ?>
          </div>
        <?php } ?>
          
          <?php 
            if ($_SESSION['customer_id']) {      
              require($template->get_template_dir('tpl_modules_oprc_credit.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_credit.php');
            }
          ?>

          <!-- bof FEC v1.27 CHECKBOX -->
          <?php
            $counter = 0;
            if (OPRC_CHECKBOX == 'true') {
              $checkbox = ($_SESSION['oprc_checkbox'] == '1' ? true : false);
              $counter++;
          ?>
            <div id="checkoutFECCheckbox" class="nmx-box">
              <h3><?php echo TABLE_HEADING_OPRC_CHECKBOX; ?></h3>
              <div class="nmx-checkbox">
                  <div class="custom-control custom-checkbox">
                    <?php echo zen_draw_checkbox_field('oprc_checkbox', '1', $checkbox, 'id="oprc_checkbox"'); ?> <label><?php echo TEXT_OPRC_CHECKBOX; ?></label>
                  </div>
                  <?php if ($messageStack->size('oprc_checkbox') > 0) { echo '<span class="alert validation disablejAlert">'; echo $messageStack->output('oprc_checkbox'); echo '</span>'; } ?>
              </div>
            </div>
          <?php 
            } 
          ?>

          <?php if (OPRC_GIFT_MESSAGE == 'true') {
              $counter++;
          ?>
            <div id="giftMessage" class="nmx-box">
            <?php if (OPRC_CHECKBOX == 'true' || OPRC_DROP_DOWN == 'true' || OPRC_ORDER_TOTAL_POSITION != 'top') { ?>
            <?php } ?>
              <h3><?php echo TABLE_HEADING_GIFT_MESSAGE; ?></h3>
              <div class="boxContents">
                <?php echo zen_draw_textarea_field('gift-message', '45', '3', $_SESSION['gift-message']); ?>
                <?php if ($messageStack->size('gift-message') > 0) { echo '<span class="alert validation disablejAlert">'; echo $messageStack->output('gift-message'); echo '</span>'; } ?>
              </div>
            </div>
          <?php
            }
          ?>
          <!-- eof FEC v1.27 CHECKBOX -->

          <!-- bof FEC v1.24a DROP DOWN -->
          <?php 
            if (OPRC_DROP_DOWN == 'true') {
              $counter++;
          ?>
            <div id="checkoutDropdown" class="nmx-box">
              <h3><?php echo TABLE_HEADING_DROPDOWN; ?></h3>
              <div class="boxContents">
                <label><?php echo TEXT_DROP_DOWN; ?></label>
                <?php echo zen_draw_pull_down_menu('dropdown', $dropdown_list_array, $_SESSION['dropdown'], 'onchange="updateForm()"', true); ?>
                <?php if ($messageStack->size('dropdown') > 0) { echo '<span class="alert validation disablejAlert">'; echo $messageStack->output('dropdown'); echo '</span>'; } ?>
              </div>
            </div>
          <?php
            } 
          ?>
          <!-- eof DROP DOWN -->

          <?php
          if (DISPLAY_CONDITIONS_ON_CHECKOUT == 'true') {
          ?>
          <div id="conditions_checkout" class="nmx-box">

              <h3><?php echo TABLE_HEADING_CONDITIONS; ?></h3>
              
              <?php 
                if ($messageStack->size('conditions') > 0) {
                  echo '<div class="disablejAlert">';
                  echo    $messageStack->output('conditions');
                  echo '</div>';
                }
              ?>
              <p class="information nmx-mb0"><?php echo TEXT_PRIVACY_CONDITIONS_DESCRIPTION_OPRC;?></p>
              
              <div class="nmx-checkbox nmx-hidden">
                <label>
                  <?php echo TEXT_CONDITIONS_CONFIRM; ?>
                  <?php echo zen_draw_checkbox_field('conditions', '1', true, 'id="conditions"');?>
                </label>
              </div>
              
          </div>
          <?php
            }
          ?>
          
        </div>
        <!-- panel body -->
      </div>
      <!-- end panel -->

      <div class="nmx-panel">
        <!-- panel head -->
        <div class="nmx-panel-head">
          <?php echo HEADING_STEP_3_COMMENTS; ?>
        </div>
        <!-- end panel head -->
        <div class="nmx-panel-body">
          <?php 
            if ($_SESSION['customer_id']) {      
              require($template->get_template_dir('tpl_modules_oprc_comments.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_comments.php');
            }
          ?>
        </div>
      </div>
      <!-- end panel -->

      <?php
        if (OPRC_ONE_PAGE == 'true') {
          $title_checkout = TITLE_CONFIRM_CHECKOUT;
          $checkout_procedure = TEXT_CONFIRM_CHECKOUT; 
          $button = BUTTON_IMAGE_CONFIRM_ORDER;
          $button_alt = BUTTON_CONFIRM_ORDER_ALT;
          $button_class = 'button_confirm_checkout';
        } else {
          $title_checkout = TITLE_CONTINUE_CHECKOUT_CONFIRMATION;
          $checkout_procedure = TEXT_CONTINUE_CHECKOUT_CONFIRMATION;
          $button = BUTTON_IMAGE_CONTINUE_CHECKOUT;
          $button_alt = BUTTON_CONTINUE_ALT;
          $button_class = 'button_continue_checkout';
        }
      ?>
      
    </div>
  </div>
