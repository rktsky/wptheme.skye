<?php
/** Isolate native WordPress/optional-provider boundaries while executing actual Skye functions and templates. */

if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit;
}

/** Local deterministic request data; no database, network or persistent site state exists. */
class CoreFixture {
	/** @var string Theme source path used by the actual component resolver. */
	public static $theme;
	/** @var string Temporary child-theme override directory. */
	public static $child;
	/** @var array Native query flags set by individual scenarios. */
	public static $flags = array();
	/** @var array Explicit per-entity metadata fixture. */
	public static $meta = array();
	/** @var array Native options fixture. */
	public static $options = array();
	/** @var array Observed native API calls. */
	public static $calls = array();
	/** @var array Native hook callbacks registered by actual theme libraries. */
	public static $hooks = array();
	/** @var array Optional provider denied post identities. */
	public static $denied = array();
	/** @var array Password-protected post identities. */
	public static $passwords = array();
	/** @var array Current user fixture. */
	public static $user;
	/** @var array Published child result supplied to the actual bounded-query helper. */
	public static $children = array();
	/** @var int Current post identity. */
	public static $id = 7;
	/** @var string Stored page template slug. */
	public static $template = '';
	/** @var array Native selectable template eligibility supplied by the fixture. */
	public static $selectable = array( 'templates/pagebuilder.php' => 'Page Builder', 'templates/redirectchild.php' => 'First Child' );
	/** @var string|false Current post type. */
	public static $type = 'post';
	/** @var array Native query variable boundary. */
	public static $query = array();
	/** @var string Ready native widget output supplied at the API boundary. */
	public static $sidebar_html = '';
	/** @var mixed Optional primary-term provider result. */
	public static $primary = false;

	/**
	 * Supply only external API outcomes while the actual theme decides behavior.
	 *
	 * All writes are rejected so a rendering regression cannot silently modify data.
	 *
	 * @since 3.0.0
	 * @param string $name Native API boundary name from an explicit adapter.
	 * @param array $args Actual arguments sent by the production caller.
	 * @return mixed Fixture response appropriate for that native API.
	 * @throws RuntimeException On an unexpected API or any rendering write.
	 */
	public static function call( $name, $args ) {
		self::$calls[] = array( $name, $args );
		if ( str_starts_with( $name, 'is_' ) ) {
			return (bool) ( self::$flags[$name] ?? false );
		}
		switch ( $name ) {
			case 'add_action':
			case 'add_filter':
				self::$hooks[$args[0]][] = $args[1];
				return true;
			case 'apply_filters':
				return $args[1];
			case 'locate_template':
				foreach ( (array) $args[0] as $relative ) {
					foreach ( array( self::$child, self::$theme ) as $directory ) {
						if ( is_file( $directory . '/' . $relative ) ) {
							return $directory . '/' . $relative;
						}
					}
				}
				return '';
			case 'get_the_ID':
			case 'get_queried_object_id':
				return self::$id;
			case 'wp_get_theme':
				return new CoreTheme();
			case 'get_post':
				$post = new WP_Post();
				$post->ID = $args[0];
				return $post;
			case 'get_query_var':
				return self::$query[$args[0]] ?? '';
			case 'get_post_type':
				return self::$type;
			case 'get_page_template_slug':
				return self::$template;
			case 'get_post_meta':
				return self::$meta['post'][$args[0]][$args[1]] ?? '';
			case 'get_user_meta':
				return self::$meta['user'][$args[0]][$args[1]] ?? '';
			case 'get_term_meta':
				return self::$meta['term'][$args[0]][$args[1]] ?? '';
			case 'get_comment_meta':
				return self::$meta['comment'][$args[0]][$args[1]] ?? '';
			case 'get_metadata':
				return self::$meta[$args[0]][$args[1]][$args[2]] ?? '';
			case 'get_option':
				return self::$options[$args[0]] ?? ( $args[1] ?? false );
			case 'get_field':
				if ( 'pageheader' === $args[0] ) {
					return array();
				}
				throw new RuntimeException( 'The raw helper must not invoke ACF formatting.' );
			case 'wp_get_attachment_image_url':
				return 4 === $args[0] ? 'https://example.invalid/image.jpg' : false;
			case 'taxonomy_exists':
				return 'invalid' !== $args[0];
			case 'get_term':
				$term = new WP_Term();
				$term->term_id = $args[0];
				return $term;
			case 'has_term':
				return 23 === $args[0];
			case 'wp_get_post_terms':
				return 'error' === $args[1] ? new WP_Error() : array( (object) array( 'term_id' => 23 ) );
			case 'get_the_excerpt':
				return 'Native excerpt';
			case 'wp_trim_words':
				return $args[0];
			case 'post_password_required':
				return in_array( $args[0], self::$passwords, true );
			case 'members_can_current_user_view_post':
				return ! in_array( $args[0], self::$denied, true );
			case 'wp_get_current_user':
				return self::$user;
			case 'wp_parse_args':
				return array_merge( $args[1], $args[0] );
			case 'wp_nav_menu':
				return '<ul><li>Native menu for ' . ( $args[0]['theme_location'] ?? '' ) . '</li></ul>';
			case 'wp_safe_redirect':
				return true;
			case 'get_posts':
				return self::$children;
			case 'get_permalink':
				return 'https://example.invalid/p/' . $args[0];
			case 'get_the_title':
				return 'Fixture title';
			case 'get_the_date':
				return '03.11.2026';
			case 'get_post_time':
				return '2026-11-03T12:00:00+00:00';
			case 'get_post_field':
				return 5;
			case 'get_the_author_meta':
				return 'Fixture Author';
			case 'get_the_post_thumbnail':
				return '<img src="https://example.invalid/thumb.jpg" alt="">';
			case 'get_theme_mod':
				return false;
			case 'get_bloginfo':
				return 'Fixture Site';
			case 'home_url':
				return 'https://example.invalid/';
			case '__':
			case 'esc_html__':
			case 'esc_attr__':
				return $args[0];
			case 'esc_html':
			case 'esc_attr':
				return htmlspecialchars( (string) $args[0], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
			case 'esc_url':
				return preg_match( '~^(?:https?://|/)~', $args[0] ) ? htmlspecialchars( $args[0], ENT_QUOTES, 'UTF-8' ) : '';
			case 'wp_strip_all_tags':
				return strip_tags( $args[0] );
			case 'esc_html_e':
			case 'esc_attr_e':
				echo htmlspecialchars( $args[0], ENT_QUOTES, 'UTF-8' );
				return null;
			case 'wp_kses_post':
				return strip_tags( $args[0], '<p><a><strong><em>' );
			case 'have_posts':
			case 'comments_open':
			case 'get_comments_number':
				return false;
			case 'dynamic_sidebar':
				echo self::$sidebar_html;
				return true;
			case 'the_posts_navigation':
			case 'the_post_navigation':
			case 'the_content':
				echo 'Native content';
				return null;
			case 'wp_link_pages':
				return '';
			case 'get_search_form':
			case 'register_post_type':
			case 'status_header':
			case 'nocache_headers':
			case 'register_taxonomy':
				return null;
			case 'update_field':
			case 'update_post_meta':
			case 'delete_post_meta':
			case 'set_transient':
			case 'delete_transient':
				throw new RuntimeException( 'Unexpected rendering write: ' . $name );
		}
		throw new RuntimeException( 'Unexpected native API boundary: ' . $name );
	}
}

/** Native identity stand-in needed only for explicit typed metadata scenarios. */
class WP_Post {
	/** @var int Native identity. */
	public $ID = 7;
	/** @var string Publication state used by explicit archive integrations. */
	public $post_status = 'publish';
}
/** Native user stand-in; no authentication or persistent account is created. */
class WP_User {
	/** @var int Native identity. */
	public $ID = 8;
	/** @var string[] Native role identifiers for the legacy compatibility gate. */
	public $roles = array();
	/** @var bool Whether the synthetic user is authenticated. */
	public $authenticated = false;
	/**
	 * Return the explicit authentication fixture for the actual legacy gate.
	 *
	 * @since 3.0.0
	 * @return bool Fixture account existence.
	 */
	public function exists() {
		return $this->authenticated;
	}
}
/** Native term identity stand-in. */
class WP_Term {
	/** @var int Native term identity. */
	public $term_id = 9;
}
/** Native comment identity stand-in. */
class WP_Comment {
	/** @var int Native comment identity. */
	public $comment_ID = 10;
}
/** Native-style error boundary; it deliberately cannot be indexed as an array. */
class WP_Error {
}

/**
 * Check a native-style error value without hiding failures in the real helper.
 *
 * @since 3.0.0
 * @param mixed $value Actual helper response.
 * @return bool Whether it is the synthetic native error.
 */
function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

/**
 * Supply the isolated native add_action boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function add_action( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native add_filter boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function add_filter( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native apply_filters boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function apply_filters( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native locate_template boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function locate_template( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native get_the_ID boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function get_the_ID( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native get_queried_object_id boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function get_queried_object_id( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native get_post_type boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function get_post_type( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native get_page_template_slug boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function get_page_template_slug( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native get_post_meta boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function get_post_meta( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native get_user_meta boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function get_user_meta( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native get_term_meta boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function get_term_meta( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native get_comment_meta boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function get_comment_meta( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native get_metadata boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function get_metadata( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native get_option boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function get_option( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native get_field boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
if ( ! defined( 'SKYE_CORE_WITHOUT_ACF' ) ) {
	/**
	 * Supply the optional ACF boundary only in the provider-present scenario.
	 *
	 * The raw helper must not require or invoke this formatting API.
	 *
	 * @since 3.0.0
	 * @param mixed ...$args Actual optional-provider arguments.
	 * @return mixed Explicit fixture result.
	 */
	function get_field( ...$args ) {
		return CoreFixture::call( __FUNCTION__, $args );
	}
}

/**
 * Supply the isolated native wp_get_attachment_image_url boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function wp_get_attachment_image_url( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native taxonomy_exists boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function taxonomy_exists( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native wp_get_post_terms boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function wp_get_post_terms( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native get_the_excerpt boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function get_the_excerpt( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native wp_trim_words boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function wp_trim_words( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native post_password_required boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function post_password_required( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native members_can_current_user_view_post boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function members_can_current_user_view_post( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native wp_get_current_user boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function wp_get_current_user( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native wp_parse_args boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function wp_parse_args( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native wp_nav_menu boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function wp_nav_menu( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native get_posts boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function get_posts( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native get_permalink boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function get_permalink( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native get_the_title boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function get_the_title( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native get_the_date boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function get_the_date( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native get_post_time boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function get_post_time( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native get_post_field boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function get_post_field( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native get_the_author_meta boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function get_the_author_meta( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native get_the_post_thumbnail boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function get_the_post_thumbnail( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native get_theme_mod boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function get_theme_mod( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native get_bloginfo boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function get_bloginfo( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native home_url boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function home_url( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native __ boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function __( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native esc_html__ boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function esc_html__( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native esc_attr__ boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function esc_attr__( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native esc_html boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function esc_html( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native esc_attr boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function esc_attr( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native esc_url boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function esc_url( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native wp_strip_all_tags boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function wp_strip_all_tags( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native esc_html_e boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function esc_html_e( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native esc_attr_e boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function esc_attr_e( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native wp_kses_post boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function wp_kses_post( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native have_posts boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function have_posts( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native comments_open boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function comments_open( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native get_comments_number boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function get_comments_number( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native the_posts_navigation boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function the_posts_navigation( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native the_post_navigation boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function the_post_navigation( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native get_search_form boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function get_search_form( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native register_post_type boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function register_post_type( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native register_taxonomy boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function register_taxonomy( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native update_field boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function update_field( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native update_post_meta boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function update_post_meta( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native delete_post_meta boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function delete_post_meta( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native set_transient boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function set_transient( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native delete_transient boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function delete_transient( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native is_404 boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function is_404( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native is_search boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function is_search( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native is_home boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function is_home( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native is_archive boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function is_archive( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native is_page boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function is_page( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native is_singular boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function is_singular( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native is_admin boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function is_admin( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native is_preview boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function is_preview( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native is_feed boundary to actual production callers.
 *
 * The fixture records calls and never performs an external or persistent action.
 *
 * @since 3.0.0
 * @param mixed ...$args Arguments supplied by the actual production function.
 * @return mixed Explicit native-boundary fixture response.
 */
function is_feed( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply native content output without performing a site request.
 *
 * @since 3.0.0
 * @return void
 */
function the_content() {
	CoreFixture::call( __FUNCTION__, array() );
}

/**
 * Record pagination delegation with an explicit empty API outcome, without copying Core pagination logic.
 *
 * Real pagination behavior belongs to the separate native WordPress tests; this
 * fixture only proves the actual component calls the expected public boundary.
 *
 * @since 3.5.0
 * @param mixed ...$args Actual component-supplied native pagination arguments.
 * @return string Empty isolated API response, never a synthetic pagination renderer.
 */
function wp_link_pages( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/** Minimal native-query boundary used only to observe the legacy denial response. */
class CoreQuery {
	/**
	 * Observe the native not-found transition used by the actual permission gate.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function set_404() {
		CoreFixture::$flags = array( 'is_404' => true );
	}
}

/**
 * Observe the native wp_safe_redirect response boundary without emitting a real HTTP response.
 *
 * @since 3.0.0
 * @param mixed ...$args Actual native response arguments.
 * @return mixed Explicit response fixture result.
 */
function wp_safe_redirect( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Observe the native status_header response boundary without emitting a real HTTP response.
 *
 * @since 3.0.0
 * @param mixed ...$args Actual native response arguments.
 * @return mixed Explicit response fixture result.
 */
function status_header( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Observe the native nocache_headers response boundary without emitting a real HTTP response.
 *
 * @since 3.0.0
 * @param mixed ...$args Actual native response arguments.
 * @return mixed Explicit response fixture result.
 */
function nocache_headers( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/** Optional-provider boundary activated only after the absent-provider case. */
class CorePrimaryTerm {
	/**
	 * Accept the real provider constructor contract without querying a site.
	 *
	 * @since 3.0.0
	 * @param string $taxonomy Requested native taxonomy.
	 * @param int $post_id Requested native post identity.
	 * @return void
	 */
	public function __construct( $taxonomy, $post_id ) {
	}
	/**
	 * Supply a controlled provider result to the real fallback/error handling.
	 *
	 * @since 3.0.0
	 * @return mixed Primary-term fixture result.
	 */
	public function get_primary_term() {
		return CoreFixture::$primary;
	}
}

/**
 * Supply the isolated native get_term boundary for primary-term verification.
 *
 * @since 3.0.0
 * @param mixed ...$args Actual native term arguments.
 * @return mixed Controlled native term response.
 */
function get_term( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native has_term boundary for primary-term verification.
 *
 * @since 3.0.0
 * @param mixed ...$args Actual native term arguments.
 * @return mixed Controlled native term response.
 */
function has_term( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/** Native theme eligibility boundary; production still decides the routing. */
class CoreTheme {
	/**
	 * Supply selectable template eligibility without scanning another code tree.
	 *
	 * @since 3.0.0
	 * @param WP_Post|null $post Native record used by the real template API.
	 * @param string $post_type Native fallback type.
	 * @return string[] Explicit registered template-name map.
	 */
	public function get_page_templates( $post = null, $post_type = 'page' ) {
		return CoreFixture::$selectable;
	}
}

/**
 * Supply the isolated native wp_get_theme boundary to actual routing code.
 *
 * @since 3.0.0
 * @param mixed ...$args Actual native routing arguments.
 * @return mixed Controlled native routing response.
 */
function wp_get_theme( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native get_post boundary to actual routing code.
 *
 * @since 3.0.0
 * @param mixed ...$args Actual native routing arguments.
 * @return mixed Controlled native routing response.
 */
function get_post( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native is_post_type_archive boundary to actual query routing.
 *
 * @since 3.0.0
 * @param mixed ...$args Actual native query arguments.
 * @return mixed Controlled native query response.
 */
function is_post_type_archive( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native get_query_var boundary to actual query routing.
 *
 * @since 3.0.0
 * @param mixed ...$args Actual native query arguments.
 * @return mixed Controlled native query response.
 */
function get_query_var( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native is_active_sidebar boundary for actual footer composition.
 *
 * @since 3.0.0
 * @param mixed ...$args Actual native sidebar arguments.
 * @return mixed Controlled native sidebar response.
 */
function is_active_sidebar( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}

/**
 * Supply the isolated native dynamic_sidebar boundary for actual footer composition.
 *
 * @since 3.0.0
 * @param mixed ...$args Actual native sidebar arguments.
 * @return mixed Controlled native sidebar response.
 */
function dynamic_sidebar( ...$args ) {
	return CoreFixture::call( __FUNCTION__, $args );
}
