jQuery(document).ready(function() {
  // LICENSE
  // hide documentation unless license terms are agreed
  //jQuery('#licenseAgreed').hide();
  // show the documentation if the page loads with agreement radio checked
  // if (jQuery('input[name="license"]:checked').val() == 1) {
  //   jQuery('#licenseAgreed').fadeIn();
  // }
  // bind click event to license agreement and show/hide documentation based on selection
  // jQuery('input[name="license"]').live('click', function() {
  //   if (jQuery('input[name="license"]:checked').val() == 1) {
  //     jQuery('#licenseAgreed').fadeIn(); 
  //   } else {
  //     jQuery('#licenseAgreed').fadeOut();
  //   }
  // });

// Menu Links
  $("#btnInstallation").click(function(){
    $(".bodyHeaderContainer").hide();
    $("#Installation").show();
  });
   $("#btnInstallationTips").click(function(){
    $(".bodyHeaderContainer").hide();
    $("#InstallationTips").show();
  });
    $("#btnUninstall").click(function(){
    $(".bodyHeaderContainer").hide();
    $("#Uninstall").show();
  });
    $("#btnAbout").click(function(){
    $(".bodyHeaderContainer").hide();
    $("#About").show();
  });
    $("#btnLicense").click(function(){
    $(".bodyHeaderContainer").hide();
    $("#License").show();
  });
    $("#btnDisclaimer").click(function(){
    $(".bodyHeaderContainer").hide();
    $("#Disclaimer").show();
  });
    $("#btnHelp").click(function(){
    $(".bodyHeaderContainer").hide();
    $("#Help").show();
  });


  
  // CODE BOXES
  // highlight
  sh_highlightDocument();
  
  jQuery('.select').live('click', function() {
    let textToCopy = jQuery(this).parent('div').next('pre').children('code')[0];
    let clickedSelect = jQuery(this);
    // select all
    const range = document.createRange();
    const selection = window.getSelection();
    range.selectNodeContents(textToCopy);
    selection.removeAllRanges();
    selection.addRange(range);
    
    // Copy selected text
    const selectedText = selection.toString();

    navigator.clipboard.writeText(selectedText)
      .then(() => {
        clickedSelect.addClass('copied');
      })
      .catch(err => {
        console.error("Copy failed: ", err);
      });
  });

  // Remove Copied text
  jQuery('.select').live('mouseout', function() {
    jQuery(this).removeClass('copied');
  });
});