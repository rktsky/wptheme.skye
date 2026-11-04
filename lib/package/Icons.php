<?php
/** Render trusted build-owned glyphs with a complete native Font Awesome fallback. */

namespace Rocketsky\Skye\Packages;

use Rocketsky\Skye\Assets;

/** Keep subset publication separate from unknown dynamic icon requirements. */
class Icons {
	/** @var bool A requested unknown glyph revokes this request's subset-only policy. */
	private static $fallback = false;

	/**
	 * Require full provider CSS unless a project explicitly audits all icon sources.
	 *
	 * A source inventory cannot prove which stored or third-party classes may appear.
	 * The conservative default therefore retains the complete free provider path.
	 *
	 * @since 3.0.0
	 * @return bool Whether native Font Awesome styles must be enqueued.
	 */
	public static function needs_styles() {
		return self::$fallback || apply_filters( 'skye_icon_subset_complete', false ) !== true;
	}

	/**
	 * Render a provider icon through a documented component, never arbitrary SVG input.
	 *
	 * Missing subset entries use the complete provider's original font classes;
	 * decorative icons need adjacent accessible text supplied by their caller.
	 *
	 * @since 3.0.0
	 * @param string $name Free provider key in solid/name, regular/name or brands/name form.
	 * @param array $attributes Optional extra CSS class string under the class key.
	 * @return string Escaped component markup or an empty refusal for invalid names.
	 */
	public static function render( $name, $attributes = array() ) {
		if ( ! is_string( $name ) || ! preg_match( '~^(solid|regular|brands)/([a-z0-9]+(?:-[a-z0-9]+)*)$~D', $name, $parts ) ) {
			return '';
		}
		$record = Assets\performance_manifest()['icons'][$name] ?? null;
		$extra = is_array( $attributes ) && is_string( $attributes['class'] ?? null ) ? $attributes['class'] : '';
		$glyph = array( 'kind' => 'font', 'classes' => 'fa-' . $parts[1] . ' fa-' . $parts[2] . ' ' . $extra );
		if ( self::valid_record( $record ) ) {
			$glyph = array( 'kind' => 'svg', 'classes' => 'skye-icon ' . $extra, 'view_box' => $record['view_box'], 'paths' => $record['paths'] );
		} else {
			self::$fallback = true;
			wp_enqueue_style( 'skye/fontawesome' );
		}
		return skye_component_html( 'icons', 'glyph', array( 'glyph' => $glyph ) );
	}

	/**
	 * Validate the tiny geometry schema even though records are generated locally.
	 *
	 * This prevents malformed deployments from becoming active attributes or markup.
	 *
	 * @since 3.0.0
	 * @param mixed $record Generated view-box and path records.
	 * @return bool Whether every published record meets the serializer contract.
	 */
	private static function valid_record( $record ) {
		if ( ! is_array( $record ) || ! is_string( $record['view_box'] ?? null ) || ! preg_match( '/^0 0 [1-9][0-9]* [1-9][0-9]*$/D', $record['view_box'] ) ||
			! is_array( $record['paths'] ?? null ) || ! $record['paths'] || count( $record['paths'] ) > 8 ) {
			return false;
		}
		foreach ( $record['paths'] as $path ) {
			if ( ! is_array( $path ) || ! is_string( $path['d'] ?? null ) || ! preg_match( '/^[0-9a-zA-Z.,\s-]+$/D', $path['d'] ) ) {
				return false;
			}
		}
		return true;
	}
}
