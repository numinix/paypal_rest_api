<?php
$define = [
	'TEXT_BUTTON_SAVE_CHANGES' => 'Save Changes',
	'TEXT_BUTTON_CANCEL' => 'Cancel',
	'BOX_CONFIGURATION_DEFAULT' => 'Default Configuration Template'
];

$zc158 = (PROJECT_VERSION_MAJOR > 1 || (PROJECT_VERSION_MAJOR == 1 && substr(PROJECT_VERSION_MINOR, 0, 3) >= '5.8'));
if ($zc158) {
    return $define;
} else {
    nmx_create_defines($define);
}