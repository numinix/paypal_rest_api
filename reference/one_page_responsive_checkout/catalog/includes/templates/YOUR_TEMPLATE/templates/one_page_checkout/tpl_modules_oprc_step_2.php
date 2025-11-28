<?php
  // column configuration
  $columns = '';
  if (OPRC_SHIPPING_ADDRESS == 'false' || $_SESSION['cart']->get_content_type() == 'virtual') {
    $columns = '0';
    $widthClass = 'width50';
  } else {
    $widthClass = 'width33';
  }

  if ($hideRegistration == 'true') {
    echo '<div id="easyLogin"' . ($_GET['step'] != 2 ? ' style="display: none"' : '') . '>';
  } 
?> 
<div id="column1<?php echo $columns; ?>">
  <div id="column2<?php echo $columns; ?>">       
    <?php 
      // start checkout form before first column   
      echo zen_draw_form('create_account', zen_href_link(FILENAME_OPRC_CREATE_ACCOUNT, '&step=2', 'SSL'), 'post');
    ?>

      <!-- panel -->
      <div class="nmx-panel">
          
          <!-- panel head -->
          <div class="nmx-panel-head">
            <span id="step1"><a href="<?php echo zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, 'step=1', 'SSL'); ?>"><?php echo HEADING_STEP_1; ?></a></span>
          </div>
          <!-- end panel head -->

      </div>
      <!-- end panel -->

      <!-- panel -->
      <div class="nmx-panel" id="nmx-panel-step2">
        
        <!-- panel head -->
        <div class="nmx-panel-head current">
          <span id="step2"><?php echo ((OPRC_SHIPPING_ADDRESS == 'false' || $_SESSION['cart']->get_content_type() == 'virtual') ? HEADING_STEP_2_NO_SHIPPING : HEADING_STEP_2); ?></span>
        </div>
        <!-- end panel head -->
        
        <!-- panel body -->
        <div class="nmx-panel-body nmx-cf">
          <?php if ($messageStack->size('create_account') > 0) { echo '<div class="disablejAlert registrationError">'; echo $messageStack->output('create_account'); echo '</div>'; } ?>
          <div id="oprc_column1<?php echo $columns; ?>" class="nmx-box">
            <?php echo zen_draw_hidden_field('oprcaction', 'process', 'class="hiddenField"') . zen_draw_hidden_field('email_format', $email_format, 'class="hiddenField"'); ?>
            <?php require($template->get_template_dir('tpl_modules_oprc_billing_address.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_billing_address.php'); ?>
          </div>
          <?php if ((OPRC_SHIPPING_ADDRESS == 'true' && OPRC_FORCE_SHIPPING_ADDRESS_TO_BILLING == 'false') && $_SESSION['cart']->get_content_type() != 'virtual') { ?>
          <div id="oprc_column2<?php echo $columns; ?>" class="nmx-box">
            <?php require($template->get_template_dir('tpl_modules_oprc_shipping_address.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_shipping_address.php'); ?>
          </div>
          <?php } ?>
          <?php
            require($template->get_template_dir('tpl_modules_oprc_contact.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_contact.php'); 
          ?>
          <?php
            if(OPRC_RECAPTCHA_STATUS == 'true') {
          ?>

          <!-- Google reCAPTCHA -->
          <div id="securityCheck" class="nmx-box">
            <h3><?php echo ENTRY_SECURITY_CHECK; ?></h3>
            <div class="g-recaptcha" data-sitekey="<?php echo OPRC_RECAPTCHA_KEY; ?>" data-theme="<?php echo OPRC_RECAPTCHA_THEME; ?>"></div>
          </div>
          <!-- end Google reCAPTCHA -->
          
          <?php
            }
          ?>
          <?php 
            if (DISPLAY_PRIVACY_CONDITIONS == 'true') {
          ?>
          <div id="privacyCheck" class="nmx-box">
            <h3><?php echo TABLE_HEADING_PRIVACY_CONDITIONS; ?></h3>
            <p class="information nmx-mb0"><?php echo TEXT_PRIVACY_CONDITIONS_DESCRIPTION_OPRC;?></p>
            <div class="nmx-checkbox nmx-hidden">
              <label>
                <?php echo zen_draw_checkbox_field('privacy_conditions', '1', true, 'id="privacy"');?>
                <?php echo TEXT_PRIVACY_CONDITIONS_CONFIRM;?>
              </label>
            </div>
          </div>
          <?php
            }
          ?>
          <div id="registerButton" class="nmx-box nmx-cf">
              <?php
                echo zen_draw_hidden_field('oprcType', 'register', 'class="hiddenField"');
              ?>
              <div class="nmx-pull-right"><?php echo (OPRC_CSS_BUTTONS == 'false' ? zen_image_submit(BUTTON_IMAGE_CONTINUE_CHECKOUT, BUTTON_CONTINUE_ALT) : zenCssButton(BUTTON_IMAGE_CONTINUE_CHECKOUT, BUTTON_CONTINUE_ALT, 'submit', 'button_continue_checkout')); ?></div>
              
              <?php
                if (OPRC_HIDE_REGISTRATION == 'true') {
              ?>
              <div id="hideregistrationBack" class="nmx-pull-left">
                <a class="btn btn-secondary cssButton cssButton--outline btn-outline" href="<?php echo zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL'); ?>"><?php echo BUTTON_BACK_ALT; ?></a>
              </div>
              <?php
                }
              ?>
          </div>
        </div>
        <!-- end panel body -->

      </div>
      <!-- end panel -->

      <!-- panel -->
      <div class="nmx-panel">
        
        <!-- panel head -->
        <div class="nmx-panel-head">
          <span id="step3"><?php echo ((OPRC_SHIPPING_ADDRESS == 'false' || $_SESSION['cart']->get_content_type() == 'virtual') ? HEADING_STEP_3_NO_SHIPPING : HEADING_STEP_3); ?></span>
        </div>
        <!-- end panel head -->

      </div>
      <!-- end panel -->
    
    </form>  
  </div>
</div>
<?php
  if ($hideRegistration == 'true') {
    echo '</div>';
    if ($_GET['step'] != 2) {
?>
  <script type="text/javascript"><!--//
  document.addEventListener('DOMContentLoaded', function() {
    jQuery('#easyLogin').hide();
  });
  //--></script>
<?php 
    }
  }
?>
