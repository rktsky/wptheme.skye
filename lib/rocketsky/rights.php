<?php
/** Preserve the historical role gate; new authorization uses native object capabilities. */

/**
 * Check the legacy role/login contract before any protected output is produced.
 *
 * This adapter is not an object-authorization replacement. A denied caller may
 * request false without output, or a native 404 response with an existing local
 * template. It must be called before headers/output, not inside a partial view.
 *
 * @since 2020-12-17
 * @param string|string[]|bool $role Public/login sentinel or accepted legacy roles.
 * @param string|false $template Local template slug for denial, or false to return.
 * @return bool True when admitted; false on denial without a response template.
 */
function rights( $role = true, $template = '404' ) {
	$user = wp_get_current_user();
	$logged_in = $user->exists();
	$allowed = in_array( $role, array( 'public', 'all' ), true );
	if ( 'public_only' === $role ) {
		$allowed = ! $logged_in;
	} elseif ( true === $role || '' === $role || 'loggedin' === $role || array( '' ) === $role ) {
		$allowed = $logged_in;
	} elseif ( ! $allowed && ( is_string( $role ) || is_array( $role ) ) ) {
		$allowed = (bool) array_intersect( array_filter( (array) $role, 'is_string' ), (array) $user->roles );
	}
	if ( $allowed ) {
		return true;
	}
	if ( false === $template || '' === $template ) {
		return false;
	}
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	$candidates = array( 'views/404.php' );
	if ( is_string( $template ) && preg_match( '~^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$~D', $template ) ) {
		array_unshift( $candidates, $template . '.php', 'views/' . $template . '.php' );
	}
	$path = locate_template( $candidates, false, false );
	if ( $path ) {
		include $path;
	}
	exit;
}
