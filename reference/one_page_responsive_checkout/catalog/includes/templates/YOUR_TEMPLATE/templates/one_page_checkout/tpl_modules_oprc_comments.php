
<div id="orderComments" class="nmx-box">
  <?php if (OPRC_CHECKBOX == 'true' || OPRC_DROP_DOWN == 'true' || OPRC_GIFT_MESSAGE == 'true' || $gift_wrap_switch || OPRC_ORDER_TOTAL_POSITION != 'top') { ?>
  <?php } ?>
  <div class="boxContents">
    <?php echo zen_draw_textarea_field('comments', '45', '3'); ?>
    <?php if ($messageStack->size('comments') > 0) { echo '<span class="alert validation disablejAlert">'; echo $messageStack->output('comments'); echo '</span>'; } ?>
  </div>
</div>