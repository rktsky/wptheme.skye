<?php
/** Display the prepared native/custom-field site identity; all stored values are escaped. */
?>
<a class="navbar-brand" href="<?php echo esc_url( $brand['url'] ); ?>">
	<?php if ( '' !== $brand['image'] ) : ?>
		<?php echo $brand['image']; ?>
	<?php elseif ( '' !== $brand['logo'] ) : ?>
		<img src="<?php echo esc_url( $brand['logo'] ); ?>" alt="<?php echo esc_attr( $brand['name'] ); ?>" decoding="async">
	<?php else : ?>
		<?php echo esc_html( $brand['name'] ); ?>
	<?php endif; ?>
</a>
