<?php
/**
 * Page Template
 *
 * Loaded automatically by index.php?main_page=create_account.<br />
 * Displays Create Account form.
 *
 * @package templateSystem - FEC
 * @copyright Copyright 2007-2008 Numinix Technology http://www.numinix.com
 * @copyright Copyright 2003-2007 Zen Cart Development Team
 * @copyright Portions Copyright 2007 Joseph Schilz
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: tpl_modules_oprc_billing_address.php 17 2012-09-20 16:35:14Z jono@numinix $
 */
?>
<?php
  $oprcAddressLookupManager = function_exists('oprc_address_lookup_manager')
    ? oprc_address_lookup_manager()
    : null;
  $oprcAddressLookupEnabled = (is_object($oprcAddressLookupManager)
    && method_exists($oprcAddressLookupManager, 'isEnabled')
    && $oprcAddressLookupManager->isEnabled());
?>
<?php if(!isset($_SESSION['customer_id']) || $_SESSION['customer_id'] == "") {?>
  <div id="billingAddressContainer" class="columnInner">
    <h3 class="nmx-mt0"><?php echo TABLE_HEADING_BILLING_ADDRESS; ?></h3>
    <div id="requiredText" class="alert"><?php echo TEXT_REQUIRED_INFORMATION_OPRC; ?></div>
    <div class="nmx-box">
      
      <div class="nmx-form-address">
        <?php
          if (ACCOUNT_GENDER == 'true') {
        ?>
        <div class="nmx-form-group nmx-form-group-gender">
          <?php echo '<div class="custom-control custom-radio">' . zen_draw_radio_field('gender', 'm', ($_SESSION['gender'] == 'm' ? true : false), 'id="gender-male"') . '<label for="gender-male">' . MALE . '</label></div>' . '<div class="custom-control custom-radio">' .zen_draw_radio_field('gender', 'f', ($_SESSION['gender'] == 'f' ? true : false), 'id="gender-female"') . '<label for="gender-female">' . FEMALE . '</label></div>' . (zen_not_null(ENTRY_GENDER_TEXT) ? '<span class="alert">' . ENTRY_GENDER_TEXT . '</span>': ''); ?>
          <?php if ($messageStack->size('gender') > 0) { echo '<div class="disablejAlert alert validation">'; echo $messageStack->output('gender'); echo '</div>'; } ?>
        </div>
        <?php
          }
        ?>
        <div class="nmx-row nmx-form-group">
          <div class="nmx-col-6">
            <label for="firstname"><?php echo ENTRY_FIRST_NAME . oprc_required_indicator(ENTRY_FIRST_NAME_MIN_LENGTH, ENTRY_FIRST_NAME_TEXT); ?></label>
            <?php echo zen_draw_input_field('firstname', (isset($_SESSION['firstname']) ? $_SESSION['firstname'] : ''), zen_set_field_length(TABLE_CUSTOMERS, 'customers_firstname', '40') . ' class="nmx-fw" id="firstname"'); ?>
            <?php if ($messageStack->size('firstname') > 0) { echo '<div class="disablejAlert alert validation">'; echo $messageStack->output('firstname'); echo '</div>'; } ?> 
          </div>
          <div class="nmx-col-6">
            <label for="lastname"><?php echo ENTRY_LAST_NAME . oprc_required_indicator(ENTRY_LAST_NAME_MIN_LENGTH, ENTRY_LAST_NAME_TEXT); ?></label>
            <?php echo zen_draw_input_field('lastname', (isset($_SESSION['lastname']) ? $_SESSION['lastname'] : '') , zen_set_field_length(TABLE_CUSTOMERS, 'customers_lastname', '40') . ' class="nmx-fw" id="lastname"'); ?>
            <?php if ($messageStack->size('lastname') > 0) { echo '<div class="disablejAlert alert validation">'; echo $messageStack->output('lastname'); echo '</div>'; } ?>
          </div>
        </div>
        <?php if ($oprcAddressLookupEnabled) { ?>
        <div class="nmx-row nmx-form-group">
          <div class="nmx-col-12">
            <label for="postcode"><?php echo ENTRY_POST_CODE . oprc_required_indicator(ENTRY_POSTCODE_MIN_LENGTH, ENTRY_POST_CODE_TEXT); ?></label>
            <div class="oprc-address-lookup__control">
              <?php echo zen_draw_input_field('postcode', (isset($_SESSION['postcode']) ? $_SESSION['postcode'] : ''), zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_postcode', '40') . ' class="nmx-fw" id="postcode"'); ?>
              <button type="button" class="btn btn-secondary oprc-address-lookup__trigger js-oprc-address-lookup-trigger"><?php echo TEXT_OPRC_ADDRESS_LOOKUP_BUTTON; ?></button>
            </div>
            <?php if ($messageStack->size('postcode') > 0) { echo '<div class="disablejAlert alert validation">'; echo $messageStack->output('postcode'); echo '</div>'; } ?>
            <div class="oprc-address-lookup__results js-oprc-address-lookup-results" role="status" aria-live="polite"></div>
          </div>
        </div>
        <?php } else { ?>
        <div class="nmx-row nmx-form-group">
          <div class="nmx-col-12">
            <label for="postcode"><?php echo ENTRY_POST_CODE . oprc_required_indicator(ENTRY_POSTCODE_MIN_LENGTH, ENTRY_POST_CODE_TEXT); ?></label>
            <div class="oprc-address-lookup__control">
              <?php echo zen_draw_input_field('postcode', (isset($_SESSION['postcode']) ? $_SESSION['postcode'] : ''), zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_postcode', '40') . ' class="nmx-fw" id="postcode"'); ?>
            </div>
            <?php if ($messageStack->size('postcode') > 0) { echo '<div class="disablejAlert alert validation">'; echo $messageStack->output('postcode'); echo '</div>'; } ?>
          </div>
        </div>
        <?php } ?>
        <?php
          if (ACCOUNT_COMPANY == 'true') {
        ?>
        <div class="nmx-row nmx-form-group">
          <div class="nmx-col-12">
            <label for="company"><?php echo ENTRY_COMPANY . oprc_required_indicator(ENTRY_COMPANY_MIN_LENGTH, ENTRY_COMPANY_TEXT); ?></label>
            <?php echo zen_draw_input_field('company', (isset($_SESSION['company']) ? $_SESSION['company'] : '') , zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_company', '40') . ' class="nmx-fw" id="company"'); ?>
            <?php if ($messageStack->size('company') > 0) { echo '<div class="disablejAlert alert validation">'; echo $messageStack->output('company'); echo '</div>'; } ?>
          </div>
        </div>
        <?php
          }
        ?>
        <div class="nmx-row nmx-form-group">
          <div class="nmx-col-6">
            <label for="street-address"><?php echo ENTRY_STREET_ADDRESS . oprc_required_indicator(ENTRY_STREET_ADDRESS_MIN_LENGTH, ENTRY_STREET_ADDRESS_TEXT); ?></label>
            <?php echo zen_draw_input_field('street_address', (isset($_SESSION['street_address']) ? $_SESSION['street_address'] : ''), zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_street_address', '40') . ' class="nmx-fw" id="street-address"'); ?>
            <?php if ($messageStack->size('street_address') > 0) { echo '<div class="disablejAlert alert validation">'; echo $messageStack->output('street_address'); echo '</div>'; } ?>
          </div>
          <?php
            if (ACCOUNT_SUBURB == 'true') {
          ?>
          <div class="nmx-col-6">
            <label for="suburb"><?php echo ENTRY_SUBURB . oprc_required_indicator(oprc_get_min_length('ENTRY_SUBURB_MIN_LENGTH'), ENTRY_SUBURB_TEXT); ?></label>
            <?php echo zen_draw_input_field('suburb', (isset($_SESSION['suburb']) ? $_SESSION['suburb'] : ''), zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_suburb', '40') . ' class="nmx-fw" id="suburb"'); ?>
            <?php if ($messageStack->size('suburb') > 0) { echo '<div class="disablejAlert alert validation">'; echo $messageStack->output('suburb'); echo '</div>'; } ?>
          </div>
          <?php
            }
          ?>
        </div>
        <div class="nmx-row nmx-form-group">
          <div class="nmx-col-6">
            <label for="country"><?php echo ENTRY_COUNTRY . (zen_not_null(ENTRY_COUNTRY_TEXT) ? '<span class="alert">' . ENTRY_COUNTRY_TEXT . '</span>': ''); ?></label>
            <?php echo zen_oprc_get_country_list('zone_country_id', $selected_country, 'class="nmx-fw" id="country" ' . ($flag_show_pulldown_states == true ? 'onchange="update_zone(this.form);"' : '')); ?>
            <?php if ($messageStack->size('zone_country_id') > 0) { echo '<div class="disablejAlert alert validation">'; echo $messageStack->output('zone_country_id'); echo '</div>'; } ?>
          </div>
          <div class="nmx-col-6">
            <label for="city"><?php echo ENTRY_CITY . oprc_required_indicator(ENTRY_CITY_MIN_LENGTH, ENTRY_CITY_TEXT); ?></label>
            <?php echo zen_draw_input_field('city', (isset($_SESSION['city']) ? $_SESSION['city'] : ''), zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_city', '40') . ' class="nmx-fw" id="city"'); ?>
            <?php if ($messageStack->size('city') > 0) { echo '<div class="disablejAlert alert validation">'; echo $messageStack->output('city'); echo '</div>'; } ?>
          </div>
        </div>
        <div class="nmx-row nmx-form-group">
          <div class="nmx-col-12">
            <?php
              if (ACCOUNT_STATE == 'true') {
                if ($flag_show_pulldown_states == true) {
            ?>
              <label for="stateZone" class="nmx-fw" id="zoneLabel"><?php echo ENTRY_STATE . oprc_required_indicator(ENTRY_STATE_MIN_LENGTH, ENTRY_STATE_TEXT); ?></label>
              <?php
                    echo zen_draw_pull_down_menu('zone_id', zen_prepare_country_zones_pull_down($selected_country), $zone_id, 'class="nmx-fw" id="stateZone"');
              ?>
              <?php if ($messageStack->size('zone_id') > 0) { echo '<div class="disablejAlert alert validation">'; echo $messageStack->output('zone_id'); echo '</div>'; } ?>
            
            <?php } ?>
            <div class="nmx-mt0 nmx-ha">
              <label for="state" class="nmx-fw" id="stateLabel"><?php echo $state_field_label; ?></label>
              <?php
                  echo zen_draw_input_field('state', $state, zen_set_field_length(TABLE_ADDRESS_BOOK, 'entry_state', '40') . ' class="nmx-fw" id="state"');
                  if ($flag_show_pulldown_states == false) {
                    echo zen_draw_hidden_field('zone_id', (isset($zone_name) ? $zone_name : ''), ' ');
                  }
              ?>
              <?php if ($messageStack->size('state') > 0) { echo '<div class="disablejAlert alert validation">'; echo $messageStack->output('state'); echo '</div>'; } ?>
            </div>
            <?php
              }
            ?>
          </div>
        </div>

        <?php if (ACCOUNT_TELEPHONE_SHIPPING == 'true') { ?>
        <div class="nmx-row nmx-form-group">
          <div class="nmx-col-6">
            <label for="telephone"><?php echo ENTRY_TELEPHONE_NUMBER . oprc_required_indicator(ENTRY_TELEPHONE_MIN_LENGTH, ENTRY_TELEPHONE_NUMBER_TEXT); ?></label>
            <?php echo zen_draw_input_field('telephone', (isset($_SESSION['telephone']) ? $_SESSION['telephone'] : ''), zen_set_field_length(TABLE_CUSTOMERS, 'customers_telephone', '40') . ' class="nmx-fw" id="telephone"'); ?>
            <?php if ($messageStack->size('telephone') > 0) { echo '<div class="disablejAlert alert validation">'; echo $messageStack->output('telephone'); echo '</div>'; } ?>
          </div>
        </div>
        <?php } ?>
      </div>
      <!-- END NEW? PROVIDE... -->
    </div>
    
    <input type="hidden" name="one_page_checkout" value="TEXT" checked="checked" class="nmx-fw" id="one-page-checkout-text" />
  </div>
<?php } ?>
