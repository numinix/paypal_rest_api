<?php if (OPRC_CONFIDENCE == 'true') { ?>
  <div id="confidence" class="nmx-panel nmx-wrapper">
    
    <div class="nmx-panel-head">
      <?php echo HEADING_CONFIDENCE; ?>
    </div>

    <div class="nmx-panel-body">
      <?php
        if (OPRC_CONFIDENCE_HTML != '') {
          echo OPRC_CONFIDENCE_HTML;
        } else {
          echo '<div class="confidenceItem">' . zen_image(zen_output_string($template->get_template_dir("ssl.jpg", DIR_WS_TEMPLATE, $current_page_base, 'images/one_page_checkout/') . "ssl.jpg"), "SSL", 100) . '</div>';
          echo '<div class="confidenceItem">' . zen_image(zen_output_string($template->get_template_dir("paypal.jpg", DIR_WS_TEMPLATE, $current_page_base, 'images/one_page_checkout/') . "paypal.jpg"), "PayPal", 100) . '</div>';
        }
      ?>
    </div>
    
  </div>
<?php } ?>