<?php
/** Render native search results and the empty state without missing legacy components. */
get_component( 'page', 'header', array( 'header' => skye_page_header_model() ) );
?>
<div class="container py-4">
	<?php if ( ! have_posts() ) : ?>
		<p><?php esc_html_e( 'No results were found.', 'skye' ); ?></p>
		<?php get_search_form(); ?>
	<?php endif; ?>
	<?php while ( have_posts() ) : the_post(); ?>
		<?php get_component( 'search', false, array( 'article' => skye_post_model( get_the_ID() ) ) ); ?>
	<?php endwhile; ?>
	<?php the_posts_navigation(); ?>
</div>
