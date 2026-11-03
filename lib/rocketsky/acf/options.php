<?php
/** Register optional ACF theme settings and authorize their native save boundary. */

/**
 * Register the historical theme option pages with administrative capabilities.
 *
 * ACF PRO remains an independently updated plugin. Capability checks apply to
 * the complete shared theme_options record, including API configuration.
 *
 * @since 2016-11-21
 * @return void
 */
function register_options() {
	if ( ! function_exists( 'acf_add_options_page' ) ) {
		return;
	}
	$pages = array(
		'theme-options-parent' => __( 'Skye Einstellungen', 'skye' ),
		'theme-options' => __( 'Grundeinstellungen', 'skye' ),
		'theme-options-api' => __( 'APIs', 'skye' ),
	);
	foreach ( $pages as $slug => $title ) {
		acf_add_options_page( array(
			'page_title' => $title,
			'menu_title' => $title,
			'menu_slug' => $slug,
			'capability' => 'manage_options',
			'parent_slug' => 'theme-options-parent' === $slug ? '' : 'theme-options-parent',
			'post_id' => 'theme_options',
			'autoload' => false,
			'redirect' => 'theme-options-parent' === $slug,
		) );
	}
}
add_action( 'acf/init', 'register_options' );

/**
 * Validate the posted settings identity before ACF accepts the native form.
 *
 * ACF validates its own nonce; this additional check prevents a valid form
 * token from granting administrative settings rights to an ordinary editor.
 *
 * @since 3.0.0
 * @return void
 */
function skye_validate_theme_options() {
	foreach ( array( '_acf_post_id', 'post_id' ) as $key ) {
		if ( isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) && 'theme_options' === wp_unslash( $_POST[ $key ] ) && ! current_user_can( 'manage_options' ) ) {
			acf_add_validation_error( '', __( 'Keine Berechtigung zum Ändern der Theme-Einstellungen.', 'skye' ) );
			return;
		}
	}
}
add_action( 'acf/validate_save_post', 'skye_validate_theme_options' );

/**
 * Deny unauthorized option writes before ACF's priority-10 storage callback.
 *
 * This canonical save identity also covers callers that bypass form validation.
 * Ordinary posts and other option owners keep their own authorization rules.
 *
 * @since 3.0.0
 * @param int|string $post_id Canonical ACF storage owner.
 * @return void
 */
function skye_authorize_theme_options( $post_id ) {
	if ( 'theme_options' === $post_id && ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Keine Berechtigung zum Ändern der Theme-Einstellungen.', 'skye' ), '', array( 'response' => 403 ) );
	}
}
add_action( 'acf/save_post', 'skye_authorize_theme_options', 5 );

/**
 * Read known legacy settings through Core's option cache once per request and blog.
 *
 * Avoid persisting a duplicate unbounded transient of the same native options.
 * The historical global remains available to child-theme presentation code.
 *
 * @since 2021-02-03
 * @return array{theme_logo:mixed,favicon:mixed,gmakey:mixed} Raw stored values.
 */
function get_theme_options() {
	global $theme_options;
	static $loaded_blog = null;
	$blog_id = get_current_blog_id();
	if ( ! is_array( $theme_options ) || $loaded_blog !== $blog_id ) {
		$theme_options = array();
		$loaded_blog = $blog_id;
		foreach ( array( 'theme_logo', 'favicon', 'gmakey' ) as $key ) {
			$theme_options[ $key ] = get_option( 'theme_options_' . $key, false );
		}
	}
	return $theme_options;
}
add_action( 'init', 'get_theme_options' );
add_action( 'switch_blog', 'get_theme_options', 10, 0 );

/**
 * Invalidate only the request read cache after a native legacy option write.
 *
 * Core fires these hooks after its own storage/cache update succeeds. Keeping
 * this callback separate from ACF save handling avoids favicon synchronization
 * or authorization side effects for independent public Options API callers.
 *
 * @since 3.0.0
 * @param string $option Native option name after a successful add, update, or delete.
 * @return void
 */
function skye_invalidate_theme_options_cache( $option ) {
	if ( in_array( $option, array( 'theme_options_theme_logo', 'theme_options_favicon', 'theme_options_gmakey' ), true ) ) {
		$GLOBALS['theme_options'] = null;
	}
}
add_action( 'added_option', 'skye_invalidate_theme_options_cache', 10, 1 );
add_action( 'updated_option', 'skye_invalidate_theme_options_cache', 10, 1 );
add_action( 'deleted_option', 'skye_invalidate_theme_options_cache', 10, 1 );

/**
 * Refresh request settings and the legacy favicon after an authorized ACF save.
 *
 * The actual storage identity replaces the unreliable admin-screen heuristic,
 * so frontend saves and background calls follow the same bounded contract.
 *
 * @since 2020-12-10
 * @param int|string|false $post_id ACF owner; false preserves old no-argument calls.
 * @return void
 */
function unset_theme_options( $post_id = false ) {
	if ( 'theme_options' !== $post_id || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$GLOBALS['theme_options'] = null;
	delete_transient( 'rs_theme_options' );
	$missing = new stdClass();
	$value = get_option( 'theme_options_favicon', $missing );
	if ( $value === $missing ) {
		return;
	}
	$icon = absint( $value );
	if ( ! $icon || wp_attachment_is_image( $icon ) ) {
		update_option( 'site_icon', $icon );
	}
}
add_action( 'acf/save_post', 'unset_theme_options', 20 );
