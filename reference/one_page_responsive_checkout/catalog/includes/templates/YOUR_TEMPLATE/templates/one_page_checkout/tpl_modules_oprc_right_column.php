<div id="oprcRight" class="stickem">  
  <div id="shoppingBagContainer">
    <!-- panel -->
    <div class="nmx-panel">
      
      <!-- panel head -->
      <div class="nmx-panel-head">
          <span><?php echo TABLE_HEADING_SHOPPING_CART; ?></span>
          <a class="nmx-head-link--small" href="<?php echo zen_href_link(FILENAME_SHOPPING_CART, '', 'NONSSL'); ?>">
            <?php echo BUTTON_EDIT_CART_SMALL_ALT; ?>
          </a>
      </div>
      <!-- end panel head -->
      
      <!-- panel body -->
      <div class="nmx-panel-body nmx-cf">

        <?php require($template->get_template_dir('tpl_modules_oprc_ordertotal.php',DIR_WS_TEMPLATE, $current_page_base,'templates/one_page_checkout'). '/tpl_modules_oprc_ordertotal.php'); ?>

        <!-- help -->
        <div class="nmx-help">
          <h3 class="nmx-mt0"><?php echo TEXT_NEED_HELP; ?></h3><!--EOF #needHelpHead-->
          <p class="nmx-mb0 nmx-mt0">
            <?php echo TEXT_CONTACT_US_AT; ?> <a href="mailto:<?php echo STORE_OWNER_EMAIL_ADDRESS; ?>" target="_blank"><?php echo STORE_OWNER_EMAIL_ADDRESS; ?></a>
          </p>
        </div>
        <!-- end/help -->
      </div>
      <!-- end panel body -->

    </div>
    <!-- end panel -->
  </div>
</div>