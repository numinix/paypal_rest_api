<?php
/**
 * Page Template
 *
 * Loaded automatically by index.php?main_page=adress_book.<br />
 * Allows customer to manage entries in their address book
 *
 * @package templateSystem
 * @copyright Copyright 2003-2005 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: tpl_address_book_default.php 5369 2006-12-23 10:55:52Z drbyte $
 */
?>

<div class="centerColumn ac-main" id="savedCreditCardsDefault">
  <h1><?php echo HEADING_TITLE; ?></h1>
  <?php require($template->get_template_dir('tpl_modules_account_menu.php',DIR_WS_TEMPLATE, $current_page_base,'templates'). '/tpl_modules_account_menu.php'); ?>
  <?php if ($hide_saved_cards_page == false) { ?> <!-- bof STRIN-1846 -->
  <section>
    <div class="nmx nmx-plugin nmx-plugin--oprc">    
      <?php if ($messageStack->size('saved_credit_cards') > 0) echo $messageStack->output('saved_credit_cards'); ?> 
          
      <!-- delete -->
      <?php if(isset($delete_card)) { ?>
      <section class="nmx-panel">
        <div class="nmx-panel-head">
            <?php echo HEADING_TITLE_DELETE_CARD; ?>
        </div>
        <div class="nmx-panel-body">
          <p class="nmx-mt0 nmx-p0">Are you sure that you would like the delete the saved profile <?php echo zen_output_string_protected($delete_card['type']); ?> <?php echo zen_output_string_protected($delete_card['last_digits']); ?>?</p>
          <div class="nmx-buttons">
            <?php echo '<a class="cssButton" href="' . zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, 'delete=' . $delete_card['saved_credit_card_id'], 'SSL') . '">';?><?php echo BUTTON_DELETE_ALT; ?></button></a>
            <?php echo '<a href="' . zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, '', 'SSL') . '">';?><?php echo BUTTON_CANCEL_ALT; ?></a>
          </div>
        </div>
      </section>
      <?php } ?>
      <!-- end/delete -->
      <!-- add/edit -->
      <?php if($_GET['edit'] || ($_GET['action'] == 'add')) { ?>
      <section class="nmx-panel">
        <?php echo zen_draw_form('edit_credit_card', zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, '', 'SSL'), 'post', 'id="js-form_credit_card"'); ?>
            <!-- hidden -->
            <?php echo zen_draw_hidden_field('action', 'process'); ?>
            <?php echo zen_draw_hidden_field('saved_credit_card_id', $edit_card['saved_credit_card_id']); ?>
            <?php echo zen_draw_hidden_field('existing_address_id', $edit_card['address_id']); ?>
            <!-- end/hidden -->

            <div class="nmx-panel-head">
                <?php echo isset($_GET['edit']) ? HEADING_TITLE_EDIT_CARD : HEADING_TITLE_ADD_NEW_CARD; ?>
            </div>
            <div class="nmx-panel-body">
              <span class="nmx-required"><?php echo FORM_REQUIRED_INFORMATION; ?></span>
              
              <div class="creditcard-form">
                <?php require($template->get_template_dir('tpl_modules_account_credit_card_details.php', DIR_WS_TEMPLATE, $current_page_base,'templates'). '/' . 'tpl_modules_account_credit_card_details.php'); ?>

                <div class="nmx-row nmx-cf">
                  <div class="nmx-col-6">
                    <label for="js-address_book"><?php echo ($add_address_enabled ? LABEL_SELECT_ADDRESS_OR_ENTER_NEW : LABEL_SELECT_ADDRESS) ?></label>
                    <?php 
                        if(isset($edit_card['address_id']) && $edit_card['address_id'] > 0){
                            $default_address = $edit_card['address_id'];
                        }
                        elseif(isset($_SESSION['customer_default_address_id']) && (int)$_SESSION['customer_default_address_id'] > 0) {
                            $default_address = $_SESSION['customer_default_address_id'];
                        }
                        else {
                            $default_address = 0;
                        }
                    ?>
                    <?php echo zen_draw_pull_down_menu('address_book_id', $saved_addresses, $default_address, 'id="js-address_book"');?>
                  </div>
                </div>

                <div id="js-address_entry" class="nmx-address__entry nmx-form-group nmx-hidden">
                  <?php require($template->get_template_dir('tpl_modules_saved_credit_cards_address_book_details.php', DIR_WS_TEMPLATE, $current_page_base,'templates'). '/' . 'tpl_modules_saved_credit_cards_address_book_details.php'); ?>
                </div>
              </div>
              <div class="nmx-buttons">
                  <button class="cssButton" href="<?php echo zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, '', 'SSL'); ?>"><?php echo BUTTON_SAVE_CARD_ALT; ?></button>
                  <a href="<?php echo zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, '', 'SSL'); ?>"><?php echo BUTTON_CANCEL_ALT; ?></a>
              </div>
            </div>
        </form>
      </section>
      <?php } else { ?>
      <?php if (!isset($delete_card)): ?>
        <section class="nmx-panel nmx-m0 nmx-p0 nmx-b0">
          <div class="nmx-panel-body nmx-p0">
            <p class="nmx-mt0 nmx-p0"><?php echo INTRO_PAGE; ?></p>
            <ul class="nmx-addresses nmx-clist">
              <?php
              /**
              * Used to loop thru and display address book entries
              */
                if (is_array($saved_credit_cards)) {
                  $year = date('y');
                  $month = date('m');
                  foreach ($saved_credit_cards as $credit_card) {
                  	$expired = false;                  	
                  	if (substr($credit_card['expiry'], 2,2) < $year || ( substr($credit_card['expiry'], 2,2) == $year && substr($credit_card['expiry'], 0,2) < $month) ){
                  		$expired = true;
                  	}
              ?>

              <?php if ($credit_card['is_primary']) { ?>
              <li class="nmx-address is-default">
              <?php } else { ?>
              <li class="nmx-address">
              <?php } ?>
                <ul class="nmx-address-details nmx-clist">
                  <?php if($expired){ ?>
                  <li class="card-expired">
                    <strong>&nbsp;</strong>
                    <span class="">Expired profile</span>
                  </li>
                  <?php }?>
                  <li>
                    <strong>Profile:</strong>
                    <span><?php echo zen_output_string_protected($credit_card['type']); ?> ending in <?php echo zen_output_string_protected($credit_card['last_digits']); ?></span>
                  </li>
                  <li>
                    <strong>Exp.:</strong>
                    <span><?php echo substr($credit_card['expiry'], 0, 2).'/'.substr($credit_card['expiry'], 2);?></span>
                  </li>           
                  <li>
                    <strong>Name used:</strong>
                    <span><?php echo $credit_card['name_on_card']; ?></span>
                  </li>
                  <!-- 
                  <li>
                    <strong>Name on card:</strong>
                    <span><?php //echo $credit_card['name_on_card']; ?></span>
                  </li>
                  <li>
                    <strong>Card type:</strong>
                    <span><?php //echo zen_output_string_protected($credit_card['type']); ?></span>
                  </li>
                  <li>
                    <strong>Card number:</strong>
                    <span>*****<?php //echo zen_output_string_protected($credit_card['last_digits']); ?></span>
                  </li>
                   -->
                </ul>
                <div class="nmx-buttons nmx-buttons--addresses">  
                  <?php if (!$credit_card['is_primary']) {?>
                    <a href="<?php echo zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, 'setprimary=' . $credit_card['saved_credit_card_id'], 'SSL');?>"><?php echo BUTTON_MAKE_DEFAULT_ALT; ?></a>
                  <?php } ?>
                  <a href="<?php echo zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, 'edit=' . $credit_card['saved_credit_card_id'] . '#container__add-card', 'SSL') ?>"><?php echo BUTTON_EDIT_ALT; ?></a>
                  <?php //if (!$credit_card['is_primary']) { ?>
                  <a href="<?php echo zen_href_link(FILENAME_ACCOUNT_SAVED_CREDIT_CARDS, 'delete_confirm=' . $credit_card['saved_credit_card_id'], 'SSL') ?>"><?php echo BUTTON_DELETE_ALT; ?></a>
                  <?php //} ?>
                </div>
              </li>
              <?php
                  }
                }
              ?>
            </ul>
          </div>
        </section>
      <?php endif ?>
      <?php } ?>  
    </div>
  </section>
  <?php } ?> <!-- bof STRIN-1846 -->

</div> <!-- end/centerColumn -->
