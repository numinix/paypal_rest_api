  <?php
    $countries = $db->execute('SELECT countries_id, phone_format from ' . TABLE_COUNTRIES);
    $country_phone_formats = array();

    while(!$countries->EOF) {
     $country_phone_formats[$countries->fields['countries_id']] = $countries->fields['phone_format'];
     if(strlen($countries->fields['phone_format']) == 0) {
       $country_phone_formats[$countries->fields['countries_id']] = "99999999999999"; //clear mask, this country doesn't have one.
     }

     $countries->MoveNext();
   }
?>
<script language="javascript" type="text/javascript"><!--//
   var country_phone_formats = JSON.parse('<?php echo json_encode($country_phone_formats); ?>');

  jQuery(document).ready(function() {
     //for address book edit form in my account
     if($('[name="addressbook"]').length) {
        $('[name="addressbook"]').find("#telephone").mask(country_phone_formats[$('[name="addressbook"]').find('#country').val()]);
        $('[name="addressbook"]').find('#country').change(function(){      
          $('[name="addressbook"]').find("#telephone").mask(country_phone_formats[$('[name="addressbook"]').find('#country').val()]);
        });
     }

     //for OPRC checkout
     if($('[name="checkout_address"]').length) {
       $("#telephone").mask(country_phone_formats[$('[name="checkout_address"]').find('#country').val()]);
       $('[name="checkout_address"]').find('#country').change(function(){      
         $("#telephone").mask(country_phone_formats[$('[name="checkout_address"]').find('#country').val()]);
       });
     }
  });

//--></script>

