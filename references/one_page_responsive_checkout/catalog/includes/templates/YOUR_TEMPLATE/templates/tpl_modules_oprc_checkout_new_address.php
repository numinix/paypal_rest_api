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
<?php
  $oprcAddressLookupManager = function_exists('oprc_address_lookup_manager')
    ? oprc_address_lookup_manager()
    : null;
  $oprcAddressLookupEnabled = (is_object($oprcAddressLookupManager)
    && method_exists($oprcAddressLookupManager, 'isEnabled')
    && $oprcAddressLookupManager->isEnabled());
  $hasTelephoneField = (ACCOUNT_TELEPHONE == 'true' || ACCOUNT_TELEPHONE_SHIPPING == 'true');
  // Ensure the submit button spans the full width so it can align to the
  // bottom-right corner of the modal consistently across tabs.
  $addressFormActionsWidthClass = 'address-form-field--full';
?>
<!-- nmx panel -->
<div class="nmx-panel nmx-tab-content" id="nmx-panel-new-address">

  <!-- panel body -->
  <div class="nmx-panel-body">
    <div id="checkoutNewAddress"<?php echo ((isset($_SESSION['cowoa']) && $_SESSION['cowoa']) || zen_count_customer_address_book_entries() <= 1 ? ' class="newAddressOnly"' : '')?>>

        <div id="newAddressContainer" class="changeAddressFormContainer">
          <div id="addressFields" class="address-form-grid">
            <div class="nmx-form-group address-form-field address-form-field--full">
              <label title="address_title"><?php echo ENTRY_ADDRESS_TITLE; ?></label>
              <?php echo zen_draw_input_field('address_title', '', zen_set_field_length(TABLE_ADDRESS_BOOK, 'address_title', '40') . ' id="address_title"'); ?>
            </div>
            <?php
              if (ACCOUNT_GENDER == 'true') {
            ?>
            <div class="nmx-form-group address-form-field address-form-field--full address-form-field--gender">
              <div class="custom-control custom-checkbox">
                <?php echo zen_draw_radio_field('gender', 'm', '', 'id="gender-male"'); ?><label for="gender-male"><?php echo MALE; ?></label>
              </div>
              <div class="custom-control custom-checkbox">
                <?php echo zen_draw_radio_field('gender', 'f', '', 'id="gender-female"'); ?><label for="gender-female"><?php echo FEMALE; ?></label>
              </div>
            </div>
            <?php
              }
            ?>

            <div class="nmx-form-group address-form-field address-form-field--half">
              <label for="firstname"><?php echo ENTRY_FIRST_NAME . oprc_required_indicator(ENTRY_FIRST_NAME_MIN_LENGTH, ENTRY_FIRST_NAME_TEXT); ?></label>
              <?php echo zen_draw_input_field('firstname', '', zen_set_field_length(TABLE_CUSTOMERS, 'customers_firstname', '40') . ' id="firstname"'); ?>
            </div>

            <div class="nmx-form-group address-form-field address-form-field--half">
              <label for="lastname"><?php echo ENTRY_LAST_NAME . oprc_required_indicator(ENTRY_LAST_NAME_MIN_LENGTH, ENTRY_LAST_NAME_TEXT); ?></label>
              <?php echo zen_draw_input_field('lastname', '', zen_set_field_length(TABLE_CUSTOMERS, 'customers_lastname', '40') . ' id="lastname"'); ?>
            </div>

            <?php if ($oprcAddressLookupEnabled) { ?>
            <div class="nmx-form-group address-form-field address-form-field--half address-form-field--postcode">
              <label for="postcode"><?php echo ENTRY_POST_CODE . oprc_required_indicator(ENTRY_POSTCODE_MIN_LENGTH, ENTRY_POST_CODE_TEXT); ?></label>
              <div class="oprc-address-lookup__control">
                <?php echo zen_draw_input_field('postcode', '', zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_postcode', '40') . ' id="postcode"'); ?>
                <button type="button" class="btn btn-secondary oprc-address-lookup__trigger js-oprc-address-lookup-trigger"><?php echo TEXT_OPRC_ADDRESS_LOOKUP_BUTTON; ?></button>
              </div>
            </div>
            <div class="nmx-form-group address-form-field address-form-field--full address-form-field--postcode-results">
              <div class="oprc-address-lookup__results js-oprc-address-lookup-results" role="status" aria-live="polite"></div>
            </div>
            <?php } else { ?>
            <div class="nmx-form-group address-form-field address-form-field--half address-form-field--postcode">
              <label for="postcode"><?php echo ENTRY_POST_CODE . oprc_required_indicator(ENTRY_POSTCODE_MIN_LENGTH, ENTRY_POST_CODE_TEXT); ?></label>
              <div class="oprc-address-lookup__control">
                <?php echo zen_draw_input_field('postcode', '', zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_postcode', '40') . ' id="postcode"'); ?>
              </div>
            </div>
            <?php } ?>

            <?php
              if (ACCOUNT_COMPANY == 'true') {
            ?>
            <div class="nmx-form-group address-form-field address-form-field--full">
              <label for="company"><?php echo ENTRY_COMPANY . oprc_required_indicator(ENTRY_COMPANY_MIN_LENGTH, ENTRY_COMPANY_TEXT); ?></label>
              <?php echo zen_draw_input_field('company', '', zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_company', '40') . ' id="company"'); ?>
            </div>
            <?php
              }
            ?>

            <div class="nmx-form-group address-form-field address-form-field--half">
              <label for="street-address"><?php echo ENTRY_STREET_ADDRESS . oprc_required_indicator(ENTRY_STREET_ADDRESS_MIN_LENGTH, ENTRY_STREET_ADDRESS_TEXT); ?></label>
              <?php echo zen_draw_input_field('street_address', '', zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_street_address', '40') . ' id="street-address"'); ?>
            </div>
            <?php
                if (ACCOUNT_SUBURB == 'true') {
            ?>
            <div class="nmx-form-group address-form-field address-form-field--half">
              <label for="suburb"><?php echo ENTRY_SUBURB . oprc_required_indicator(oprc_get_min_length('ENTRY_SUBURB_MIN_LENGTH'), ENTRY_SUBURB_TEXT); ?></label>
              <?php echo zen_draw_input_field('suburb', '', zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_suburb', '40') . ' id="suburb"'); ?>
            </div>
            <?php
                }
            ?>

            <div class="nmx-form-group address-form-field address-form-field--half">
              <label for="country"><?php echo ENTRY_COUNTRY . (zen_not_null(ENTRY_COUNTRY_TEXT) ? '<span class="alert">' . ENTRY_COUNTRY_TEXT . '</span>': ''); ?></label>
              <?php echo zen_oprc_get_country_list('zone_country_id', $selected_country, 'id="country" ' . ($flag_show_pulldown_states == true ? 'onchange="update_zone(this.form);"' : '')); ?>
            </div>

            <div class="nmx-form-group address-form-field address-form-field--half">
              <label for="city"><?php echo ENTRY_CITY . oprc_required_indicator(ENTRY_CITY_MIN_LENGTH, ENTRY_CITY_TEXT); ?></label>
              <?php echo zen_draw_input_field('city', '', zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_city', '40') . ' id="city"'); ?>
            </div>

            <?php
                if (ACCOUNT_STATE == 'true') {
            ?>
            <div class="nmx-form-group address-form-field address-form-field--half address-form-field--state">
              <?php
                    if ($flag_show_pulldown_states == true) {
                      $state_hidden = 'nmx-hidden';
              ?>
              <div class="address-form-field__state-select">
                <label for="stateZone" id="zoneLabel"><?php echo ENTRY_STATE . oprc_required_indicator(ENTRY_STATE_MIN_LENGTH, ENTRY_STATE_TEXT); ?></label>
                <?php echo zen_draw_pull_down_menu('zone_id', zen_prepare_country_zones_pull_down($selected_country), $zone_id, 'id="stateZone"'); ?>
              </div>
              <br class="clearBoth nmx-hidden" id="stBreak" />
              <?php } ?>
              <div class="nmx-form-group address-form-field__state-input">
                <label class="<?php if(isset($state_hidden)) echo $state_hidden; ?>" for="state" id="stateLabel"><?php echo $state_field_label; ?></label>
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

            <?php if ($hasTelephoneField) { ?>
            <div class="nmx-form-group address-form-field address-form-field--half">
              <label for="telephone"><?php echo ENTRY_TELEPHONE . oprc_required_indicator(ENTRY_TELEPHONE_MIN_LENGTH, ENTRY_TELEPHONE_NUMBER_TEXT); ?></label>
              <?php echo zen_draw_input_field('telephone', '', zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_telephone', '40') . ' id="telephone"'); ?>
            </div>
            <?php } ?>

            <div class="nmx-form-group address-form-field <?php echo $addressFormActionsWidthClass; ?> address-form-actions">
              <span id="js-submit-new-address">
                <?php echo zen_draw_hidden_field('action', 'submit') . (OPRC_CSS_BUTTONS == 'true' ? zenCssButton(BUTTON_IMAGE_CONTINUE, BUTTON_CONTINUE_ALT, 'submit', 'button_continue') : zen_image_submit(BUTTON_IMAGE_CONTINUE, BUTTON_CONTINUE_ALT)); ?>
              </span>
              <span class="nmx-button-helper">
                <?php echo TITLE_CONTINUE_CHECKOUT_PROCEDURE . '<br />' . TEXT_CONTINUE_CHECKOUT_PROCEDURE; ?></span>
              <?php
                if ($process == true) {
              ?>
              <span class="address-form-actions__back">
                <?php echo '<a href="' . zen_href_link(FILENAME_CHECKOUT_SHIPPING_ADDRESS, '', 'SSL') . '">' . (OPRC_CSS_BUTTONS == 'true' ? zenCssButton(BUTTON_IMAGE_BACK, BUTTON_BACK_ALT, 'button', 'button_back') : zen_image_button(BUTTON_IMAGE_BACK, BUTTON_BACK_ALT)) . '</a>'; ?>
              </span>
              <?php
                }
              ?>
            </div>

          </div>
      </div>
    </div>
  </div>
  <!-- end panel body -->

</div>
<!-- end nmx panel -->