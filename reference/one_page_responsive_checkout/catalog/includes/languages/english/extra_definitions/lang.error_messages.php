<?php
/* error messages for JavaScript validation */
$define = [
    'OPRC_ENTRY_COMPANY_ERROR' => 'Please enter a company name.',
    'OPRC_ENTRY_GENDER_ERROR' => 'Please choose a salutation.',
    'OPRC_ENTRY_FIRST_NAME_ERROR' => 'Please enter a valid first name, minimum of ' . ENTRY_FIRST_NAME_MIN_LENGTH . ' characters.',
    'OPRC_ENTRY_LAST_NAME_ERROR' => 'Please enter a valid last name, minimum of ' . ENTRY_LAST_NAME_MIN_LENGTH . ' characters.',
    'OPRC_ENTRY_DATE_OF_BIRTH_ERROR' => 'Please select your date of birth.',
    'OPRC_ENTRY_EMAIL_ADDRESS_ERROR' => 'Please enter a valid address, minimum of ' . ENTRY_EMAIL_ADDRESS_MIN_LENGTH . ' characters.',
    'OPRC_ENTRY_EMAIL_ADDRESS_CHECK_ERROR' => 'Please enter a valid email address.',
    'OPRC_ENTRY_EMAIL_ADDRESS_ERROR_EXISTS' => 'Email address already exists.',
    'OPRC_ENTRY_EMAIL_ADDRESS_CONFIRM_ERROR' => 'Email address confirmation mismatch.',
    'OPRC_ENTRY_NICK_DUPLICATE_ERROR' => 'Nickname is already in use.',
    'OPRC_ENTRY_NICK_LENGTH_ERROR' => 'Please enter a valid nick name, minimum ' . ENTRY_NICK_MIN_LENGTH . ' characters.',
    'OPRC_ENTRY_STREET_ADDRESS_ERROR' => 'Please enter a valid street address, minimum of ' . ENTRY_STREET_ADDRESS_MIN_LENGTH . ' characters.',
    'OPRC_ENTRY_POST_CODE_ERROR' => 'Please enter a valid postal/zip code, minimum of ' . ENTRY_POSTCODE_MIN_LENGTH . ' characters.',
    'OPRC_ENTRY_CITY_ERROR' => 'Please enter a valid city, minimum of ' . ENTRY_CITY_MIN_LENGTH . ' characters.',
    'OPRC_ENTRY_STATE_ERROR' => 'Please enter a valid state, minimum of ' . ENTRY_STATE_MIN_LENGTH . ' characters.',
    'OPRC_ENTRY_STATE_ERROR_SELECT' => 'Select a state.',
    'OPRC_ENTRY_COUNTRY_ERROR' => 'Please select a country from the countries pull down menu.',
    'OPRC_ENTRY_TELEPHONE_NUMBER_ERROR' => 'Please enter a valid telephone number, minimum of ' . ENTRY_TELEPHONE_MIN_LENGTH . ' characters.',
    'OPRC_ENTRY_FAX_NUMBER_ERROR' => '',
    'OPRC_ENTRY_NEWSLETTER_ERROR' => '',
    'OPRC_ENTRY_PASSWORD_ERROR' => 'Please enter a valid password, minimum of ' . ENTRY_PASSWORD_MIN_LENGTH . ' characters.',
    'OPRC_ENTRY_PASSWORD_ERROR_NOT_MATCHING' => 'Password confirmation mismatch.',
    'OPRC_ENTRY_EXCEEDED_ACCOUNTS_ERROR' => 'Address entries exceeded. Please delete an address before adding a new one.'
];

if (defined('ACCOUNT_DOB_REJECT_REGISTRATION')) {
    $define['OPRC_ENTRY_DATE_OF_BIRTH_UNDER_AGE_ERROR'] = 'You must be older than '. ACCOUNT_DOB_REJECT_REGISTRATION .' to register.';
}

$zc158 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= '5.8'));
if ($zc158) {
    return $define;
} else {
    nmx_create_defines($define);
}
