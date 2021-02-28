<?php

	global $data, $post;

	if( empty( $data ) )
		$data = (object) [];

	$data->image = get_post_thumbnail_id( $post );
	$data->type = 'image';
	if( empty( $data->image ) )
		$data->type = 'simple';

	if( empty( $data->url ) )
		$data->url = get_permalink( get_option( 'page_for_posts' ) );

	if( empty( $data->urltitle ) )
		$data->urltitle = __( 'Alle News', 'sage' );

	$data->categories = wp_get_post_categories( $post->ID, [ 'fields' => 'ids' ] );
	$data->tags = get_the_tags( $post->ID );
	$data->breadcrumbs = 'categories';
	$data->newslead = get( 'lead' );
	$data->noblockstitle = true;

	// Migration
	$data->custom = get( 'custom_teasertext' );
	if( !empty( $data->custom ) ) {
		update_field( 'lead', $data->custom, $post->ID );
		delete_post_meta( $post->ID, 'custom_teasertext' );
	}

	$data->newslist = get_posts( [
		'post_type' => 'post',
		'numberposts' => 4,
		'orderby' => 'rand',
	    'date_query' => array(
	        'after' => date('Y-m-d', strtotime('-2 years')) 
	    ),
	]);

?>

<?php get_component( 'page', 'header' ); ?>

<div class="container mt-5">

	<?php if( !empty( $data->tags ) ): ?>
		<div class="row pt-5 pb-3">
			<div class="col-lg-10 col-12 offset-lg-1 meta">
				<?php get_component( 'news', 'tags' ); ?>
			</div>
		</div>
	<?php endif; ?>

	<div class="row pt-5 pb-6">
		<div class="col-lg-10 col-12 offset-lg-1 meta">
			<?php get_component( 'news', 'meta' ); ?>
		</div>
	</div>

	<?php if( !empty( $data->newslead ) ): ?>
		<div class="row">
			<div class="col-lg-8 col-sm-10 col-12 offset-lg-1">
				<div class="ct-lead"><?php echo $data->newslead; ?></div>
			</div>
		</div>
	<?php endif; ?>

	<div class="row">
		<div class="col-lg-7 col-sm-9 col-12 offset-lg-2 offset-sm-1 mt-6">

			<?php while (have_posts()) : the_post(); ?>
				<article <?php post_class(); ?>>
					<header>
						<?php get_template_part('templates/entry-meta'); ?>
					</header>
					<div class="entry-content">
						<?php the_content(); ?>
					</div>
					<footer>
						<?php wp_link_pages(['before' => '<nav class="page-nav"><p>' . __('Pages:', 'sage'), 'after' => '</p></nav>']); ?>
					</footer>
					<?php comments_template('/components/comments.php'); ?>
				</article>
			<?php endwhile; ?>
	
		</div>
	</div>

	<div class="row mb-2">
		<div class="col-12">
			<h3>Lust auf mehr?</h3>
		</div>
	</div>

</div>

<?php get_component( 'news', 'news-blocks' ); ?>