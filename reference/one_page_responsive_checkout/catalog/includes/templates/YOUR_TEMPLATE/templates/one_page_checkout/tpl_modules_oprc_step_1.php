<?php
  //$columns = '';
  //if (OPRC_NOACCOUNT_SWITCH == 'false' || !$_SESSION['cart']->count_contents() > 0 || $_SESSION['cart']->get_content_type() != 'physical') {
    $columns = '2';
    $widthClass = 'width50';
  //} else {
    //$widthClass = 'width33';
  //}
?>

<div id="hideRegistration" <?php echo ($columns == '2') ? 'class="emptyCart"' : ''; ?> <?php if (isset($_GET['step']) && $_GET['step'] == 2) echo ' style="display: none;"'; ?>>

    <!-- panel -->
    <div class="nmx-panel">

      <!-- panel head -->
      <div class="nmx-panel-head current">
        <?php if((OPRC_NOACCOUNT_SWITCH == 'true' && $_SESSION['cart']->count_contents() > 0 && ($_SESSION['cart']->get_content_type() == 'physical' || OPRC_NOACCOUNT_VIRTUAL == 'true') && OPRC_NOACCOUNT_DEFAULT == 'true')) { ?>
          <?php echo HEADING_STEP_1_GUEST; ?>
        <?php } else { ?>
          <?php echo HEADING_STEP_1; ?>
        <?php } ?>
      </div>
      <!-- end panel head -->

      <!-- panel body -->
      <div class="nmx-panel-body nmx-cf">
        
        <!-- row -->
        <div class="nmx-row nmx-cf nmx-row--login">
          
          <!-- col-6 -->
          <div class="nmx-col-6">
            <?php require($template->get_template_dir('tpl_modules_oprc_login.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_login.php'); ?>
            <?php if (OPRC_NOACCOUNT_SWITCH == 'true') { ?>
              <?php echo TEXT_COWOA_LOGIN; ?>
            <?php } ?>
          </div>
          <!-- end col-6 -->

          <!-- col-6 -->
          <div class="nmx-col-6">
              
              <!-- option 1 -->
              <?php if (OPRC_NOACCOUNT_SWITCH == 'false' || !$_SESSION['cart']->count_contents() > 0 || ($_SESSION['cart']->get_content_type() != 'physical' && OPRC_NOACCOUNT_VIRTUAL == 'false') || OPRC_NOACCOUNT_DEFAULT != 'true') { ?>
                <h3 class="nmx-mt0"><?php echo HEADING_NEW_CUSTOMERS; ?></h3>
                <div class="nmx-form">
                  <?php echo HIDEREGISTRATION_CREATE_ACCOUNT; ?>
                  <?php echo TEXT_ACCOUNT_BENEFITS; ?>
                  <?php echo zen_draw_form('hideregistration_register', zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, 'type=register&hideregistration=true&step=2', 'SSL'), 'post', 'id="hideregistration_register"'); ?>
                    
                    <!-- email -->
                    <div class="nmx-form-group">
                      <label for="hide_email_address_register"><?php echo ENTRY_EMAIL_ADDRESS; ?></label>
                      <?php echo zen_draw_input_field('hide_email_address_register', $_SESSION['email_address_register'], zen_set_field_length(TABLE_CUSTOMERS, 'customers_email_address', '40') . ' class="nmx-form-control"'); ?>
                    </div>
                    <!-- end email -->

                    <!-- confirm email -->
                    <?php if (OPRC_CONFIRM_EMAIL == 'true') { ?>
                      <div class="nmx-form-group">
                        <label for="hide_login_email_address_confirm"><?php echo ENTRY_EMAIL_ADDRESS_CONFIRM; ?></label>
                        <?php echo zen_draw_input_field('hide_email_address_confirm', $_SESSION['email_address_confirm'], zen_set_field_length(TABLE_CUSTOMERS, 'customers_email_address', '40') . ' class="nmx-form-control"'); ?>
                      </div>
                    <?php } ?>
                    <!-- end confirm email -->
                    <div class="nmx-buttons">
                      <?php echo (OPRC_CSS_BUTTONS == 'false' ? zen_image_submit(BUTTON_IMAGE_CREATE_ACCOUNT, BUTTON_CREATE_ACCOUNT_ALT) : zenCssButton(BUTTON_IMAGE_CREATE_ACCOUNT, BUTTON_CREATE_ACCOUNT_ALT, 'submit', 'button_create_account')); ?>
                    </div>
                    <?php echo zen_draw_hidden_field('checkoutType', 'account'); ?>
                  </form>
                </div>
              <!-- end option 1 -->
              
              <!-- option 2 -->
              <?php } elseif (OPRC_NOACCOUNT_SWITCH == 'true' && $_SESSION['cart']->count_contents() > 0 && ($_SESSION['cart']->get_content_type() == 'physical' || OPRC_NOACCOUNT_VIRTUAL == 'true') && OPRC_NOACCOUNT_DEFAULT == 'true') { 
              ?>
                <h3 class="nmx-mt0"><?php echo HEADING_COWOA; ?></h3>
                <div class="nmx-form">
                  <?php echo zen_draw_form('hideregistration_guest', zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, 'type=cowoa&hideregistration=true&step=2', 'SSL'), 'post', 'id="hideregistration_guest"'); ?>
                    <div class="boxContents">
                      <?php echo HIDEREGISTRATION_COWOA; ?>
                      <?php echo TEXT_COWOA_BENEFITS; ?>          
                    </div>
                    <div class="nmx-buttons">
                      <?php echo (OPRC_CSS_BUTTONS == 'false' ? zen_image_submit(BUTTON_IMAGE_CONTINUE, BUTTON_CONTINUE_ALT) : zenCssButton('', BUTTON_COWOA_ALT, 'submit', 'button_cowoa')); ?>
                      <?php echo zen_draw_hidden_field('checkoutType', 'guest'); ?>
                    </div>
                  </form>
                </div>
              <?php } ?>
              <!-- end option 2 -->

          </div>
          <!-- end col-6 -->

        </div>
        <!-- end row -->

      </div>
      <!-- end panel body -->

    </div>
    <!-- end panel -->

    <!-- panel -->
    <div class="nmx-panel">
      
      <!-- panel head -->
      <div class="nmx-panel-head">
        <?php echo ((OPRC_SHIPPING_ADDRESS == 'false' || $_SESSION['cart']->get_content_type() == 'virtual') ? HEADING_STEP_2_NO_SHIPPING : HEADING_STEP_2); ?>
      </div>
      <!-- end panel head -->

    </div>
    <!-- end panel -->

    <!-- panel -->
    <div class="nmx-panel">
      
      <!-- panel head -->
      <div class="nmx-panel-head">
        <?php echo ((OPRC_SHIPPING_ADDRESS == 'false' || $_SESSION['cart']->get_content_type() == 'virtual') ?  HEADING_STEP_3_NO_SHIPPING : HEADING_STEP_3); ?>
      </div>
      <!-- end panel head -->

    </div>
    <!-- end panel -->

</div>
<?php
  if ($_GET['step'] == 2) {
?>
  <script type="text/javascript"><!--//
  document.addEventListener('DOMContentLoaded', function() {
    jQuery('#hideRegistration').hide();
  });
  //--></script>
<?php 
  }
?>