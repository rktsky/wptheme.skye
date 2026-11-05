<?php
/** Apply existing website identity to native administration and login without changing saved settings. */

namespace Rocketsky\Skye\Packages;

use Rocketsky\Skye\Assets;
use Rocketsky\Skye\Packages\Assets\PathIdentity;

require_once __DIR__ . '/assets/PathIdentity.php';

/** Keep branding limited to native UI assets, validated tokens and public identity filters. */
final class Branding {
	/** @var bool A complete owned administrator stylesheet was enqueued for this response. */
	private $admin_ready = false;
	/** @var bool A complete owned login stylesheet was enqueued for this response. */
	private $login_ready = false;
	/** @var bool The current login presentation contains a validated public logo URL. */
	private $login_logo = false;
	/** @var bool The enqueued login response contains a complete validated palette. */
	private $login_palette = false;

	/**
	 * Attach only native administration and login presentation boundaries.
	 *
	 * No frontend enqueue hook, persistent user preference or authentication
	 * callback is registered. Existing form handling remains entirely native.
	 *
	 * @since 3.4.0
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ), 20, 0 );
		add_action( 'login_enqueue_scripts', array( $this, 'login_styles' ), 20, 0 );
		add_filter( 'admin_body_class', array( $this, 'admin_classes' ) );
		add_filter( 'login_body_class', array( $this, 'login_classes' ) );
		add_filter( 'login_headerurl', array( $this, 'login_url' ) );
		add_filter( 'login_headertext', array( $this, 'login_text' ) );
	}

	/**
	 * Read the current build palette and validate any explicit project adapter.
	 *
	 * The build extracts existing website tokens; PHP never parses frontend CSS.
	 * Unknown metadata or incomplete filtered palettes retain native UI colors.
	 * Filter results are evaluated per call, without caching project decisions.
	 *
	 * @since 3.4.0
	 * @return array{primary?:string,secondary?:string,background?:string,text?:string} Complete normalized colors or an empty fallback.
	 */
	public static function colors() {
		$record = Assets\build_root()['manifest']->getPath( '_branding', array() );
		$colors = is_array( $record ) && ( $record['version'] ?? null ) === 1 ? ( $record['colors'] ?? array() ) : array();
		$colors = apply_filters( 'skye_branding_colors', $colors );
		if ( ! is_array( $colors ) ) {
			return array();
		}
		$valid = array();
		foreach ( array( 'primary', 'secondary', 'background', 'text' ) as $name ) {
			if ( ! is_string( $colors[$name] ?? null ) || preg_match( '/^#[a-fA-F0-9]{6}$/D', $colors[$name] ) !== 1 ) {
				return array();
			}
			$valid[$name] = strtolower( $colors[$name] );
		}
		return $valid;
	}

	/**
	 * Resolve the public site logo without trusting arbitrary filtered CSS or protocols.
	 *
	 * The default shares the frontend identity source, including the historical
	 * ACF option. A project can adapt its own stored identity through one filter.
	 * No URL is fetched, and absent or malformed logos use the site name instead.
	 *
	 * @since 3.4.0
	 * @return string Valid absolute HTTP(S) URL without credentials, or an empty text fallback.
	 */
	public static function logo() {
		$brand = skye_brand_identity();
		$value = apply_filters( 'skye_branding_logo', $brand['logo'] ?? '' );
		if ( ! is_string( $value ) || strlen( $value ) > 4096 || preg_match( '//u', $value ) !== 1 || preg_match( '/[\p{Z}\p{C}<>"\'\\\\]/u', $value ) ) {
			return '';
		}
		$parts = wp_parse_url( $value );
		if ( ! is_array( $parts ) || ! in_array( strtolower( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true ) ||
			empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || ( isset( $parts['port'] ) && ( $parts['port'] < 1 || $parts['port'] > 65535 ) ) ) {
			return '';
		}
		return esc_url_raw( $value, array( 'http', 'https' ) );
	}

	/**
	 * Resolve the package stylesheet from the parent deployment, independently of child builds.
	 *
	 * A child manifest owns its palette but need not duplicate original package
	 * styles. The manifest has one request-local snapshot per parent path and URI;
	 * current file ownership is checked again before delivery. Only immutable
	 * package filenames may cross this boundary; incomplete deployments fail closed.
	 *
	 * @since 3.4.0
	 * @param string $filename Exact package entry, backend.css or login.css.
	 * @return string Verified parent-owned stylesheet URL, or an empty refusal.
	 */
	public static function asset_uri( $filename ) {
		static $manifests = array();
		if ( ! in_array( $filename, array( 'backend.css', 'login.css' ), true ) ) {
			return '';
		}
		$root = get_template_directory();
		$uri = get_template_directory_uri();
		$key = $root . "\0" . $uri;
		if ( ! isset( $manifests[$key] ) ) {
			$manifests[$key] = new Assets\JsonManifest( $root . '/dist/assets.json' );
		}
		$manifest = $manifests[$key];
		$relative = $manifest->getPath( 'styles/' . $filename, $manifest->getPath( $filename, '' ) );
		$stem = pathinfo( $filename, PATHINFO_FILENAME );
		if ( ! is_string( $relative ) || ! preg_match( '~^styles/' . $stem . '-[a-f0-9]{16}\.css$~D', $relative ) ) {
			return '';
		}
		$file = PathIdentity::canonical( $root . '/dist/' . $relative );
		$dist = PathIdentity::canonical( $root . '/dist' );
		return $file && $dist && str_starts_with( $file, $dist . DIRECTORY_SEPARATOR ) && is_file( $file ) && is_readable( $file )
			? $uri . '/dist/' . $relative : '';
	}

	/**
	 * Calculate relative sRGB luminance for automatic readable brand foregrounds.
	 *
	 * Inputs are internal normalized six-digit colors. The WCAG formula avoids
	 * choosing white button text solely because a brand color looks saturated.
	 *
	 * @since 3.4.0
	 * @param string $color Valid normalized hexadecimal color.
	 * @return float Relative luminance between zero and one.
	 */
	private static function luminance( $color ) {
		$channels = array();
		foreach ( array( 1, 3, 5 ) as $offset ) {
			$value = hexdec( substr( $color, $offset, 2 ) ) / 255;
			$channels[] = $value <= 0.04045 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}

	/**
	 * Compare two validated colors using native double precision.
	 *
	 * @since 3.4.0
	 * @param string $first Valid normalized hexadecimal color.
	 * @param string $second Valid normalized hexadecimal color.
	 * @return float WCAG contrast ratio, always at least one.
	 */
	private static function contrast( $first, $second ) {
		$a = self::luminance( $first );
		$b = self::luminance( $second );
		return ( max( $a, $b ) + 0.05 ) / ( min( $a, $b ) + 0.05 );
	}

	/**
	 * Choose the more readable neutral without changing the underlying brand color.
	 *
	 * @since 3.4.0
	 * @param string $background Valid normalized hexadecimal surface color.
	 * @return string Black or white, providing at least the normal-text contrast threshold.
	 */
	private static function foreground( $background ) {
		return self::contrast( '#000000', $background ) >= self::contrast( '#ffffff', $background ) ? '#000000' : '#ffffff';
	}

	/**
	 * Mix a finite number of sRGB channels for hover and accessible tone generation.
	 *
	 * @since 3.4.0
	 * @param string $color Valid normalized starting color.
	 * @param string $toward Valid normalized destination color.
	 * @param float $amount Fraction between zero and one, controlled internally.
	 * @return string Normalized six-digit mixed color.
	 */
	private static function mix( $color, $toward, $amount ) {
		$result = '#';
		foreach ( array( 1, 3, 5 ) as $offset ) {
			$value = (int) round( hexdec( substr( $color, $offset, 2 ) ) * ( 1 - $amount ) + hexdec( substr( $toward, $offset, 2 ) ) * $amount );
			$result .= str_pad( dechex( $value ), 2, '0', STR_PAD_LEFT );
		}
		return $result;
	}

	/**
	 * Preserve an existing color when readable, otherwise find its nearest bounded neutral tone.
	 *
	 * Only native administration/login text is adjusted. The website's original
	 * palette and frontend styles remain unchanged, and the loop has a fixed limit.
	 *
	 * @since 3.4.0
	 * @param string $color Valid normalized starting foreground.
	 * @param string $background Valid normalized background.
	 * @return string Same color or the first five-percent tone meeting contrast 4.5.
	 */
	private static function legible( $color, $background ) {
		$target = self::foreground( $background );
		for ( $step = 0; $step <= 20; $step++ ) {
			$candidate = self::mix( $color, $target, $step / 20 );
			if ( self::contrast( $candidate, $background ) >= 4.5 ) {
				return $candidate;
			}
		}
		return $target;
	}

	/**
	 * Serialize validated channels for native RGB custom-property consumers.
	 *
	 * Both original brand surfaces and readable native controls need the same
	 * channel order, without parsing or accepting arbitrary CSS values.
	 *
	 * @since 3.4.0
	 * @param string $color Internal normalized six-digit hexadecimal color.
	 * @return string Comma-separated red, green and blue decimal channels.
	 */
	private static function rgb( $color ) {
		$channels = array();
		foreach ( array( 1, 3, 5 ) as $offset ) {
			$channels[] = hexdec( substr( $color, $offset, 2 ) );
		}
		return implode( ',', $channels );
	}

	/**
	 * Serialize only validated color values and computed readable native UI tones.
	 *
	 * This emits custom properties only, never complete page layouts or arbitrary
	 * project CSS. Missing palettes deliberately emit no native-color overrides.
	 *
	 * @since 3.4.0
	 * @param array $colors Complete validated primary, secondary, background and text colors.
	 * @return string Scoped CSS declaration block, or an empty native fallback.
	 */
	private static function variables( $colors ) {
		if ( ! $colors ) {
			return '';
		}
		$colors['control-text'] = self::legible( $colors['text'], '#ffffff' );
		$colors['control-link'] = self::legible( $colors['primary'], '#ffffff' );
		$colors['text'] = self::legible( $colors['text'], $colors['background'] );
		$colors['on-primary'] = self::foreground( $colors['primary'] );
		$colors['on-secondary'] = self::foreground( $colors['secondary'] );
		$colors['link'] = self::legible( $colors['primary'], $colors['background'] );
		$colors['focus'] = $colors['link'];
		$colors['hover'] = self::mix( $colors['primary'], '#000000', 0.12 );
		$colors['on-hover'] = self::foreground( $colors['hover'] );
		$declarations = '--skye-brand-primary-rgb:' . self::rgb( $colors['primary'] ) . ';';
		$declarations .= '--skye-brand-control-rgb:' . self::rgb( $colors['control-link'] ) . ';';
		foreach ( $colors as $key => $value ) {
			$declarations .= '--skye-brand-' . $key . ':' . $value . ';';
		}
		return 'body.skye-branding{' . $declarations . '}';
	}

	/**
	 * Enqueue one small administrator skin after the native color scheme.
	 *
	 * A missing palette or asset retains native UI colors. No preference, role,
	 * notice meaning or plugin state is saved or replaced.
	 *
	 * @since 3.4.0
	 * @return void
	 */
	public function admin_styles() {
		if ( ! is_admin() || wp_doing_ajax() || $this->admin_ready ) {
			return;
		}
		$colors = self::colors();
		if ( ! $colors ) {
			return;
		}
		$url = self::asset_uri( 'backend.css' );
		if ( $url === '' ) {
			return;
		}
		wp_enqueue_style( 'skye/backend', $url, array( 'colors' ), null );
		wp_add_inline_style( 'skye/backend', self::variables( $colors ) );
		$this->admin_ready = true;
	}

	/**
	 * Brand login presentation while keeping every native authentication control intact.
	 *
	 * JSON string escaping plus rejected control/markup characters bounds the
	 * CSS URL. Login without an image uses the native site-name text instead.
	 * Repeated hook calls do not duplicate inline declarations in one response.
	 *
	 * @since 3.4.0
	 * @return void
	 */
	public function login_styles() {
		if ( $this->login_ready ) {
			return;
		}
		if ( ! self::login_assets() ) {
			return;
		}
		$colors = self::colors();
		$css = self::variables( $colors );
		$logo = self::logo();
		if ( $logo !== '' ) {
			$css .= 'body.skye-login-branding{--skye-login-logo:url(' . wp_json_encode( $logo, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ');}';
		}
		if ( $css !== '' ) {
			wp_add_inline_style( 'skye/login', $css );
		}
		$this->login_ready = true;
		$this->login_logo = $logo !== '';
		$this->login_palette = (bool) $colors;
	}

	/**
	 * Register the owned login stylesheet through the existing native handle.
	 *
	 * The historical setup helper can delegate here without constructing another
	 * hook service. Repeated native enqueues preserve one stylesheet dependency.
	 *
	 * @since 3.4.0
	 * @return bool Whether the complete parent-owned stylesheet is available and queued.
	 */
	public static function login_assets() {
		$url = self::asset_uri( 'login.css' );
		if ( $url === '' ) {
			return false;
		}
		wp_enqueue_style( 'skye/login', $url, array( 'login' ), null );
		return true;
	}

	/**
	 * Add a stylesheet-owned administrator scope without replacing native classes.
	 *
	 * @since 3.4.0
	 * @param string $classes Existing space-separated administrator classes.
	 * @return string Original classes with one branding marker after a successful enqueue.
	 */
	public function admin_classes( $classes ) {
		return $this->admin_ready && ! preg_match( '/(?:^|\s)skye-branding(?:\s|$)/', $classes ) ? $classes . ' skye-branding' : $classes;
	}

	/**
	 * Distinguish image and text login branding without changing native action classes.
	 *
	 * @since 3.4.0
	 * @param array $classes Existing native login class names.
	 * @return array Original classes with bounded package presentation markers.
	 */
	public function login_classes( $classes ) {
		if ( $this->login_ready ) {
			$markers = array( 'skye-login-branding', $this->login_logo ? 'skye-login-has-logo' : 'skye-login-text-logo' );
			if ( $this->login_palette ) {
				$markers[] = 'skye-branding';
			}
			foreach ( $markers as $marker ) {
				if ( ! in_array( $marker, $classes, true ) ) {
					$classes[] = $marker;
				}
			}
		}
		return $classes;
	}

	/**
	 * Point the native login identity link to this site, without changing form destinations.
	 *
	 * @since 3.4.0
	 * @param string $url Earlier native header-logo link URL.
	 * @return string Native site homepage after branding is available; otherwise the prior URL.
	 */
	public function login_url( $url ) {
		return $this->login_ready ? home_url( '/' ) : $url;
	}

	/**
	 * Give the existing login identity anchor its actual site name as accessible text.
	 *
	 * Core prints this filter as element content without another escape step.
	 *
	 * @since 3.4.0
	 * @param string $text Earlier native header-logo accessible text.
	 * @return string HTML-escaped website name after branding is available; otherwise the prior text.
	 */
	public function login_text( $text ) {
		return $this->login_ready ? esc_html( wp_strip_all_tags( get_bloginfo( 'name' ) ) ) : $text;
	}
}
