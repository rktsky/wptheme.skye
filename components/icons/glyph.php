<?php
/** Display a trusted installed-provider glyph; paths are data, never user-supplied SVG markup. */
?>
<?php if ( 'svg' === $glyph['kind'] ) : ?>
	<svg class="<?php echo esc_attr( $glyph['classes'] ); ?>" viewBox="<?php echo esc_attr( $glyph['view_box'] ); ?>" aria-hidden="true" focusable="false"><?php foreach ( $glyph['paths'] as $path ) : ?><path d="<?php echo esc_attr( $path['d'] ); ?>"></path><?php endforeach; ?></svg>
<?php else : ?>
	<i class="<?php echo esc_attr( $glyph['classes'] ); ?>" aria-hidden="true"></i>
<?php endif; ?>
