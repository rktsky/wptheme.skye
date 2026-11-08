<?php
/** Render one native source chooser; both native content stores remain inside the original post form. */
?>
<fieldset data-skye-content-sources>
	<legend class="screen-reader-text"><?php esc_html_e( 'Inhalt bearbeiten mit', 'skye' ); ?></legend>
	<p><label><input type="radio" name="skye_content_source" value="builder" <?php checked( $source, 'builder' ); ?>> <?php esc_html_e( 'Seitenbaukasten', 'skye' ); ?></label></p>
	<p><label><input type="radio" name="skye_content_source" value="editor" <?php checked( $source, 'editor' ); ?>> <?php esc_html_e( 'Standardeditor', 'skye' ); ?></label></p>
	<p class="description"><?php esc_html_e( 'Die Inhalte beider Varianten bleiben gespeichert. Auf der Website erscheint nur die gewählte Variante.', 'skye' ); ?></p>
	<?php if ( ! $supported ) : ?>
		<p class="description"><?php esc_html_e( 'Beide Eingaben bleiben sichtbar, bis die vorhandene ACF-Anbindung geprüft ist. Es gilt die vollständige Feldprüfung.', 'skye' ); ?></p>
	<?php endif; ?>
	<input type="hidden" name="skye_content_source_post" value="<?php echo esc_attr( $post_id ); ?>">
	<?php wp_nonce_field( 'skye-content-source-' . $post_id, 'skye_content_source_nonce', false ); ?>
</fieldset>
