<?php
// braintree_clear_cart.php

// Initialize Zen Cart environment
require('../includes/configure.php');
ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
chdir(DIR_FS_CATALOG);
$current_page_base = 'paypalac_wallet_ajax'; // or similar unique name
$loaderPrefix = 'paypalac_wallet_ajax';
require('includes/application_top.php');

// Set header to return JSON
header('Content-Type: application/json');

// Check that the cart exists and is an object
if (!isset($_SESSION['cart']) || !is_object($_SESSION['cart'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Cart not found.'
    ]);
    exit;
}

// Backup the current cart session to another session variable
// Using clone to ensure we create a copy rather than a reference
$_SESSION['cart_backup'] = clone $_SESSION['cart'];

// Reset the current cart
$_SESSION['cart']->reset(true);

// Return success response
echo json_encode([
    'success' => true,
    'message' => 'Cart has been cleared and backup saved.'
]);
exit;