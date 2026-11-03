<?php
/** Resolve classic-theme headings through public WordPress title APIs. */
namespace Rocketsky\Skye\Titles;

/**
 * Return the current query heading without assuming a configured posts page.
 *
 * Native title filters remain responsible for plugin-provided naming.
 *
 * @since 2015-11-11
 * @return string Query heading, escaped by its presentation template.
 */
function title() {
	if ( is_home() ) {
		$posts_page = (int) get_option( 'page_for_posts' );
		return $posts_page ? get_the_title( $posts_page ) : __( 'Latest Posts', 'skye' );
	}
	if ( is_archive() ) {
		return wp_strip_all_tags( get_the_archive_title() );
	}
	if ( is_search() ) {
		return sprintf( __( 'Search Results for %s', 'skye' ), get_search_query( false ) );
	}
	if ( is_404() ) {
		return __( 'Not Found', 'skye' );
	}
	return get_the_title();
}
