<?php
  // BOF DOUBLE BOX
  if (MODULE_ORDER_TOTAL_DOUBLEBOX_STATUS == 'true') {
    $doubleboxsettings = array();
    $prod_count = 1; 
    for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
       $prid = $order->products[$i]['id'];
       $doubleboxsettings[$prid] = array(); 
       for ($q = 1; $q <= $order->products[$i]['qty']; $q++) {
           $name = "doublebox_prod_" . $prod_count; 
           if (isset($_POST[$name]) && ($_POST[$name] == 'on')) {
              $_SESSION[$name] = $_POST[$name];
              $doubleboxsettings[$prid][$q] = 1;  
           } else {
              $_SESSION[$name] = false;
              $doubleboxsettings[$prid][$q] = 0;  
           }
           $prod_count++;  
       }
    }
    // tsg_logger($doubleboxsettings);
    $_SESSION['doubleboxsettings'] = $doubleboxsettings;
  }
  // EOF DOUBLE BOX
?>