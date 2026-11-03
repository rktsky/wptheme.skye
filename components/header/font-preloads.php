<?php
/** Display prepared owned font hints while preserving their exact type and CORS contracts. */
?>
<?php foreach ( $fonts as $font ) : ?>
	<link rel="preload" href="<?php echo esc_url( $font['href'] ); ?>" as="font" type="<?php echo esc_attr( $font['type'] ); ?>"<?php if ( isset( $font['crossorigin'] ) ) : ?> crossorigin="<?php echo esc_attr( $font['crossorigin'] ); ?>"<?php endif; ?>>
<?php endforeach; ?>
