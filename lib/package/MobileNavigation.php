<?php
/** Prepare progressive native navigation without changing foreign menus or child asset roots. */

namespace Rocketsky\Skye\Packages;

use Rocketsky\Skye\Assets;

defined( 'ABSPATH' ) || exit;

/** Own opt-in delivery and scoped menu identities while Core retains link serialization. */
final class MobileNavigation {
	/**
	 * Register native asset and menu hooks without querying or rendering menu content.
	 *
	 * @since 3.2.0
	 * @return void Existing child headers retain their previous behavior until opting in.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 105 );
		// Core may omit repeated item IDs; only marked serializations restore distinct IDs afterwards.
		add_filter( 'nav_menu_item_id', array( $this, 'item_id' ), PHP_INT_MAX, 4 );
	}

	/**
	 * Enable the parent standard while preserving custom child-theme integration boundaries.
	 *
	 * @since 3.2.0
	 * @return bool Public opt-in/out policy; this method never creates menu content.
	 */
	public static function enabled() {
		return (bool) apply_filters( 'skye_mobile_navigation_enabled', get_stylesheet_directory() === get_template_directory() );
	}

	/**
	 * Describe one navigation instance with bounded IDs, supported breakpoints and plain labels.
	 *
	 * Filters configure UI only. Native menu HTML and brand nodes remain explicit
	 * component inputs, never arbitrary markup embedded in these options.
	 *
	 * @since 3.2.0
	 * @param array $overrides Optional per-instance plain configuration; use a unique ID per instance.
	 * @return array Validated UI model with no URLs, secrets or queried menu records.
	 */
	public static function model( $overrides = array() ) {
		$defaults = array(
			'id' => 'skye-navigation', 'breakpoint' => 1200, 'location' => 'primary_navigation',
			'label' => __( 'Primary navigation', 'skye' ), 'title' => __( 'Navigation', 'skye' ),
			'open_text' => __( 'Menu', 'skye' ), 'close_text' => __( 'Close', 'skye' ),
			'open_label' => __( 'Open menu', 'skye' ), 'close_label' => __( 'Close navigation', 'skye' ),
		);
		$configured = apply_filters( 'skye_mobile_navigation_options', $defaults );
		$values = array_merge( $defaults, is_array( $configured ) ? $configured : array(), is_array( $overrides ) ? $overrides : array() );
		$result = $defaults;
		foreach ( array( 'id', 'location' ) as $key ) {
			$limit = $key === 'id' ? 47 : 63;
			if ( is_string( $values[$key] ) && preg_match( '/^[a-zA-Z][a-zA-Z0-9_-]{0,' . $limit . '}$/D', $values[$key] ) ) {
				$result[$key] = $values[$key];
			}
		}
		if ( in_array( $values['breakpoint'], array( 768, 992, 1200 ), true ) ) {
			$result['breakpoint'] = $values['breakpoint'];
		}
		foreach ( array( 'label', 'title', 'open_text', 'close_text', 'open_label', 'close_label' ) as $key ) {
			if ( is_string( $values[$key] ) && trim( $values[$key] ) !== '' && strlen( $values[$key] ) <= 240 ) {
				$result[$key] = wp_strip_all_tags( $values[$key] );
			}
		}
		return $result;
	}

	/**
	 * Give one native menu serialization a distinct list and item identity namespace.
	 *
	 * Use different prefixes for desktop, modal and native details fallback. Core
	 * and provider filters still own links, current state and item serialization.
	 *
	 * @since 3.2.0
	 * @param array $arguments Native wp_nav_menu arguments, unchanged when the prefix is invalid.
	 * @param string $prefix Unique bounded menu ID prefix, independent of saved menu identities.
	 * @return array Native arguments with an explicit scoped identity marker when valid.
	 */
	public static function menu_args( $arguments, $prefix ) {
		$arguments = is_array( $arguments ) ? $arguments : array();
		if ( is_string( $prefix ) && preg_match( '/^[a-zA-Z][a-zA-Z0-9_-]{0,63}$/D', $prefix ) ) {
			$arguments['menu_id'] = $prefix;
			$arguments['skye_navigation_id_prefix'] = $prefix;
		}
		return $arguments;
	}

	/**
	 * Prefix only marked menu-item IDs through Core's public serializer hook.
	 *
	 * @since 3.2.0
	 * @param string $id Earlier native/provider item ID.
	 * @param \WP_Post $item Actual native menu item.
	 * @param object $arguments Native menu arguments carrying the explicit owned marker.
	 * @param int $depth Native zero-based nesting depth; no depth is silently excluded.
	 * @return string Scoped item ID, or the untouched earlier value for unrelated menus.
	 */
	public function item_id( $id, $item, $arguments, $depth ) {
		$prefix = $arguments->skye_navigation_id_prefix ?? null;
		return is_string( $prefix ) && preg_match( '/^[a-zA-Z][a-zA-Z0-9_-]{0,63}$/D', $prefix ) && (int) $item->ID > 0
			? $prefix . '-item-' . (int) $item->ID : $id;
	}

	/**
	 * Resolve only this package's two immutable assets from the parent build itself.
	 *
	 * A child's complete manifest must not redirect parent package dependencies
	 * into nonexistent child files. Invalid or incomplete deployments keep the
	 * native details fallback and do not enqueue an unverified source URL.
	 *
	 * @since 3.2.0
	 * @param string $filename Exact public package entry: mobile-navigation.js or mobile-navigation.css.
	 * @return string Parent deployment URL, or an empty refusal.
	 */
	public static function asset_uri( $filename ) {
		if ( ! in_array( $filename, array( 'mobile-navigation.js', 'mobile-navigation.css' ), true ) ) {
			return '';
		}
		$root = get_template_directory();
		$manifest = new Assets\JsonManifest( $root . '/dist/assets.json' );
		$relative = $manifest->getPath( $filename, '' );
		$directory = str_ends_with( $filename, '.js' ) ? 'scripts' : 'styles';
		$extension = pathinfo( $filename, PATHINFO_EXTENSION );
		if ( ! is_string( $relative ) || ! preg_match( '~^' . $directory . '/mobile-navigation-[a-f0-9]{16}\.' . $extension . '$~D', $relative ) ) {
			return '';
		}
		$file = realpath( $root . '/dist/' . $relative );
		$dist = realpath( $root . '/dist' );
		return $file && $dist && str_starts_with( $file, $dist . DIRECTORY_SEPARATOR ) && is_file( $file ) && is_readable( $file )
			? get_template_directory_uri() . '/dist/' . $relative : '';
	}

	/**
	 * Load small independent navigation assets only for an opted-in assigned menu.
	 *
	 * @since 3.2.0
	 * @return void Script failure or absent dialog support retains the native details control.
	 */
	public function enqueue() {
		if ( ! self::enabled() || ! has_nav_menu( self::model()['location'] ) ) {
			return;
		}
		$script = self::asset_uri( 'mobile-navigation.js' );
		$style = self::asset_uri( 'mobile-navigation.css' );
		if ( $script === '' || $style === '' ) {
			return;
		}
		wp_enqueue_style( 'skye/mobile-navigation-style', $style, array(), null );
		wp_enqueue_script( 'skye/mobile-navigation', $script, array(), null, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}
}
