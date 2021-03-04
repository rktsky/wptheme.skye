<?php

	global $data, $news, $wp_query;

	if( empty( $data ) )
		$data = (object) [];

	if( is_home() )
		$data->lead = get( 'lead', get_option( 'page_for_posts' ) );

	$data->currentpage = 1;
	if( !empty( $wp_query->query_vars[ 'paged' ] ) && $wp_query->query_vars[ 'paged' ] > 1 )
		$data->currentpage = $wp_query->query_vars[ 'paged' ];

?>

<?php if ( have_posts() ): ?>

	<?php get_component( 'page', 'header' ); ?>

	<div class="container">
		<div class="row archive archive-container">
			<?php
				while ( have_posts() ) {
					the_post();
					$news = $post;
					?>
					<div class="col-md-6 col-12 archive element">
						<?php
							get_component( 'posts', 'element' );
						?>
					</div>
					<?php
				}
			?>
		</div>
	</div>

<?php endif; ?>

<div class="d-flex justify-content-center mb-8">
	<div class="site-navigation">
		<?php 
			echo get_previous_posts_link( '<i class="fas fa-chevron-left"></i>' );
			echo ' Seite ' . $data->currentpage . '/' . $wp_query->max_num_pages . ' ';
			echo get_next_posts_link( '<i class="fas fa-chevron-right"></i>', $wp_query->max_num_pages );
		?>
	</div>
</div>