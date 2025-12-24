<div id="oprcRight">
  <div class="stickem-container">
    <div class="oprcRight-scroll-container">
      <?php
        $checkoutLogoHtml = '';
        $logoAltText = defined('HEADER_ALT_TEXT') ? HEADER_ALT_TEXT : STORE_NAME;
        $checkoutLogoClasses = 'oprcRight-header oprc-checkout-logo-image';

        if (defined('OPRC_CHECKOUT_LOGO_SECURE_PATH') && trim(OPRC_CHECKOUT_LOGO_SECURE_PATH) !== '') {
          $checkoutLogoSrc = trim(OPRC_CHECKOUT_LOGO_SECURE_PATH);
        } else {
          $checkoutLogoSrc = $template->get_template_dir(HEADER_LOGO_IMAGE, DIR_WS_TEMPLATE, $current_page_base, 'images') . '/' . HEADER_LOGO_IMAGE;
        }

        if (!empty($checkoutLogoSrc)) {
          $checkoutLogoHtml = '<img class="' . $checkoutLogoClasses . '" src="' . $checkoutLogoSrc . '" alt="' . zen_output_string_protected($logoAltText) . '" />';
        }
      ?>
      <?php if ($checkoutLogoHtml !== '') { ?>
        <div class="panel-image__wrapper" id="show_image__wrapper">
          <a class="oprc-shopping-cart-logo" href="<?php echo zen_href_link(FILENAME_DEFAULT); ?>">
            <?php echo $checkoutLogoHtml; ?>
          </a>
        </div>
      <?php } ?>
      <div class="nmx-panel-head oprc-shopping-cart-head oprc-shopping-cart-header">
        <span class="oprc-shopping-cart-title"><?php echo zen_output_string_protected(defined('TABLE_HEADING_SHOPPING_CART') ? TABLE_HEADING_SHOPPING_CART : 'Shopping Cart'); ?></span>
        <a class="nmx-head-link--small oprc-shopping-cart-edit" href="<?php echo zen_href_link(FILENAME_SHOPPING_CART, '', 'NONSSL'); ?>">
          <?php echo BUTTON_EDIT_CART_SMALL_ALT; ?>
        </a>
      </div>
      <div id="shoppingBagContainer">
        <!-- panel -->
        <div class="nmx-panel oprc-shopping-cart-panel">

          <!-- panel body -->
          <div class="nmx-panel-body nmx-cf">

            <?php require($template->get_template_dir('tpl_modules_oprc_ordertotal.php', DIR_WS_TEMPLATE, $current_page_base, 'templates/one_page_checkout') . '/tpl_modules_oprc_ordertotal.php'); ?>

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
  </div>
</div>
