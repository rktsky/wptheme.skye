<?php
/** Expose optional native delivery settings and map only eligible public static URLs. */

namespace Rocketsky\Skye\Packages;

/** Keep CDN policy independent of ACF and disabled until explicitly configured. */
final class DeliverySettings {
	/**
	 * Register native settings and presentation URL filters without changing content.
	 *
	 * Saved attachments, GUIDs, administrator URLs, form actions, POSTs and server
	 * HTTP requests retain their original values. No HTML replacement runs here.
	 *
	 * @since 3.1.0
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_menu', array( $this, 'menu' ) );
		foreach ( array( 'skye_public_asset_url', 'skye_static_asset_url', 'script_loader_src', 'style_loader_src', 'wp_get_attachment_url' ) as $hook ) {
			add_filter( $hook, array( $this, 'map' ), 90 );
		}
		add_filter( 'wp_get_attachment_image_src', array( $this, 'image' ), 90 );
		add_filter( 'wp_calculate_image_srcset', array( $this, 'srcset' ), 90 );
		add_filter( 'wp_preload_resources', array( $this, 'preloads' ), 90 );
		add_filter( 'skye_canonical_asset_url', array( $this, 'canonical' ), 90 );
		add_filter( 'skye_static_asset_cache_key', array( $this, 'cache_key' ), 90 );
	}

	/**
	 * Derive allowed static roots from this installation instead of hardcoding wp-content.
	 *
	 * @since 3.1.0
	 * @return array<string> Native public content/upload/includes path prefixes.
	 */
	public static function prefixes() {
		static $prefixes;
		if ( isset( $prefixes ) ) {
			return $prefixes;
		}
		$content = untrailingslashit( wp_parse_url( content_url(), PHP_URL_PATH ) ?: '/wp-content' );
		$includes = untrailingslashit( wp_parse_url( includes_url(), PHP_URL_PATH ) ?: '/wp-includes' );
		$uploads = wp_upload_dir( null, false );
		$upload_path = wp_parse_url( $uploads['baseurl'] ?? '', PHP_URL_PATH );
		return $prefixes = array_values( array_unique( array_filter( array(
			$content . '/themes/', $content . '/plugins/', $content . '/uploads/',
			$includes . '/js/', $includes . '/css/', is_string( $upload_path ) ? trailingslashit( $upload_path ) : '',
		) ) ) );
	}

	/**
	 * Validate administrator-owned CDN settings without fetching the configured host.
	 *
	 * Prefixes must stay inside native static roots. Credentials, private IPs,
	 * arbitrary origins and unsupported URL components never enter delivery.
	 *
	 * @since 3.1.0
	 * @param mixed $input Native Settings API input or trusted project configuration.
	 * @return array{cdn_enabled:bool,cdn_url:string,prefixes:array} Safe settings.
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$url = is_string( $input['cdn_url'] ?? null ) ? trim( $input['cdn_url'] ) : '';
		$parts = $url !== '' ? wp_parse_url( $url ) : false;
		$valid_url = is_array( $parts ) && ( $parts['scheme'] ?? '' ) === 'https' && ! empty( $parts['host'] ) &&
			filter_var( $parts['host'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) && str_contains( $parts['host'], '.' ) &&
			! isset( $parts['user'] ) && ! isset( $parts['pass'] ) && ! isset( $parts['query'] ) && ! isset( $parts['fragment'] ) &&
			( ! isset( $parts['port'] ) || $parts['port'] === 443 ) && ! preg_match( '/[\x00-\x20]/', $url ) &&
			preg_match( '~^/[a-zA-Z0-9_./-]*$~D', $parts['path'] ?? '/' ) && ! in_array( '..', explode( '/', $parts['path'] ?? '' ), true );
		if ( $valid_url && filter_var( $parts['host'], FILTER_VALIDATE_IP ) && ! filter_var( $parts['host'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			$valid_url = false;
		}
		$url = $valid_url ? untrailingslashit( $url ) : '';
		$allowed = self::prefixes();
		$requested = $input['prefixes'] ?? $allowed;
		$requested = is_string( $requested ) ? preg_split( '/\R/', $requested ) : $requested;
		$prefixes = array();
		foreach ( is_array( $requested ) ? array_slice( $requested, 0, 24 ) : array() as $prefix ) {
			$prefix = is_string( $prefix ) ? trailingslashit( trim( $prefix ) ) : '';
			if ( ! preg_match( '~^/[a-zA-Z0-9_./-]+/$~D', $prefix ) || in_array( '..', explode( '/', $prefix ), true ) ) {
				continue;
			}
			foreach ( $allowed as $root ) {
				if ( str_starts_with( $prefix, $root ) ) {
					$prefixes[] = $prefix;
					break;
				}
			}
		}
		$prefixes = array_values( array_unique( $prefixes ) );
		return array( 'cdn_enabled' => ! empty( $input['cdn_enabled'] ) && $url !== '' && (bool) $prefixes, 'cdn_url' => $url, 'prefixes' => $prefixes );
	}

	/**
	 * Read the small native option through Core's cache and normalize trusted filters.
	 *
	 * @since 3.1.0
	 * @return array Sanitized current settings; missing configuration stays disabled.
	 */
	private static function configuration() {
		$defaults = array( 'cdn_enabled' => false, 'cdn_url' => '', 'prefixes' => self::prefixes() );
		$value = get_option( 'skye_delivery', $defaults );
		return self::sanitize( apply_filters( 'skye_cdn_configuration', $value ) );
	}

	/**
	 * Map a same-origin, allowlisted static URL only on anonymous public GETs.
	 *
	 * Unknown query parameters remain at the origin so signed or stateful URLs
	 * cannot accidentally forward credentials/nonces to another hostname.
	 *
	 * @since 3.1.0
	 * @param mixed $url Native public asset URL.
	 * @return mixed Validated delivery URL or the original value.
	 */
	public function map( $url ) {
		if ( ! is_string( $url ) || ! StaticDelivery::public_request() ) {
			return $url;
		}
		$config = self::configuration();
		if ( ! $config['cdn_enabled'] ) {
			return $url;
		}
		$parts = wp_parse_url( $url );
		$origin = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $parts ) || ! is_array( $origin ) || ! in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true ) ||
			strtolower( $parts['host'] ?? '' ) !== strtolower( $origin['host'] ?? '' ) || ( $parts['port'] ?? null ) !== ( $origin['port'] ?? null ) ||
			isset( $parts['user'] ) || isset( $parts['pass'] ) || ! self::eligible_path( $parts['path'] ?? '', $config['prefixes'] ) ) {
			return $url;
		}
		if ( isset( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
			if ( array_keys( $query ) !== array( 'ver' ) || ! is_string( $query['ver'] ) ) {
				return $url;
			}
		}
		return $config['cdn_url'] . $parts['path'] . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' ) . ( isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '' );
	}

	/**
	 * Recover only this configured mapper's canonical local source for static caching.
	 *
	 * No HTTP request is made and third-party CDN URLs cannot select local files.
	 *
	 * @since 3.1.0
	 * @param mixed $url Registered source URL possibly mapped by this package.
	 * @return mixed Original canonical same-origin URL, otherwise unchanged.
	 */
	public function canonical( $url ) {
		if ( ! is_string( $url ) || ! StaticDelivery::public_request() ) {
			return $url;
		}
		$config = self::configuration();
		if ( ! $config['cdn_enabled'] || ! str_starts_with( $url, $config['cdn_url'] . '/' ) ) {
			return $url;
		}
		$path = substr( $url, strlen( $config['cdn_url'] ) );
		if ( ! self::eligible_path( $path, $config['prefixes'] ) ) {
			return $url;
		}
		$origin = wp_parse_url( home_url( '/' ) );
		return $origin['scheme'] . '://' . $origin['host'] . ( isset( $origin['port'] ) ? ':' . $origin['port'] : '' ) . $path;
	}

	/**
	 * Check a literal public static path against a bounded native-root allowlist.
	 *
	 * @since 3.1.0
	 * @param string $path Canonical URL path, without a query string.
	 * @param array $prefixes Validated native static roots or their subdirectories.
	 * @return bool Whether the path is an eligible non-executable static resource.
	 */
	private static function eligible_path( $path, $prefixes ) {
		if ( ! is_string( $path ) || ! preg_match( '~^/[a-zA-Z0-9_./-]+\.(?:css|js|png|jpe?g|gif|webp|avif|svg|ico|woff2?|ttf|otf|pdf|mp4|webm|mp3|ogg)$~iD', $path ) ||
			in_array( '..', explode( '/', $path ), true ) || str_contains( $path, '//' ) ) {
			return false;
		}
		foreach ( $prefixes as $prefix ) {
			if ( str_starts_with( $path, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Preserve native image dimensions and intermediate-size identity while mapping its URL.
	 *
	 * @since 3.1.0
	 * @param array|false $image Native image tuple.
	 * @return array|false Native tuple with an optional delivery URL.
	 */
	public function image( $image ) {
		if ( is_array( $image ) && isset( $image[0] ) ) {
			$image[0] = $this->map( $image[0] );
		}
		return $image;
	}

	/**
	 * Map structured native srcset candidates without changing descriptors or widths.
	 *
	 * @since 3.1.0
	 * @param mixed $sources Native structured image candidates.
	 * @return mixed Original candidates with eligible public URLs mapped.
	 */
	public function srcset( $sources ) {
		if ( is_array( $sources ) ) {
			foreach ( $sources as &$source ) {
				if ( is_array( $source ) && isset( $source['url'] ) ) {
					$source['url'] = $this->map( $source['url'] );
				}
			}
			unset( $source );
		}
		return $sources;
	}

	/**
	 * Keep native preload hints aligned with mapped font and responsive image resources.
	 *
	 * Unknown responsive serialization stays native; no arbitrary HTML is parsed.
	 *
	 * @since 3.1.0
	 * @param mixed $resources Native preload records.
	 * @return mixed Records with eligible URLs mapped and all other attributes retained.
	 */
	public function preloads( $resources ) {
		if ( ! is_array( $resources ) ) {
			return $resources;
		}
		foreach ( $resources as &$resource ) {
			if ( ! is_array( $resource ) ) {
				continue;
			}
			if ( isset( $resource['href'] ) ) {
				$resource['href'] = $this->map( $resource['href'] );
			}
			if ( is_string( $resource['imagesrcset'] ?? null ) ) {
				$candidates = preg_split( '/,\s*/', $resource['imagesrcset'] );
				$mapped = array();
				foreach ( $candidates as $candidate ) {
					if ( ! preg_match( '/^(\S+)\s+([0-9.]+[wx])$/D', trim( $candidate ), $match ) ) {
						$mapped = array();
						break;
					}
					$mapped[] = $this->map( $match[1] ) . ' ' . $match[2];
				}
				if ( $mapped ) {
					$resource['imagesrcset'] = implode( ', ', $mapped );
				}
			}
		}
		unset( $resource );
		return $resources;
	}

	/**
	 * Bind persistent CSS derivatives to the complete public delivery configuration.
	 *
	 * @since 3.1.0
	 * @param string $key Existing project mapping version token.
	 * @return string Version token including the sanitized CDN policy hash.
	 */
	public function cache_key( $key ) {
		return $key . '|' . hash( 'sha256', wp_json_encode( self::configuration() ) );
	}

	/**
	 * Register the native Settings API boundary with its administrator capability.
	 *
	 * @since 3.1.0
	 * @return void
	 */
	public function register() {
		register_setting( 'skye_delivery', 'skye_delivery', array( 'type' => 'array', 'sanitize_callback' => array( self::class, 'sanitize' ), 'show_in_rest' => false, 'default' => array( 'cdn_enabled' => false, 'cdn_url' => '', 'prefixes' => self::prefixes() ) ) );
	}

	/**
	 * Add one presentation settings page without requiring a separate options plugin.
	 *
	 * @since 3.1.0
	 * @return void
	 */
	public function menu() {
		add_theme_page( __( 'Skye Auslieferung', 'skye' ), __( 'Skye Auslieferung', 'skye' ), 'manage_options', 'skye-delivery', array( $this, 'page' ) );
	}

	/**
	 * Prepare a small settings model while Core owns nonce and option persistence.
	 *
	 * @since 3.1.0
	 * @return void Unauthorized callers receive a native capability failure.
	 */
	public function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'skye' ), '', array( 'response' => 403 ) );
		}
		ob_start();
		settings_fields( 'skye_delivery' );
		$hidden_fields = ob_get_clean();
		get_component( 'settings', 'delivery', array( 'settings' => self::configuration(), 'action' => admin_url( 'options.php' ), 'hidden_fields' => $hidden_fields, 'skip_bundled' => defined( 'CORE_UPGRADE_SKIP_NEW_BUNDLED' ) && CORE_UPGRADE_SKIP_NEW_BUNDLED ) );
	}
}
