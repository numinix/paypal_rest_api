<?php if(!isset($_SESSION['customer_id']) && $_SESSION['customer_id'] == "") {?>
<div class="nmx-fw" id="shippingAddressContainer" class="nmx-box">
  <h3><?php echo TABLE_HEADING_SHIPPING_ADDRESS; ?></h3>
  <?php if (OPRC_SHIPPING_ADDRESS == 'true' && $_SESSION['cart']->get_content_type() != 'virtual') echo '<div id="copyBillingOption" class="nmx-box nmx-box--bg"><div class="custom-control custom-checkbox">' . zen_draw_checkbox_field('shippingAddress', '1', $shippingAddress, 'class="nmx-fw" id="shippingAddress-checkbox"') . '<label for="shippingAddress-checkbox">' . ENTRY_COPYBILLING  . '</label></div></div>'; ?>
  <div class="nmx-fw" id="shippingField" class="nmx-box" <?php if($shippingAddress) echo 'style="display: none;"'; ?>>   
      <div class="nmx-form-address">
        <?php
          if (ACCOUNT_GENDER == 'true') {
        ?>
        <div class="nmx-form-group nmx-form-group-gender">
          <?php echo '<div class="custom-control custom-radio">' . zen_draw_radio_field('gender_shipping', 'm', ($_SESSION['gender_shipping'] == 'm' ? true : false), 'id="gender-male_shipping"') . '<label for="gender-male_shipping">' . MALE . '</label></div>' . '<div class="custom-control custom-radio">' .zen_draw_radio_field('gender_shipping', 'f', ($_SESSION['gender_shipping'] == 'f' ? true : false), 'id="gender-female_shipping"') . '<label for="gender-female_shipping">' . FEMALE . '</label></div>' . (zen_not_null(ENTRY_GENDER_TEXT) ? '<span class="alert">' . ENTRY_GENDER_TEXT . '</span>': ''); ?>
          <?php if ($messageStack->size('gender_shipping') > 0) { echo '<div class="disablejAlert alert validation">'; echo $messageStack->output('gender_shipping'); echo '</div>'; } ?>
        </div>
        <?php
          }
        ?>
        <div class="nmx-row nmx-form-group">
          <div class="nmx-col-6">
            <label for="firstname_shipping"><?php echo ENTRY_FIRST_NAME . (zen_not_null(ENTRY_FIRST_NAME_TEXT) ? '<span class="alert">' . ENTRY_FIRST_NAME_TEXT . '</span>': ''); ?></label>
            <?php echo zen_draw_input_field('firstname_shipping', $_SESSION['firstname_shipping'], zen_set_field_length(TABLE_CUSTOMERS, 'customers_firstname', '40') . ' class="nmx-fw" id="firstname_shipping"'); ?>
            <?php if ($messageStack->size('firstname_shipping') > 0) { echo '<div class="disablejAlert alert validation">'; echo $messageStack->output('firstname_shipping'); echo '</div>'; } ?>
          </div>  
          <div class="nmx-col-6">
            <label for="lastname_shipping"><?php echo ENTRY_LAST_NAME . (zen_not_null(ENTRY_LAST_NAME_TEXT) ? '<span class="alert">' . ENTRY_LAST_NAME_TEXT . '</span>': ''); ?></label>
            <?php echo zen_draw_input_field('lastname_shipping', $_SESSION['lastname_shipping'], zen_set_field_length(TABLE_CUSTOMERS, 'customers_lastname', '40') . ' class="nmx-fw" id="lastname_shipping"'); ?>
            <?php if ($messageStack->size('lastname_shipping') > 0) { echo '<div class="disablejAlert alert validation">'; echo $messageStack->output('lastname_shipping'); echo '</div>'; } ?> 
          </div>
        </div>
        <?php
          if (ACCOUNT_COMPANY == 'true') {
        ?>
        <div class="nmx-row nmx-form-group">
          <div class="nmx-col-12"> 
            <label for="company_shipping"><?php echo ENTRY_COMPANY . (zen_not_null(ENTRY_COMPANY_TEXT) ? '<span class="alert">' . ENTRY_COMPANY_TEXT . '</span>': ''); ?></label>
            <?php echo zen_draw_input_field('company_shipping', $_SESSION['company_shipping'], zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_company', '40') . ' class="nmx-fw" id="company_shipping"'); ?>
            <?php if ($messageStack->size('company_shipping') > 0) { echo '<div class="disablejAlert alert validation">'; echo $messageStack->output('company_shipping'); echo '</div>'; } ?>
          </div>
        </div>
        <?php
          }
        ?>
        <div class="nmx-form-group nmx-row">
          <div class="nmx-col-6">
            <label for="street-address_shipping"><?php echo ENTRY_STREET_ADDRESS . (zen_not_null(ENTRY_STREET_ADDRESS_TEXT) ? '<span class="alert">' . ENTRY_STREET_ADDRESS_TEXT . '</span>': ''); ?></label>
            <?php echo zen_draw_input_field('street_address_shipping', $_SESSION['street_address_shipping'], zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_street_address', '40') . ' class="nmx-fw" id="street-address_shipping"'); ?>
            <?php if ($messageStack->size('street_address_shipping') > 0) { echo '<div class="disablejAlert alert validation">'; echo $messageStack->output('street_address_shipping'); echo '</div>'; } ?>
          </div>
          <?php
            if (ACCOUNT_SUBURB == 'true') {
          ?>
            <div class="nmx-col-6">
              <label for="suburb_shipping"><?php echo ENTRY_SUBURB . (zen_not_null(ENTRY_SUBURB_TEXT) ? '<span class="alert">' . ENTRY_SUBURB_TEXT . '</span>': ''); ?></label>
              <?php echo zen_draw_input_field('suburb_shipping', $_SESSION['suburb_shipping'], zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_suburb', '40') . ' class="nmx-fw" id="suburb_shipping"'); ?>
              <?php if ($messageStack->size('suburb_shipping') > 0) { echo '<div class="disablejAlert alert validation">'; echo $messageStack->output('suburb_shipping'); echo '</div>'; } ?>
            </div>
          <?php
            }
          ?>
        </div>
        <div class="nmx-row nmx-form-group">
          <!-- country -->
          <?php
            if ($disable_country == true) {
              $addclass = "hiddenField";
            }
          ?>
          <div class="nmx-col-6 <?php echo $addclass; ?>">
            <label for="country_shipping"><?php echo ENTRY_COUNTRY . (zen_not_null(ENTRY_COUNTRY_TEXT) ? '<span class="alert">' . ENTRY_COUNTRY_TEXT . '</span>': ''); ?></label>
            <?php echo zen_get_country_list('zone_country_id_shipping', $selected_country_shipping, 'class="nmx-fw" id="country_shipping" ' . ($flag_show_pulldown_states_shipping == true ? 'onchange="update_zone_shipping(this.form);"' : '')); ?>
            <?php if ($messageStack->size('zone_country_id_shipping') > 0) { echo '<div class="disablejAlert alert validation">'; echo $messageStack->output('zone_country_id_shipping'); echo '</div>'; } ?>
          </div>
          <!-- end/country -->
          <div class="nmx-col-6">
            <label for="city_shipping"><?php echo ENTRY_CITY . (zen_not_null(ENTRY_CITY_TEXT) ? '<span class="alert">' . ENTRY_CITY_TEXT . '</span>': ''); ?></label>
            <?php echo zen_draw_input_field('city_shipping', $_SESSION['city_shipping'], zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_city', '40') . ' class="nmx-fw" id="city_shipping"'); ?>
            <?php if ($messageStack->size('city_shipping') > 0) { echo '<div class="disablejAlert alert validation">'; echo $messageStack->output('city_shipping'); echo '</div>'; } ?>
          </div>
        </div>
        <div class="nmx-row nmx-form-group">
          <div class="nmx-col-6">
            <?php
              if (ACCOUNT_STATE == 'true') {
                if ($flag_show_pulldown_states_shipping == true) {
            ?>
              <label for="zone_id_shipping" class="nmx-fw" id="zone_id_shipping"><?php echo ENTRY_STATE . (zen_not_null(ENTRY_STATE_TEXT) ? '<span class="alert">' . ENTRY_STATE_TEXT . '</span>': ''); ?></label>
              <?php
                echo zen_draw_pull_down_menu('zone_id_shipping', zen_prepare_country_zones_pull_down($selected_country_shipping), $zone_id_shipping, 'class="nmx-fw" id="zone_id_shipping"');
              ?>
              <?php if ($messageStack->size('zone_id_shipping') > 0) { echo '<div class="disablejAlert alert validation">'; echo $messageStack->output('zone_id_shipping'); echo '</div>'; } ?>
            <?php
              }
            ?>
            <div class="nmx-mt0 nmx-ha">
              <label for="state_shipping" class="nmx-fw" id="stateLabelShipping"><?php echo $state_field_label_shipping; ?></label>
              <?php
                echo zen_draw_input_field('state_shipping', $state_shipping, zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_state', '40') . ' class="nmx-fw" id="state_shipping"');
                echo '';
                if ($flag_show_pulldown_states_shipping == false) {
                  echo zen_draw_hidden_field('zone_id_shipping', $zone_name_shipping, ' ');
                }
              ?>
              <?php if ($messageStack->size('state_shipping') > 0) { echo '<div class="disablejAlert alert validation">'; echo $messageStack->output('state_shipping'); echo '</div>'; } ?>
            </div>
            <?php
              }
            ?>
          </div>
          <div class="nmx-col-6">
            <label for="postcode_shipping"><?php echo ENTRY_POST_CODE . (zen_not_null(ENTRY_POST_CODE_TEXT) ? '<span class="alert">' . ENTRY_POST_CODE_TEXT . '</span>': ''); ?></label>        
            <?php echo zen_draw_input_field('postcode_shipping', $_SESSION['postcode_shipping'], zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_postcode', '40') . ' class="nmx-fw" id="postcode_shipping"'); ?>
            <?php if ($messageStack->size('postcode_shipping') > 0) { echo '<div class="disablejAlert alert validation">'; echo $messageStack->output('postcode_shipping'); echo '</div>'; } ?>
          </div>
        </div>  
       
        <?php if (ACCOUNT_TELEPHONE_SHIPPING == 'true') { ?>
        <div class="nmx-row nmx-form-group">
          <div class="nmx-col-6">
            <label for="telephone_shipping"><?php echo ENTRY_TELEPHONE_NUMBER . (zen_not_null(ENTRY_TELEPHONE_NUMBER_TEXT) ? '<span class="alert">' . ENTRY_TELEPHONE_NUMBER_TEXT . '</span>': ''); ?></label>
            <?php echo zen_draw_input_field('telephone_shipping', $_SESSION['telephone_shipping'], zen_set_field_length(TABLE_CUSTOMERS, 'customers_telephone_shipping', '40') . ' class="nmx-fw" id="telephone_shipping"'); ?>
            <?php if ($messageStack->size('telephone_shipping') > 0) { echo '<div class="disablejAlert alert validation">'; echo $messageStack->output('telephone_shipping'); echo '</div>'; } ?>
          </div>
        </div>
        <?php } ?>
      </div>
  </div>

</div>
<?php 
}
?>