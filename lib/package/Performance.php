<?php
/** Preserve native WordPress dependency graphs while describing verified provider intent. */

namespace Rocketsky\Skye\Packages;

/** Coordinate optional Gravity Forms optimization without changing Core policy. */
class Performance {
	/**
	 * Register native enqueue observers instead of relocating global script output.
	 *
	 * This keeps ACF, translations, XML-RPC and Customizer ownership outside the theme.
	 *
	 * @since 2021-03-04 First recorded Performance class in Skye history.
	 * @return void
	 */
	public function __construct() {
		add_action( 'gform_enqueue_scripts', array( $this, 'defer_form_scripts' ), 20, 2 );
		add_action( 'gform_enqueue_scripts', array( $this, 'alias_empty_form_style' ), 1001, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'defer_form_dependencies' ), 1000 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_form_assets' ), 5 );
	}

	/**
	 * Declare PHP-rendered forms before the head through the provider's native API.
	 *
	 * The theme cannot infer arbitrary ACF layouts or addon fields. A project's
	 * view-model supplies only known form IDs and AJAX flags, without rendering a
	 * form or exposing stored field objects to the browser.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function enqueue_form_assets() {
		if ( ! function_exists( 'gravity_form_enqueue_scripts' ) || is_admin() ||
			( function_exists( 'skye_content_access_state' ) && skye_content_access_state() !== 'allowed' ) ) {
			return;
		}
		$forms = apply_filters( 'skye_form_assets', array() );
		if ( ! is_array( $forms ) ) {
			return;
		}
		$seen = array();
		foreach ( $forms as $form ) {
			if ( ! is_array( $form ) || ! ( is_int( $form['id'] ?? null ) || is_string( $form['id'] ?? null ) ) ||
				! preg_match( '/^[1-9][0-9]*$/D', (string) $form['id'] ) ) {
				continue;
			}
			$id = (int) $form['id'];
			$ajax = ( $form['ajax'] ?? false ) === true;
			$key = $id . ':' . (int) $ajax;
			if ( ! isset( $seen[$key] ) ) {
				gravity_form_enqueue_scripts( $id, $ajax );
				$seen[$key] = true;
			}
		}
	}

	/**
	 * Retain GF's dependency handle without requesting its verified empty file.
	 *
	 * The installed provider registers an empty theme-components stylesheet. A
	 * native source-less alias still prints its dependencies and inline CSS. Only
	 * the provider's exact local file and canonical URL qualify; future nonempty
	 * files, custom sources, alternate/RTL/conditional delivery and private
	 * contexts keep their original registration. No remote request or CSS-content read
	 * is needed. This runs after the provider's priority-1000 theme enqueue and
	 * before the critical-style observer at PHP_INT_MAX.
	 *
	 * @see https://developer.wordpress.org/reference/functions/wp_register_style/
	 * @see https://docs.gravityforms.com/gform_enqueue_scripts/
	 * @param array $form Native provider form, not read or modified here.
	 * @param bool $is_ajax Native form-rendering mode, not changed here.
	 * @return void
	 * @since 3.0.0
	 */
	public function alias_empty_form_style( $form, $is_ajax ) {
		$handle = 'gform_theme_components';
		if ( ! wp_style_is( $handle, 'registered' ) || ! wp_style_is( $handle, 'enqueued' ) ||
			wp_style_is( $handle, 'done' ) || ! is_callable( array( '\GFCommon', 'get_base_url' ) ) ||
			! is_callable( array( '\GFCommon', 'get_base_path' ) ) || ! self::public_form_asset_request() ) {
			return;
		}
		$styles = wp_styles();
		$style = $styles->registered[$handle];
		if ( ! is_string( $style->src ) || isset( $styles->args[$handle] ) ) {
			return;
		}
		foreach ( array( 'rtl', 'suffix', 'path', 'alt', 'title', 'conditional', 'inlined_src' ) as $key ) {
			if ( array_key_exists( $key, $style->extra ) ) {
				return;
			}
		}
		$base_url = \GFCommon::get_base_url();
		$base_path = \GFCommon::get_base_path();
		if ( ! is_string( $base_url ) || ! is_string( $base_path ) || ! $base_url || ! $base_path ) {
			return;
		}
		$file = false;
		foreach ( array( 'theme-components.min.css', 'theme-components.css' ) as $name ) {
			$relative = '/assets/css/dist/' . $name;
			if ( $style->src === rtrim( $base_url, '/' ) . $relative ) {
				$file = rtrim( $base_path, '/\\' ) . $relative;
				break;
			}
		}
		if ( ! $file ) {
			return;
		}
		// Native plugin updates in this request must not inherit an earlier zero stat.
		clearstatcache( true, $file );
		if ( ! is_file( $file ) || is_link( $file ) || ! is_readable( $file ) ) {
			return;
		}
		// A concurrent plugin update may remove the file after the regular-file check.
		if ( @filesize( $file ) !== 0 ) {
			return;
		}
		wp_deregister_style( $handle );
		wp_register_style( $handle, false, $style->deps, $style->ver, $style->args );
		foreach ( $style->extra as $key => $value ) {
			wp_style_add_data( $handle, $key, $value );
		}
		wp_style_add_data( $handle, 'skye-empty-style-alias', true );
	}

	/**
	 * Limit the empty-file optimization to ordinary anonymous frontend delivery.
	 *
	 * Editor, authenticated, preview, protected, API and submission contexts keep
	 * provider registrations intact. These native request checks do not render a
	 * form or hydrate ACF; the current queried post is already available.
	 *
	 * @return bool Whether this is a public GET/HEAD frontend request.
	 * @since 3.0.0
	 */
	public static function public_form_asset_request() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_user_logged_in() ||
			( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ||
			( defined( 'WP_CLI' ) && WP_CLI ) || ! in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true ) ||
			is_customize_preview() || is_preview() || is_feed() || is_404() || ( is_singular() && post_password_required() ) ) {
			return false;
		}
		$post = get_queried_object();
		if ( $post instanceof \WP_Post && ( $post->post_status !== 'publish' || $post->post_password !== '' ) ) {
			// A valid password cookie does not make protected content public.
			return false;
		}
		return true;
	}

	/**
	 * Describe late provider scripts and their native WordPress dependencies.
	 *
	 * Theme Layers can enqueue after the provider's early hook. Recheck the same
	 * verified provider intent once all public enqueues have completed, then let
	 * Core resolve its own small accessibility dependencies. In particular Core
	 * retains blocking i18n/hooks when immediate locale inline code requires it;
	 * no translations, script bodies or after-inline blocks are moved or wrapped.
	 *
	 * @see https://developer.wordpress.org/reference/functions/wp_script_add_data/
	 * @return void
	 * @since 3.0.0
	 */
	public function defer_form_dependencies() {
		if ( is_admin() || ! wp_script_is( 'gform_gravityforms', 'enqueued' ) ) {
			return;
		}
		$this->defer_form_scripts( array(), false );
		$scripts = wp_scripts();
		foreach ( array( 'wp-dom-ready', 'wp-hooks', 'wp-i18n', 'wp-a11y' ) as $handle ) {
			if ( wp_script_is( $handle, 'registered' ) && ! wp_script_is( $handle, 'done' ) &&
				$scripts->registered[$handle]->src && ! $scripts->get_data( $handle, 'strategy' ) ) {
				wp_script_add_data( $handle, 'strategy', 'defer' );
			}
		}
	}

	/**
	 * Describe Gravity Forms' existing deferred scripts to WordPress itself.
	 *
	 * GF's tag callback is invisible to Core's dependency strategy resolver. Only
	 * mirror its current intent while that exact provider filter is still active:
	 * the provider must itself defer a minimal probe tag for each verified handle.
	 * Its checked implementation reads registered dependencies without enqueuing
	 * assets or producing output. Run after GF's priority-10 theme enqueue; only
	 * queued, registered source scripts are touched. Aliases, unrelated plugins,
	 * explicit strategies, dependencies and inline code remain intact. Core can
	 * still keep jQuery blocking when a dependent or after-inline requires it.
	 *
	 * @see https://docs.gravityforms.com/gform_enqueue_scripts/
	 * @see https://developer.wordpress.org/reference/functions/wp_script_add_data/
	 * @param array $form Native form configuration, not read or modified here.
	 * @param bool  $is_ajax Native AJAX rendering flag, not changed here.
	 * @return void
	 * @since 3.0.0
	 */
	public function defer_form_scripts( $form, $is_ajax ) {
		$provider = array( 'GFFormDisplay', 'add_script_defer' );
		if ( is_admin() || ! is_callable( $provider ) || false === has_filter( 'script_loader_tag', $provider ) ||
			! class_exists( '\WP_HTML_Tag_Processor' ) ) {
			return;
		}
		$scripts = wp_scripts();
		// GFForms::register_scripts(): verified frontend graph, without broad prefix matching.
		$handles = array(
			'gform_json',
			'gform_gravityforms',
			'gform_gravityforms_utils',
			'gform_gravityforms_theme_vendors',
			'gform_gravityforms_theme',
			'gform_conditional_logic',
			'gform_page_conditional_logic',
			'gform_chosen',
			'gform_selectwoo',
			'gform_simplebar',
			'gform_placeholder',
			'gform_gravityforms_libraries',
		);
		foreach ( $handles as $handle ) {
			if ( ! wp_script_is( $handle, 'registered' ) || ! wp_script_is( $handle, 'enqueued' ) ||
				wp_script_is( $handle, 'done' ) || ! $scripts->registered[$handle]->src || $scripts->get_data( $handle, 'strategy' ) ) {
				continue;
			}
			// Inspect the provider's output; never force an attribute or bypass Core's guards.
			$tag = \GFFormDisplay::add_script_defer( '<script src="about:blank"></script>', $handle );
			if ( ! is_string( $tag ) ) {
				continue;
			}
			$probe = new \WP_HTML_Tag_Processor( $tag );
			if ( ! $probe->next_tag( 'SCRIPT' ) || null === $probe->get_attribute( 'defer' ) ) {
				continue;
			}
			wp_script_add_data( $handle, 'strategy', 'defer' );
		}
	}

}
