<?php
/**
 * Deliver explicitly verified first viewports before their complete stylesheet.
 *
 * @since 3.0.0
 */

namespace Rocketsky\Skye\Packages;

use Rocketsky\Skye\Assets;

/**
 * Opt in only when the complete first viewport has a matching build contract.
 *
 * Full styles remain in their original native queue positions. Other layouts,
 * private requests, missing builds and unsupported header markup load normally.
 * No ACF hydration, stylesheet parsing or network requests happen at runtime.
 *
 * @since 3.0.0
 */
class CriticalStyles {
	/** @var string Request-local prepared font records on the owned native style alias. @since 3.0.0 */
	public const BODY_FONT_PRELOADS = 'skye-body-font-preloads';
	/** @var array Native complete provider handles, retained in their original order. @since 3.0.0 */
	private const FORM_STYLES = array( 'gform_basic', 'gform_theme_components', 'gform_theme' );
	/** @var string Build-preserved marker replaced with the trusted native script body. @since 3.0.0 */
	private const FORM_SCRIPT_TOKEN = '/*__SKYE_NATIVE_FORM_SCRIPT__*/';
	/** @var bool Whether the verified main styles and shared loader were installed. */
	private $enabled = false;
	/** @var array<int,bool> Actual supported provider form IDs observed this request. */
	private $simple_forms = array();
	/** @var array<int,array> Native render-hook contracts retained until the final pre-print check. @since 3.3.0 */
	private $form_producers = array();
	/** @var string Trusted own-asset wrapper; empty keeps provider initialization native. */
	private $form_gate = '';
	/** @var bool One unsupported provider form disables this request's opt-in. */
	private $unsupported_form = false;
	/** @var array<string,\_WP_Dependency> Original verified native style registrations. */
	private $original_styles = array();
	/** @var array<string,\_WP_Dependency> Registrations created by this instance only. */
	private $selected_styles = array();
	/** @var \_WP_Dependency|null Own head helper, retained for a safe before-head opt-out. */
	private $loader_registration;
	/** @var \_WP_Dependency|null Own alias retaining font hints already removed from the head. */
	private $critical_registration;
	/** @var bool Prevent final-URL filters from recursively preparing another font batch. */
	private $preparing_fonts = false;
	/** @var bool Prevent repeated or reentrant body actions from printing another batch. */
	private $fonts_printed = false;
	/** @var string Native owner handle for a prepared critical or explicitly opted-in font batch. */
	private $font_handle = '';
	/** @var \_WP_Dependency|null Exact owner identity retained across the head/body boundary. */
	private $font_registration;

	/**
	 * Wait for theme and provider enqueues before examining their actual handles.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function __construct() {
		add_action( 'gform_enqueue_scripts', array( $this, 'observe_form' ), PHP_INT_MAX, 2 );
		add_filter( 'gform_post_render_script', array( $this, 'form_script' ), 20, 3 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 999 );
		add_action( 'wp_print_styles', array( $this, 'validate_delivery' ), 0 );
		add_filter( 'wp_preload_resources', array( $this, 'defer_font_preloads' ), PHP_INT_MAX );
		add_action( 'wp_body_open', array( $this, 'render_font_preloads' ), 0 );
		add_filter( 'style_loader_tag', array( $this, 'style_tag' ), 100, 4 );
	}

	/**
	 * Move only unchanged own font hints after final public URL mapping.
	 *
	 * Chromium treats head font preloads as a bounded document-rendering barrier,
	 * independently of font-display. Body preloads remain ordinary HTML hints and
	 * use the same files and CORS policy without adding that head-only barrier.
	 * Any foreign attribute or same-URL collision keeps the original head record.
	 * Store accepted records on their exact native registration, never in options.
	 * Outside critical delivery, skye_font_preload_position must explicitly select
	 * body; its default head position preserves ordinary complete-CSS behaviour.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/wp_preload_resources/
	 * @see https://developer.wordpress.org/reference/functions/wp_style_add_data/
	 * @param mixed $resources Final native preload records after provider mapping.
	 * @return mixed Original records, or their ordered head subset.
	 * @since 3.0.0
	 */
	public function defer_font_preloads( $resources ) {
		if ( ! is_array( $resources ) || ! $resources || $this->preparing_fonts || $this->fonts_printed || did_action( 'wp_body_open' ) ) {
			return $resources;
		}
		$critical = $this->enabled && apply_filters( 'skye_progressive_styles', true ) && $this->critical_registration &&
			( wp_styles()->registered['skye/css-critical'] ?? null ) === $this->critical_registration;
		$position = apply_filters( 'skye_font_preload_position', $critical ? 'body' : 'head' );
		if ( $position !== 'body' || ( ! $critical && ( ! class_exists( StaticDelivery::class ) || ! StaticDelivery::public_request() ) ) ) {
			return $resources;
		}
		$handle = $critical ? 'skye/css-critical' : 'skye/css';
		$registration = wp_styles()->registered[$handle] ?? null;
		if ( ! $registration || ( $this->font_registration && ( $this->font_registration !== $registration || $this->font_handle !== $handle ) ) ) {
			return $resources;
		}
		$this->font_handle = $handle;
		$this->font_registration = $registration;
		$this->preparing_fonts = true;
		try {
			$expected = array();
			foreach ( Assets\font_preloads() as $font ) {
				$font['href'] = Assets\public_url( $font['href'] );
				if ( ! is_string( $font['href'] ) || ! $font['href'] ) {
					return $resources;
				}
				ksort( $font );
				$expected[$font['href']] = $font;
			}
			if ( ! $expected ) {
				return $resources;
			}
			$unsafe = array();
			foreach ( $resources as $resource ) {
				if ( ! is_array( $resource ) || ! is_string( $resource['href'] ?? null ) || ! isset( $expected[$resource['href']] ) ) {
					continue;
				}
				$comparison = $resource;
				ksort( $comparison );
				if ( $comparison !== $expected[$resource['href']] ) {
					$unsafe[$resource['href']] = true;
				}
			}
			$pending = wp_styles()->get_data( $this->font_handle, self::BODY_FONT_PRELOADS );
			if ( $pending !== false && ! is_array( $pending ) ) {
				return $resources;
			}
			$pending = is_array( $pending ) ? $pending : array();
			$had_pending = (bool) $pending;
			foreach ( $unsafe as $href => $unused ) {
				unset( $pending[$href] );
			}
			$head = array();
			foreach ( $resources as $key => $resource ) {
				if ( is_array( $resource ) && is_string( $resource['href'] ?? null ) &&
					isset( $expected[$resource['href']] ) && ! isset( $unsafe[$resource['href']] ) ) {
					$pending[$resource['href']] = $resource;
				} else {
					$head[$key] = $resource;
				}
			}
			if ( ! $pending ) {
				if ( $had_pending ) {
					wp_style_add_data( $this->font_handle, self::BODY_FONT_PRELOADS, array() );
				}
				return $resources;
			}
			if ( ! function_exists( 'get_component' ) ||
				! ( $component = locate_template( 'components/header/font-preloads.php', false, false ) ) || ! is_readable( $component ) ) {
				return $resources;
			}
			return wp_style_add_data( $this->font_handle, self::BODY_FONT_PRELOADS, $pending ) ? $head : $resources;
		} finally {
			$this->preparing_fonts = false;
		}
	}

	/**
	 * Print prepared native hints once, including after a later delivery opt-out.
	 *
	 * Once head output removed a hint it still belongs at the next native body
	 * boundary, even if a subsequent provider restores ordinary blocking CSS.
	 * The view reads only prepared metadata through the existing component loader.
	 * Clear the queue after rendering; mark first so reentrant actions stay inert.
	 *
	 * @see https://developer.wordpress.org/reference/functions/wp_body_open/
	 * @return void
	 * @since 3.0.0
	 */
	public function render_font_preloads() {
		if ( $this->fonts_printed || ! $this->font_registration ||
			( wp_styles()->registered[$this->font_handle] ?? null ) !== $this->font_registration ) {
			return;
		}
		$pending = wp_styles()->get_data( $this->font_handle, self::BODY_FONT_PRELOADS );
		if ( ! is_array( $pending ) || ! $pending ) {
			return;
		}
		$this->fonts_printed = true;
		try {
			get_component( 'header', 'font-preloads', array( 'fonts' => array_values( $pending ) ) );
		} finally {
			wp_style_add_data( $this->font_handle, self::BODY_FONT_PRELOADS, array() );
		}
	}

	/**
	 * Retain blocking delivery when the provider supplies a richer form instance.
	 *
	 * GF's native enqueue supplies the effective form without another metadata
	 * lookup. This runs after the native theme-layer enqueue at priority 1000,
	 * during the existing before-head form discovery. A later simple form cannot
	 * undo an earlier refusal, and no plugin initialization, rendered form, stored
	 * setting or submitted value is changed.
	 *
	 * @see https://docs.gravityforms.com/gform_enqueue_scripts/
	 * @param mixed $form Actual provider form supplied by its native enqueue.
	 * @param bool $is_ajax Native rendering mode, unchanged by this observer.
	 * @return void
	 * @since 3.0.0
	 */
	public function observe_form( $form, $is_ajax ) {
		if ( ! apply_filters( 'skye_progressive_styles', true ) ) {
			if ( $this->enabled ) {
				$this->disable_delivery();
			}
			return;
		}
		if ( ! $this->unsupported_form && self::supported_form( $form ) ) {
			$this->simple_forms[(int) $form['id']] = true;
			$this->form_producers[(int) $form['id']] = self::form_markup_hooks( $form );
			$this->enqueue_form_styles();
			return;
		}
		$this->disable_delivery();
	}

	/**
	 * Restore ordinary native delivery when eligibility disappears before printing.
	 *
	 * The capability may be withdrawn after enqueues by privacy/provider filters.
	 * Core invokes this action before resolving its style graph; no asset parsing,
	 * query or new source discovery is needed at this final boundary.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	public function validate_delivery() {
		if ( ! $this->enabled ) {
			return;
		}
		foreach ( $this->form_producers as $hooks ) {
			if ( ! self::native_form_producers( $hooks ) ) {
				$this->disable_delivery();
				return;
			}
		}
		if ( ! apply_filters( 'skye_progressive_styles', true ) ||
			( wp_scripts()->registered['skye/css-loader'] ?? null ) !== $this->loader_registration ||
			( ! wp_script_is( 'skye/css-loader', 'enqueued' ) && ! wp_script_is( 'skye/css-loader', 'done' ) ) ) {
			$this->disable_delivery();
			return;
		}
		foreach ( $this->original_styles as $handle => $original ) {
			$current = wp_styles()->registered[$handle] ?? null;
			$main = $handle === 'skye/css';
			if ( ! $current || ! in_array( $handle, wp_styles()->queue, true ) || $current !== ( $this->selected_styles[$handle] ?? null ) ||
				$current->src !== ( $main ? $original->src : false ) || $current->args !== ( $main ? 'all' : $original->args ) ||
				$current->deps !== array_merge( $original->deps, array( $main ? 'skye/css-critical' : 'skye/css' ) ) ||
				! is_array( $current->extra ) || array_diff( array_keys( $current->extra ), array( 'after', 'skye-after-paint' ) ) ||
				( $current->extra['skye-after-paint'] ?? false ) !== true ) {
				$this->disable_delivery();
				return;
			}
		}
	}

	/**
	 * Revoke this instance's unprinted optimization without changing provider data.
	 *
	 * A later unsupported form or private delivery refusal is permanent for this
	 * request. Existing GF configuration and inline callbacks remain native.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function disable_delivery() {
		$this->unsupported_form = true;
		$this->enabled = false;
		$this->form_gate = '';
		$this->restore_theme_styles();
		foreach ( self::FORM_STYLES as $handle ) {
			if ( wp_style_is( $handle, 'registered' ) && ! wp_style_is( $handle, 'done' ) ) {
				wp_style_add_data( $handle, 'skye-after-paint', false );
			}
		}
	}

	/**
	 * Opt in the complete native GF style set only after provider registration.
	 *
	 * The native theme-layer enqueue runs at priority 1000. This observer runs
	 * afterwards and changes only delivery metadata; dependencies, source URLs,
	 * content, order and provider script registrations remain untouched.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function enqueue_form_styles() {
		if ( ! $this->enabled || ! $this->form_gate || $this->unsupported_form || ! $this->simple_forms ||
			! apply_filters( 'skye_progressive_styles', true ) ) {
			return;
		}
		foreach ( self::FORM_STYLES as $handle ) {
			// Only our verified empty provider alias needs no LINK; its graph remains native.
			$empty_alias = $handle === 'gform_theme_components' && wp_style_is( $handle, 'registered' ) &&
				wp_styles()->registered[$handle]->src === false && wp_styles()->get_data( $handle, 'skye-empty-style-alias' ) === true;
			if ( ! wp_style_is( $handle, 'registered' ) || ! wp_style_is( $handle, 'enqueued' ) ||
				wp_style_is( $handle, 'done' ) || ( empty( wp_styles()->registered[$handle]->src ) && ! $empty_alias ) ) {
				return;
			}
		}
		foreach ( self::FORM_STYLES as $handle ) {
			wp_style_add_data( $handle, 'skye-after-paint', true );
		}
	}

	/**
	 * Preserve one native form body while its selected complete CSS becomes ready.
	 *
	 * GF retains initializeOnLoaded(), its event dispatch and every original body
	 * byte. The own asset closes over the native callback receiver, arguments,
	 * ambient event and iframe current_page variable without eval or a GF patch.
	 * Once means one invocation; later AJAX validation renders run independently.
	 *
	 * @see https://docs.gravityforms.com/gform_post_render_script/
	 * @param string $script Original native post-render JavaScript body.
	 * @param int $form_id Native form ID supplied by the provider filter.
	 * @param int|string $current_page Native page literal or surrounding iframe variable.
	 * @return string Original body, or its trusted own-asset readiness wrapper.
	 * @since 3.0.0
	 */
	public function form_script( $script, $form_id, $current_page ) {
		if ( ! $this->enabled || ! $this->form_gate || $this->unsupported_form || ! is_string( $script ) ||
			! ( is_int( $form_id ) || is_string( $form_id ) ) || ! preg_match( '/^[1-9][0-9]*$/D', (string) $form_id ) ||
			empty( $this->simple_forms[(int) $form_id] ) || ! wp_script_is( 'skye/css-loader', 'enqueued' ) ||
			! apply_filters( 'skye_progressive_styles', true ) ) {
			return $script;
		}
		foreach ( self::FORM_STYLES as $handle ) {
			if ( wp_styles()->get_data( $handle, 'skye-after-paint' ) !== true ) {
				return $script;
			}
		}
		return str_replace( self::FORM_SCRIPT_TOKEN, $script, $this->form_gate );
	}

	/**
	 * Admit only native, non-enhanced single-input forms with no custom init.
	 *
	 * GF initializes fixed/slide columns before its post-render event, while
	 * counters, masks, compound fields and add-ons may measure layout afterwards.
	 * The verified fill-layout text/email subset binds native controls without
	 * those modules. Future features keep the complete blocking stylesheet.
	 * These checks inspect the existing enqueue object, never GFAPI::get_form().
	 *
	 * @param mixed $form Actual native enqueue configuration.
	 * @return bool Whether its built-in initialization is within the tested scope.
	 * @since 3.0.0
	 */
	public static function supported_form( $form ) {
		if ( ! is_array( $form ) || empty( $form['id'] ) || ! ( is_int( $form['id'] ) || is_string( $form['id'] ) ) ||
			! preg_match( '/^[1-9][0-9]*$/D', (string) $form['id'] ) || empty( $form['fields'] ) || ! is_array( $form['fields'] ) ||
			! is_callable( array( '\GFFormDisplay', 'get_form_theme_slug' ) ) ||
			! is_callable( array( '\GFCommon', 'get_form_column_layout' ) ) ||
			\GFFormDisplay::get_form_theme_slug( $form ) !== 'gravity-theme' ||
			\GFCommon::get_form_column_layout( $form ) !== 'fill' ||
			( ! empty( $form['labelPlacement'] ) && $form['labelPlacement'] !== 'top_label' ) ) {
			return false;
		}
		foreach ( array( 'cssClass', 'conditionalLogic', 'pagination', 'enableHoneypot', 'enableAnimation', 'firstPageCssClass' ) as $key ) {
			if ( ! empty( $form[$key] ) ) {
				return false;
			}
		}
		if ( ( isset( $form['button'] ) && ! is_array( $form['button'] ) ) || ( isset( $form['save'] ) && ! is_array( $form['save'] ) ) ||
			! empty( $form['save']['enabled'] ) || ! empty( $form['button']['conditionalLogic'] ) ||
			! empty( $form['button']['cssClass'] ) ||
			( ! empty( $form['button']['type'] ) && $form['button']['type'] !== 'text' ) ) {
			return false;
		}
		$form_id = (int) $form['id'];
		if ( has_action( 'gform_register_init_scripts' ) !== false || has_action( 'gform_register_init_scripts_' . $form_id ) !== false ||
			! empty( \GFFormDisplay::$init_scripts[$form_id] ) ||
			self::unsupported_form_addons() ) {
			return false;
		}
		foreach ( $form['fields'] as $field ) {
			if ( ! is_object( $field ) ||
				! in_array( get_class( $field ), array( 'GF_Field_Text', 'GF_Field_Email' ), true ) ||
				$field->type !== ( get_class( $field ) === 'GF_Field_Text' ? 'text' : 'email' ) || $field->size !== 'large' ||
				( ! empty( $field->inputType ) && $field->inputType !== $field->type ) ||
				( ! empty( $field->visibility ) && $field->visibility !== 'visible' ) ||
				( ! empty( $field->labelPlacement ) && $field->labelPlacement !== 'top_label' ) ||
				( ! empty( $field->pageNumber ) && (int) $field->pageNumber !== 1 ) ||
				( isset( $field->maxLength ) && $field->maxLength !== '' && $field->maxLength !== null ) ) {
				return false;
			}
			foreach ( array( 'cssClass', 'conditionalLogic', 'inputs', 'fields', 'inputMask', 'inputMaskValue', 'enablePasswordInput',
				'emailConfirmEnabled', 'enableEnhancedUI', 'enableCalculation', 'calculationFormula', 'productField', 'choices', 'enablePrice' ) as $key ) {
				if ( ! empty( $field->$key ) ) {
					return false;
				}
			}
		}
		return self::native_form_producers( self::form_markup_hooks( $form ) );
	}

	/**
	 * Describe render hooks that can add controls, markup or executable form code.
	 *
	 * Only the theme's own class-only adapters and readiness wrapper are known.
	 * Third-party producers, including native anti-spam integrations, need their
	 * ordinary complete delivery. No plugin version, project-specific form identity, rendered HTML,
	 * submission value or provider callback is inspected or invoked here.
	 *
	 * @since 3.3.0
	 * @param array $form Already available native enqueue object.
	 * @return array<string,array|null> Hook names and optional exact own callback contracts.
	 */
	private static function form_markup_hooks( array $form ) {
		$id = (int) $form['id'];
		$hooks = array();
		$known = array(
			'gform_field_content' => array( FormControls::class, 'gravityforms', 20, 5 ),
			'gform_submit_button' => array( FormControls::class, 'submit_button', 20, 1 ),
			'gform_next_button' => array( FormControls::class, 'submit_button', 20, 1 ),
			'gform_previous_button' => array( FormControls::class, 'submit_button', 20, 1 ),
			'gform_post_render_script' => array( self::class, 'form_script', 20, 3 ),
		);
		foreach ( array( 'gform_get_form_filter', 'gform_form_tag', 'gform_form_after_open', 'gform_form_after_close',
			'gform_field_content', 'gform_field_input', 'gform_field_container', 'gform_submit_button',
			'gform_next_button', 'gform_previous_button', 'gform_post_render_script' ) as $hook ) {
			$hooks[$hook] = $known[$hook] ?? null;
			$hooks[$hook . '_' . $id] = null;
		}
		foreach ( $form['fields'] as $field ) {
			$field_id = $field->id ?? null;
			if ( ( is_int( $field_id ) || is_string( $field_id ) ) && preg_match( '/^[1-9][0-9]*$/D', (string) $field_id ) ) {
				foreach ( array( 'gform_field_content', 'gform_field_input', 'gform_field_container' ) as $hook ) {
					$hooks[$hook . '_' . $id . '_' . $field_id] = null;
				}
			}
		}
		return $hooks;
	}

	/**
	 * Refuse additional render producers without executing or altering their hooks.
	 *
	 * Core's public WP_Hook callback records retain identity, priority and arity.
	 * Rechecking before stylesheet output catches registrations added after the
	 * provider enqueue; callbacks introduced after head output require a project's
	 * separately verified layout contract rather than an inferred guarantee.
	 *
	 * @see https://developer.wordpress.org/reference/classes/wp_hook/
	 * @since 3.3.0
	 * @param array<string,array|null> $hooks Expected native hook contracts.
	 * @return bool Whether every current producer is absent or the exact own adapter.
	 */
	private static function native_form_producers( array $hooks ) {
		global $wp_filter;
		foreach ( $hooks as $hook => $known ) {
			if ( ! isset( $wp_filter[$hook] ) ) {
				continue;
			}
			$registry = $wp_filter[$hook];
			if ( ! $registry instanceof \WP_Hook ) {
				return false;
			}
			foreach ( $registry->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					$function = $callback['function'];
					if ( ! $known || ! is_array( $function ) || count( $function ) !== 2 || ! is_object( $function[0] ) ||
						get_class( $function[0] ) !== $known[0] || $function[1] !== $known[1] ||
						$priority !== $known[2] || $callback['accepted_args'] !== $known[3] ) {
						return false;
					}
				}
			}
		}
		return true;
	}

	/**
	 * Distinguish audited passive registrations from active frontend extensions.
	 *
	 * Native Theme Layers is always registered and its effective theme is checked
	 * above. No project-specific feed or add-on is assumed passive; unknown extensions
	 * keep ordinary blocking delivery until their effective contract is verified.
	 * Unknown add-ons retain the conservative fallback without loading feed data,
	 * constructing add-ons or invoking their script/configuration callbacks.
	 *
	 * @return bool Whether an add-on can introduce unverified initialization.
	 * @since 3.0.0
	 */
	private static function unsupported_form_addons() {
		if ( ! class_exists( '\GFAddOn', false ) || ! is_callable( array( '\GFAddOn', 'get_registered_addons' ) ) ) {
			return false;
		}
		$passive = array( 'Gravity_Forms\Gravity_Forms\Theme_Layers\GF_Theme_Layers' );
		foreach ( \GFAddOn::get_registered_addons() as $addon ) {
			if ( ! in_array( $addon, $passive, true ) ) {
				return true;
			}
		}
		$scripts = wp_scripts();
		foreach ( array_merge( $scripts->queue, $scripts->done ) as $handle ) {
			if ( strpos( $handle, 'gform_apc_' ) === 0 || strpos( $handle, 'gform_advancedpostcreation_' ) === 0 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Add the verified CSS before one complete, progressively loaded stylesheet.
	 *
	 * An explicit project layout contract advertises this request's support.
	 * Inline CSS has a separate alias dependency because after-inline on a media
	 * stylesheet would be printed too late and would not inherit its media query.
	 * GF opt-in is separate and requires actual supported native form instances.
	 *
	 * @see https://developer.wordpress.org/reference/functions/wp_add_inline_style/
	 * @see https://developer.wordpress.org/reference/functions/wp_add_inline_script/
	 * @return void
	 * @since 3.0.0
	 */
	public function enqueue() {
		if ( $this->enabled || $this->unsupported_form || ! apply_filters( 'skye_progressive_styles', true ) || ! self::supported_header() || Icons::needs_styles() ) {
			return;
		}
		$originals = self::native_theme_styles();
		if ( ! $originals || wp_style_is( 'skye/css-critical', 'registered' ) || wp_script_is( 'skye/css-loader', 'registered' ) ) {
			return;
		}
		$css = self::read_asset( Assets\dist_path( 'styles/main-critical.css' ), 98304 );
		$loader = self::read_asset( Assets\dist_path( 'scripts/critical-styles.js' ), 8192 );
		if ( ! $css || ! $loader || stripos( $css, '</style' ) !== false || stripos( $loader, '</script' ) !== false ) {
			return;
		}
		// Match the final font/image origin, including an optional CDN base path.
		$base = dirname( dirname( Assets\public_url( Assets\asset_uri( 'styles/main-critical.css' ) ) ) );
		if ( ! preg_match( '~^https?://[a-z0-9.:-]+(?:/[a-zA-Z0-9_./%-]+)*$~D', $base ) ) {
			return;
		}
		$css = str_replace( '__SKYE_THEME_DIST__', $base, $css );
		if ( strlen( $css ) > 98304 ) {
			return;
		}
		$gate = self::read_asset( Assets\dist_path( 'scripts/critical-form-gate.js' ), 4096 );
		$this->form_gate = substr_count( $gate, self::FORM_SCRIPT_TOKEN ) === 1 &&
			strpos( $gate, '//' ) === false && stripos( $gate, '</script' ) === false ? $gate : '';
		wp_register_style( 'skye/css-critical', false, array(), null );
		$this->critical_registration = wp_styles()->registered['skye/css-critical'];
		wp_add_inline_style( 'skye/css-critical', $css );
		// A tiny native inline helper cannot fail through another CDN request.
		wp_register_script( 'skye/css-loader', false, array(), null, false );
		wp_enqueue_script( 'skye/css-loader' );
		if ( ! wp_add_inline_script( 'skye/css-loader', $loader ) ) {
			return;
		}
		$this->loader_registration = wp_scripts()->registered['skye/css-loader'];
		$this->original_styles = $originals;
		$main = $originals['skye/css'];
		$mobile = $originals['skye/css-mobile'];
		// The complete original AST already covers every viewport. Keep the mobile
		// handle/inline position as an alias so dependents need no second CSS request.
		$this->selected_styles['skye/css'] = self::replace_style( 'skye/css', $main, $main->src, array_merge( $main->deps, array( 'skye/css-critical' ) ), 'all', $main->extra );
		$this->selected_styles['skye/css-mobile'] = self::replace_style( 'skye/css-mobile', $mobile, false, array_merge( $mobile->deps, array( 'skye/css' ) ), $mobile->args, $mobile->extra );
		$this->enabled = true;
		foreach ( Assets\stylesheet_variants() as $handle => $variant ) {
			wp_style_add_data( $handle, 'skye-after-paint', true );
		}
		$this->enqueue_form_styles();
	}

	/**
	 * Admit only the exact own native style pair with unambiguous presentation.
	 *
	 * Canonical manifest URLs and media must match the shared descriptors. Native
	 * inline CSS is retained; RTL, alternate, conditional, path/SRI and unknown
	 * metadata keep the original pair. Shared leaf dependencies already execute
	 * before both styles; differing or nested graphs cannot safely be reordered.
	 *
	 * @return array<string,\_WP_Dependency> Immutable snapshots, or an empty refusal.
	 * @since 3.0.0
	 */
	private static function native_theme_styles() {
		$styles = wp_styles();
		$variants = Assets\stylesheet_variants();
		if ( array_keys( $variants ) !== array( 'skye/css', 'skye/css-mobile' ) ) {
			return array();
		}
		$originals = array();
		foreach ( $variants as $handle => $variant ) {
			if ( ! wp_style_is( $handle, 'registered' ) || ! in_array( $handle, $styles->queue, true ) || wp_style_is( $handle, 'done' ) || isset( $styles->args[$handle] ) ) {
				return array();
			}
			$style = $styles->registered[$handle];
			if ( get_class( $style ) !== '_WP_Dependency' ) {
				return array();
			}
			if ( $style->handle !== $handle || $style->src !== Assets\asset_uri( $variant['file'] ) || $style->args !== $variant['media'] ||
				( $style->ver !== null && ! is_scalar( $style->ver ) ) || ! is_array( $style->deps ) || ! is_array( $style->extra ) ||
				array_diff( array_keys( $style->extra ), array( 'after', 'skye-after-paint' ) ) ||
				( isset( $style->extra['skye-after-paint'] ) && $style->extra['skye-after-paint'] !== false ) ) {
				return array();
			}
			if ( isset( $style->extra['after'] ) ) {
				if ( ! is_array( $style->extra['after'] ) ) {
					return array();
				}
				foreach ( $style->extra['after'] as $inline ) {
					if ( ! is_string( $inline ) ) {
						return array();
					}
				}
			}
			$originals[$handle] = clone $style;
		}
		if ( $originals['skye/css']->deps !== $originals['skye/css-mobile']->deps ) {
			return array();
		}
		foreach ( $originals['skye/css']->deps as $dependency ) {
			if ( ! is_string( $dependency ) || isset( $variants[$dependency] ) || $dependency === 'skye/css-critical' ||
				! isset( $styles->registered[$dependency] ) || $styles->registered[$dependency]->deps ) {
				return array();
			}
		}
		return $originals;
	}

	/**
	 * Re-register one native style without touching its queue or metadata payload.
	 *
	 * Core's remove/add operations leave queue positions intact. Copy the exact
	 * metadata array rather than replaying deprecated legacy data through setters.
	 * No linked stylesheet or inline content is inspected, rewritten or created.
	 *
	 * @see https://developer.wordpress.org/reference/functions/wp_register_style/
	 * @param string $handle Existing own style handle.
	 * @param \_WP_Dependency $style Native registration supplying the version.
	 * @param string|false $src Canonical stylesheet URL or native alias.
	 * @param array $dependencies Unchanged graph plus the explicit own dependency.
	 * @param mixed $media Native media argument to retain or select.
	 * @param array $extra Exact pre-existing inline/presentation metadata.
	 * @return \_WP_Dependency New registration owned by this instance.
	 * @since 3.0.0
	 */
	private static function replace_style( $handle, $style, $src, $dependencies, $media, $extra ) {
		wp_deregister_style( $handle );
		wp_register_style( $handle, $src, $dependencies, $style->ver, $media );
		wp_styles()->registered[$handle]->extra = $extra;
		return wp_styles()->registered[$handle];
	}

	/**
	 * Restore the complementary pair before output, preserving later inline data.
	 *
	 * Only registrations still owned by this instance may be restored. A third
	 * party's replacement is not overwritten. Remove only our appended graph
	 * edges/marker; preserve subsequent versions, dependencies and inline additions.
	 * Already printed styles cannot be undone and are left untouched.
	 *
	 * @return void
	 * @since 3.0.0
	 */
	private function restore_theme_styles() {
		foreach ( $this->original_styles as $handle => $original ) {
			if ( wp_style_is( $handle, 'done' ) ) {
				return;
			}
		}
		foreach ( $this->original_styles as $handle => $original ) {
			$current = wp_styles()->registered[$handle] ?? null;
			if ( ! $current || $current !== ( $this->selected_styles[$handle] ?? null ) ) {
				continue;
			}
			$dependency = $handle === 'skye/css' ? 'skye/css-critical' : 'skye/css';
			$dependencies = $current->deps;
			$key = array_search( $dependency, $dependencies, true );
			if ( $key !== false ) {
				array_splice( $dependencies, $key, 1 );
			}
			$extra = $current->extra;
			if ( array_key_exists( 'skye-after-paint', $original->extra ) ) {
				$extra['skye-after-paint'] = $original->extra['skye-after-paint'];
			} else {
				unset( $extra['skye-after-paint'] );
			}
			$selected_src = $handle === 'skye/css' ? $original->src : false;
			$src = $current->src === $selected_src ? $original->src : $current->src;
			$media = $handle === 'skye/css' && $current->args === 'all' ? $original->args : $current->args;
			self::replace_style( $handle, $current, $src, $dependencies, $media, $extra );
		}
		if ( $this->loader_registration && ! wp_script_is( 'skye/css-loader', 'done' ) &&
			( wp_scripts()->registered['skye/css-loader'] ?? null ) === $this->loader_registration ) {
			wp_dequeue_script( 'skye/css-loader' );
		}
		$this->original_styles = array();
		$this->selected_styles = array();
	}

	/**
	 * Require an explicitly audited first-viewport contract from this exact build.
	 *
	 * A generic page can show arbitrary content immediately after its heading.
	 * Literal class discovery alone therefore never enables progressive delivery.
	 * Projects opt in only after testing their own geometry and dynamic states.
	 *
	 * @since 3.0.0
	 * @return bool Whether this public layout accepts the generated contract.
	 */
	public static function supported_header() {
		if ( ! Performance::public_form_asset_request() ) {
			return false;
		}
		$build = Assets\performance_manifest()['critical'] ?? array();
		$contract = apply_filters( 'skye_critical_layout', false, $build );
		return is_array( $build ) && is_array( $contract ) && ! empty( $build['id'] ) && ! empty( $build['build_hash'] ) &&
			( $contract['id'] ?? null ) === $build['id'] && ( $contract['build_hash'] ?? null ) === $build['build_hash'] &&
			( $contract['first_viewport'] ?? null ) === true;
	}

	/**
	 * Convert an opted-in native link in place and retain its no-JavaScript peer.
	 *
	 * The native parser preserves source, media and attributes. Unexpected markup
	 * stays blocking; no regex attribute rewriting or new download URL is used.
	 *
	 * @since 3.0.0
	 * @param string $tag Native serialized stylesheet link.
	 * @param string $handle Registered style handle.
	 * @param string $href Native final URL, retained by the parser.
	 * @param string $media Native media condition, retained unchanged.
	 * @return string Ordinary link or one preload plus its noscript fallback.
	 */
	public function style_tag( $tag, $handle, $href, $media ) {
		if ( ! $this->enabled || wp_styles()->get_data( $handle, 'skye-after-paint' ) !== true ||
			! class_exists( '\WP_HTML_Tag_Processor' ) || ! function_exists( 'get_component' ) ) {
			return $tag;
		}
		$parser = new \WP_HTML_Tag_Processor( $tag );
		if ( ! $parser->next_tag( 'LINK' ) || $parser->get_attribute( 'rel' ) !== 'stylesheet' ||
			$parser->get_attribute( 'href' ) !== $href || $parser->get_attribute( 'media' ) !== $media ) {
			return $tag;
		}
		foreach ( array( 'onload', 'onerror', 'disabled', 'as', 'data-skye-after-paint' ) as $attribute ) {
			if ( $parser->get_attribute( $attribute ) !== null ) {
				return $tag;
			}
		}
		$names = $parser->get_attribute_names_with_prefix( '' );
		if ( ! is_array( $names ) || array_diff( $names, array( 'id', 'rel', 'href', 'type', 'media' ) ) ) {
			return $tag;
		}
		$parser->set_attribute( 'rel', 'preload' );
		$parser->set_attribute( 'as', 'style' );
		$parser->set_attribute( 'data-skye-after-paint', '1' );
		if ( $parser->next_tag() ) {
			return $tag;
		}
		$html = skye_component_html( 'assets', 'stylesheet', array( 'preload_html' => $parser->get_updated_html(), 'stylesheet_html' => $tag ) );
		return $html !== '' ? $html : $tag;
	}

	/**
	 * Read a small trusted build asset, failing closed on incomplete deployment.
	 *
	 * @param string $path Absolute own-theme asset path.
	 * @param int $limit Maximum source size in bytes.
	 * @return string Asset bytes, or an empty string when unavailable.
	 * @since 3.0.0
	 */
	private static function read_asset( $path, $limit ) {
		if ( ! is_file( $path ) || ! is_readable( $path ) || filesize( $path ) > $limit ) {
			return '';
		}
		$content = file_get_contents( $path );
		return is_string( $content ) && strlen( $content ) <= $limit ? $content : '';
	}
}
