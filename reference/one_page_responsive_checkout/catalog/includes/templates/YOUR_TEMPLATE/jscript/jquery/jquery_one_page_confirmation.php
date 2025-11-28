<script type="text/javascript"><!--
jQuery(document).ready(function() {
<?php if (OPRC_ONE_PAGE == 'true') { ?>
  if (jQuery(window).width() >= 1000) {
    jQuery('#checkoutConfirmDefault').css('visibility', 'hidden');
    blockPage();
    jQuery('[name="checkout_confirmation"]').submit();
  }
<?php } ?>
  jQuery('[name="checkout_confirmation"]').submit(function() {
    jQuery('#btn_submit').attr("disabled", true);
    blockPage();
    return true;    
  });
  if (jQuery('#messageStackErrors').children('.messageStackError').length > 0 || jQuery('#messageStackErrors').children('.messageStackCaution').length > 0 || jQuery('#messageStackErrors').children('.messageStackSuccess').length > 0) {
    var messageStackErrors = jQuery('#messageStackErrors').html();
    // update messageStack only
    displayErrors(messageStackErrors);  
  }
});
function displayErrors(messageStackErrors) {
  var cssObject = { 'position' : 'fixed',
                    'top' : '0',
                    'left' : '0',  
                    'z-index' : '2000',
                    'width' : '100%',
                    'text-align' : 'center'
                  }
  jQuery('div#messageStackErrors').replaceWith('<div id="messageStackErrors">' + messageStackErrors + '</div>');
  jQuery('div#messageStackErrors').css(cssObject);
<?php if ((int)OPRC_MESSAGESTACK_DURATION > 0) { ?>
  setTimeout("jQuery('div#messageStackErrors').fadeOut()", <?php echo (int)OPRC_MESSAGESTACK_DURATION; ?>); // ignore syntax error
<?php } ?> 
}
function blockPage() {
  jQuery.blockUI({
    message: '<?php echo addslashes(OPRC_PROCESSING_TEXT); ?>', 
    css: { 
      border: 'none', 
      padding: '15px', 
      backgroundColor: '<?php echo OPRC_MESSAGE_BACKGROUND_COLOR; ?>', 
      '-webkit-border-radius': '10px', 
      '-moz-border-radius': '10px', 
      opacity: '<?php echo OPRC_MESSAGE_OPACITY; ?>', 
      color: '<?php echo OPRC_MESSAGE_TEXT_COLOR; ?>' 
    },
    overlayCSS: { 
      backgroundColor: '<?php echo OPRC_MESSAGE_OVERLAY_COLOR; ?>',
      color: '<?php echo OPRC_MESSAGE_OVERLAY_TEXT_COLOR; ?>',
      opacity: '<?php echo OPRC_MESSAGE_OVERLAY_OPACITY; ?>'
    } 
  });
}
--></script>