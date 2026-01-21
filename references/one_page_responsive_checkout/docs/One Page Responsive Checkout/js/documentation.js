jQuery(document).ready(function() {
  // LICENSE
  // hide documentation unless license terms are agreed
  //jQuery('#licenseAgreed').hide();
  // show the documentation if the page loads with agreement radio checked
  // if (jQuery('input[name="license"]:checked').val() == 1) {
  //   jQuery('#licenseAgreed').fadeIn();
  // }
  // bind click event to license agreement and show/hide documentation based on selection
  // jQuery(document).on('click', 'input[name="license"]', function() {
  //   if (jQuery('input[name="license"]:checked').val() == 1) {
  //     jQuery('#licenseAgreed').fadeIn(); 
  //   } else {
  //     jQuery('#licenseAgreed').fadeOut();
  //   }
  // });

  // Menu Links
  const sections = [
    { button: '#btnInstallation', target: '#Installation' },
    { button: '#btnUpgrade', target: '#Upgrade' },
    { button: '#btnConfiguration', target: '#Configuration' },
    { button: '#btnInstallationTips', target: '#InstallationTips' },
    { button: '#btnTroubleshooting', target: '#Troubleshooting' },
    { button: '#btnUninstall', target: '#Uninstall' },
    { button: '#btnAbout', target: '#About' },
    { button: '#btnLicense', target: '#License' },
    { button: '#btnDisclaimer', target: '#Disclaimer' },
    { button: '#btnHelp', target: '#Help' }
  ];

  sections.forEach(({ button, target }) => {
    jQuery(button).on('click', function() {
      jQuery('.bodyHeaderContainer').hide();
      jQuery(target).show();
    });
  });


  
  // CODE BOXES
  // highlight
  sh_highlightDocument();
  
  jQuery(document).on('click', '.select', function() {
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
  jQuery(document).on('mouseout', '.select', function() {
    jQuery(this).removeClass('copied');
  });
});
