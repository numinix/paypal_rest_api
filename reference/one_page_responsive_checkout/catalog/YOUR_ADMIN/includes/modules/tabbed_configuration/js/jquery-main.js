/*
	jQuery Tabs
	About: Tabs that allow users seeing other contents on the same page.
*/ 

jQuery(function () {
	
	// hide all the content
	jQuery('.mod-tabs-content div').css('display', 'none');
	
	// build tabs 
	$tabs = '<ul class="mod-tabs resp-tabs-list">';
	jQuery('.resp-tabs-list li').each(function() {
		$tabs += '<li>' + jQuery(this).html() + '</li>';
	});
	$tabs += '</ul>';
	// replace first tab
	jQuery('.resp-tabs-list:first').replaceWith($tabs);
	// remove all the other tabs
	jQuery('.resp-tabs-list:not(:first)').remove();
	
	// build tabbed contents
	$tabsContent = '<div class="mod-tabs-content resp-tabs-container">';
	jQuery('.mod-tabs-content').each(function() {
		$tabsContent += jQuery(this).html();
	});
	jQuery('.mod-tabs-content:not(:first)').remove();
	$tabsContent += '</div>';
	jQuery('.mod-tabs-content:first').replaceWith($tabsContent);
	
  // activate easy responsive tabs	
  jQuery('#tabs').easyResponsiveTabs();
  
  jQuery('.resp-tab-content:not(:first)').css('display', 'none');
  	
});