<?php
/**
 *  ot_local_sales_tax module
 *
 *   By Heather Gardner AKA: LadyHLG
 *   The module should apply tax based on the field you
 *    choose options are Zip Code, City, and Suburb.
 *    It should also compound the tax to whatever zone
 *    taxes you already have set up.  Which means you
 *    can apply multiple taxes to any zone based on
 *    different criteria.
 */
return [
    'HEADING_TITLE' => 'Local Sales Taxes',

    'TEXT_INFO_HEADING_NEW_LOCAL_SALES_TAX' => 'New Local Sales Tax',
    'TEXT_INFO_INSERT_INTRO' => 'Please enter the new sales tax with its related data',
    'TEXT_INFO_COUNTRY' => 'Country:',
    'TEXT_INFO_COUNTRY_ZONE' => 'Zone:<br>What zone is this tax applied to?',
    'TEXT_INFO_TAX_RATE' => 'Tax Rate (%)',
    'TEXT_INFO_FIELDMATCH' => 'Search Field:<br>What info are we basing this sales tax on?',
    'TEXT_INFO_DATAMATCH' => 'Search Data:<br>What data are we searching for?
        <br>For Ranges use "-to-" for separator<br>example:53000-to-56000.
        <br><br>Use semi-colon with no spaces for deliminated lists
        <br>example:Madison;Milwaukee;Green Bay
        <br><br>Delimited lists may include both ranges and single entries
        <br>example:53525;53711;53528;54000-to-56000',
    'TEXT_INFO_RATE_DESCRIPTION' => 'Description:<br>Tax description will appear in cart checkout.',
    'TEXT_INFO_TAX_SHIPPING' => 'Apply this tax to shipping charges?',
    'TEXT_INFO_TAX_CLASS_TITLE' => 'Tax Class:',

    'TEXT_INFO_DESCRIPTION' => 'Description',

    'TEXT_ALL_COUNTRIES' => 'All Countries',
    'TYPE_BELOW' => 'Select Zone',
    'PLEASE_SELECT' => 'Please Select',

    'TEXT_INFO_HEADING_EDIT_LOCAL_SALES_TAX' => 'Edit Tax Rate',
    'TEXT_INFO_EDIT_INTRO' => 'Please make any necessary changes',

    'TEXT_INFO_HEADING_DELETE_LOCAL_SALES_TAX' => 'Delete Tax Rate',
    'TEXT_INFO_DELETE_INTRO' => 'Are you sure you want to delete this tax rate?',

    'TABLE_HEADING_LOCAL_SALES_TAX_ZONE' => 'Tax Zone',
    'TABLE_HEADING_LOCAL_SALES_TAX_FIELD' => 'Apply To',
    'TABLE_HEADING_LOCAL_SALES_TAX_DATA' => 'Look For',
    'TABLE_HEADING_LOCAL_SALES_TAX_RATE' => 'Tax Rate',
    'TABLE_HEADING_LOCAL_SALES_TAX_LABEL' => 'Tax Description',
    'TABLE_HEADING_LOCAL_SALES_TAX_SHIPPING' => 'Tax Shipping',
    'TABLE_HEADING_LOCAL_SALES_TAX_CLASS' => 'Tax Class',
    'TABLE_HEADING_LOCAL_SALES_TAX_ID' => 'ID',
    'TABLE_HEADING_ACTION' => 'Action',

    'TEXT_DISPLAY_NUMBER_OF_LOCAL_ST' => 'Displaying <b>%d</b> to <b>%d</b> (of <b>%d</b> local taxes)',
];
