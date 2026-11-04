<?php
/** Preserve the legacy menu class API while delegating visitor state to native WordPress. */
namespace Rocketsky\Skye\Packages;

/** Native request-scoped rendering replaces unsafe persisted finished menu HTML. */
class MenuCache {
	/** @var string|false Last native menu output, retained for legacy consumers. */
	public $data = false;

	/**
	 * Render a native menu when the historical constructor receives arguments.
	 *
	 * WordPress retains current-item, language and plugin permission state per
	 * request. The old anonymous ctmenu query parameter has no effect at all.
	 *
	 * @since 2021-02-03
	 * @param array|false $args Native wp_nav_menu() arguments, or false to skip output.
	 * @return void
	 */
	public function __construct( $args = array() ) {
		if ( false === $args ) {
			return;
		}
		$args = wp_parse_args( $args, array( 'fallback_cb' => false ) );
		unset( $args['active'] );
		$args['echo'] = false;
		$this->data = wp_nav_menu( $args );
		if ( is_string( $this->data ) ) {
			echo $this->data;
		}
	}

	/**
	 * Retain the historical menu-save callback without another presentation cache.
	 *
	 * Native menu storage invalidates its own object caches; no theme transient
	 * exists to purge, and visiting a URL cannot mutate cached site data.
	 *
	 * @since 2021-02-03
	 * @return void
	 */
	public static function purge() {
	}
}
