<?php
  // BEGIN OPTIONAL INSURANCE 
  if (zen_not_null($_POST['opt_insurance'])) {
    $_SESSION['opt_insurance'] = $_POST['opt_insurance'];
  } else {
    unset($_SESSION['opt_insurance']);
  }
  // END OPTIONAL INSURANCE
?>