<?php
/**
 * jscript_main
 *
 * @package OPRC Advanced
 * @copyright Copyright 2007 Numinix Technology http://www.numinix.com
 * @copyright Copyright 2003-2006 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: jscript_main.php 3 2012-07-08 21:11:34Z numinix $
 */
?>
<script type="text/javascript">
<!--
var selected;
var submitter = null;

function popupWindow(url) {
  window.open(url,'popupWindow','toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=yes,copyhistory=no,width=450,height=320,screenX=150,screenY=150,top=150,left=150')
}

function couponpopupWindow(url) {
  window.open(url,'couponpopupWindow','toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,resizable=yes,copyhistory=no,width=450,height=320,screenX=150,screenY=150,top=150,left=150')
}

function submitFunction(gv, total) {
  if (gv >= total) {
    submitter = 1;  
  }
}

function methodSelect(theMethod) {
  if (document.getElementById(theMethod)) {
    document.getElementById(theMethod).checked = 'checked';
  }
}

function expandToWindow(element) {
  var margin = 10; 

  if (element.style.height < window.innerHeight) { 
    element.style.height = window.innerHeight - (2 * margin) 
  }
}

// paulm fix to only submit the form once
function submitonce(form){
  if (document.form.btn_submit) {
    document.form.btn_submit.disabled = true;
    setTimeout('button_timeout()', 4000);
    document.form.submit();
  }
}
//-->
</script>