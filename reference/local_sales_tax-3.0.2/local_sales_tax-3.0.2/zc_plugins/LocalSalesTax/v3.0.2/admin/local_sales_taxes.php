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
 *  local_sales_taxes.php  version 2.5.3
 */
require 'includes/application_top.php';

zen_define_default('MAX_DISPLAY_TAX_RESULTS', '1000');

//input cleanup and validation to protect against malicious input
$_GET['page'] = (int)($_GET['page'] ?? '1');
if ($_GET['page'] < 1) {
    $_GET['page'] = 1;
}
if (isset($_GET['stID'])) {
    $_GET['stID'] = (int)$_GET['stID'];
}

//create array that can be used for Zip, City, or Suburb, depending on what is selected in the 'Local Sales Taxes' plug-in
$za_lookup = [
    ['id' => 'postcode', 'text' => 'Zip Code'],
    ['id' => 'city', 'text' => 'City'],
    ['id' => 'suburb', 'text' => 'Suburb'],
];

$action = ($_GET['action'] ?? '');

//insert, save or deleteconfirm
if ($action !== '') {
    switch ($action){
        case 'insert':
            $tax_shipping = (isset($_POST['tax_shipping'])) ? 'true' : 'false';
            $db->Execute(
                "INSERT INTO " . TABLE_LOCAL_SALES_TAXES . "
                    (zone_id, local_fieldmatch, local_datamatch, local_tax_rate, local_tax_label, local_tax_shipping, local_tax_class_id)
                 VALUES
                    (
                        " . (int)$_POST['zone_id'] . ",
                       '" . zen_db_input($_POST['tax_fieldmatch']) . "',
                       '" . zen_db_input($_POST['tax_datamatch']) . "',
                       " . (float)$_POST['tax_rate'] . ",
                       '" . zen_db_input($_POST['tax_description']) . "',
                       '$tax_shipping',
                       " . (int)$_POST['tax_class_id'] ."
                    )"
            );
            zen_redirect(zen_href_link(FILENAME_LOCAL_SALES_TAXES));
            break;

        case 'save':
            $local_tax_id = (int)($_GET['stID'] ?? '0');
            $tax_shipping = (isset($_POST['tax_shipping'])) ? 'true' : 'false';
            $db->Execute(
                "UPDATE " . TABLE_LOCAL_SALES_TAXES . "
                    SET zone_id = " . (int)$_POST['zone_id'] . ",
                        local_fieldmatch = '" . zen_db_input($_POST['tax_fieldmatch']) . "',
                        local_datamatch = '" . zen_db_input($_POST['tax_datamatch']) . "',
                        local_tax_rate = " . (float)$_POST['tax_rate'] . ",
                        local_tax_label = '" . zen_db_input($_POST['tax_description']) . "',
                        local_tax_shipping = '$tax_shipping',
                        local_tax_class_id = " . (int)$_POST['tax_class_id'] . "
                  WHERE local_tax_id = " . $local_tax_id . "
                  LIMIT 1"
            );
            zen_redirect(zen_href_link(FILENAME_LOCAL_SALES_TAXES, 'page=' . $_GET['page'] . '&stID=' . $local_tax_id));
            break;

        case 'deleteconfirm':
            $local_tax_id = (int)($_GET['stID'] ?? '0');

            $db->Execute("DELETE FROM " . TABLE_LOCAL_SALES_TAXES . " WHERE local_tax_id = $local_tax_id LIMIT 1");

            zen_redirect(zen_href_link(FILENAME_LOCAL_SALES_TAXES, 'page=' . $_GET['page']));
            break;

        default:
            break;
    }
}

//The following sets up the screen display and FORM field actions
?>
<!doctype html>
<html <?= HTML_PARAMS ?>>
  <head>
    <?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
    <script>
function update_zone(theForm){
    var NumState = theForm.zone_id.options.length;
    var SelectedCountry = '';

    while (NumState > 0) {
        NumState--;
        theForm.zone_id.options[NumState] = null;
    }
    SelectedCountry = theForm.zone_country_id.options[theForm.zone_country_id.selectedIndex].value;
    <?= zen_js_zone_list('SelectedCountry', 'theForm', 'zone_id') ?>
}
    </script>
  </head>
  <body>
<?php require DIR_WS_INCLUDES . 'header.php'; ?>
    <div class="container-fluid">
      <h1><?= HEADING_TITLE ?></h1>
      <!-- body_text //-->
      <div class="row">
          <div class="col-xs-12 col-sm-12 col-md-9 col-lg-9 configurationColumnLeft">
            <table class="table table-hover table-striped">
              <thead>
                <tr class="dataTableHeadingRow">
                  <th class="dataTableHeadingContent"><?= TABLE_HEADING_LOCAL_SALES_TAX_ID ?></th>
                  <th class="dataTableHeadingContent"><?= TABLE_HEADING_LOCAL_SALES_TAX_ZONE ?></th>
                  <th class="dataTableHeadingContent text-center"><?= TABLE_HEADING_LOCAL_SALES_TAX_FIELD ?></th>
                  <th class="dataTableHeadingContent text-center"><?= TABLE_HEADING_LOCAL_SALES_TAX_DATA ?></th>
                  <th class="dataTableHeadingContent text-center"><?= TABLE_HEADING_LOCAL_SALES_TAX_RATE ?></th>
                  <th class="dataTableHeadingContent text-center"><?= TABLE_HEADING_LOCAL_SALES_TAX_LABEL ?></th>
                  <th class="dataTableHeadingContent text-center"><?= TABLE_HEADING_LOCAL_SALES_TAX_SHIPPING ?></th>
                  <th class="dataTableHeadingContent text-right"><?= TABLE_HEADING_LOCAL_SALES_TAX_CLASS ?></th>
                  <th class="dataTableHeadingContent text-right"><?= TABLE_HEADING_ACTION ?></th>
                </tr>
              </thead>
              <tbody>
<?php
$localtax_query_raw =
    "SELECT st.local_tax_id, st.zone_id, st.local_fieldmatch, st.local_datamatch, st.local_tax_rate, st.local_tax_label, st.local_tax_shipping, st.local_tax_class_id,
            tc.tax_class_title, z.zone_name, z.zone_country_id
       FROM " . TABLE_LOCAL_SALES_TAXES . " st
            LEFT JOIN " . TABLE_TAX_CLASS . " tc
                ON st.local_tax_class_id = tc.tax_class_id
            LEFT JOIN " . TABLE_ZONES . " z
                ON st.zone_id = z.zone_id
      ORDER BY st.zone_id, st.local_datamatch";
$localtax_split = new splitPageResults($_GET['page'], MAX_DISPLAY_TAX_RESULTS, $localtax_query_raw, $localtax_query_numrows);
$localtax = $db->Execute($localtax_query_raw);

foreach ($localtax as $local) {
    if ((!isset($_GET['stID']) || $_GET['stID'] == $localtax->fields['local_tax_id']) && !isset($stInfo) && !str_starts_with($action, 'new')) {
        $stInfo = new objectInfo($local);
    }

    if (isset($stInfo) && is_object($stInfo) && $local['local_tax_id'] == $stInfo->local_tax_id) {
        $is_selected = true;
?>
                <tr id="defaultSelected" class="dataTableRowSelected" onclick="document.location.href = '<?= zen_href_link(FILENAME_LOCAL_SALES_TAXES, 'page=' . $_GET['page'] . '&stID=' . $stInfo->local_tax_id . '&action=edit') ?>'">
<?php
    } else {
        $is_selected = false;
?>
                <tr class="dataTableRow" onclick="document.location.href = '<?= zen_href_link(FILENAME_LOCAL_SALES_TAXES, 'page=' . $_GET['page'] . '&stID=' . $localtax->fields['local_tax_id']) ?>'">
<?php
    }
?>
                    <td class="dataTableContent"><?php echo $local['local_tax_id']; ?></td>
                    <td class="dataTableContent"><?php echo $local['zone_name']; ?></td>
                    <td class="dataTableContent text-center"><?php echo $local['local_fieldmatch']; ?></td>
                    <td class="dataTableContent text-center"><?php echo wordwrap($local['local_datamatch'], 90, "\n", true); ?></td>
                    <td class="dataTableContent text-center"><?php echo $local['local_tax_rate']; ?></td>
                    <td class="dataTableContent text-center"><?php echo $local['local_tax_label']; ?></td>
                    <td class="dataTableContent text-center"><?php echo $local['local_tax_shipping']; ?></td>
                    <td class="dataTableContent text-center"><?php echo $local['tax_class_title']; ?></td>
                    <td class="dataTableContent text-right">
<?php
    if ($is_selected === true){
        echo zen_icon('caret-right', '', '2x', true);
    } else {
?>
                        <a href="<?= zen_href_link(FILENAME_LOCAL_SALES_TAXES, 'page=' . $_GET['page'] . '&stID=' . $localtax->fields['local_tax_id']) ?>">
                            <?= zen_icon('circle-info', '', '2x', true) ?>
                        </a>
<?php
    }
?>
                    </td>
                </tr>

<?php
}
?>
              </tbody>
            </table>
          </div>
          <div class="col-xs-12 col-sm-12 col-md-3 col-lg-3 configurationColumnRight">
<?php
$heading = [];
$contents = [];
$link_params = 'page=' . $_GET['page'];
if (isset($stInfo)) {
    $link_params .= '&stID=' . $stInfo->local_tax_id;
}
switch ($action) {
    case 'new':
        $heading[] = ['text' => '<b>' . TEXT_INFO_HEADING_NEW_LOCAL_SALES_TAX . '</b>'];

        $contents = ['form' => zen_draw_form('local_sales_tax', FILENAME_LOCAL_SALES_TAXES, 'page=' . $_GET['page'] . '&action=insert', 'post', 'class="form-horizontal"')];
        $contents[] = ['text' => TEXT_INFO_INSERT_INTRO];
        $contents[] = ['text' => '<br>' . TEXT_INFO_COUNTRY . '<br>' . zen_draw_pull_down_menu('zone_country_id', zen_get_countries_for_admin_pulldown(), STORE_COUNTRY, 'class="form-control" onchange="update_zone(this.form);"')];
        $contents[] = ['text' => '<br>' . TEXT_INFO_COUNTRY_ZONE . '<br>' . zen_draw_pull_down_menu('zone_id', zen_prepare_country_zones_pull_down(STORE_COUNTRY), STORE_ZONE, 'class="form-control"')];
        $contents[] = ['text' => '<br>' . TEXT_INFO_TAX_RATE . '<br>' . zen_draw_input_field('tax_rate', '', 'class="form-control"')];
        $contents[] = ['text' => '<br>' . TEXT_INFO_FIELDMATCH . '<br>' . zen_draw_pull_down_menu('tax_fieldmatch', $za_lookup, 'postcode', 'class="form-control"')];
        $contents[] = ['text' => '<br>' . TEXT_INFO_DATAMATCH . '<br>' . zen_draw_textarea_field('tax_datamatch', 'soft', 35, 4, '', 'class="form-control"')];
        $contents[] = ['text' => '<br>' . TEXT_INFO_RATE_DESCRIPTION . '<br>' . zen_draw_input_field('tax_description', '', 'class="form-control"')];
        $contents[] = ['text' => '<br>' . TEXT_INFO_TAX_SHIPPING . ' ' . zen_draw_checkbox_field('tax_shipping', 'on')];
        $contents[] = ['text' => '<br>' . TEXT_INFO_TAX_CLASS_TITLE . '<br>' . zen_tax_classes_pull_down('class="form-control" name="tax_class_id"')];
        $contents[] = ['align' => 'center', 'text' => '<button type="submit" class="btn btn-warning">' . IMAGE_INSERT . '</button> <a href="' . zen_href_link(FILENAME_LOCAL_SALES_TAXES, 'page=' . $_GET['page']) . '" class="btn btn-default" role="button">' . IMAGE_CANCEL . '</a>'];
        break;

    case 'edit':
        $heading[] = ['text' => '<b>' . TEXT_INFO_HEADING_EDIT_LOCAL_SALES_TAX . '</b>'];

        $contents = ['form' => zen_draw_form('local_sales_tax', FILENAME_LOCAL_SALES_TAXES, $link_params . '&action=save', 'post', 'class="form-horizontal"')];
        $contents[] = ['text' => TEXT_INFO_EDIT_INTRO];
        $contents[] = ['text' => '<br>' . TEXT_INFO_COUNTRY . '<br>' . zen_draw_pull_down_menu('zone_country_id', zen_get_countries_for_admin_pulldown(), $stInfo->zone_country_id, 'class="form-control" onchange="update_zone(this.form);"')];
        $contents[] = ['text' => '<br>' . TEXT_INFO_COUNTRY_ZONE . '<br>' . zen_draw_pull_down_menu('zone_id', zen_prepare_country_zones_pull_down($stInfo->zone_country_id), $stInfo->zone_id, 'class="form-control"')];
        $contents[] = ['text' => '<br>' . TEXT_INFO_TAX_RATE . '<br>' . zen_draw_input_field('tax_rate', $stInfo->local_tax_rate, 'class="form-control"')];
        $contents[] = ['text' => '<br>' . TEXT_INFO_FIELDMATCH . '<br>' . zen_draw_pull_down_menu('tax_fieldmatch', $za_lookup, $stInfo->local_fieldmatch, 'class="form-control"')];
        $contents[] = ['text' => '<br>' . TEXT_INFO_DATAMATCH . '<br>' . zen_draw_textarea_field('tax_datamatch', 'soft', 35, 4, $stInfo->local_datamatch, 'class="form-control"')];
        $contents[] = ['text' => '<br>' . TEXT_INFO_RATE_DESCRIPTION . '<br>' . zen_draw_input_field('tax_description', $stInfo->local_tax_label, 'class="form-control"')];
        $contents[] = ['text' => '<br>' . TEXT_INFO_TAX_SHIPPING . ' ' . zen_draw_checkbox_field('tax_shipping', 'on', $stInfo->local_tax_shipping === 'true')];
        $contents[] = ['text' => '<br>' . TEXT_INFO_TAX_CLASS_TITLE . '<br>' . zen_tax_classes_pull_down('class="form-control" name="tax_class_id"', $stInfo->local_tax_class_id)];
        $contents[] = ['align' => 'center', 'text' => '<button type="submit" class="btn btn-warning">' . IMAGE_UPDATE . '</button> <a href="' . zen_href_link(FILENAME_LOCAL_SALES_TAXES, $link_params) . '" class="btn btn-default" role="button">' . IMAGE_CANCEL . '</a>'];
        break;

    case 'delete':
        $heading[] = ['text' => '<b>' . TEXT_INFO_HEADING_DELETE_LOCAL_SALES_TAX . '</b>'];

        $contents = ['form' => zen_draw_form('local_sales_tax', FILENAME_LOCAL_SALES_TAXES, $link_params . '&action=deleteconfirm')];
        $contents[] = ['text' => TEXT_INFO_DELETE_INTRO];
        $contents[] = ['text' => '<br><b>' . $stInfo->local_tax_label . ' - ' . $stInfo->local_tax_rate . '%</b>'];
        $contents[] = ['align' => 'center', 'text' => '<button type="submit" class="btn btn-danger">' . IMAGE_DELETE . '</button> <a href="' . zen_href_link(FILENAME_LOCAL_SALES_TAXES, $link_params) . '" class="btn btn-default" role="button">' . IMAGE_CANCEL . '</a>'];
        break;

    default:
        if (isset($stInfo) && is_object($stInfo)) {
            $heading[] = ['text' => '<b>' . $stInfo->local_tax_label . '</b>'];

            $contents[] = ['align' => 'center', 'text' => '<a href="' . zen_href_link(FILENAME_LOCAL_SALES_TAXES, $link_params . '&action=edit') . '" class="btn btn-primary" role="button">' . IMAGE_EDIT . '</a> <a href="' . zen_href_link(FILENAME_LOCAL_SALES_TAXES, $link_params . '&action=delete') . '" class="btn btn-warning" role="button">' . IMAGE_DELETE . '</a>'];
            $contents[] = ['text' => '<br>' . TEXT_INFO_DESCRIPTION . '<br>' . $stInfo->local_tax_label . ' - ' . $stInfo->local_tax_rate . '%'];
        }
        break;

}

if (!empty($heading) && !empty($contents)){
    $box = new box;
    echo $box->infoBox($heading, $contents);
}
?>
          </div>
        </div>
        <div class="row">
          <table class="table">
            <tr>
              <td><?= $localtax_split->display_count($localtax_query_numrows, MAX_DISPLAY_TAX_RESULTS, $_GET['page'], TEXT_DISPLAY_NUMBER_OF_LOCAL_ST) ?></td>
              <td class="text-right"><?= $localtax_split->display_links($localtax_query_numrows, MAX_DISPLAY_TAX_RESULTS, MAX_DISPLAY_PAGE_LINKS, $_GET['page']) ?></td>
            </tr>
<?php
if ($action == '') {
?>
            <tr>
              <td class="text-right" colspan="2">
                <a href="<?= zen_href_link(FILENAME_LOCAL_SALES_TAXES, 'page=' . $_GET['page'] . '&action=new') ?>" class="btn btn-primary" role="button">
                    <?= IMAGE_NEW_TAX_RATE ?>
                </a>
              </td>
            </tr>
<?php
}
?>
          </table>
        </div>
    </div>
<!-- body_eof //-->
<!-- footer //-->
<?php require DIR_WS_INCLUDES . 'footer.php'; ?>
<!-- footer_eof //-->
  </body>
</html>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
