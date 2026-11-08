<?php
/** Keep retained inactive drafts out of native public excerpts, REST responses and feeds. */

namespace Rocketsky\Skye\Packages;

defined( 'ABSPATH' ) || exit;

/** Adapt public producers through native hooks without changing stored posts or arbitrary content filters. */
final class SourcePublicContext {

	/** @var array<int,array{request:\WP_REST_Request,native:bool}> Nested native REST callback scopes. */
	private static $requests = array();

	/** @var \WeakMap<object,array>|null Native replacement values scoped to their exact per-call argument object. */
	private static $replacement_inputs;

	/**
	 * Register public boundaries without a global the_content rewrite or provider persistence.
	 *
	 * @since 3.5.0
	 * @return void The excerpt producer is replaced only after ordinary application setup.
	 */
	public function __construct() {
		add_action( 'wp_loaded', array( self::class, 'install_excerpt' ), PHP_INT_MAX );
		add_action( 'rest_api_init', array( self::class, 'register_rest' ) );
		add_filter( 'rest_request_before_callbacks', array( self::class, 'before_rest' ), 0, 3 );
		add_filter( 'rest_request_after_callbacks', array( self::class, 'after_rest' ), PHP_INT_MAX, 3 );
		add_filter( 'acf/rest/get_fields', array( self::class, 'rest_fields' ), 10, 3 );
		add_filter( 'the_content_feed', array( self::class, 'feed' ), 0 );
		add_filter( 'wpseo_replacements', array( self::class, 'capture_replacements' ), PHP_INT_MIN, 2 );
		add_filter( 'wpseo_replacements', array( self::class, 'replacements' ), 10, 2 );
		if ( did_action( 'wp_loaded' ) ) {
			self::install_excerpt();
		}
		if ( did_action( 'rest_api_init' ) ) {
			self::register_rest();
		}
	}

	/**
	 * Replace exactly the original Core producer while retaining its existing priority and order.
	 *
	 * Public hook records are read but never edited. All priority-ten callbacks are
	 * removed and added through Core APIs in their original sequence; absent,
	 * duplicate, modified or currently executing producers remain untouched.
	 *
	 * @since 3.5.0
	 * @return bool Whether the owned wrapper is installed or was already present.
	 */
	public static function install_excerpt() {
		global $wp_filter;
		if ( doing_filter( 'get_the_excerpt' ) ) {
			return false;
		}
		if ( has_filter( 'get_the_excerpt', array( self::class, 'excerpt' ) ) === 10 ) {
			return true;
		}
		$hook = $wp_filter['get_the_excerpt'] ?? null;
		if ( ! $hook instanceof \WP_Hook ) {
			return false;
		}
		$records = $hook->callbacks[10] ?? array();
		$originals = 0;
		foreach ( $hook->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $record ) {
				if ( ( $record['function'] ?? null ) === 'wp_trim_excerpt' ) {
					if ( $priority !== 10 || ( $record['accepted_args'] ?? null ) !== 2 ) {
						return false;
					}
					$originals++;
				}
			}
		}
		if ( $originals !== 1 ) {
			return false;
		}
		foreach ( $records as $record ) {
			remove_filter( 'get_the_excerpt', $record['function'], 10 );
		}
		foreach ( $records as $record ) {
			$callback = $record['function'] === 'wp_trim_excerpt' ? array( self::class, 'excerpt' ) : $record['function'];
			add_filter( 'get_the_excerpt', $callback, 10, $record['accepted_args'] );
		}
		return true;
	}

	/**
	 * Resolve a known builder's explicit semantic HTML, failing closed for unsafe public content.
	 *
	 * The same project projection serves Yoast image analysis and public exports.
	 * Unlike the analysis hook's native fallback, a refused builder can never return
	 * the retained editor body. No layout template or stored post is modified.
	 *
	 * @since 3.5.0
	 * @param \WP_Post $post Exact native document.
	 * @return string|null Builder HTML or an empty refusal; null for editor/unmanaged content.
	 */
	public static function projection( \WP_Post $post ) {
		if ( ContentSources::analysis_source( $post ) !== 'builder' ) {
			return null;
		}
		if ( $post->ID <= 0 || $post->post_password !== '' || ! is_post_publicly_viewable( $post ) ||
			skye_content_access_state( $post->ID ) !== 'allowed' ) {
			return '';
		}
		try {
			return YoastAnalysis::content( $post->post_content, $post );
		} catch ( \Throwable $error ) {
			return '';
		}
	}

	/**
	 * Delegate to Core once, substituting only automatic inactive-editor excerpt input.
	 *
	 * Manual excerpts and nonempty earlier filter decisions retain native behavior.
	 * Core receives a clone with active builder HTML, so its existing formatting,
	 * length, more and excerpt filters run once without evaluating inactive shortcodes.
	 *
	 * @since 3.5.0
	 * @param string $excerpt Current native/earlier excerpt value.
	 * @param \WP_Post|object|int|null $post Exact Core excerpt owner.
	 * @return string The original native producer's result for the active input.
	 */
	public static function excerpt( $excerpt = '', $post = null ) {
		$document = get_post( $post );
		if ( ! is_string( $excerpt ) || trim( $excerpt ) !== '' || ! $document instanceof \WP_Post || trim( $document->post_excerpt ) !== '' ) {
			return wp_trim_excerpt( $excerpt, $post );
		}
		$current = end( self::$requests );
		if ( $current && $current['native'] && $current['request']->get_param( 'context' ) === 'edit' && current_user_can( 'edit_post', $document->ID ) ) {
			return wp_trim_excerpt( $excerpt, $post );
		}
		$projection = self::projection( $document );
		if ( $projection === null ) {
			return wp_trim_excerpt( $excerpt, $post );
		}
		$active = clone $document;
		$active->post_content = $projection;
		return wp_trim_excerpt( $excerpt, $active );
	}

	/**
	 * Attach final response adaptation only to original Core post controllers.
	 *
	 * @since 3.5.0
	 * @return void Custom controllers and non-post resources retain their own contracts.
	 */
	public static function register_rest() {
		foreach ( get_post_types( array( 'show_in_rest' => true ), 'objects' ) as $type ) {
			$controller = $type->get_rest_controller();
			if ( $controller && get_class( $controller ) === 'WP_REST_Posts_Controller' ) {
				add_filter( 'rest_prepare_' . $type->name, array( self::class, 'rest_response' ), 0, 3 );
			}
		}
	}

	/**
	 * Bind ACF's pre-hydration field filter to the actual nested native read request.
	 *
	 * @since 3.5.0
	 * @param mixed $response Earlier native dispatch decision, preserved exactly.
	 * @param array $handler Actual matched route handler.
	 * @param \WP_REST_Request $request Current native request identity.
	 * @return mixed The unchanged earlier dispatch result.
	 */
	public static function before_rest( $response, $handler, $request ) {
		$callback = $handler['callback'] ?? null;
		$native = is_array( $callback ) && isset( $callback[0], $callback[1] ) && is_object( $callback[0] ) &&
			get_class( $callback[0] ) === 'WP_REST_Posts_Controller' && in_array( $callback[1], array( 'get_item', 'get_items' ), true );
		self::$requests[] = array( 'request' => $request, 'native' => $native );
		return $response;
	}

	/**
	 * Release only the matching request scope after native callbacks, including permission errors.
	 *
	 * @since 3.5.0
	 * @param mixed $response Original native response/error.
	 * @param array $handler Matched handler; no callback registry is changed.
	 * @param \WP_REST_Request $request Completed native request identity.
	 * @return mixed The unchanged native response.
	 */
	public static function after_rest( $response, $handler, $request ) {
		$current = array_pop( self::$requests );
		if ( ! $current || $current['request'] !== $request ) {
			self::$requests = array();
		}
		return $response;
	}

	/**
	 * Test the exact public REST read context without deriving it from URL/Host input.
	 *
	 * @since 3.5.0
	 * @param \WP_REST_Request $request Actual native request.
	 * @return bool Whether this is an ordinary public representation, including authenticated view reads.
	 */
	private static function public_rest( \WP_REST_Request $request ) {
		return in_array( $request->get_method(), array( 'GET', 'HEAD' ), true ) &&
			in_array( $request->get_param( 'context' ) ?: 'view', array( 'view', 'embed' ), true );
	}

	/**
	 * Accept an explicit canonical native ID without allowing zero to select the ambient post.
	 *
	 * @since 3.5.0
	 * @param mixed $value Actual provider resource or replacement identity.
	 * @return int Positive canonical native ID, or zero for uncertain attribution.
	 */
	private static function post_id( $value ) {
		if ( ! is_int( $value ) && ! is_string( $value ) ) {
			return 0;
		}
		return preg_match( '/^[1-9][0-9]*$/D', (string) $value ) && (string) (int) $value === (string) $value ? (int) $value : 0;
	}

	/**
	 * Remove only the inactive exact builder root before original ACF loads values or embed links.
	 *
	 * @since 3.5.0
	 * @param array $fields Original REST-enabled fields for one native group.
	 * @param array $resource Original ACF type/sub_type/id attribution.
	 * @param string $method Original ACF request method.
	 * @return array Original ordered fields with at most the verified inactive root omitted.
	 */
	public static function rest_fields( $fields, $resource, $method ) {
		$current = end( self::$requests );
		if ( ! is_array( $fields ) || ! is_array( $resource ) || ! $current || ! $current['native'] || ! self::public_rest( $current['request'] ) ||
			! in_array( $method, array( 'GET', 'HEAD' ), true ) || ( $resource['type'] ?? null ) !== 'post' ) {
			return $fields;
		}
		$id = self::post_id( $resource['id'] ?? null );
		if ( ! $id ) {
			return $fields;
		}
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || ( $resource['sub_type'] ?? null ) !== $post->post_type ) {
			return $fields;
		}
		$source = ContentSources::analysis_source( $post );
		$restricted = $post->post_password !== '' || ! is_post_publicly_viewable( $post ) || skye_content_access_state( $post->ID ) !== 'allowed';
		if ( $source !== 'editor' && ! ( $source === 'builder' && $restricted ) ) {
			return $fields;
		}
		$root = ContentSources::field( $post );
		if ( ! $root ) {
			return $fields;
		}
		foreach ( $fields as $index => $field ) {
			if ( is_array( $field ) && ( $field['key'] ?? null ) === $root['key'] && ( $field['name'] ?? null ) === $root['name'] &&
				( $field['type'] ?? null ) === 'flexible_content' ) {
				unset( $fields[$index] );
			}
		}
		return $fields;
	}

	/**
	 * Replace only an existing public builder representation, preserving Core's raw edit response.
	 *
	 * Semantic HTML is exported directly; running arbitrary content filters twice
	 * would duplicate shortcode/provider effects. Native ACF root suppression is
	 * performed before hydration by rest_fields(), not by deleting unrelated links.
	 *
	 * @since 3.5.0
	 * @param \WP_REST_Response $response Original Core post response.
	 * @param \WP_Post $post Exact Core response owner, never the ambient loop post.
	 * @param \WP_REST_Request $request Exact native representation request.
	 * @return \WP_REST_Response Same response/status/field selection with active semantic content.
	 */
	public static function rest_response( $response, $post, $request ) {
		$current = end( self::$requests );
		if ( ! $response instanceof \WP_REST_Response || ! $post instanceof \WP_Post ||
			! $request instanceof \WP_REST_Request || ! self::public_rest( $request ) ||
			$request->get_method() === 'HEAD' || $response->get_status() !== 200 ||
			! $current || ! $current['native'] || $current['request'] !== $request ) {
			return $response;
		}
		$data = $response->get_data();
		if ( ! is_array( $data ) || ! is_array( $data['content'] ?? null ) || ! array_key_exists( 'rendered', $data['content'] ) ) {
			return $response;
		}
		$projection = self::projection( $post );
		if ( $projection === null ) {
			return $response;
		}
		$data['content']['rendered'] = $projection;
		$response->set_data( $data );
		return $response;
	}

	/**
	 * Export active semantic HTML only for the actual native feed loop item.
	 *
	 * @since 3.5.0
	 * @param string $content Existing native feed content, unchanged outside the feed loop.
	 * @return string Active builder projection or native content; no template/formatter is run again.
	 */
	public static function feed( $content ) {
		global $post;
		if ( ! is_feed() || ! in_the_loop() || ! $post instanceof \WP_Post || get_the_ID() !== $post->ID ) {
			return $content;
		}
		$projection = self::projection( $post );
		return $projection === null ? $content : str_replace( ']]>', ']]&gt;', $projection );
	}

	/**
	 * Match the reviewed Yoast 28.8 automatic variable producer without executing a second filter pipeline.
	 *
	 * @since 3.5.0
	 * @param string $content Already shortcode-normalized native replacement input.
	 * @return string|null Original automatic variable value, including its native character boundary.
	 */
	private static function replacement_excerpt( $content ) {
		if ( $content === '' ) {
			return null;
		}
		$content = wp_strip_all_tags( strip_shortcodes( $content ) );
		$limit = get_locale() === 'ja' ? 80 : 156;
		if ( mb_strlen( $content ) <= $limit ) {
			return $content;
		}
		$value = wp_html_excerpt( $content, $limit );
		$space = strrpos( $value, ' ' );
		return $space ? substr( $value, 0, $space ) : $value;
	}

	/**
	 * Capture the native variable output before ordinary project replacements, once per argument object.
	 *
	 * A weak key does not retain unused provider contexts. The later adapter consumes
	 * this observation and preserves any subsequently changed foreign value instead
	 * of guessing a future provider's automatic excerpt algorithm.
	 *
	 * @since 3.5.0
	 * @param mixed $replacements Native variable results.
	 * @param mixed $arguments Original per-call provider argument object.
	 * @return mixed The untouched native results.
	 */
	public static function capture_replacements( $replacements, $arguments ) {
		if ( is_array( $replacements ) && is_object( $arguments ) && array_key_exists( '%%excerpt%%', $replacements ) ) {
			self::$replacement_inputs = self::$replacement_inputs ?? new \WeakMap();
			self::$replacement_inputs[$arguments] = $replacements;
		}
		return $replacements;
	}

	/**
	 * Replace only an actually requested native automatic excerpt variable for a known builder.
	 *
	 * Manual excerpts, literal descriptions, excerpt_only, other variables and any
	 * earlier changed replacement remain unchanged. Unknown provider versions get
	 * an empty automatic builder value rather than publishing the inactive editor.
	 *
	 * @since 3.5.0
	 * @param array $replacements Original generated variables after earlier filters.
	 * @param object $arguments Exact normalized Yoast variable context.
	 * @return array Same variables with at most the verified automatic excerpt replaced.
	 */
	public static function replacements( $replacements, $arguments ) {
		$native = is_object( $arguments ) && self::$replacement_inputs !== null ? ( self::$replacement_inputs[$arguments] ?? null ) : null;
		if ( is_object( $arguments ) && self::$replacement_inputs !== null ) {
			unset( self::$replacement_inputs[$arguments] );
		}
		if ( ! is_array( $replacements ) || ! is_array( $native ) ||
			! array_key_exists( '%%excerpt%%', $replacements ) || ! is_object( $arguments ) ||
			! is_string( $arguments->post_content ?? null ) || ( $arguments->post_excerpt ?? null ) !== '' ||
			$replacements['%%excerpt%%'] !== $native['%%excerpt%%'] ) {
			return $replacements;
		}
		$id = self::post_id( $arguments->ID ?? null );
		if ( ! $id ) {
			return $replacements;
		}
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || $post->post_excerpt !== '' ) {
			return $replacements;
		}
		$body = $post->post_content;
		if ( $arguments->post_content !== $body ) {
			if ( ! function_exists( 'YoastSEO' ) ) {
				return $replacements;
			}
			try {
				$body = YoastSEO()->helpers->string->strip_shortcode( $body );
			} catch ( \Throwable $error ) {
				return $replacements;
			}
		}
		if ( $arguments->post_content !== $body ) {
			return $replacements;
		}
		$projection = self::projection( $post );
		if ( $projection !== null ) {
			$replacements['%%excerpt%%'] = defined( 'WPSEO_VERSION' ) && WPSEO_VERSION === '28.8' ? ( self::replacement_excerpt( $projection ) ?? '' ) : '';
		}
		return $replacements;
	}
}
