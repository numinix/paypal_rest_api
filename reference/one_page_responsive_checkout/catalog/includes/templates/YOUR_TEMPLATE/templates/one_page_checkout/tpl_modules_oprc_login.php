<?php if(!isset($_SESSION['customer_id']) && $_SESSION['customer_id'] == "" && OPRC_NOACCOUNT_ONLY_SWITCH != 'true') {?>

<div id="oprc_login">
  <?php echo zen_draw_form('login', zen_href_link(FILENAME_OPRC_LOGIN, '', 'SSL'), 'post', 'id="login"'); ?>
    <h3 class="nmx-mt0"><?php echo HEADING_RETURNING_CUSTOMER; ?></h3>
    <div class="nmx-form">
    <?php 
      if ($messageStack->size('login') > 0) {
        $loginErrors = true;
      } 
      ?>    
      <div class="loginIntro"><?php echo TEXT_OPRC_LOGIN_INTRO; ?></div>

      <div class="nmx-form-group">
        <label for="login-email-address"><?php echo ENTRY_EMAIL_ADDRESS; ?></label>
        <?php echo zen_draw_input_field('email_address', '', zen_set_field_length(TABLE_CUSTOMERS, 'customers_email_address', '40') . ' id="login-email-address" class="nmx-form-control ' . ($loginErrors ? ' missing' : '') . '" '); ?>
      </div>
      
      <div class="nmx-form-group">
        <label for="login-password"><?php echo ENTRY_PASSWORD; ?></label>
        <?php echo zen_draw_password_field('password', '', zen_set_field_length(TABLE_CUSTOMERS, 'customers_password') . ' id="login-password" class="nmx-form-control ' . ($loginErrors ? ' missing' : '') . '" '); ?>
      </div>

      <?php echo zen_draw_hidden_field('securityToken', $_SESSION['securityToken'], 'class="hiddenField"'); ?>
      <?php echo zen_draw_hidden_field('main_page', $_REQUEST['main_page'], 'class="hiddenField"'); ?>
      <?php echo zen_draw_hidden_field('oprcType', 'login', 'class="hiddenField"'); ?>
      <?php echo zen_draw_hidden_field('oprcaction', 'process', 'class="hiddenField"'); ?>
      <?php 
      if ($messageStack->size('login') > 0) {
        echo '<div class="disablejAlert alert loginError">';
        echo $messageStack->output('login');
        echo '</div>';
      } 
      ?>
    </div>
    <div class="nmx-buttons">
    	<?php echo (OPRC_CSS_BUTTONS == 'false' ? zen_image_submit(BUTTON_IMAGE_LOGIN, BUTTON_LOGIN_ALT) : zenCssButton(BUTTON_IMAGE_LOGIN, BUTTON_LOGIN_ALT, 'submit', 'button_login')); ?>  
      <span id="forgottenPasswordLink"><?php echo '<a href="' . zen_href_link(FILENAME_PASSWORD_FORGOTTEN, '', 'SSL') . ' #passwordForgotten">' . TEXT_PASSWORD_FORGOTTEN . '</a>'; ?></span>
      <?php
        if (OPRC_EASY_SIGNUP_AUTOMATIC_LOGIN == 'true') {
          echo '<span>
                  <label>
                    ' . zen_draw_checkbox_field('loginCookie', '1', '', 'id="loginCookie"') . ENTRY_AUTOMATIC_LOGIN . '
                  </label>
                </span>';
        } 
      ?>      
    </div>
  </form>
</div>
<?php } ?>
