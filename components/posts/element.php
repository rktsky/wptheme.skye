<?php

	global $news;

	if( empty( $news ) )
		$news = (object) [];

?>

	<article class="article clickme">
		<div class="image coverparent">
			<?php echo get_the_post_thumbnail( $news->ID, 'medium', [ 'class' => 'cover' ] ); ?>
		</div>
		<div class="content">
			<div class="content-inner">
				<?php if( !empty( $news->categories ) ): ?>
					<div class="categories">
						<?php
							$first = true;
							foreach( $news->categories as $c ) {

								if( $first ) {
									$first = false;
								} else {
									echo ' / ';
								} 

								echo '<a href="' . get_category_link( $c ) . '">' . $c->name . '</a>';

							}
						?>
					</div>
				<?php endif; ?>
				<h3 class="mt-3 mb-3"><?php echo $news->post_title; ?></h3>
				<div class="meta"><?php get_component( 'news', 'meta' ); ?></div>
				<div class="teaser mt-4 mb-4 col-12"><?php echo get_excerpt( $news->ID ); ?></div>
				<a class="ct-arrow alone" href="<?php echo get_permalink( $news->ID ); ?>"><?php echo _x( 'Mehr lesen', 'read more for post teaser ', 'skye' ); ?></a>
			</div>
		</div>
	</article>
