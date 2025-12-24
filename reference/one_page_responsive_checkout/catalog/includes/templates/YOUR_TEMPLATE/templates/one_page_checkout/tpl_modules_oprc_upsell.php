<?php
  if (OPRC_ONE_PAGE_CHECKOUT_UPSELL_STATUS == 'true') {  
    include(DIR_WS_MODULES . zen_get_module_directory(FILENAME_ONE_PAGE_CHECKOUT_UPSELL)); 
    if ($flag_show_checkout_upsell) { 
?>
<div id="qcUpSell" class="nmx-panel nmx-wrapper">
  <div class="nmx-panel-head">
    <?php echo HEADING_ONE_PAGE_CHECKOUT_UPSELL; ?>
  </div>
  <div class="nmx-panel-body">
    <?php
    /**
     * require the list_box_content template to display the up-sell info
     */
    require($template->get_template_dir('tpl_columnar_display.php',DIR_WS_TEMPLATE, $current_page_base,'common'). '/tpl_columnar_display.php');
    ?>
  </div>
</div>
<?php 
    }
  } 
?>