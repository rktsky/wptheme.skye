<?php
/** Connect explicit project projections to native Yoast analysis without replacing its ACF collector. */

namespace Rocketsky\Skye\Packages;

use Rocketsky\Skye\Assets;
use Rocketsky\Skye\Packages\Assets\PathIdentity;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/assets/PathIdentity.php';

/** Keep saved image analysis and authorized live editing separate from public rendering and storage. */
final class YoastAnalysis {
	/**
	 * Attach native analysis boundaries without collecting fields or loading frontend assets.
	 *
	 * Projects may supply semantic mappings. A verified ContentSources profile also
	 * enables the native active-source collector path without mapping unknown layouts.
	 *
	 * @since 3.5.0
	 * @return void Registers no persistence, transport or score-writing hook.
	 */
	public function __construct() {
		add_filter( 'wpseo_pre_analysis_post_content', array( self::class, 'content' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ), 21 );
	}

	/**
	 * Offer the exact public post to a project's saved semantic projection.
	 *
	 * Yoast also uses this filter for social-image discovery. No global post,
	 * template render, field lookup or private content is substituted implicitly.
	 * Known editor mode retains Core content. A known builder excludes inactive Core
	 * content even without a semantic mapper; null/errors keep that active default.
	 * Unknown source profiles retain the preceding project/native contract.
	 *
	 * @since 3.5.0
	 * @param mixed $content Incoming Yoast analysis content, unchanged for unsupported input.
	 * @param mixed $post Exact second native filter argument; never resolved from the loop.
	 * @return mixed Explicit active project HTML, empty unmapped builder content or native input.
	 */
	public static function content( $content, $post ) {
		if ( ! is_string( $content ) || ! $post instanceof \WP_Post || $post->ID <= 0 || $post->post_password !== '' ||
			! is_post_publicly_viewable( $post ) || skye_content_access_state( $post->ID ) !== 'allowed' ) {
			return $content;
		}
		$source = null;
		if ( class_exists( ContentSources::class, false ) ) {
			try {
				$source = ContentSources::analysis_source( $post );
			} catch ( \Throwable $error ) {
				return $content;
			}
		}
		if ( $source === 'editor' ) {
			return $content;
		}
		$fallback = $source === 'builder' ? '' : $content;
		if ( false === has_filter( 'skye_yoast_analysis_content' ) ) {
			return $fallback;
		}
		try {
			$projection = apply_filters( 'skye_yoast_analysis_content', null, $post, $content );
		} catch ( \Throwable $error ) {
			return $fallback;
		}
		return is_string( $projection ) ? $projection : $fallback;
	}

	/**
	 * Resolve only the package's immutable parent asset, preserving child build independence.
	 *
	 * A missing, escaped or incomplete build retains native analysis rather than
	 * loading an unverified source URL. This work runs only after explicit opt-in.
	 *
	 * @since 3.5.0
	 * @param string $filename Exact yoast-analysis.js or content-sources-yoast.js asset.
	 * @return string Verified package URL, or an empty refusal.
	 */
	public static function asset_uri( $filename = 'yoast-analysis.js' ) {
		if ( ! in_array( $filename, array( 'yoast-analysis.js', 'content-sources-yoast.js' ), true ) ) {
			return '';
		}
		$root = get_template_directory();
		$manifest = new Assets\JsonManifest( $root . '/dist/assets.json' );
		$relative = $manifest->getPath( $filename, '' );
		if ( ! is_string( $relative ) || ! preg_match( '~^scripts/' . pathinfo( $filename, PATHINFO_FILENAME ) . '-[a-f0-9]{16}\.js$~D', $relative ) ) {
			return '';
		}
		$file = PathIdentity::canonical( $root . '/dist/' . $relative );
		$dist = PathIdentity::canonical( $root . '/dist' );
		return $file && $dist && str_starts_with( $file, $dist . DIRECTORY_SEPARATOR ) && is_file( $file ) && is_readable( $file )
			? get_template_directory_uri() . '/dist/' . $relative : '';
	}

	/**
	 * Register the source-policy coupler only beside the verified actual source controller.
	 *
	 * Its dependency holds both APIs in native order. Foreign handles, missing
	 * builds and unavailable source profiles leave the original collector alone.
	 * @since 3.5.0
	 * @param \WP_Post $post Exact editable document already authorized by enqueue().
	 * @return array{id:string,script:string}|null Native source contract or an unavailable profile.
	 */
	private static function default_editor( \WP_Post $post ) {
		if ( ! class_exists( ContentSources::class, false ) || ! wp_script_is( 'skye-content-sources', 'registered' ) ||
			! ContentSources::editor_context( $post ) || wp_script_is( 'skye-content-sources-yoast', 'registered' ) ) {
			return null;
		}
		$controller = wp_scripts()->registered['skye-content-sources'];
		if ( $controller->src !== ContentSources::asset_uri( 'content-sources.js' ) || $controller->deps !== array( 'jquery', 'acf-input' ) ||
			$controller->ver !== null || wp_script_is( 'skye-content-sources', 'done' ) ) {
			return null;
		}
		$uri = self::asset_uri( 'content-sources-yoast.js' );
		if ( $uri === '' ) {
			return null;
		}
		wp_register_script( 'skye-content-sources-yoast', $uri, array( 'skye-yoast-analysis', 'skye-content-sources' ), null, true );
		return array( 'id' => 'skye-content-sources', 'script' => 'skye-content-sources-yoast' );
	}

	/**
	 * Load one authorized project's live adapter after its declared native dependencies.
	 *
	 * The project registers its own script with skye-yoast-analysis as a dependency
	 * and returns only its handle and bounded identity. No content catalog, ACF
	 * exclusions or frontend metadata is generated by this package.
	 *
	 * @since 3.5.0
	 * @param string $hook Actual native administration screen filename.
	 * @return void Unknown contracts, screens, capabilities and missing assets remain native.
	 */
	public static function enqueue( $hook ) {
		if ( ! defined( 'WPSEO_VERSION' ) ||
			! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		global $post;
		if ( ! $screen || $screen->base !== 'post' || ! $post instanceof \WP_Post || $post->ID <= 0 ||
			$screen->post_type !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		try {
			$default = self::default_editor( $post );
			$config = apply_filters( 'skye_yoast_analysis_editor', $default, $post );
		} catch ( \Throwable $error ) {
			return;
		}
		if ( ! is_array( $config ) || ! is_string( $config['id'] ?? null ) || ! is_string( $config['script'] ?? null ) ||
			! preg_match( '/^[a-zA-Z][a-zA-Z0-9_-]{0,63}$/D', $config['id'] ) ||
			! preg_match( '/^[a-zA-Z][a-zA-Z0-9_-]{0,63}$/D', $config['script'] ) ||
			$config['script'] === 'skye-yoast-analysis' || ! wp_script_is( $config['script'], 'registered' ) ||
			wp_script_is( $config['script'], 'done' ) ) {
			return;
		}
		$project = wp_scripts()->registered[$config['script']];
		if ( ! in_array( 'skye-yoast-analysis', $project->deps, true ) || wp_script_is( 'skye-yoast-analysis', 'registered' ) ) {
			return;
		}
		$collector = wp_scripts()->registered['yoast-acf-analysis-post'] ?? null;
		if ( $collector || defined( 'AC_SEO_ACF_ANALYSIS_PLUGIN_FILE' ) ) {
			if ( ! $collector || $collector->ver !== '3.2' || $collector->deps !== array( 'jquery', 'underscore' ) ||
				! defined( 'AC_SEO_ACF_ANALYSIS_PLUGIN_FILE' ) ||
				$collector->src !== plugins_url( '/js/yoast-acf-analysis.js', AC_SEO_ACF_ANALYSIS_PLUGIN_FILE ) ) {
				return;
			}
		}
		$uri = self::asset_uri();
		if ( $uri === '' ) {
			return;
		}
		$dependencies = array( 'jquery' );
		if ( $collector ) {
			$dependencies[] = 'yoast-acf-analysis-post';
		}
		wp_register_script( 'skye-yoast-analysis', $uri, $dependencies, null, true );
		wp_localize_script( 'skye-yoast-analysis', 'SkyeYoastAnalysisConfig', array( 'id' => $config['id'] ) );
		wp_enqueue_script( $config['script'] );
		if ( $default ) {
			wp_enqueue_script( $default['script'] );
		}
	}
}
