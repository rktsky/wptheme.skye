<?php
/** Display a prepared post card without reading custom fields or unrelated entities. */
?>
<article class="article mb-4">
	<?php if ( '' !== $article['image'] ) : ?>
		<div class="entry-image"><?php echo $article['image']; ?></div>
	<?php endif; ?>
	<h2 class="entry-title"><a href="<?php echo esc_url( $article['url'] ); ?>"><?php echo esc_html( $article['title'] ); ?></a></h2>
	<?php get_component( 'posts', 'meta', array( 'article' => $article ) ); ?>
	<?php if ( '' !== $article['excerpt'] ) : ?>
		<p class="entry-summary"><?php echo esc_html( $article['excerpt'] ); ?></p>
	<?php endif; ?>
</article>
