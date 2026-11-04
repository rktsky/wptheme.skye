<?php
/** Resolve the optional first-child redirect before page headers or content are emitted. */

/**
 * Find the first published direct child using one bounded native query.
 *
 * Stable menu_order/ID ordering avoids random destinations; private/draft pages,
 * password-protected children and optional Members-denied records are skipped.
 * The limit is deliberate so a malformed hierarchy cannot trigger unbounded work.
 *
 * @since 3.0.0
 * @param int $parent_id Native parent page identity.
 * @return string Safe candidate permalink, or an empty string when none is eligible.
 */
function skye_first_child_url( $parent_id ) {
	$children = get_posts( array(
		'post_type' => 'page', 'post_parent' => (int) $parent_id, 'post_status' => 'publish',
		'posts_per_page' => 20, 'orderby' => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
		'no_found_rows' => true, 'ignore_sticky_posts' => true,
	) );
	foreach ( $children as $child ) {
		if ( (int) $child->ID !== (int) $parent_id && 'allowed' === skye_content_access_state( $child->ID ) ) {
			$url = get_permalink( $child->ID );
			if ( is_string( $url ) && '' !== $url && $url !== get_permalink( $parent_id ) ) {
				return $url;
			}
		}
	}
	return '';
}

/**
 * Apply the selected redirect template only to ordinary public page requests.
 *
 * The actual selectable slug and a legacy slug are recognized. A temporary safe
 * redirect avoids permanently caching a destination that changes with page order
 * or permissions. No eligible child leaves the normal page visible.
 *
 * @since 3.0.0
 * @return void
 */
function skye_redirect_first_child() {
	if ( is_admin() || ! is_page() || is_preview() || is_feed() || ! in_array( get_page_template_slug( get_queried_object_id() ), array( 'templates/redirectchild.php', 'template-redirectchild.php' ), true ) ) {
		return;
	}
	if ( 'allowed' !== skye_content_access_state( get_queried_object_id() ) ) {
		return;
	}
	$url = skye_first_child_url( get_queried_object_id() );
	if ( '' !== $url && wp_safe_redirect( $url, 302, 'Skye' ) ) {
		exit;
	}
}
add_action( 'template_redirect', 'skye_redirect_first_child', 10 );
