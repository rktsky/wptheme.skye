<?php
/** Deliver explicitly reviewed static assets through native WordPress registrations and filters. */

namespace Rocketsky\Skye\Packages;

use Rocketsky\Skye\Packages\Assets\LocalAssets;

require_once __DIR__ . '/assets/LocalAssets.php';
require_once __DIR__ . '/assets/FullCss.php';

/** Provide an opt-in static boundary without parsing or caching public HTML. */
final class StaticDelivery {
	/** @var bool One resolved footer queue may install at most one reviewed bundle. */
	private $prepared = false;

	/**
	 * Observe the final native footer order and individual native stylesheet links.
	 *
	 * No optimization is active without a project-supplied reviewed plan. Native
	 * inline scripts, translations, nonce data and form submissions remain uncached.
	 *
	 * @since 3.1.0
	 * @return void
	 */
	public function __construct() {
		add_filter( 'print_scripts_array', array( $this, 'prepare_scripts' ), PHP_INT_MAX );
		add_filter( 'style_loader_tag', array( $this, 'inline_style' ), 120, 4 );
	}

	/**
	 * Replace reviewed sources only after Core resolves their actual footer order.
	 *
	 * Earlier member handles become native source-less aliases. The last original
	 * carrier receives the immutable complete bundle and keeps its native strategy,
	 * dependencies, identity and inline metadata. A project must explicitly verify
	 * that its native deferred initialization permits these static sources to share
	 * that carrier; arbitrary scripts are never inferred as safe to aggregate.
	 *
	 * @since 3.1.0
	 * @param array $to_do Native resolved handles, returned without reordering.
	 * @return array Unchanged native queue; unsupported plans retain all sources.
	 */
	public function prepare_scripts( $to_do ) {
		if ( $this->prepared || ! is_array( $to_do ) || ! doing_action( 'wp_print_footer_scripts' ) ||
			! has_filter( 'skye_static_script_bundle' ) || ! self::public_request() ) {
			return $to_do;
		}
		$plan = apply_filters( 'skye_static_script_bundle', false );
		if ( ! is_array( $plan ) || ( $plan['native_deferred_initialization'] ?? false ) !== true ||
			! is_array( $plan['sources'] ?? null ) || count( $plan['sources'] ) < 2 || count( $plan['sources'] ) > 16 ) {
			return $to_do;
		}
		$scripts = wp_scripts();
		if ( $scripts->do_concat ) {
			return $to_do;
		}
		$ordered = array_values( array_intersect( $to_do, array_keys( $plan['sources'] ) ) );
		if ( count( $ordered ) !== count( $plan['sources'] ) || end( $ordered ) !== ( $plan['carrier'] ?? '' ) ) {
			return $to_do;
		}
		$registrations = array();
		$source_hashes = array();
		$bytes = '';
		foreach ( $ordered as $handle ) {
			$descriptor = $plan['sources'][$handle];
			$item = $scripts->registered[$handle] ?? null;
			if ( ! self::matches( $item, $descriptor ) || in_array( $handle, $scripts->done, true ) ||
				(int) $scripts->get_data( $handle, 'group' ) !== 1 || ! empty( $item->textdomain ) ||
				isset( $scripts->args[$handle] ) || array_diff( array_keys( $item->extra ), array( 'group', 'strategy', 'data', 'before', 'after' ) ) ) {
				return $to_do;
			}
			$source = LocalAssets::read( $descriptor['url'], 'js' );
			if ( ! $source ) {
				return $to_do;
			}
			$registrations[$handle] = clone $item;
			$source_hashes[$handle] = $source['sha256'];
			// Keep original public source/license bytes; delimiters only protect ASI.
			$bytes .= ";\n" . $source['bytes'] . "\n";
		}
		$carrier = $plan['carrier'];
		if ( $scripts->get_data( $carrier, 'strategy' ) !== 'defer' ) {
			return $to_do;
		}
		$bundle = LocalAssets::publish_script( $bytes, $source_hashes );
		if ( ! $bundle ) {
			return $to_do;
		}
		foreach ( $registrations as $handle => $item ) {
			wp_deregister_script( $handle );
			wp_register_script( $handle, $handle === $carrier ? $bundle['url'] : false, $item->deps, $item->ver, array( 'in_footer' => true ) );
			foreach ( $item->extra as $key => $value ) {
				if ( $key === 'strategy' && $handle !== $carrier ) {
					continue; // Core forbids a loading strategy on a source-less alias.
				}
				wp_script_add_data( $handle, $key, $value );
			}
		}
		$this->prepared = true;
		return $to_do;
	}

	/**
	 * Inline one complete verified stylesheet at its native cascade position.
	 *
	 * Existing native inline rules remain Core-owned and separate. Unknown link
	 * attributes, RTL/conditional variants, imports or unsupported URLs retain the
	 * original link; no selector subset or critical viewport is guessed here.
	 *
	 * @since 3.1.0
	 * @param string $tag Native link serialization after earlier filters.
	 * @param string $handle Native stylesheet handle.
	 * @param string $href Final native delivery URL.
	 * @param string $media Native media condition, retained on the style element.
	 * @return string Complete style serialization or the untouched native link.
	 */
	public function inline_style( $tag, $handle, $href, $media ) {
		if ( ! has_filter( 'skye_inline_stylesheets' ) || ! self::public_request() || ! class_exists( '\WP_HTML_Tag_Processor' ) ) {
			return $tag;
		}
		$plan = apply_filters( 'skye_inline_stylesheets', array() );
		$item = wp_styles()->registered[$handle] ?? null;
		if ( ! is_array( $plan ) || ! isset( $plan[$handle] ) || ! self::matches( $item, $plan[$handle] ) ||
			array_intersect( array_keys( $item->extra ), array( 'rtl', 'conditional', 'alt', 'title', 'path', 'inlined_src' ) ) ) {
			return $tag;
		}
		$processor = new \WP_HTML_Tag_Processor( $tag );
		if ( ! $processor->next_tag( 'LINK' ) || $processor->get_attribute( 'rel' ) !== 'stylesheet' ||
			$processor->get_attribute( 'href' ) !== $href || $processor->get_attribute( 'id' ) !== $handle . '-css' ||
			array_diff( $processor->get_attribute_names_with_prefix( '' ), array( 'id', 'rel', 'href', 'media', 'type' ) ) ) {
			return $tag;
		}
		if ( $processor->next_tag() ) {
			return $tag;
		}
		$source = LocalAssets::read( $plan[$handle]['url'], 'css' );
		$css = $source ? LocalAssets::inline_css( $source ) : false;
		if ( $css === false ) {
			return $tag;
		}
		$style = new \WP_HTML_Tag_Processor( '<style></style>' );
		$style->next_tag();
		$style->set_attribute( 'id', $handle . '-css' );
		$style->set_attribute( 'media', $media );
		$style->set_modifiable_text( "\n" . $css . "\n" );
		return $style->get_updated_html() . "\n";
	}

	/**
	 * Check explicit version/source/dependency contracts before replacing delivery.
	 *
	 * Relative native source paths are accepted only when their exact URL path
	 * agrees with the caller's canonical source. No prefix matching is used.
	 *
	 * @since 3.1.0
	 * @param object|null $item Actual registered native dependency.
	 * @param mixed $descriptor Explicit url/version/dependencies descriptor.
	 * @return bool Whether the complete documented registration matches.
	 */
	private static function matches( $item, $descriptor ) {
		if ( ! is_object( $item ) || ! is_array( $descriptor ) || ! is_string( $descriptor['url'] ?? null ) ||
			! array_key_exists( 'version', $descriptor ) || ! is_array( $descriptor['dependencies'] ?? null ) ) {
			return false;
		}
		return in_array( apply_filters( 'skye_canonical_asset_url', $item->src ), array( $descriptor['url'], wp_parse_url( $descriptor['url'], PHP_URL_PATH ) ), true ) &&
			$item->ver === $descriptor['version'] && $item->deps === $descriptor['dependencies'];
	}

	/**
	 * Exclude authenticated, writing, API, protected and special frontend contexts.
	 *
	 * @since 3.1.0
	 * @return bool Whether this is an ordinary anonymous public GET request.
	 */
	public static function public_request() {
		return did_action( 'wp' ) && ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'GET' && empty( $_POST ) &&
			Performance::public_form_asset_request() && ( ! function_exists( 'skye_content_access_state' ) || skye_content_access_state() === 'allowed' );
	}
}
