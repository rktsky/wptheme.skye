<?php
/**
 * Template Name: Redirect to First Child
 * Template Post Type: page
 *
 * The template_redirect callback resolves a published child before output.
 * Pages without an eligible child retain their ordinary native content.
 */
get_component( 'page', 'header', array( 'header' => skye_page_header_model() ) );
?>
<div class="container py-4">
	<?php while ( have_posts() ) : the_post(); ?>
		<?php get_component( 'page', 'content' ); ?>
	<?php endwhile; ?>
</div>
