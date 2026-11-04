<?php
/** Rebase complete known static CSS without removing selectors or guessing critical rules. */

namespace Rocketsky\Skye\Packages\Assets;

/** Keep unsupported CSS native rather than attempting a general-purpose optimizer. */
final class FullCss {
	/**
	 * Rebase ordinary static url() references while retaining every source rule.
	 *
	 * Imports, escaped functions/URLs, image-set(), malformed comments/strings and HTML-closing tokens
	 * refuse inlining. They keep their complete original native stylesheet link.
	 *
	 * @since 3.1.0
	 * @param string $css Full source bytes from a verified local stylesheet.
	 * @param string $source Canonical source URL used for relative asset resolution.
	 * @return string|false Complete rebased CSS, or false for unsupported syntax.
	 */
	public static function rebase( $css, $source ) {
		if ( ! is_string( $css ) || strlen( $css ) > 524288 || stripos( $css, '</style' ) !== false ) {
			return false;
		}
		$output = '';
		$length = strlen( $css );
		for ( $index = 0; $index < $length; ) {
			if ( substr( $css, $index, 2 ) === '/*' ) {
				$end = strpos( $css, '*/', $index + 2 );
				if ( $end === false ) {
					return false;
				}
				$output .= substr( $css, $index, $end + 2 - $index );
				$index = $end + 2;
				continue;
			}
			if ( $css[$index] === '"' || $css[$index] === "'" ) {
				$end = self::string_end( $css, $index );
				if ( $end === false ) {
					return false;
				}
				$output .= substr( $css, $index, $end + 1 - $index );
				$index = $end + 1;
				continue;
			}
			if ( preg_match( '/\G@import\b/i', $css, $match, 0, $index ) ||
				preg_match( '/\G(?:-webkit-)?image(?:-set)?\s*\(/i', $css, $match, 0, $index ) ||
				preg_match( '/\G@[a-zA-Z0-9_-]*\\\\/', $css, $match, 0, $index ) ) {
				return false;
			}
			if ( $css[$index] === '\\' && preg_match( '/\G(?:[a-zA-Z0-9_-]|\\\\(?:[a-fA-F0-9]{1,6}\s?|[^\r\n]))+\s*\(/', $css, $match, 0, $index ) ) {
				return false; // Escaped function names cannot safely inherit a new base URL.
			}
			if ( ( $index === 0 || ! preg_match( '/[a-zA-Z0-9_-]/', $css[$index - 1] ) ) && preg_match( '/\Gurl\s*\(\s*/i', $css, $match, 0, $index ) ) {
				$start = $index + strlen( $match[0] );
				$quoted = isset( $css[$start] ) && in_array( $css[$start], array( '"', "'" ), true );
				$end = $quoted ? self::string_end( $css, $start ) : strpos( $css, ')', $start );
				if ( $end === false ) {
					return false;
				}
				$value = $quoted ? substr( $css, $start + 1, $end - $start - 1 ) : trim( substr( $css, $start, $end - $start ) );
				if ( ! $quoted && preg_match( '/[\x00-\x20\x7f\"\'()]/', $value ) ) {
					return false;
				}
				$close = $quoted ? $end + 1 : $end;
				while ( $close < $length && ctype_space( $css[$close] ) ) {
					$close++;
				}
				if ( ! isset( $css[$close] ) || $css[$close] !== ')' || str_contains( $value, '\\' ) || preg_match( '/[\r\n\x00]/', $value ) ) {
					return false;
				}
				$url = self::absolute_url( $value, $source );
				if ( $url === false ) {
					return false;
				}
				$output .= 'url("' . str_replace( '"', '%22', $url ) . '")';
				$index = $close + 1;
				continue;
			}
			$output .= $css[$index];
			$index++;
		}
		return $output;
	}

	/**
	 * Locate one CSS string end without confusing escaped quotes with delimiters.
	 *
	 * @since 3.1.0
	 * @param string $css Full bounded source.
	 * @param int $start Opening quote offset.
	 * @return int|false Closing offset, or false for an unterminated string.
	 */
	private static function string_end( $css, $start ) {
		$quote = $css[$start];
		for ( $index = $start + 1, $length = strlen( $css ); $index < $length; $index++ ) {
			if ( $css[$index] === '\\' ) {
				$index++;
			} elseif ( $css[$index] === $quote ) {
				return $index;
			}
		}
		return false;
	}

	/**
	 * Resolve static references conservatively and apply the optional delivery mapper.
	 *
	 * Data URLs and local fragment references stay untouched. No URL is fetched.
	 *
	 * @since 3.1.0
	 * @param string $value Parsed static CSS URL value.
	 * @param string $source Canonical stylesheet URL.
	 * @return string|false Absolute delivery URL, unchanged data/fragment, or false.
	 */
	private static function absolute_url( $value, $source ) {
		if ( $value === '' || str_starts_with( $value, '#' ) || stripos( $value, 'data:' ) === 0 ) {
			return $value;
		}
		$base = wp_parse_url( $source );
		if ( ! is_array( $base ) || empty( $base['scheme'] ) || empty( $base['host'] ) ) {
			return false;
		}
		if ( str_starts_with( $value, '//' ) ) {
			$value = $base['scheme'] . ':' . $value;
		}
		$parts = wp_parse_url( $value );
		if ( ! is_array( $parts ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || ( isset( $parts['scheme'] ) && ! in_array( $parts['scheme'], array( 'http', 'https' ), true ) ) ) {
			return false;
		}
		if ( ! empty( $parts['host'] ) ) {
			return apply_filters( 'skye_static_asset_url', $value );
		}
		$path = $parts['path'] ?? '';
		$path = str_starts_with( $path, '/' ) ? $path : dirname( $base['path'] ?? '/' ) . '/' . $path;
		$normalized = array();
		foreach ( explode( '/', $path ) as $part ) {
			if ( $part === '..' ) {
				array_pop( $normalized );
			} elseif ( $part !== '' && $part !== '.' ) {
				$normalized[] = $part;
			}
		}
		$url = $base['scheme'] . '://' . $base['host'] . ( isset( $base['port'] ) ? ':' . $base['port'] : '' ) . '/' . implode( '/', $normalized );
		$url .= isset( $parts['query'] ) ? '?' . $parts['query'] : '';
		$url .= isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '';
		return apply_filters( 'skye_static_asset_url', $url );
	}
}
