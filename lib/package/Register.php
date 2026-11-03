<?php
/** Register explicit project post-type and taxonomy configuration through native APIs. */
namespace Rocketsky\Skye\Packages;

/** Keep project extension files child-overridable without coupling to ACF. */
class Register {
	/** @var string Local configuration path, retained as a legacy extension point. */
	public $post_types_url;
	/** @var string Local taxonomy configuration path. */
	public $taxonomies_url;

	/**
	 * Resolve configuration files and register the existing init callbacks.
	 *
	 * Definitions remain project-owned PHP data; no sample post types are created.
	 *
	 * @since 2018-09-05
	 * @return void
	 */
	public function __construct() {
		$this->post_types_url = locate_template( 'lib/config/post_types.php', false, false );
		$this->taxonomies_url = locate_template( 'lib/config/taxonomies.php', false, false );
		add_action( 'init', array( $this, 'post_types' ), 0 );
		add_action( 'init', array( $this, 'taxonomies' ) );
	}

	/**
	 * Register valid project post types while leaving an empty base configuration inert.
	 *
	 * include (not include_once) keeps local definitions available to a repeated
	 * callback or a separate instance without leaking variables into global scope.
	 *
	 * @since 2018-09-05
	 * @return void
	 */
	public function post_types() {
		$rs_post_types = array();
		if ( $this->post_types_url && is_file( $this->post_types_url ) ) {
			include $this->post_types_url;
		}
		foreach ( $rs_post_types as $key => $value ) {
			if ( is_string( $key ) && is_array( $value['args'] ?? null ) ) {
				register_post_type( $key, $value['args'] );
			}
		}
	}

	/**
	 * Register explicit taxonomy definitions after their project post types.
	 *
	 * Missing or incomplete definitions cannot create half-configured taxonomies.
	 *
	 * @since 2018-09-05
	 * @return void
	 */
	public function taxonomies() {
		$rs_taxonomies = array();
		if ( $this->taxonomies_url && is_file( $this->taxonomies_url ) ) {
			include $this->taxonomies_url;
		}
		foreach ( $rs_taxonomies as $key => $value ) {
			if ( is_string( $key ) && is_array( $value['args'] ?? null ) && isset( $value['post_types'] ) ) {
				register_taxonomy( $key, $value['post_types'], $value['args'] );
			}
		}
	}
}
