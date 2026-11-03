<?php
/** Display the unchanged native archive/posts-index loop with explicit reusable cards. */
get_component( 'page', 'header', array( 'header' => skye_page_header_model() ) );
?>
<div class="container py-4">
	<?php if ( ! have_posts() ) : ?>
		<p><?php esc_html_e( 'No posts were found.', 'skye' ); ?></p>
	<?php endif; ?>
	<?php while ( have_posts() ) : the_post(); ?>
		<?php get_component( 'posts', 'element', array( 'article' => skye_post_model( get_the_ID() ) ) ); ?>
	<?php endwhile; ?>
	<?php the_posts_navigation(); ?>
</div>
