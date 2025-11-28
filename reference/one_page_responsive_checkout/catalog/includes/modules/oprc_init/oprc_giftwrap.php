<?php
  if (OPRC_GIFT_WRAPPING_SWITCH == 'true') {
    // set the selection mechanism. You may need to override configured 
    // value if other things are missing.
    $wrapconfig = "Checkbox"; 
    if (defined('MODULE_ORDER_TOTAL_GIFTWRAP_CHECKOUT_WRAP_SELECT')) { 
       if (MODULE_ORDER_TOTAL_GIFTWRAP_CHECKOUT_WRAP_SELECT == "Images") { 
          $papername = array(); 
          $dir_name = GIFTWRAP_IMAGE_DIR;  
          if (is_dir($dir_name) && is_readable($dir_name)) {
             $d = dir($dir_name); 
             while (false != ($f = $d->read())) {
                if ( ("." == $f) || (".." == $f) ) continue;
                if (is_file("$dir_name/$f")) {
                   if (exif_imagetype("$dir_name/$f")) { 
                      $papername[] = $f;
                   }
                }
             }
             $d->close(); 
          }
          if (sizeof($papername) > 0) { 
              $wrapconfig = "Images"; 
          }
       } else if (MODULE_ORDER_TOTAL_GIFTWRAP_CHECKOUT_WRAP_SELECT == "Descriptions") { 
          if (is_array($wrap_selections)) { 
              $wrapconfig = "Descriptions"; 
          }
       }
    }
    $_SESSION['wrapconfig'] = $wrapconfig;
  }
?>