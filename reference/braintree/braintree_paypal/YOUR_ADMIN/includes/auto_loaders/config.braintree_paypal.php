<?php
if (!defined('IS_ADMIN_FLAG')) {
  die('Illegal Access');
}

global $db;

$isInstallRequest = (
  ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' &&
  isset($_GET['action'], $_GET['set'], $_POST['module']) &&
  $_GET['action'] === 'install' &&
  $_GET['set'] === 'payment' &&
  $_POST['module'] === 'braintree_paypal'
);

$isModuleInstalled = false;
if (isset($db) && is_object($db)) {
  $check = $db->Execute(
    "SELECT configuration_key FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_BRAINTREE_PAYPAL_STATUS' LIMIT 1"
  );
  $isModuleInstalled = (is_object($check) && isset($check->EOF) && !$check->EOF);
}

if ($isModuleInstalled || $isInstallRequest) {
  $autoLoadConfig[999][] = array(
    'autoType' => 'init_script',
    'loadFile' => 'init_braintree_paypal_config.php'
  );
}
