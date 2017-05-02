<?php

class scalable_vector_graphics {

	public function execute() {
		$this->_enable_svg_mime_type();
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'sanitize_svg' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'styles' ) );
	}

	// Here we use a whitelist library to attempt at sanitizing potential security threats.
	public function sanitize_svg( $file ) {
		if( $file[ 'type' ] == 'image/svg+xml' ) {
			require_once 'library/class.svg-sanitizer.php';

			$svg = new SvgSanitizer();
			// We read in the temporary file prior to WordPress moving it.
			$svg->load( $file[ 'tmp_name' ] );
			$svg->sanitize();
			$sanitized_svg = $svg->saveSVG();

			global $wp_filesystem;
			$credentials = request_filesystem_credentials(site_url() . '/wp-admin/', '', FALSE, FALSE, array());
			if ( ! WP_Filesystem( $credentials ) ) {
				request_filesystem_credentials( site_url() . '/wp-admin/', '', TRUE, FALSE, NULL );
			}

			// Using the filesystem API provided by WordPress, we replace the contents of the temporary file and then let the process continue as normal.
			$replace_uploaded_file = $wp_filesystem->put_contents($file['tmp_name'], $sanitized_svg, FS_CHMOD_FILE);
		}

		return $file;
	}

	private function _enable_svg_mime_type() {
		add_filter( 'upload_mimes', array( &$this, 'allow_svg_uploads' ) );
	}

	public function allow_svg_uploads( $existing_mime_types = array() ) {
		return $this->_add_mime_type( $existing_mime_types );
	}

	private function _add_mime_type( $mime_types ) {
		$mime_types[ 'svg' ] = 'image/svg+xml';

		return $mime_types;
	}

	public function styles() {
		wp_add_inline_style( 'wp-admin', "img.attachment-80x60[src$='.svg'] { width: 100%; height: auto; }" );
	}
}

if ( class_exists( 'scalable_vector_graphics' ) and ! isset( $scalable_vector_graphics ) ) {
	$scalable_vector_graphics = new scalable_vector_graphics();
	$scalable_vector_graphics->execute();
}

?>
