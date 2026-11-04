<?php
/** Display a validated WebP alternative while retaining exact native WordPress IMG output. */
?>
<picture><source type="image/webp" srcset="<?php echo esc_attr( $webp_source['srcset'] ); ?>"<?php if ( ! empty( $sizes ) ) : ?> sizes="<?php echo esc_attr( $sizes ); ?>"<?php endif; ?>><?php echo $native_image; ?></picture>
