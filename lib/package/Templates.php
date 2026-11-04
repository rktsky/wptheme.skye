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
	 * Archive term/user identities must never be interpreted as post identities.
	 *
	 * @since 2021-02-28
	 * @return void
	 */
	public function __construct() {
		$this->ID = is_singular() ? get_queried_object_id() : 0;
		$this->type = $this->ID ? get_post_type( $this->ID ) : false;
		if ( is_post_type_archive() ) {
			$type = get_query_var( 'post_type' );
			$this->type = is_string( $type ) ? $type : false;
		}
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
			$assigned = $this->assigned_template();
			return $this->find( $assigned ?: 'page', 'page' );
		}
		if ( is_singular() ) {
			$assigned = $this->assigned_template();
			if ( $assigned ) {
				return $this->find( $assigned, 'single' );
			}
			return $this->find( $this->type && ! in_array( $this->type, array( 'post', 'page' ), true ) ? $this->type . '/single' : 'single', 'single' );
		}
		return $this->find( 'default' );
	}

	/**
	 * Accept only templates that WordPress exposes for the actual singular record.
	 *
	 * A corrupted stored slug must not include bootstrap/library entrypoints or
	 * recursively reload base.php. The native theme API also preserves supported
	 * custom-post templates and plugin-filtered template eligibility.
	 *
	 * @since 3.0.0
	 * @return string|false Registered selectable template slug, or false.
	 */
	private function assigned_template() {
		$assigned = get_page_template_slug( $this->ID );
		if ( ! is_string( $assigned ) || '' === $assigned ) {
			return false;
		}
		$templates = wp_get_theme()->get_page_templates( get_post( $this->ID ), $this->type ?: 'page' );
		return isset( $templates[$assigned] ) ? $assigned : false;
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
