<?php
/** Display escaped headings and deliberately permitted WordPress rich text. */
?>
<header class="page-header container py-4">
	<h1><?php echo esc_html( $header['title'] ); ?></h1>
	<?php if ( '' !== $header['lead'] ) : ?>
		<div class="lead"><?php echo wp_kses_post( $header['lead'] ); ?></div>
	<?php endif; ?>
	<?php if ( $header['breadcrumbs'] ) : ?>
		<?php get_component( 'breadcrumbs', false, array( 'breadcrumb_html' => skye_breadcrumbs_html() ) ); ?>
	<?php endif; ?>
</header>
