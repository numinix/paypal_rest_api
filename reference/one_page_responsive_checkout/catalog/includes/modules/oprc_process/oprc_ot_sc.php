<?php
  if (zen_not_null($_POST['opt_credit'])) {
    $_SESSION['opt_credit'] = $_POST['opt_credit'];
  } else {
    unset($_SESSION['opt_credit']);
  }
?>