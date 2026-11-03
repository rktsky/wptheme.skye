<?php
/** Native presentation filters and the shared classic-theme view models. */
namespace Rocketsky\Skye\Extras;

require_once __DIR__ . '/presentation.php';

/**
 * Add a sanitized singular slug without altering native body classes.
 *
 * @since 2015-11-11
 * @param string[] $classes Native body classes.
 * @return string[] Native classes plus the singular post slug when appropriate.
 */
function body_class( $classes ) {
	if ( is_singular() && ! is_front_page() ) {
		$slug = sanitize_html_class( get_post_field( 'post_name', get_queried_object_id() ) );
		if ( '' !== $slug && ! in_array( $slug, $classes, true ) ) {
			$classes[] = $slug;
		}
	}
	return $classes;
}
add_filter( 'body_class', __NAMESPACE__ . '\\body_class' );

/**
 * Keep native excerpt trimming textual; cards provide their own accessible link.
 *
 * @since 2015-11-11
 * @return string Plain ellipsis without a second embedded read-more link.
 */
function excerpt_more() {
	return '…';
}
add_filter( 'excerpt_more', __NAMESPACE__ . '\\excerpt_more' );
