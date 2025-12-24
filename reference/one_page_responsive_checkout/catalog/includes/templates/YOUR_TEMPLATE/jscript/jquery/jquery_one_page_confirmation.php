<script type="text/javascript"><!--
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
  <?php if (defined('OPRC_MESSAGESTACK_DURATION') && (int)OPRC_MESSAGESTACK_DURATION > 0) { ?>
  setTimeout("jQuery('div#messageStackErrors').fadeOut()", <?php echo (int)OPRC_MESSAGESTACK_DURATION; ?>); // ignore syntax error
<?php } ?> 
}
function blockPage() {
  oprcShowProcessingOverlay();
}

function unblockPage() {
  oprcHideProcessingOverlay();
}
--></script>
