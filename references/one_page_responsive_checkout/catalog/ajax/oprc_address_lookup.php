<?php
require('../includes/configure.php');
ini_set('include_path', DIR_FS_CATALOG . PATH_SEPARATOR . ini_get('include_path'));
chdir(DIR_FS_CATALOG);
require_once('includes/application_top.php');

header('Content-Type: application/json');

if (!function_exists('oprc_address_lookup_sanitize_value')) {
    /**
     * Removes HTML markup and control characters from provider data while preserving symbols like ampersands.
     *
     * @param string $value
     * @return string
     */
    function oprc_address_lookup_sanitize_value($value)
    {
        static $sanitizeControlCharactersPattern = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u';

        $value = (string)$value;
        $value = strip_tags($value);

        if ($value !== '') {
            $cleaned = preg_replace($sanitizeControlCharactersPattern, '', $value);
            if ($cleaned !== null) {
                $value = $cleaned;
            }
        }

        return trim($value);
    }
}

$response = [
    'success' => false,
    'addresses' => []
];

$manager = oprc_address_lookup_manager();
if (!$manager->isEnabled()) {
    $response['message'] = TEXT_OPRC_ADDRESS_LOOKUP_UNAVAILABLE;
    echo json_encode($response);
    require_once('includes/application_bottom.php');
    exit;
}

$postalCode = isset($_REQUEST['postal_code']) ? zen_db_prepare_input($_REQUEST['postal_code']) : '';
$postalCode = trim($postalCode);
if ($postalCode === '') {
    $response['message'] = TEXT_OPRC_ADDRESS_LOOKUP_MISSING_POSTCODE;
    echo json_encode($response);
    require_once('includes/application_bottom.php');
    exit;
}

$context = [];
if (isset($_REQUEST['context']) && is_array($_REQUEST['context'])) {
    foreach ($_REQUEST['context'] as $key => $value) {
        if (is_scalar($value)) {
            $context[$key] = trim(zen_db_prepare_input($value));
        }
    }
}

$addresses = $manager->lookup($postalCode, $context);
if (!empty($addresses)) {
    foreach ($addresses as &$address) {
        if (isset($address['label'])) {
            $address['label'] = oprc_address_lookup_sanitize_value($address['label']);
        }
        if (isset($address['fields']) && is_array($address['fields'])) {
            foreach ($address['fields'] as $field => $value) {
                $address['fields'][$field] = oprc_address_lookup_sanitize_value($value);
            }
        }
    }
    unset($address);
    $response['success'] = true;
    $response['addresses'] = $addresses;
    $response['provider'] = [
        'key' => $manager->getProviderKey(),
        'title' => $manager->getProviderTitle()
    ];
    echo json_encode($response);
    require_once('includes/application_bottom.php');
    exit;
}

$message = $manager->getLastError();
if ($message === '') {
    $message = TEXT_OPRC_ADDRESS_LOOKUP_NO_RESULTS;
}

$response['message'] = zen_output_string_protected($message);

echo json_encode($response);

require_once('includes/application_bottom.php');
exit;
// eof
