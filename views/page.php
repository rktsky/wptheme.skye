<?php
/** Compose ordinary page content using the native loop and protected presentation boundary. */
get_component( 'page', 'header', array( 'header' => skye_page_header_model() ) );
?>
<div class="container py-4">
	<?php while ( have_posts() ) : the_post(); ?>
		<article <?php post_class(); ?>><?php get_component( 'page', 'content' ); ?></article>
		<?php skye_render_comments(); ?>
	<?php endwhile; ?>
</div>
