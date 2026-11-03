<?php
/** Provide a complete final view fallback through existing page components. */
get_component( 'page', 'header', array( 'header' => skye_page_header_model() ) );
?>
<div class="container py-4">
	<?php while ( have_posts() ) : the_post(); ?>
		<article <?php post_class(); ?>><?php get_component( 'page', 'content' ); ?></article>
	<?php endwhile; ?>
	<?php the_posts_navigation(); ?>
</div>
