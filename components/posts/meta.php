<?php

	global $news, $post;

	if( empty( $news ) )
		$news = $post;

	$author = get( 'author', $news->ID );
	if( empty( $author ) ) {
		$author = $news->post_author;
		$name = get_the_author_meta( 'display_name', $author );
		$aimg = get_avatar( $author );
	} else {
		$name = get( 'prename', $author ) . ' ' . get( 'name', $author );
		$aimg = wp_get_attachment_image( get( 'photo', $author ), 'thumbnail', false, [ 'class' => 'background' ] );
	}

?>

<div>
	<?php echo $aimg; ?>
</div>
<div>
	<span class="author"><?php echo $name; ?></span> • 
	<time class="updated" datetime="<?php echo get_post_time( 'c', true ); ?>"><?php echo get_the_date( 'd.m.Y', $news->ID ); ?></time>
</div>
