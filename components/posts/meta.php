<?php
/** Display already prepared author/date values only for accessible content. */
?>
<?php if ( $article['allowed'] && '' !== $article['date'] ) : ?>
	<p class="entry-meta">
		<time datetime="<?php echo esc_attr( $article['datetime'] ); ?>"><?php echo esc_html( $article['date'] ); ?></time>
		<?php if ( '' !== $article['author'] ) : ?><span class="author"><?php echo esc_html( $article['author'] ); ?></span><?php endif; ?>
	</p>
<?php endif; ?>
