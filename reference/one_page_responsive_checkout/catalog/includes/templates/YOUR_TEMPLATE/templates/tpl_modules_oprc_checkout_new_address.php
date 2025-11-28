<?php
/**
 * Module Template
 *
 * Allows entry of new addresses during checkout stages
 *
 * @package templateSystem
 * @copyright Copyright 2003-2006 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: tpl_modules_checkout_new_address.php 3 2012-07-08 21:11:34Z numinix $
 */
?>
<!-- nmx panel -->
<div class="nmx-panel nmx-tab-content" id="nmx-panel-new-address">

  <!-- panel head -->
  <?php if (!$_SESSION['cowoa'] && zen_count_customer_address_book_entries() > 1) { ?>
    <div class="nmx-panel-head">
      <?php echo TITLE_PLEASE_SELECT; ?>
    </div>
  <?php } ?>
  <!-- end panel head -->

  <!-- panel body -->
  <div class="nmx-panel-body">
    <div id="checkoutNewAddress"<?php echo ($_SESSION['cowoa'] || zen_count_customer_address_book_entries() <= 1 ? ' class="newAddressOnly"' : '')?>>

        <div id="newAddressContainer" class="changeAddressFormContainer">
          <div id="addressFields">
            <div class="nmx-form-group nmx-row">
              <div class="nmx-col-12">
                <label title="address_title"><?php echo ENTRY_ADDRESS_TITLE; ?></label>
                <?php echo zen_draw_input_field('address_title', '', zen_set_field_length(TABLE_ADDRESS_BOOK, 'address_title', '40') . ' id="address_title"'); ?>
              </div>
            </div>
            <?php
              if (ACCOUNT_GENDER == 'true') {
            ?>
            <?php echo '<div class="nmx-form-group-gender">
                          <div class="custom-control custom-checkbox">
                            ' . zen_draw_radio_field('gender', 'm', '', 'id="gender-male"') . '<label for="gender-male">' . MALE . '</label>
                          </div>
                          <div class="custom-control custom-checkbox">
                            ' . zen_draw_radio_field('gender', 'f', '', 'id="gender-female"') . '<label for="gender-female">' . FEMALE . '</label>
                          </div>
                        </div>'; ?>
            <?php
              }
            ?>

            <div class="nmx-row nmx-form-group">
              <div class="nmx-col-6">
                <label for="firstname"><?php echo ENTRY_FIRST_NAME . (zen_not_null(ENTRY_FIRST_NAME_TEXT) ? '<span class="alert">' . ENTRY_FIRST_NAME_TEXT . '</span>': ''); ?></label>
                <?php echo zen_draw_input_field('firstname', '', zen_set_field_length(TABLE_CUSTOMERS, 'customers_firstname', '40') . ' id="firstname"'); ?>
              </div>
              

              <div class="nmx-col-6">
                <label for="lastname"><?php echo ENTRY_LAST_NAME . (zen_not_null(ENTRY_LAST_NAME_TEXT) ? '<span class="alert">' . ENTRY_LAST_NAME_TEXT . '</span>': ''); ?></label>
                <?php echo zen_draw_input_field('lastname', '', zen_set_field_length(TABLE_CUSTOMERS, 'customers_lastname', '40') . ' id="lastname"'); ?>
              </div>
            </div>


            <?php
              if (ACCOUNT_COMPANY == 'true') {
            ?>
            <div class="nmx-form-group nmx-row">
              <div class="nmx-col-12">
                <label for="company"><?php echo ENTRY_COMPANY . (zen_not_null(ENTRY_COMPANY_TEXT) ? '<span class="alert">' . ENTRY_COMPANY_TEXT . '</span>': ''); ?></label>
                <?php echo zen_draw_input_field('company', '', zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_company', '40') . ' id="company"'); ?>
              </div>
            </div>
            <?php
              }
            ?>

            <div class="nmx-row nmx-form-group">
              <div class="nmx-col-6">
                <label for="street-address"><?php echo ENTRY_STREET_ADDRESS . (zen_not_null(ENTRY_STREET_ADDRESS_TEXT) ? '<span class="alert">' . ENTRY_STREET_ADDRESS_TEXT . '</span>': ''); ?></label>
                <?php echo zen_draw_input_field('street_address', '', zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_street_address', '40') . ' id="street-address"'); ?>
              </div>
              <?php
                if (ACCOUNT_SUBURB == 'true') {
              ?>
                <div class="nmx-col-6">
                  <label for="suburb"><?php echo ENTRY_SUBURB . (zen_not_null(ENTRY_SUBURB_TEXT) ? '<span class="alert">' . ENTRY_SUBURB_TEXT . '</span>': ''); ?></label>
                  <?php echo zen_draw_input_field('suburb', '', zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_suburb', '40') . ' id="suburb"'); ?>
                </div>
              <?php
                }
              ?>
            </div>

            

            <div class="nmx-row nmx-form-group">
              <div class="nmx-col-6">
                <label for="country"><?php echo ENTRY_COUNTRY . (zen_not_null(ENTRY_COUNTRY_TEXT) ? '<span class="alert">' . ENTRY_COUNTRY_TEXT . '</span>': ''); ?></label>
                <?php echo zen_get_country_list('zone_country_id', $selected_country, 'id="country" ' . ($flag_show_pulldown_states == true ? 'onchange="update_zone(this.form);"' : '')); ?>
              </div>

              <div class="nmx-col-6">
                <label for="city"><?php echo ENTRY_CITY . (zen_not_null(ENTRY_CITY_TEXT) ? '<span class="alert">' . ENTRY_CITY_TEXT . '</span>': ''); ?></label>
                <?php echo zen_draw_input_field('city', '', zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_city', '40') . ' id="city"'); ?>
              </div>
            </div>
            
            <div class="nmx-form-group nmx-row">
              <?php
                if (ACCOUNT_STATE == 'true') {
              ?>
                <div class="nmx-col-6">
                  <?php 
                    if ($flag_show_pulldown_states == true) {
                      $state_hidden = 'nmx-hidden';
                  ?>
                    <div class="">
                      <label for="stateZone" id="zoneLabel"><?php echo ENTRY_STATE . (zen_not_null(ENTRY_STATE_TEXT) ? '<span class="alert">' . ENTRY_STATE_TEXT . '</span>': ''); ?></label>
                      <?php echo zen_draw_pull_down_menu('zone_id', zen_prepare_country_zones_pull_down($selected_country), $zone_id, 'id="stateZone"'); ?>
                    </div>
                    <br class="clearBoth nmx-hidden" id="stBreak" />
                  <?php } ?>
                  <div class="nmx-form-group">
                    <label class="<?php echo $state_hidden ?>" for="state" id="stateLabel"><?php echo $state_field_label; ?></label>
                    <?php
                        echo zen_draw_input_field('state', '', zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_state', '40') . ' id="state"');
                        if ($flag_show_pulldown_states == false) {
                          echo zen_draw_hidden_field('zone_id', $zone_name, ' ');
                        }
                    ?>
                  </div>
                </div>
              <?php
                }
              ?>
              
              <div class="nmx-col-6">
                <label for="postcode"><?php echo ENTRY_POST_CODE . (zen_not_null(ENTRY_POST_CODE_TEXT) ? '<span class="alert">' . ENTRY_POST_CODE_TEXT . '</span>': ''); ?></label>
                <?php echo zen_draw_input_field('postcode', '', zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_postcode', '40') . ' id="postcode"'); ?>
              </div>
            </div>

            <?php if (ACCOUNT_TELEPHONE == 'true' || ACCOUNT_TELEPHONE_SHIPPING == 'true') { ?>
            <div class="nmx-form-group nmx-row">
              <div class="nmx-col-6">
                <label for="telephone"><?php echo ENTRY_TELEPHONE . (zen_not_null(ENTRY_TELEPHONE_NUMBER_TEXT) ? '<span class="alert">' . ENTRY_TELEPHONE_NUMBER_TEXT . '</span>': ''); ?></label>
                <?php echo zen_draw_input_field('telephone', '', zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_telephone', '40') . ' id="telephone"'); ?>
              </div>
            </div>
            <?php } ?>

          </div>
          <div class="nmx-buttons">
            <span id="js-submit-new-address">
              <?php echo zen_draw_hidden_field('action', 'submit') . (OPRC_CSS_BUTTONS == 'true' ? zenCssButton(BUTTON_IMAGE_CONTINUE, BUTTON_CONTINUE_ALT, 'submit', 'button_continue') : zen_image_submit(BUTTON_IMAGE_CONTINUE, BUTTON_CONTINUE_ALT)); ?>
            </span>
            <span class="nmx-button-helper">
              <?php echo TITLE_CONTINUE_CHECKOUT_PROCEDURE . '<br />' . TEXT_CONTINUE_CHECKOUT_PROCEDURE; ?></span>
          
            <?php
              if ($process == true) {
            ?>
              <?php echo '<a href="' . zen_href_link(FILENAME_CHECKOUT_SHIPPING_ADDRESS, '', 'SSL') . '">' . (OPRC_CSS_BUTTONS == 'true' ? zenCssButton(BUTTON_IMAGE_BACK, BUTTON_BACK_ALT, 'button', 'button_back') : zen_image_button(BUTTON_IMAGE_BACK, BUTTON_BACK_ALT)) . '</a>'; ?>
            <?php
              }
            ?>
          </div>

      </div>
    </div>
  </div>
  <!-- end panel body -->

</div>
<!-- end nmx panel -->