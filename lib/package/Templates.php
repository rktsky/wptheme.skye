<?php
/** Resolve child-overridable classic theme views without querying legacy archive records. */
namespace Rocketsky\Skye\Packages;

/** Keep the existing views/components architecture behind native query routing. */
class Templates {
	/** @var int Current queried post identity; archives may have no post. */
	private $ID = 0;
	/** @var string|false Current queried post type. */
	private $type = false;

	/**
	 * Capture query identity after WordPress has resolved the main request.
	 *
	 * No secondary content query or global post replacement is needed to route it.
	 *
	 * @since 2021-02-28
	 * @return void
	 */
	public function __construct() {
		$this->ID = get_queried_object_id();
		$this->type = get_post_type( $this->ID );
	}

	/**
	 * Resolve the actual query with complete generic fallbacks.
	 *
	 * The posts index precedes front-page handling so a latest-posts homepage keeps
	 * its archive loop. Custom post views remain child-overridable by type.
	 *
	 * @since 2021-02-28
	 * @return string Absolute local template path, or an empty string if absent.
	 */
	public function template() {
		if ( is_404() ) {
			return $this->find( '404' );
		}
		if ( is_search() ) {
			return $this->find( 'search' );
		}
		if ( is_home() || is_archive() ) {
			return $this->find( $this->type && ! in_array( $this->type, array( 'post', 'page' ), true ) ? $this->type . '/archive' : 'archive', 'archive' );
		}
		if ( is_page() ) {
			$assigned = get_page_template_slug( $this->ID );
			return $this->find( $assigned ?: 'page', 'page' );
		}
		if ( is_singular() ) {
			return $this->find( $this->type && ! in_array( $this->type, array( 'post', 'page' ), true ) ? $this->type . '/single' : 'single', 'single' );
		}
		return $this->find( 'default' );
	}

	/**
	 * Preserve explicit archive-page integration without the old ct-archive query.
	 *
	 * Projects that need a content record can supply one through the documented
	 * filter; generic archive rendering never changes the main loop's global post.
	 *
	 * @since 2021-02-28
	 * @param string $posttype Native post type.
	 * @return \WP_Post|false Explicit published archive record, or false.
	 */
	public function get_archive_site( $posttype ) {
		$archive = apply_filters( 'skye_archive_page', false, $posttype );
		return $archive instanceof \WP_Post && 'publish' === $archive->post_status ? $archive : false;
	}

	/**
	 * Find a safe relative view or assigned template with an existing final fallback.
	 *
	 * Stored template slugs cannot traverse directories or include arbitrary files.
	 * locate_template() retains the native child-before-parent resolution order.
	 *
	 * @since 2021-02-28
	 * @param string $name Relative view or selectable template path.
	 * @param string|false $fallback Generic view used for an absent specialised one.
	 * @return string Absolute local template path, or an empty string.
	 */
	private function find( $name, $fallback = false ) {
		$name = preg_replace( '/\.php$/D', '', (string) $name );
		$candidates = array();
		if ( preg_match( '~^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$~D', $name ) ) {
			$candidates[] = 'views/' . $name . '.php';
			$candidates[] = $name . '.php';
		}
		if ( is_string( $fallback ) && preg_match( '/^[a-zA-Z0-9_-]+$/D', $fallback ) ) {
			$candidates[] = 'views/' . $fallback . '.php';
		}
		$candidates[] = 'views/default.php';
		return locate_template( array_unique( $candidates ), false, false );
	}
}
