<?php
if ($_SESSION['cart']->count_contents() > 0 && OPRC_NOACCOUNT_SWITCH == 'true' && OPRC_NOACCOUNT_ONLY_SWITCH == 'false') {
?>
<?php
  if(OPRC_NOACCOUNT_VIRTUAL == 'true' || $_SESSION['cart']->get_content_type() == 'physical') {
    if (OPRC_COWOA_FIELD_TYPE == 'button') {
    ?>
      <div id="cowoaOff" class="cowoaSwitch<?php echo ($_GET['type'] != 'cowoa' ? ' hiddenField' : ''); ?>">
        <div class="boxContents">
          <div class="information"><?php echo TEXT_RATHER_COWOA; ?></div>
          <div id="no_account_switch" class="buttonRow forward">
            <a href="<?php echo zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, 'type=cowoa&hideregistration=true', 'SSL'); ?>" class="disableLink"><?php echo zen_image_button(BUTTON_IMAGE_CONTINUE, BUTTON_CONTINUE_ALT); ?></a>
          </div>
        </div>
      </div>
      <div id="cowoaOn" class="cowoaSwitch<?php echo ($_GET['type'] == 'cowoa' ? ' hiddenField' : ''); ?>">
        <div class="boxContents">
          <div class="information"><?php echo TEXT_NEW_CUSTOMER_INTRODUCTION; ?></div>
          <div id="register_switch" class="buttonRow forward">
            <a href="<?php echo zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, 'hideregistration=true', 'SSL'); ?>" class="disableLink"><?php echo zen_image_button(BUTTON_IMAGE_CONTINUE, BUTTON_CONTINUE_ALT); ?></a>
          </div>
        </div>
      </div>
    <?php
    } elseif (OPRC_COWOA_FIELD_TYPE == 'checkbox') { // checkbox
      echo '<div class="nmx-box--bg nmx-box--cowoa">
                  <div class="custom-control custom-checkbox">
                      ' . zen_draw_checkbox_field('js-cowoa-checkbox', '', (OPRC_NOACCOUNT_DEFAULT == 'true' ? false : true), 'id="cowoa-checkbox"') . '
                      ' . zen_draw_checkbox_field('cowoa-checkbox', (OPRC_NOACCOUNT_DEFAULT == 'true' ? 'true' : 'false'), true, ' class="guest-default-hidden js-guest-default-hidden"') . '
                      <label class="checkboxLabel" for="cowoa-checkbox"><a href="' . zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL') . '#hideregistration_guest" class="disableLink">' . TEXT_COWOA_UNCHECKED . '</a></label>
                  </div>
                </div>';
      echo '<noscript class="nmx-box--bg"><p><label class="checkboxLabel"><a href="' . zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, '', 'SSL') . '">' . TEXT_NEW_CUSTOMER_INTRODUCTION . '</a></label></p></noscript>';
    } elseif (OPRC_COWOA_FIELD_TYPE == 'radio') {
        echo '<div class="nmx-box--cowoa nmx-box--bg">';
      echo '  <div class="custom-control custom-radio">' . zen_draw_radio_field('cowoa-radio', 'on', (OPRC_NOACCOUNT_DEFAULT == 'true' ? true : false),'id="cowoa-radio-on" onclick="this.blur();"') . '<label for="cowoa-radio-on"><a href="' . zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, "type=cowoa", "SSL") . '#hideregistration_guest" class="disableLink">' . TEXT_COWOA_CHECKED . '</a></label></div>';
      echo '  <div class="custom-control custom-radio">' . zen_draw_radio_field('cowoa-radio', 'off', (OPRC_NOACCOUNT_DEFAULT != 'true' ? true : false), 'id="cowoa-radio-off" onclick="this.blur();"') . '<label for="cowoa-radio-off"><a href="' . zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, "", "SSL") . '#hideregistration_guest" class="disableLink">' . TEXT_NEW_CUSTOMER_INTRODUCTION . '</a></label></div>';
      echo '</div>';
    }
  }
}