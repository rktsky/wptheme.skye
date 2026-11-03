<?php
/** Display explicitly requested public suggestions without additional field or metadata queries. */
?>
<ul class="similar">
	<?php foreach ( $items as $item ) : ?>
		<li><a href="<?php echo esc_url( get_permalink( $item->ID ) ); ?>"><?php echo esc_html( get_the_title( $item->ID ) ); ?></a></li>
	<?php endforeach; ?>
</ul>
