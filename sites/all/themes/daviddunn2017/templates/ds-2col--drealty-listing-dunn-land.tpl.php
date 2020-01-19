<?php

/**
 * @file
 * Display Suite 2 column template.
 */
?>
<div class="row"> 
	<?php if (isset($title_suffix['contextual_links'])): ?>
	<?php print render($title_suffix['contextual_links']); ?>
	<?php endif; ?>
	<div class="col-sm-6">
		<?php print $left; ?>
	</div>
	<div class="col-sm-6">
	<?php print $right; ?>
	</div>
</div>
<?php if (!empty($drupal_render_children)): ?>
  <?php print $drupal_render_children ?>
<?php endif; ?>



