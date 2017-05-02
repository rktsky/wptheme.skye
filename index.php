<?php
	$post_type = get_post_type();	
?>

<?php get_template_part( 'templates/page', 'header' ); ?>

<?php if (!have_posts()) : ?>
	<div class="alert alert-warning">
		<?php _e( 'Leider wurden keine Ergebnisse gefunden.', THEME_TEXT_DOMAIN ) ; ?>
	</div>
	<?php get_search_form(); ?>
<?php endif; ?>

<?php while (have_posts()) : the_post(); ?>
	<?php get_template_part('templates/content', $post_type !== 'post' ? $post_type : get_post_format() ); ?>
<?php endwhile; ?>

<?php the_posts_navigation(); ?>
