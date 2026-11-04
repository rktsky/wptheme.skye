<?php
/** Wrap native theme routes and enforce optional content rules before head output. */

/**
 * Select the Skye shell without wrapping a plugin's independent response template.
 *
 * Child-theme base overrides remain native locate_template decisions. Feeds,
 * embeds and trackbacks keep Core's purpose-specific response formats.
 *
 * @since 2025-06-16
 * @param string $template Already resolved native WordPress template path.
 * @return string Skye shell path or the original external response template.
 */
function rs_base( $template = '' ) {
	if ( is_feed() || is_embed() || is_trackback() ) {
		return $template;
	}
	$native = realpath( $template );
	if ( $native ) {
		$owned = false;
		foreach ( array( get_template_directory(), get_stylesheet_directory() ) as $directory ) {
			$directory = realpath( $directory );
			if ( $directory && str_starts_with( $native, $directory . DIRECTORY_SEPARATOR ) ) {
				$owned = true;
			}
		}
		if ( ! $owned ) {
			return $template;
		}
	}
	return locate_template( 'base.php', false, false ) ?: $template;
}
add_filter( 'template_include', 'rs_base', 109 );

/**
 * Enforce optional Members permissions before custom fields or the head can render.
 *
 * ACF presentation bypasses the_content filters. Password handling stays with
 * native content rendering; a Members denial becomes a non-cacheable404 route.
 *
 * @since 3.0.0
 * @return void
 */
function skye_enforce_content_permissions() {
	if ( ! is_singular() || 'members' !== skye_content_access_state( get_queried_object_id() ) ) {
		return;
	}
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}
add_action( 'template_redirect', 'skye_enforce_content_permissions', 9 );
