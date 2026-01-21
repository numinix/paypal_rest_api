<?php

/**
 * no_account.php
 *
 * @package modules
 * @copyright Copyright 2003-2006 Zen Cart Development Team
 * @copyright Portions Copyright 2007 Joseph Schilz
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: J_Schilz for Integrated COWOA - 14 April 2007
 */
// This should be first line of the script:
$zco_notifier->notify('NOTIFY_MODULE_START_NO_ACCOUNT');
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

/**
 * Set some defaults
 */
$messageStack->reset();
$process = false;
$zone_name = '';
$entry_state_has_zones = '';
$error_state_input = false;
$state = '';
$zone_id = 0;
$error = false;
$shippingAddress = false;

$process_shipping = false;
$zone_name_shipping = '';
$entry_state_has_zones_shipping = '';
$error_state_input_shipping = false;
$state_shipping = '';
$zone_id_shipping = 0;

$captcha = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';

// create params to include in redirects
$params = '';
if (OPRC_HIDE_REGISTRATION == 'true') {
    $params .= '&hideregistration=true';
}
if ($_POST['cowoa'] == 'true') {
    $params .= '&type=cowoa';
}

// check if products in cart before logging in
$my_account = false; // default
if (!$_SESSION['cart']->count_contents() > 0 && OPRC_NOACCOUNT_ONLY_SWITCH == 'false' || (isset($_SESSION['gv_no']) && $_SESSION['gv_no'] != '')) {
    $my_account = true;
}

$temp = $current_page_base;
$current_page_base = 'lang.' . $current_page_base;
require(DIR_WS_MODULES . zen_get_module_directory('require_languages.php'));
$current_page_base = $temp;
/**
 * Process form contents
 */
if (isset($_POST['oprcaction']) && ($_POST['oprcaction'] == 'process')) {
    // begin checkout without account
    if (!isset($_SESSION['customer_id']) || !$_SESSION['customer_id']) {
        $process = true;

        // collect posts and create sessions in case of error on registration
        include(DIR_WS_CLASSES . 'oprc.php');
        $oprc = new oprc();
        $oprc->collect_posts($_POST);

        // Check Google reCAPTCHA response
        if (OPRC_RECAPTCHA_STATUS == 'true') {
            $recaptchaKey = trim(OPRC_RECAPTCHA_KEY);
            $recaptchaSecret = trim(OPRC_RECAPTCHA_SECRET);

            if ($recaptchaKey === '' || $recaptchaSecret === '') {
                $error = true;
                $messageStack->add_session('email_address_register', ENTRY_SECURITY_CHECK_RECAPTCHA_MISCONFIGURED);
                if (function_exists('zen_record_admin_activity')) {
                    zen_record_admin_activity('OPRC reCAPTCHA is enabled but keys are missing.', 'warning');
                }
            } elseif ($captcha === '') {
                $error = true;
                $messageStack->add_session('email_address_register', ENTRY_SECURITY_CHECK_RECAPTCHA_REQUIRED);
            } else {
                $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
                $verifyPayload = http_build_query([
                    'secret' => $recaptchaSecret,
                    'response' => $captcha,
                    'remoteip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
                ]);

                $verifyResponse = false;

                if (function_exists('curl_init')) {
                    $ch = curl_init($verifyUrl);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $verifyPayload);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    $verifyResponse = curl_exec($ch);
                    if (curl_errno($ch)) {
                        $verifyResponse = false;
                    }
                    curl_close($ch);
                }

                if ($verifyResponse === false) {
                    $context = stream_context_create([
                        'http' => [
                            'method' => 'POST',
                            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                            'content' => $verifyPayload,
                            'timeout' => 10,
                        ],
                    ]);
                    $verifyResponse = @file_get_contents($verifyUrl, false, $context);
                }

                $decodedResponse = $verifyResponse ? json_decode($verifyResponse, true) : [];

                if (empty($decodedResponse)) {
                    $error = true;
                    $messageStack->add_session('email_address_register', ENTRY_SECURITY_CHECK_RECAPTCHA_UNAVAILABLE);
                } elseif (empty($decodedResponse['success'])) {
                    $error = true;
                    $messageStack->add_session('email_address_register', ENTRY_SECURITY_CHECK_RECAPTCHA_REQUIRED);
                }
            }
        }

        if (ACCOUNT_GENDER == 'true') {
            if (isset($_POST['gender'])) {
                $gender = zen_db_prepare_input($_POST['gender']);
            } else {
                $gender = false;
            }
        }

        if (DISPLAY_PRIVACY_CONDITIONS == 'true') {
            if (!isset($_POST['privacy_conditions']) || ($_POST['privacy_conditions'] != '1')) {
                $error = true;
                $messageStack->add_session('create_account', ERROR_PRIVACY_STATEMENT_NOT_ACCEPTED, 'error');
            }
        }

        if (isset($_POST['email_format'])) {
            $email_format = zen_db_prepare_input($_POST['email_format']);
        }

        if (ACCOUNT_COMPANY == 'true') {
            if (isset($_POST['company'])) {
                $company = zen_db_prepare_input($_POST['company']);
            } else {
                $company = false;
            }
        }
        $firstname = zen_db_prepare_input($_POST['firstname']);
        $lastname = zen_db_prepare_input($_POST['lastname']);
        $nick = isset($_POST['nick']) ? zen_db_prepare_input($_POST['nick']) : '';
        if (ACCOUNT_DOB == 'true') {
            $_POST['dob'] = str_replace(array('mm', 'dd', 'yyyy'), array($_POST['dob_month'], $_POST['dob_day'], $_POST['dob_year']), DOB_FORMAT_STRING);
            $dob = (empty($_POST['dob']) ? zen_db_prepare_input('0001-01-01 00:00:00') : zen_db_prepare_input($_POST['dob']));
        }
        $email_address = isset($_POST['email_address_register']) ? zen_db_prepare_input($_POST['email_address_register']) : false;
        $street_address = isset($_POST['street_address']) ? zen_db_prepare_input($_POST['street_address']) : false;
        if (ACCOUNT_SUBURB == 'true') {
            if (isset($_POST['suburb'])) {
                $suburb = zen_db_prepare_input($_POST['suburb']);
            } else {
                $suburb = false;
            }
        }
        $postcode = zen_db_prepare_input($_POST['postcode']);
        $city = zen_db_prepare_input($_POST['city']);
        if (ACCOUNT_STATE == 'true') {
            if (isset($_POST['state'])) {
                $state = zen_db_prepare_input($_POST['state']);
            } else {
                $state = false;
            }
            if (isset($_POST['zone_id'])) {
                $zone_id = zen_db_prepare_input($_POST['zone_id']);
            } else {
                $zone_id = false;
            }
        }
        $country = zen_db_prepare_input($_POST['zone_country_id']);
        if (ACCOUNT_TELEPHONE == 'true') {
            if (isset($_POST['telephone'])) {
                $telephone = zen_db_prepare_input($_POST['telephone']);
            } else {
                $telephone = false;
            }
        }

        // confirm email address modification
        if (OPRC_CONFIRM_EMAIL == 'true') {
            $email_address_confirm = zen_db_prepare_input($_POST['email_address_confirm']);
            if ($email_address != $email_address_confirm) {
                $error = true;
                $messageStack->add_session('email_address_confirm', ENTRY_EMAIL_ADDRESS_CONFIRM_ERROR);
            }
        }

        $fax = isset($_POST['fax']) ? zen_db_prepare_input($_POST['fax']) : '';
        $customers_authorization = CUSTOMERS_APPROVAL_AUTHORIZATION;
        $customers_referral = isset($_POST['customers_referral']) ? zen_db_prepare_input($_POST['customers_referral']) : false;

        $newsletter = isset($_POST['newsletter']) ? zen_db_prepare_input($_POST['newsletter']) : false;
        if (
            !empty($_POST['password-register']) &&
            ((isset($_POST['cowoa-checkbox']) && (bool)$_POST['cowoa-checkbox']) ||
            (isset($_POST['cowoa-radio']) && $_POST['cowoa-radio'] == "off"))
        ) {
            $password = zen_db_prepare_input($_POST['password-register']);
            $confirmation = zen_db_prepare_input($_POST['password-confirmation']);
            $_SESSION['COWOA'] = false;
            $cowoa = 0;
            if (strlen($password) < ENTRY_PASSWORD_MIN_LENGTH) {
                $error = true;
                $messageStack->add_session('password-register', ENTRY_PASSWORD_ERROR);
            } elseif ($password != $confirmation) {
                $error = true;
                $messageStack->add_session('password-confirmation', ENTRY_PASSWORD_ERROR_NOT_MATCHING);
            }
        } elseif (OPRC_NOACCOUNT_SWITCH == 'true') {
            // create password for no account
            if (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= '5.3')) {
                $password = zen_create_PADSS_password(15);
            } else {
                $password = zen_create_random_value(15, 'mixed');
            }
            $_SESSION['COWOA'] = true;
            $cowoa = 1;
        }

        if (ACCOUNT_GENDER == 'true') {
            if (($gender != 'm') && ($gender != 'f')) {
                $error = true;
                $messageStack->add_session('gender', ENTRY_GENDER_ERROR);
            }
        }

        if (strlen($firstname) < ENTRY_FIRST_NAME_MIN_LENGTH) {
            $error = true;
            $messageStack->add_session('firstname', ENTRY_FIRST_NAME_ERROR);
        }

        if (strlen($lastname) < ENTRY_LAST_NAME_MIN_LENGTH) {
            $error = true;
            $messageStack->add_session('lastname', ENTRY_LAST_NAME_ERROR);
        }

        if (ACCOUNT_DOB == 'true') {
            if (ENTRY_DOB_MIN_LENGTH > 0 or !empty($_POST['dob'])) {
                // Support ISO-8601 style date
                if (preg_match('/^([0-9]{4})(|-|\/)([0-9]{2})\2([0-9]{2})$/', $dob)) {
                    // Account for incorrect date format provided to strtotime such as swapping day and month instead of the expected yyyymmdd, yyyy-mm-dd, or yyyy/mm/dd format
                    if (strtotime($dob) !== false) {
                        $_POST['dob'] = $dob = date(DATE_FORMAT, strtotime($dob));
                    }
                }
                if (substr_count($dob, '/') > 2 || checkdate((int)substr(zen_date_raw($dob), 4, 2), (int)substr(zen_date_raw($dob), 6, 2), (int)substr(zen_date_raw($dob), 0, 4)) == false) {
                    $error = true;
                    $messageStack->add('create_account', ENTRY_DATE_OF_BIRTH_ERROR);
                }
                if (defined('ACCOUNT_DOB_REJECT_REGISTRATION') && ACCOUNT_DOB_REJECT_REGISTRATION > 0) {
                    if(strtotime($dob) > strtotime('-' . ACCOUNT_DOB_REJECT_REGISTRATION . ' year')) {
                        $error = true;
                        $messageStack->add_session('create_account', OPRC_ENTRY_DATE_OF_BIRTH_UNDER_AGE_ERROR);
                    }
                }
            }
        }

        if (ACCOUNT_COMPANY == 'true') {
            if ((int)ENTRY_COMPANY_MIN_LENGTH > 0 && strlen($company) < ENTRY_COMPANY_MIN_LENGTH) {
                $error = true;
                $messageStack->add_session('company', ENTRY_COMPANY_ERROR);
            }
        }

        if (strlen($email_address) < ENTRY_EMAIL_ADDRESS_MIN_LENGTH) {
            $error = true;
            $messageStack->add_session('email_address_register', ENTRY_EMAIL_ADDRESS_ERROR);
        } elseif (zen_validate_email($email_address) == false) {
            $error = true;
            $messageStack->add_session('email_address_register', ENTRY_EMAIL_ADDRESS_CHECK_ERROR);
        } else {
            $check_email_query = "SELECT COWOA_account
                                  FROM " . TABLE_CUSTOMERS . "
                                  WHERE customers_email_address = '" . zen_db_input($email_address) . "'
                                  LIMIT 1;";
            $zco_notifier->notify('NOTIFY_CREATE_ACCOUNT_LOOKUP_BY_EMAIL', $email_address, $check_email_query, $send_welcome_email);
            $check_email = $db->Execute($check_email_query);
            if ($check_email->RecordCount() > 0) {
                if ($check_email->fields['COWOA_account'] == 1 && !$cowoa) {
                    $error = true;
                    $messageStack->add_session('email_address_register', ENTRY_EMAIL_ADDRESS_ERROR_GUEST);
                }
                if ($check_email->fields['COWOA_account'] != 1 && !$cowoa) {
                    $error = true;
                    $messageStack->add_session('email_address_register', ENTRY_EMAIL_ADDRESS_ERROR_EXISTS);
                }
                if (OPRC_NOACCOUNT_ALWAYS == 'false' && $check_email->fields['COWOA_account'] != 1 && $cowoa) {
                    $error = true;
                    $messageStack->add_session('email_address_register', ENTRY_EMAIL_ADDRESS_ERROR_GUEST_FULL);
                }
            } else {
                $nick_error = false;
                $zco_notifier->notify('NOTIFY_NICK_CHECK_FOR_EXISTING_EMAIL', $email_address, $nick_error, $nick);
                if ($nick_error) {
                    $error = true;
                }
            }
        }

        if (strlen($street_address) < ENTRY_STREET_ADDRESS_MIN_LENGTH) {
            $error = true;
            $messageStack->add_session('street_address', ENTRY_STREET_ADDRESS_ERROR);
        }

        // BEGIN PO Box Ban 1/1
        if (defined('PO_BOX_ERROR') && !enable_shippingAddress()) {
            if (preg_match('/PO BOX/si', $street_address)) {
                $error = true;
                $messageStack->add_session('create_account', PO_BOX_ERROR);
            } elseif (preg_match('/POBOX/si', $street_address)) {
                $error = true;
                $messageStack->add_session('create_account', PO_BOX_ERROR);
            } elseif (preg_match('/P\.O\./si', $street_address)) {
                $error = true;
                $messageStack->add_session('create_account', PO_BOX_ERROR);
            } elseif (preg_match('/P\.O/si', $street_address)) {
                $error = true;
                $messageStack->add_session('create_account', PO_BOX_ERROR);
            } elseif (preg_match('/PO\./si', $street_address)) {
                $error = true;
                $messageStack->add_session('create_account', PO_BOX_ERROR);
            }
        }
        // END PO Box Ban 1/1

        if (strlen($city) < ENTRY_CITY_MIN_LENGTH) {
            $error = true;
            $messageStack->add_session('city', ENTRY_CITY_ERROR);
        }

        if (ACCOUNT_STATE == 'true') {
            $check_query = "SELECT count(*) AS total
                      FROM " . TABLE_ZONES . "
                      WHERE zone_country_id = " . (int)$country;
            $check = $db->Execute($check_query);
            $entry_state_has_zones = ($check->fields['total'] > 0);
            if ($entry_state_has_zones == true) {
                $zone_query = "SELECT distinct zone_id, zone_name, zone_code
                       FROM " . TABLE_ZONES . "
                       WHERE zone_country_id = " . (int)$country . " AND ";
                if (trim($state) != '' && (int)$zone_id == 0) {
                    $zone_query .= "(upper(zone_name) like '" . strtoupper($state) . "%' OR upper(zone_code) like '%" . strtoupper($state) . "%') OR ";
                }
                $zone_query .= "zone_id = " . (int)$zone_id . " ORDER BY zone_code ASC, zone_name";
                $zone = $db->Execute($zone_query);

                //look for an exact match on zone ISO code
                $found_exact_iso_match = ($zone->RecordCount() == 1);
                if ($zone->RecordCount() > 1) {
                    while (!$zone->EOF && !$found_exact_iso_match) {
                        if (strtoupper($zone->fields['zone_code']) == strtoupper($state)) {
                            $found_exact_iso_match = true;
                            continue;
                        }
                        $zone->MoveNext();
                    }
                }

                if ($found_exact_iso_match) {
                    $zone_id = $zone->fields['zone_id'];
                    $zone_name = $zone->fields['zone_name'];
                } else {
                    $error = true;
                    $error_state_input = true;
                    if (ACCOUNT_STATE_DRAW_INITIAL_DROPDOWN == 'true') {
                        $messageStack->add_session('zone_id', ENTRY_STATE_ERROR_SELECT);
                    } else {
                        $messageStack->add_session('state', ENTRY_STATE_ERROR_INPUT);
                    }
                }
            } else {
                if ((int)ENTRY_STATE_MIN_LENGTH > 0 && strlen($state) < ENTRY_STATE_MIN_LENGTH) {
                    $error = true;
                    $error_state_input = true;
                    $messageStack->add_session('state', ENTRY_STATE_ERROR);
                }
            }
        }

        if (strlen($postcode) < ENTRY_POSTCODE_MIN_LENGTH) {
            $error = true;
            $messageStack->add_session('postcode', ENTRY_POST_CODE_ERROR);
        }

        if ((is_numeric($country) == false) || ($country < 1)) {
            $error = true;
            $messageStack->add_session('zone_country_id', ENTRY_COUNTRY_ERROR);
        }

        if (ACCOUNT_TELEPHONE == 'true') {
            if (strlen($telephone) < ENTRY_TELEPHONE_MIN_LENGTH) {
                $error = true;
                $messageStack->add_session('telephone', ENTRY_TELEPHONE_NUMBER_ERROR);
            }
        }

        if (!$error) {
            // begin shipping
            if (enable_shippingAddress()) {
                $process_shipping = true;
                if (ACCOUNT_GENDER == 'true') {
                    $gender_shipping = zen_db_prepare_input($_POST['gender_shipping']);
                }
                if (ACCOUNT_COMPANY == 'true') {
                    $company_shipping = zen_db_prepare_input($_POST['company_shipping']);
                }
                $firstname_shipping = zen_db_prepare_input($_POST['firstname_shipping']);
                $lastname_shipping = zen_db_prepare_input($_POST['lastname_shipping']);
                $street_address_shipping = zen_db_prepare_input($_POST['street_address_shipping']);
                if (ACCOUNT_SUBURB == 'true') {
                    $suburb_shipping = zen_db_prepare_input($_POST['suburb_shipping']);
                }
                $postcode_shipping = zen_db_prepare_input($_POST['postcode_shipping']);
                $city_shipping = zen_db_prepare_input($_POST['city_shipping']);
                if (ACCOUNT_STATE == 'true') {
                    $state_shipping = isset($_POST['state_shipping']) ? zen_db_prepare_input($_POST['state_shipping']) : '';
                    if (isset($_POST['zone_id_shipping'])) {
                        $zone_id_shipping = zen_db_prepare_input($_POST['zone_id_shipping']);
                    } else {
                        $zone_id_shipping = false;
                    }
                }
                $country_shipping = zen_db_prepare_input($_POST['zone_country_id_shipping']);
                if (ACCOUNT_TELEPHONE_SHIPPING == 'true') {
                    $telephone_shipping = zen_db_prepare_input($_POST['telephone_shipping']);
                }
                //echo ' I SEE: country=' . $country . '&nbsp;&nbsp;&nbsp;state=' . $state . '&nbsp;&nbsp;&nbsp;zone_id=' . $zone_id;
                if (ACCOUNT_GENDER == 'true') {
                    if (($gender_shipping != 'm') && ($gender_shipping != 'f')) {
                        $error = true;
                        $messageStack->add_session('gender_shipping', ENTRY_GENDER_ERROR);
                    }
                }

                if (strlen($firstname_shipping) < ENTRY_FIRST_NAME_MIN_LENGTH) {
                    $error = true;
                    $messageStack->add_session('firstname_shipping', ENTRY_FIRST_NAME_ERROR);
                }

                if (strlen($lastname_shipping) < ENTRY_LAST_NAME_MIN_LENGTH) {
                    $error = true;
                    $messageStack->add_session('lastname_shipping', ENTRY_LAST_NAME_ERROR);
                }

                if (strlen($street_address_shipping) < ENTRY_STREET_ADDRESS_MIN_LENGTH) {
                    $error = true;
                    $messageStack->add_session('street_address_shipping', ENTRY_STREET_ADDRESS_ERROR);
                }

                // BEGIN PO Box Ban 1/1
                if (defined('PO_BOX_ERROR')) {
                    if (preg_match('/PO BOX/si', $street_address_shipping)) {
                        $error = true;
                        $messageStack->add_session('create_account', PO_BOX_ERROR);
                    } elseif (preg_match('/POBOX/si', $street_address_shipping)) {
                        $error = true;
                        $messageStack->add_session('create_account', PO_BOX_ERROR);
                    } elseif (preg_match('/P\.O\./si', $street_address_shipping)) {
                        $error = true;
                        $messageStack->add_session('create_account', PO_BOX_ERROR);
                    } elseif (preg_match('/P\.O/si', $street_address_shipping)) {
                        $error = true;
                        $messageStack->add_session('create_account', PO_BOX_ERROR);
                    } elseif (preg_match('/PO\./si', $street_address_shipping)) {
                        $error = true;
                        $messageStack->add_session('create_account', PO_BOX_ERROR);
                    }
                }
                // END PO Box Ban 1/1

                if (strlen($city_shipping) < ENTRY_CITY_MIN_LENGTH) {
                    $error = true;
                    $messageStack->add_session('city_shipping', ENTRY_CITY_ERROR);
                }

                if (ACCOUNT_STATE == 'true') {
                    $check_query = "SELECT count(*) AS total
                          FROM " . TABLE_ZONES . "
                          WHERE zone_country_id = " . (int)$country_shipping;
                    $check = $db->Execute($check_query);
                    $entry_state_has_zones_shipping = ($check->fields['total'] > 0);
                    if ($entry_state_has_zones_shipping == true) {
                        $zone_query = "SELECT distinct zone_id, zone_name, zone_code
                               FROM " . TABLE_ZONES . "
                               WHERE zone_country_id = " . (int)$country_shipping . " AND ";
                        if (trim($state_shipping) != '' && (int)$zone_id_shipping == 0) {
                            $zone_query .= "(upper(zone_name) like '" . strtoupper($state_shipping) . "%' OR upper(zone_code) like '%" . strtoupper($state_shipping) . "%') OR ";
                        }
                        $zone_query .= "zone_id = " . (int)$zone_id_shipping . " ORDER BY zone_code ASC, zone_name";
                        $zone_shipping = $db->Execute($zone_query);

                        //look for an exact match on zone ISO code
                        $found_exact_iso_match_shipping = ($zone_shipping->RecordCount() == 1);
                        if ($zone_shipping->RecordCount() > 1) {
                            while (!$zone_shipping->EOF && !$found_exact_iso_match_shipping) {
                                if (strtoupper($zone->fields['zone_code']) == strtoupper($state_shipping)) {
                                    $found_exact_iso_match_shipping = true;
                                    continue;
                                }
                                $zone_shipping->MoveNext();
                            }
                        }

                        if ($found_exact_iso_match_shipping) {
                            $zone_id_shipping = $zone_shipping->fields['zone_id'];
                            $zone_name_shipping = $zone_shipping->fields['zone_name'];
                        } else {
                            $error = true;
                            $error_state_input_shipping = true;
                            if (ACCOUNT_STATE_DRAW_INITIAL_DROPDOWN == 'true') {
                                $messageStack->add_session('zone_id_shipping', ENTRY_STATE_ERROR_SELECT);
                            } else {
                                $messageStack->add_session('state_shipping', ENTRY_STATE_ERROR_INPUT);
                            }
                        }
                    } else {
                        if ((int)ENTRY_STATE_MIN_LENGTH > 0 && strlen($state_shipping) < ENTRY_STATE_MIN_LENGTH) {
                            $error = true;
                            $error_state_input_shipping = true;
                            $messageStack->add_session('state_shipping', ENTRY_STATE_ERROR);
                        }
                    }
                }

                if (strlen($postcode_shipping) < ENTRY_POSTCODE_MIN_LENGTH) {
                    $error = true;
                    $messageStack->add_session('postcode_shipping', ENTRY_POST_CODE_ERROR);
                }

                if ((is_numeric($country_shipping) == false) || ($country_shipping < 1)) {
                    $error = true;
                    $messageStack->add_session('zone_country_id_shipping', ENTRY_COUNTRY_ERROR);
                }
            }
            // end shipping
        }

        if ($error == true) {
            // hook notifier class
            $zco_notifier->notify('NOTIFY_FAILURE_DURING_NO_ACCOUNT');
            $request_param = isset($_REQUEST['request']) ? $_REQUEST['request'] : '';
            zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, 'step=2&request=' . $request_param . $params, 'SSL', false));
        } else {
            $accounts = 0;
            // create billing address
            $sql_data_array = array(
                'customers_firstname' => $firstname,
                'customers_lastname' => $lastname,
                'customers_email_address' => $email_address,
                'customers_nick' => $nick,
                'customers_telephone' => $telephone,
                'customers_fax' => $fax,
                'customers_newsletter' => (int)$newsletter,
                'customers_email_format' => $email_format,
                'customers_default_address_id' => 0,
                'customers_authorization' => (int)CUSTOMERS_APPROVAL_AUTHORIZATION
            );
            switch ($_SESSION['COWOA']) {
                case true:
                    // check for existing account
                    $account_query = "SELECT customers_id, customers_default_address_id, COWOA_account
                            FROM " . TABLE_CUSTOMERS . "
                            WHERE customers_email_address = '" . $email_address . "'
                            ORDER BY customers_id DESC;";
                    $account = $db->Execute($account_query);
                    $accounts = $account->RecordCount();

                    if ($accounts > 0) {
                        $num_account = 0;
                        while (!$account->EOF) {
                            $num_account++;
                            if ($num_account == 1) {
                                $_SESSION['customer_id'] = $account->fields['customers_id']; // set the customers id on the first loop
                                $sql_data_array['customers_id'] = $_SESSION['customer_id'];
                                $address_id = (int)$account->fields['customers_default_address_id'];
                                $sql_data_array['customers_default_address_id'] = $address_id;
                                // do not change registered accounts to guest accounts
                                //$sql_data_array['COWOA_account'] = $account->fields['COWOA_account'];
                                $db_action = 'update';
                                $db_customers_table_where = 'customers_id = ' . $_SESSION['customer_id'];
                            } elseif ($num_account > 1) {
                                if ($account->fields['customers_id'] != $_SESSION['customer_id']) { // account isn't the latest, so proceed
                                    // update orders
                                    $update_orders = "UPDATE " . TABLE_ORDERS . " SET customers_id = " . $_SESSION['customer_id'] . " WHERE customers_id = " . $account->fields['customers_id'] . ";";
                                    $db->Execute($update_orders);
                                    // delete accounts
                                    $delete_customers = "DELETE FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . $account->fields['customers_id'] . " LIMIT 1;";
                                    $db->Execute($delete_customers);
                                }
                            }
                            $account->MoveNext();
                        }

                        // get existing customers_authorization status
                        $customers_authorization_query = $db->Execute("SELECT customers_authorization FROM " . TABLE_CUSTOMERS . " WHERE customers_id = " . (int)$_SESSION['customer_id'] . " LIMIT 1;");
                        $customers_authorization = $sql_data_array['customers_authorization'] = $customers_authorization_query->fields['customers_authorization'];

                        break;
                    }
                    // do not break and execute default
                    // no break
                default:
                    // already previously checked for duplicate accounts so this must be a new registration
                    $sql_data_array['customers_password'] = zen_encrypt_password($password);
                    $sql_data_array['COWOA_account'] = (int)$cowoa;
                    $db_action = 'insert';
                    $db_customers_table_where = '';
                    break;
            }
            if ((CUSTOMERS_REFERRAL_STATUS == '2' and $customers_referral != '')) {
                $sql_data_array['customers_referral'] = $customers_referral;
            }
            if (ACCOUNT_GENDER == 'true') {
                $sql_data_array['customers_gender'] = $gender;
            }
            if (ACCOUNT_DOB == 'true') {
                $sql_data_array['customers_dob'] = empty($_POST['dob']) ? zen_db_prepare_input('0001-01-01 00:00:00') : zen_date_raw($_POST['dob']);
            }

            zen_db_perform(TABLE_CUSTOMERS, $sql_data_array, $db_action, $db_customers_table_where); // updated for COWOA

            if ($db_action == 'insert') {
                $_SESSION['customer_id'] = $db->Insert_ID();
            }

            $zco_notifier->notify('NOTIFY_MODULE_CREATE_ACCOUNT_ADDED_CUSTOMER_RECORD', array_merge(array('customer_id' => $_SESSION['customer_id']), $sql_data_array));

            $sql_data_array = array('address_title' => 'Billing',
                                    'customers_id' => $_SESSION['customer_id'],
                                    'entry_firstname' => $firstname,
                                    'entry_lastname' => $lastname,
                                    'entry_street_address' => $street_address,
                                    'entry_postcode' => $postcode,
                                    'entry_city' => $city,
                                    'entry_country_id' => $country);

            if ($db_action == 'update' && (int)$address_id != 0) {
                $sql_data_array['address_book_id'] = $address_id;
                $db_address_table_where = 'address_book_id = ' . (int)$address_id;
            } else {
                $old_db_action = $db_action;
                $db_action = 'insert';
                $db_address_table_where = '';
            }

            if (ACCOUNT_GENDER == 'true') {
                $sql_data_array['entry_gender'] = $gender;
            }
            if (ACCOUNT_COMPANY == 'true') {
                $sql_data_array['entry_company'] = $company;
            }
            if (ACCOUNT_SUBURB == 'true') {
                $sql_data_array['entry_suburb'] = $suburb;
            }
            if (ACCOUNT_STATE == 'true') {
                if ($zone_id > 0) {
                    $sql_data_array['entry_zone_id'] = $zone_id;
                    $sql_data_array['entry_state'] = '';
                } else {
                    $sql_data_array['entry_zone_id'] = '0';
                    $sql_data_array['entry_state'] = $state;
                }
            }

            if (ACCOUNT_TELEPHONE == 'true') {
                $sql_data_array['entry_telephone'] = $telephone;
            }

            zen_db_perform(TABLE_ADDRESS_BOOK, $sql_data_array, $db_action, $db_address_table_where); // updated for new COWOA method

            if ($db_action == 'insert' || $address_id == 0) { // if for some reason the address_id was set to 0, we need to get a new one from the step above
                $address_id = $db->Insert_ID();
                $sql = "UPDATE " . TABLE_CUSTOMERS . "
                  SET customers_default_address_id = '" . (int)$address_id . "'
                  WHERE customers_id = '" . (int)$_SESSION['customer_id'] . "'";

                $db->Execute($sql);
                if ($db_action == 'insert' && $old_db_action != 'update') {
                    $sql = "INSERT INTO " . TABLE_CUSTOMERS_INFO . "
                                (customers_info_id, customers_info_number_of_logons, customers_info_date_account_created)
                            VALUES (" . (int)$_SESSION['customer_id'] . ", 0, NOW())";

                    $db->Execute($sql);
                }
            }
            // End new COWOA

            if (enable_shippingAddress()) {
                // create shipping address
                $sql_data_array = array(
                    array('fieldName' => 'address_title', 'value' => 'Shipping', 'type' => 'string'),
                    array('fieldName' => 'customers_id', 'value' => $_SESSION['customer_id'], 'type' => 'integer'),
                    array('fieldName' => 'entry_firstname', 'value' => $firstname_shipping, 'type' => 'string'),
                    array('fieldName' => 'entry_lastname','value' => $lastname_shipping, 'type' => 'string'),
                    array('fieldName' => 'entry_street_address','value' => $street_address_shipping, 'type' => 'string'),
                    array('fieldName' => 'entry_postcode', 'value' => $postcode_shipping, 'type' => 'string'),
                    array('fieldName' => 'entry_city', 'value' => $city_shipping, 'type' => 'string'),
                    array('fieldName' => 'entry_country_id', 'value' => $country_shipping, 'type' => 'integer')
                );

                if (ACCOUNT_GENDER == 'true') {
                    $sql_data_array[] = array('fieldName' => 'entry_gender', 'value' => $gender_shipping, 'type' => 'enum:m|f');
                }
                if (ACCOUNT_COMPANY == 'true') {
                    $sql_data_array[] = array('fieldName' => 'entry_company', 'value' => $company_shipping, 'type' => 'string');
                }
                if (ACCOUNT_SUBURB == 'true') {
                    $sql_data_array[] = array('fieldName' => 'entry_suburb', 'value' => $suburb_shipping, 'type' => 'string');
                }
                if (ACCOUNT_STATE == 'true') {
                    if ($zone_id_shipping > 0) {
                        $sql_data_array[] = array('fieldName' => 'entry_zone_id', 'value' => $zone_id_shipping, 'type' => 'integer');
                        $sql_data_array[] = array('fieldName' => 'entry_state', 'value' => '', 'type' => 'string');
                    } else {
                        $sql_data_array[] = array('fieldName' => 'entry_zone_id', 'value' => 0, 'type' => 'integer');
                        $sql_data_array[] = array('fieldName' => 'entry_state', 'value' => $state_shipping, 'type' => 'string');
                    }
                }
                if (ACCOUNT_TELEPHONE_SHIPPING == 'true') {
                    $sql_data_array[] = array('fieldName' => 'entry_telephone', 'value' => $telephone_shipping, 'type' => 'string');
                }
                $db->perform(TABLE_ADDRESS_BOOK, $sql_data_array);

                $shipping_address_id = $db->Insert_ID();
                $sql = "UPDATE " . TABLE_CUSTOMERS . "
                    SET customers_default_shipping_address_id = '" . $shipping_address_id . "'
                    WHERE customers_id = " . (int)$_SESSION['customer_id'];

                $_SESSION['sendto'] = $_SESSION['cart_address_id'] = $shipping_address_id;
                $_SESSION['shipping'] = '';
            }

            // phpBB create account
            if (isset($phpBB) && $phpBB->phpBB['installed'] == true) {
                $phpBB->phpbb_create_account($nick, $password, $email_address);
            }
            // End phppBB create account

            if (SESSION_RECREATE == 'True') {
                zen_session_recreate();
            }

            $_SESSION['customer_first_name'] = $firstname;
            $_SESSION['customer_default_address_id'] = $address_id;
            $_SESSION['customer_country_id'] = $country;
            $_SESSION['customer_zone_id'] = $zone_id;
            $_SESSION['customers_authorization'] = $customers_authorization;
            if (!isset($_SESSION['sendto'])) {
                $_SESSION['sendto'] = $_SESSION['cart_address_id'] = $address_id;
            }

            // restore cart contents
            $_SESSION['cart']->restore_contents();

            if (OPRC_WELCOME_MESSAGE == 'true' && $_SESSION['COWOA'] != true) {
                // build the message content
                $name = $firstname . ' ' . $lastname;

                if (ACCOUNT_GENDER == 'true') {
                    if ($gender == 'm') {
                        $email_text = sprintf(EMAIL_GREET_MR, $lastname);
                    } else {
                        $email_text = sprintf(EMAIL_GREET_MS, $lastname);
                    }
                } else {
                    $email_text = sprintf(EMAIL_GREET_NONE, $firstname);
                }
                $html_msg['EMAIL_GREETING'] = str_replace('\n', '', $email_text);
                $html_msg['EMAIL_FIRST_NAME'] = $firstname;
                $html_msg['EMAIL_LAST_NAME']  = $lastname;

                // initial welcome
                $email_text .=  EMAIL_WELCOME;
                $html_msg['EMAIL_WELCOME'] = str_replace('\n', '', EMAIL_WELCOME);

                if (NEW_SIGNUP_DISCOUNT_COUPON != '' and NEW_SIGNUP_DISCOUNT_COUPON != '0') {
                    $coupon_id = NEW_SIGNUP_DISCOUNT_COUPON;
                    $coupon = $db->Execute("SELECT * FROM " . TABLE_COUPONS . " WHERE coupon_id = '" . (int)$coupon_id . "'");
                    $coupon_desc = $db->Execute("SELECT coupon_description FROM " . TABLE_COUPONS_DESCRIPTION . " WHERE coupon_id = '" . (int)$coupon_id . "' AND language_id = '" . (int)$_SESSION['languages_id'] . "'");
                    $db->Execute("INSERT INTO " . TABLE_COUPON_EMAIL_TRACK . " (coupon_id, customer_id_sent, sent_firstname, emailed_to, date_sent) VALUES ('" . (int)$coupon_id ."', '0', 'Admin', '" . $email_address . "', NOW())");

                    // if on, add in Discount Coupon explanation
                    //        $email_text .= EMAIL_COUPON_INCENTIVE_HEADER .
                    $email_text .= "\n" . EMAIL_COUPON_INCENTIVE_HEADER .
                    (!empty($coupon_desc->fields['coupon_description']) ? $coupon_desc->fields['coupon_description'] . "\n\n" : '') .
                    strip_tags(sprintf(EMAIL_COUPON_REDEEM, ' ' . $coupon->fields['coupon_code'])) . EMAIL_SEPARATOR;

                    $html_msg['COUPON_TEXT_VOUCHER_IS'] = EMAIL_COUPON_INCENTIVE_HEADER ;
                    $html_msg['COUPON_DESCRIPTION']     = (!empty($coupon_desc->fields['coupon_description']) ? '<strong>' . $coupon_desc->fields['coupon_description'] . '</strong>' : '');
                    $html_msg['COUPON_TEXT_TO_REDEEM']  = str_replace("\n", '', sprintf(EMAIL_COUPON_REDEEM, ''));
                    $html_msg['COUPON_CODE']  = $coupon->fields['coupon_code'];
                } //endif coupon

                if (NEW_SIGNUP_GIFT_VOUCHER_AMOUNT > 0) {
                    $coupon_code = zen_create_coupon_code();
                    $insert_query = $db->Execute("INSERT INTO " . TABLE_COUPONS . " (coupon_code, coupon_type, coupon_amount, date_created) VALUES ('" . $coupon_code . "', 'G', '" . NEW_SIGNUP_GIFT_VOUCHER_AMOUNT . "', NOW())");
                    $insert_id = $db->Insert_ID();
                    $db->Execute("INSERT INTO " . TABLE_COUPON_EMAIL_TRACK . " (coupon_id, customer_id_sent, sent_firstname, emailed_to, date_sent) VALUES ('" . $insert_id ."', '0', 'Admin', '" . $email_address . "', NOW())");

                    // if on, add in GV explanation
                    $email_text .= "\n\n" . sprintf(EMAIL_GV_INCENTIVE_HEADER, $currencies->format(NEW_SIGNUP_GIFT_VOUCHER_AMOUNT)) .
                    sprintf(EMAIL_GV_REDEEM, $coupon_code) .
                    EMAIL_GV_LINK . zen_href_link(FILENAME_GV_REDEEM, 'gv_no=' . $coupon_code, 'NONSSL', false) . "\n\n" .
                    EMAIL_GV_LINK_OTHER . EMAIL_SEPARATOR;
                    $html_msg['GV_WORTH'] = str_replace('\n', '', sprintf(EMAIL_GV_INCENTIVE_HEADER, $currencies->format(NEW_SIGNUP_GIFT_VOUCHER_AMOUNT)));
                    $html_msg['GV_REDEEM'] = str_replace('\n', '', str_replace('\n\n', '<br />', sprintf(EMAIL_GV_REDEEM, '<strong>' . $coupon_code . '</strong>')));
                    $html_msg['GV_CODE_NUM'] = $coupon_code;
                    $html_msg['GV_CODE_URL'] = str_replace('\n', '', EMAIL_GV_LINK . '<a href="' . zen_href_link(FILENAME_GV_REDEEM, 'gv_no=' . $coupon_code, 'NONSSL', false) . '">' . TEXT_GV_NAME . ': ' . $coupon_code . '</a>');
                    $html_msg['GV_LINK_OTHER'] = EMAIL_GV_LINK_OTHER;
                } // endif voucher

                // add in regular email welcome text
                $email_text .= "\n\n" . EMAIL_TEXT . EMAIL_CONTACT . EMAIL_GV_CLOSURE;

                $html_msg['EMAIL_MESSAGE_HTML']  = str_replace('\n', '', EMAIL_TEXT);
                $html_msg['EMAIL_CONTACT_OWNER'] = str_replace('\n', '', EMAIL_CONTACT);
                $html_msg['EMAIL_CLOSURE']       = nl2br(EMAIL_GV_CLOSURE);

                // include create-account-specific disclaimer
                $email_text .= "\n\n" . sprintf(EMAIL_DISCLAIMER_NEW_CUSTOMER, STORE_OWNER_EMAIL_ADDRESS). "\n\n";
                $html_msg['EMAIL_DISCLAIMER'] = sprintf(EMAIL_DISCLAIMER_NEW_CUSTOMER, '<a href="mailto:' . STORE_OWNER_EMAIL_ADDRESS . '">'. STORE_OWNER_EMAIL_ADDRESS .' </a>');

                //send welcome email
                zen_mail($name, $email_address, EMAIL_SUBJECT, $email_text, STORE_NAME, EMAIL_FROM, $html_msg, 'welcome');

                // send additional emails
                if (SEND_EXTRA_CREATE_ACCOUNT_EMAILS_TO_STATUS == '1' and SEND_EXTRA_CREATE_ACCOUNT_EMAILS_TO != '') {
                    if ($_SESSION['customer_id']) {
                        $account_query = "SELECT customers_firstname, customers_lastname, customers_email_address
                                FROM " . TABLE_CUSTOMERS . "
                                WHERE customers_id = '" . (int)$_SESSION['customer_id'] . "'";

                        $account = $db->Execute($account_query);
                    }

                    $extra_info = email_collect_extra_info($name, $email_address, $account->fields['customers_firstname'] . ' ' . $account->fields['customers_lastname'], $account->fields['customers_email_address']);
                    $html_msg['EXTRA_INFO'] = $extra_info['HTML'];
                    zen_mail(
                        '',
                        SEND_EXTRA_CREATE_ACCOUNT_EMAILS_TO,
                        SEND_EXTRA_CREATE_ACCOUNT_EMAILS_TO_SUBJECT . ' ' . EMAIL_SUBJECT,
                        $email_text . $extra_info['TEXT'],
                        STORE_NAME,
                        EMAIL_FROM,
                        $html_msg,
                        'welcome_extra'
                    );
                } //endif send extra emails
            }
        } //endif !error
    } // end if !$_SESSION['customer_id'];
}

// hook notifier class
if ($_SESSION['COWOA']) {
    $zco_notifier->notify('NOTIFY_LOGIN_SUCCESS_VIA_NO_ACCOUNT');
} else {
    $zco_notifier->notify('NOTIFY_LOGIN_SUCCESS_VIA_OPRC_CREATE_ACCOUNT');
}

if ($my_account && isset($_SESSION['customer_id'])) {
    // customer logged in without adding products to their cart
    zen_redirect(zen_href_link(FILENAME_ACCOUNT, '', 'SSL', false));
    //zen_redirect(oprc_back_link(true));
} else {
    if (isset($_REQUEST['request'])) {
        zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, 'request=' . $_REQUEST['request'] . $params, 'SSL', false));
    } else {
        zen_redirect(zen_href_link(FILENAME_ONE_PAGE_CHECKOUT, $params, 'SSL', false));
    }

}

// This should be last line of the script:
$zco_notifier->notify('NOTIFY_MODULE_END_NO_ACCOUNT');
exit();
