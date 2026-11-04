<?php
/** Display the prepared site identity and optional native classic-widget markup. */
?>
<footer class="content-info py-4">
	<div class="container">
		<?php get_component( 'header', 'brand', array( 'brand' => $brand ) ); ?>
		<?php if ( '' !== $sidebar_html ) : ?>
			<div class="footer-widgets"><?php echo $sidebar_html; ?></div>
		<?php endif; ?>
	</div>
</footer>
