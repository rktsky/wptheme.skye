<?php
/** Prefer the platform emoji font without adding frontend detection requests. */

/**
 * Remove optional emoji transport only when the project retains this lightweight policy.
 *
 * Native Unicode text is unchanged, and administration keeps its original editor UI.
 *
 * @since 2025-06-16
 * @return void
 */
function disable_emojis() {
	if ( ! apply_filters( 'skye_disable_emoji_transport', true ) || is_admin() ) {
		return;
	}
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	add_filter( 'wp_resource_hints', 'disable_emojis_remove_dns_prefetch', 10, 2 );
}
add_action( 'init', 'disable_emojis' );

/**
 * Retain the legacy TinyMCE filter helper without changing the default admin editor.
 *
 * @since 2025-06-16
 * @param array $plugins Native TinyMCE plugin names.
 * @return array Existing names except the optional emoji transport plugin.
 */
function disable_emojis_tinymce( $plugins ) {
	return is_array( $plugins ) ? array_diff( $plugins, array( 'wpemoji' ) ) : array();
}

/**
 * Remove only the owned emoji origin hint while preserving other providers.
 *
 * @since 2025-06-16
 * @param array $urls Native resource hint URLs or descriptor arrays.
 * @param string $relation_type Native relation type.
 * @return array Original hints with the exact emoji origin omitted for DNS prefetch.
 */
function disable_emojis_remove_dns_prefetch( $urls, $relation_type ) {
	if ( $relation_type !== 'dns-prefetch' || ! is_array( $urls ) ) {
		return $urls;
	}
	$emoji = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/' );
	foreach ( $urls as $key => $url ) {
		if ( $url === $emoji ) {
			unset( $urls[$key] );
		}
	}
	return $urls;
}
