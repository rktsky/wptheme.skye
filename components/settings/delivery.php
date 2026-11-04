<?php
/** Present optional native CDN settings and the observed Core bundled-content policy. */
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Skye Auslieferung', 'skye' ); ?></h1>
	<p><?php esc_html_e( 'CDN-Auslieferung ist standardmässig deaktiviert. Aktiviere sie erst, wenn dein CDN die erlaubten statischen Pfade mit HTTPS und passenden CORS-Headern bereitstellt.', 'skye' ); ?></p>
	<form action="<?php echo esc_url( $action ); ?>" method="post">
		<?php echo $hidden_fields; // Core serializes its native options nonce and action fields.?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'CDN', 'skye' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="skye_delivery[cdn_enabled]" value="1" <?php checked( $settings['cdn_enabled'] ); ?>>
						<?php esc_html_e( 'Öffentliche statische Dateien über das konfigurierte CDN ausliefern', 'skye' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="skye-cdn-url"><?php esc_html_e( 'CDN-Basisadresse', 'skye' ); ?></label></th>
				<td>
					<input id="skye-cdn-url" class="regular-text" type="url" name="skye_delivery[cdn_url]" value="<?php echo esc_attr( $settings['cdn_url'] ); ?>" placeholder="https://static.example.com">
					<p class="description"><?php esc_html_e( 'HTTPS ohne Zugangsdaten, Query oder Fragment. Gespeicherte Inhalte und Originaldateien bleiben an ihrer bestehenden Adresse.', 'skye' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="skye-cdn-prefixes"><?php esc_html_e( 'Erlaubte statische Pfade', 'skye' ); ?></label></th>
				<td>
					<textarea id="skye-cdn-prefixes" class="large-text code" rows="7" name="skye_delivery[prefixes]"><?php echo esc_textarea( implode( "\n", $settings['prefixes'] ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Ein Pfad pro Zeile. Nur native Upload-, Theme-, Plugin- und Includes-Verzeichnisse sowie deren Unterverzeichnisse sind zulässig.', 'skye' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
	<h2><?php esc_html_e( 'Neue WordPress-Standardthemes bei Updates', 'skye' ); ?></h2>
	<p><?php echo $skip_bundled ? esc_html__( 'Die Installation neuer gebündelter Inhalte ist in wp-config.php deaktiviert.', 'skye' ) : esc_html__( 'Die Setup-Konstante ist noch nicht aktiv. Definiere in wp-config.php vor dem Laden von WordPress: CORE_UPGRADE_SKIP_NEW_BUNDLED = true.', 'skye' ); ?></p>
</div>
