<?php
/** Compose the classic document shell with native hooks and explicit component data. */
?>
<!doctype html>
<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<?php wp_head(); ?>
	</head>
	<body <?php body_class(); ?>>
		<?php wp_body_open(); ?>
		<a class="visually-hidden-focusable" href="#main-content"><?php esc_html_e( 'Skip to content', 'skye' ); ?></a>
		<?php do_action( 'get_header' ); ?>
		<?php get_component( 'header', false, array( 'brand' => skye_brand_model(), 'navigation' => skye_navigation_model() ) ); ?>
		<main id="main-content" class="main" tabindex="-1">
			<?php skye_render_main_view(); ?>
		</main>
		<?php do_action( 'get_footer' ); ?>
		<?php get_component( 'footer', false, array( 'brand' => skye_brand_model(), 'sidebar_html' => skye_footer_sidebar_html() ) ); ?>
		<?php wp_footer(); ?>
	</body>
</html>
