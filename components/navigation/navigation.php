<?php
/** Render the Bootstrap 6 Alpha navbar with native dialog navigation and a no-JS fallback. */
?>
<nav class="navbar md:navbar-expand" aria-label="<?php esc_attr_e( 'Primary navigation', 'skye' ); ?>">
	<div class="container">
		<?php get_component( 'header', 'brand', array( 'brand' => $brand ) ); ?>
		<?php if ( '' !== $navigation['html'] ) : ?>
			<button class="btn-icon navbar-toggler skye-js-navigation" type="button" data-bs-toggle="drawer" data-bs-target="#skye-navigation" aria-controls="skye-navigation" aria-expanded="false" aria-label="<?php esc_attr_e( 'Open navigation', 'skye' ); ?>">
				<span class="navbar-toggler-icon" aria-hidden="true"></span>
			</button>
			<dialog class="drawer drawer-end skye-js-navigation" tabindex="-1" id="skye-navigation" aria-labelledby="skye-navigation-title">
				<div class="drawer-header">
					<h2 class="drawer-title" id="skye-navigation-title"><?php esc_html_e( 'Navigation', 'skye' ); ?></h2>
					<button class="btn-close" type="button" data-bs-dismiss="drawer" aria-label="<?php esc_attr_e( 'Close navigation', 'skye' ); ?>"></button>
				</div>
				<div class="drawer-body"><?php echo $navigation['html']; ?></div>
			</dialog>
			<noscript><div class="skye-no-script-navigation"><?php echo $navigation['fallback']; ?></div></noscript>
		<?php endif; ?>
	</div>
</nav>
