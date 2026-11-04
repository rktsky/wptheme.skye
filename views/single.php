<?php
/** Render native single-post content without public-request metadata migrations or random queries. */
get_component( 'page', 'header', array( 'header' => skye_page_header_model() ) );
?>
<div class="container py-4">
	<?php while ( have_posts() ) : the_post(); ?>
		<article <?php post_class(); ?>>
			<?php get_component( 'posts', 'meta', array( 'article' => skye_post_meta_model( get_the_ID() ) ) ); ?>
			<?php get_component( 'page', 'content' ); ?>
		</article>
		<?php skye_render_comments(); ?>
	<?php endwhile; ?>
	<?php the_post_navigation(); ?>
</div>
