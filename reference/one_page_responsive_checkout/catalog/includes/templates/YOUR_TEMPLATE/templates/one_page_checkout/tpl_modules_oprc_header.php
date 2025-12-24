<?php
	if (in_array($current_page_base,explode(',','one_page_checkout')) && OPRC_SIMPLIFIED_HEADER_ENABLED == 'true') {
		$flag_disable_header = true;
?>
	
	<?php
		if ($_SESSION['cart']->count_contents() > 1) {
			$items = TEXT_ITEMS;
		} else {
			$items = TEXT_ITEM;
		}
	?>

	<div class="nmx-checkout-header">
		<!--bof-branding display-->
		<div class="nmx-wrapper">
			<?php echo '<a class="nmx-checkout-logo" href="' . HTTP_SERVER . DIR_WS_CATALOG . '">' . zen_image($template->get_template_dir(HEADER_LOGO_IMAGE, DIR_WS_TEMPLATE, $current_page_base,'images'). '/' . HEADER_LOGO_IMAGE, HEADER_ALT_TEXT) . '</a>'; ?>
			<ul class="nmx-checkout-nav nmx-nav--inline">
				<li><a class="nmx-cart-link" href="<?php echo zen_href_link(FILENAME_SHOPPING_CART, '', 'SSL'); ?>"><span class="nxm-i-cart"></span> <span id="cart-count"><?php echo $_SESSION['cart']->count_contents();?> <?php echo $items; ?>, <?php echo $currencies->format($_SESSION['cart']->show_total()); ?></span></a></li>
			</ul>
		</div>
		<!--eof-branding display-->
	</div>
<?php }
