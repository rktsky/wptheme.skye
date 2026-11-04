<?php
/** Apply one external HTTP link policy through native content fragments and menu attributes. */

namespace Rocketsky\Skye\Packages;

defined( 'ABSPATH' ) || exit;

/** Share URL classification without rewriting saved content or complete page output. */
final class ExternalLinks {
	/**
	 * Observe completed editorial fragments and every native menu serialization.
	 *
	 * ACF formats WYSIWYG through its separate public content pipeline. Registering
	 * both hooks needs no ACF dependency; repeated processing remains idempotent.
	 *
	 * @since 3.2.2
	 * @return void Registers no HTTP request, browser script or persistence hook.
	 */
	public function __construct() {
		add_filter( 'the_content', array( $this, 'content' ), 90 );
		add_filter( 'acf_the_content', array( $this, 'content' ), 90 );
		add_filter( 'nav_menu_link_attributes', array( self::class, 'attributes' ), 90 );
	}

	/**
	 * Add the required browsing context and rel tokens only for a known external URL.
	 *
	 * Callers supply raw native attributes and retain responsibility for contextual
	 * escaping. Existing rel tokens, their spelling and all unrelated attributes
	 * survive. No URL is fetched or changed, and malformed destinations stay native.
	 *
	 * @since 3.2.2
	 * @param mixed $attributes Native anchor attributes, including an optional raw href.
	 * @return mixed The same attributes with external target/rel policy, or unchanged invalid input.
	 */
	public static function attributes( $attributes ) {
		if ( ! is_array( $attributes ) || ! self::external( $attributes['href'] ?? null ) ) {
			return $attributes;
		}
		$attributes['target'] = '_blank';
		$rel = is_string( $attributes['rel'] ?? null ) ? $attributes['rel'] : '';
		$tokens = preg_split( '/[\t\n\f\r ]+/', strtolower( $rel ), -1, PREG_SPLIT_NO_EMPTY );
		foreach ( array( 'noopener', 'noreferrer' ) as $required ) {
			if ( ! in_array( $required, $tokens, true ) ) {
				$rel .= ( $rel === '' || preg_match( '/[\t\n\f\r ]$/D', $rel ) ? '' : ' ' ) . $required;
			}
		}
		$attributes['rel'] = $rel;
		return $attributes;
	}

	/**
	 * Modify anchor attributes in an editorial fragment with WordPress's HTML parser.
	 *
	 * Scripts, comments, link text and unrelated attributes retain native parsing
	 * and serialization. This is a fragment filter, never a full-page output buffer.
	 *
	 * @see https://developer.wordpress.org/reference/classes/wp_html_tag_processor/
	 * @since 3.2.2
	 * @param mixed $html Native WordPress or ACF WYSIWYG content fragment.
	 * @return mixed Updated HTML or the unchanged nonstring/unsupported input.
	 */
	public function content( $html ) {
		if ( ! is_string( $html ) || $html === '' || ! class_exists( '\WP_HTML_Tag_Processor' ) ) {
			return $html;
		}
		$tags = new \WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag( 'A' ) ) {
			$before = array( 'href' => $tags->get_attribute( 'href' ), 'target' => $tags->get_attribute( 'target' ), 'rel' => $tags->get_attribute( 'rel' ) );
			$after = self::attributes( $before );
			foreach ( array( 'target', 'rel' ) as $name ) {
				if ( $after[$name] !== $before[$name] ) {
					$tags->set_attribute( $name, $after[$name] );
				}
			}
		}
		return $tags->get_updated_html();
	}

	/**
	 * Compare canonical HTTP origins instead of matching host prefixes or link text.
	 *
	 * Protocol-relative links inherit the native home URL scheme. Internal default
	 * ports normalize to their scheme; a different scheme, host or effective port
	 * is external. Relative, nonweb and browser-ambiguous URLs remain untouched.
	 *
	 * @see https://developer.wordpress.org/reference/functions/wp_parse_url/
	 * @since 3.2.2
	 * @param mixed $href Unescaped candidate destination.
	 * @return bool Whether both HTTP origins are valid and differ.
	 */
	private static function external( $href ) {
		if ( ! is_string( $href ) ) {
			return false;
		}
		$href = trim( $href );
		if ( $href === '' || preg_match( '/[\x00-\x20\x7f\\\\]/', $href ) ) {
			return false;
		}
		$home = wp_parse_url( home_url( '/' ) );
		$target = wp_parse_url( $href );
		if ( ! is_array( $home ) || ! is_array( $target ) || empty( $home['host'] ) || empty( $target['host'] ) ) {
			return false;
		}
		foreach ( array( $home['host'], $target['host'] ) as $host ) {
			if ( ( str_contains( $host, '[' ) || str_contains( $host, ']' ) ) &&
				( ! str_starts_with( $host, '[' ) || ! str_ends_with( $host, ']' ) ||
					! filter_var( substr( $host, 1, -1 ), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) ) {
				return false;
			}
		}
		$scheme = strtolower( $home['scheme'] ?? '' );
		$target_scheme = strtolower( $target['scheme'] ?? ( str_starts_with( $href, '//' ) ? $scheme : '' ) );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || ! in_array( $target_scheme, array( 'http', 'https' ), true ) ) {
			return false;
		}
		$home_port = $home['port'] ?? ( $scheme === 'https' ? 443 : 80 );
		$target_port = $target['port'] ?? ( $target_scheme === 'https' ? 443 : 80 );
		if ( $home_port < 1 || $home_port > 65535 || $target_port < 1 || $target_port > 65535 ) {
			return false;
		}
		return $scheme !== $target_scheme || strtolower( $home['host'] ) !== strtolower( $target['host'] ) || $home_port !== $target_port;
	}
}
