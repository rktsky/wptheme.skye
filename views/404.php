<?php

	use Rocketsky\Skye\Packages\NotFound;
	global $data, $theme_options;

	$similar = new NotFound;

	$data = (object) [];
	$data->image = $theme_options[ '404_image' ];
	$data->title = $theme_options[ '404_title' ];
	$data->lead = $theme_options[ '404_lead' ];
	$data->alt = $theme_options[ '404_alt' ];
	$data->feedback = $theme_options[ '404_feedback' ];
	$data->feedback_lead = $theme_options[ '404_feedback_lead' ];
	$data->form = $theme_options[ '404_form' ];
	add_filter( 'gform_confirmation_anchor_' . $data->form, '__return_false' );

?>

<?php get_component( 'pageheader/image' ); ?>

<div class="component similar">
	<div class="container">
		<div class="row">
			<div class="col-12">
				<h3><?php echo $data->alt; ?></h3>
				<?php
					$posts = $similar->get_similar_posts( false );
					if( !empty( $posts ) && count( $posts ) > 0 ) {
						foreach( $posts as $p ) {
							echo '<div class="mt-5">';
							echo '<h4 class="mb-2">' . $p->post_title . '</h4>';
							echo '<div class="ct-lead-small mb-0">' . get_excerpt( $p->ID, 15 ) . '</div>';
							echo '<a class="ct-arrow alone" href="' . get_permalink( $p->ID ) . '">Mehr anzeigen</a>';
							echo '</div>';
						}
					}
				?>
			</div>
		</div>
	</div>
</div>
