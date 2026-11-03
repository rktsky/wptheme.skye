<?php
/** Provide an explicitly requested bounded not-found suggestion query; default 404 stays lightweight. */
namespace Rocketsky\Skye\Packages;

/** Keep the legacy suggestion API without one database query per URI fragment. */
class NotFound {
	/**
	 * Fetch a small public suggestion set only when an explicit caller requests it.
	 *
	 * Query text is limited and stripped; published native posts/pages are the only
	 * candidates. The original URI is never echoed into markup or persisted.
	 *
	 * @since 2021-02-28
	 * @param bool $with Whether to render suggestions instead of returning native posts.
	 * @return string|\WP_Post[]|false Rendered HTML, native posts, or false when empty.
	 */
	public function get_similar_posts( $with = true ) {
		$path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
		$search = is_string( $path ) ? sanitize_text_field( str_replace( array( '/', '-', '_' ), ' ', rawurldecode( $path ) ) ) : '';
		$search = trim( wp_html_excerpt( $search, 120, '' ) );
		if ( '' === $search ) {
			return false;
		}
		$posts = get_posts( array( 'post_type' => array( 'post', 'page' ), 'post_status' => 'publish', 'posts_per_page' => 5, 's' => $search, 'no_found_rows' => true, 'ignore_sticky_posts' => true ) );
		$items = array();
		foreach ( $posts as $post ) {
			if ( 'allowed' === \skye_content_access_state( $post->ID ) ) {
				$items[] = $post;
			}
		}
		if ( ! $items ) {
			return false;
		}
		if ( false === $with ) {
			return $items;
		}
		return \skye_component_html( 'posts/similar', false, array( 'items' => $items ) );
	}
}
