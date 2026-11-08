<?php
/** Preserve native editor and flexible-builder drafts while selecting one explicit public source. */

namespace Rocketsky\Skye\Packages;

use Rocketsky\Skye\Assets;
use Rocketsky\Skye\Packages\Assets\PathIdentity;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/assets/PathIdentity.php';

/** Bind the native source chooser, ACF validation window and public rendering to one post identity. */
final class ContentSources {
	/** @var string Private independent metadata; legacy content is never migrated on read. */
	public const META_KEY = '_skye_content_source';
	/** @var array|null Already restricted native values during one inactive-builder validation window. */
	private static $snapshot = null;
	/** @var int Native validation nesting depth, so reentrant validation cannot restore an outer window early. */
	private static $depth = 0;

	/**
	 * Register only native editing and ACF save hooks, preserving both storage owners.
	 *
	 * Core continues saving post_content. ACF continues saving its original fields;
	 * this package persists only its independent source choice after that save.
	 *
	 * @since 3.5.0
	 * @return void No frontend content filter, data migration or vendor patch is installed.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( self::class, 'metabox' ), 5, 2 );
		add_action( 'acf/add_meta_boxes', array( self::class, 'position_builder' ), 20, 2 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ), 20 );
		add_action( 'acf/validate_save_post', array( self::class, 'begin_validation' ), 4 );
		add_action( 'acf/validate_save_post', array( self::class, 'finish_validation' ), 6 );
		add_action( 'acf/save_post', array( self::class, 'save' ), 20 );
		add_action( 'shutdown', array( self::class, 'restore_interrupted' ), 0 );
	}

	/**
	 * Resolve an explicit source or retain the historical template decision without hydrating ACF.
	 *
	 * Ordinary documents remain native editor pages. The existing builder template
	 * keeps builder mode until a user makes an authenticated explicit selection.
	 *
	 * @since 3.5.0
	 * @param int|\WP_Post $post Native document or identity; zero uses the current loop.
	 * @return string Exactly builder or editor.
	 */
	public static function mode( $post = 0 ) {
		$post = get_post( $post ?: get_the_ID() );
		if ( ! $post ) {
			return 'editor';
		}
		$source = get_post_meta( $post->ID, self::META_KEY, true );
		if ( in_array( $source, array( 'builder', 'editor' ), true ) ) {
			return $source;
		}
		return get_page_template_slug( $post ) === 'templates/pagebuilder.php' ? 'builder' : 'editor';
	}

	/**
	 * Read a bounded project field identity without loading values or a layout catalog.
	 *
	 * A project may supply one exact field key; otherwise the applicable native
	 * groups must contain one unambiguous top-level pagebuilder field.
	 *
	 * @since 3.5.0
	 * @param \WP_Post $post Exact native document.
	 * @return array{name:string,key:string}|array Empty for unknown configuration.
	 */
	public static function configuration( \WP_Post $post ) {
		try {
			$config = apply_filters( 'skye_content_sources', array( 'name' => 'pagebuilder', 'key' => '' ), $post );
		} catch ( \Throwable $error ) {
			return array();
		}
		if ( ! is_array( $config ) || ! is_string( $config['name'] ?? null ) || ! is_string( $config['key'] ?? null ) ||
			! preg_match( '/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/D', $config['name'] ) ||
			( $config['key'] !== '' && ! preg_match( '/^field_[a-zA-Z0-9_-]{1,120}$/D', $config['key'] ) ) ) {
			return array();
		}
		return array( 'name' => $config['name'], 'key' => $config['key'] );
	}

	/**
	 * Require the real flexible-content implementation rather than a PRO upsell descriptor.
	 *
	 * Free ACF can register the same type name as a plain information object. Such
	 * a descriptor cannot render or validate the retained builder draft safely.
	 *
	 * @since 3.5.0
	 * @return bool Whether the public registry exposes the exact original native field instance.
	 */
	private static function provider_supported() {
		if ( ! function_exists( 'acf_get_field_type' ) ) {
			return false;
		}
		$provider = acf_get_field_type( 'flexible_content' );
		return $provider instanceof \acf_field && get_class( $provider ) === 'acf_field_flexible_content' &&
			$provider->name === 'flexible_content';
	}

	/**
	 * Resolve only an applicable unambiguous root field, never a same-name global alias.
	 *
	 * Whole-builder conditional logic or disabled controls would remove unsaved
	 * values from native FormData. Such profiles retain the original interfaces.
	 *
	 * @since 3.5.0
	 * @param \WP_Post $post Exact current document whose native field locations apply.
	 * @return array Valid original flexible-content definition, or an empty refusal.
	 */
	public static function field( \WP_Post $post ) {
		if ( ! self::provider_supported() || ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ||
			! post_type_supports( $post->post_type, 'editor' ) ) {
			return array();
		}
		$config = self::configuration( $post );
		if ( ! $config ) {
			return array();
		}
		$matches = array();
		foreach ( acf_get_field_groups( array( 'post_id' => $post->ID ) ) as $group ) {
			foreach ( acf_get_fields( $group ) ?: array() as $field ) {
				if ( ( $field['name'] ?? null ) === $config['name'] && is_string( $field['key'] ?? null ) ) {
					$matches[$field['key']] = $field;
				}
			}
		}
		if ( count( $matches ) !== 1 ) {
			return array();
		}
		$field = reset( $matches );
		if ( ( $field['type'] ?? null ) !== 'flexible_content' || ! preg_match( '/^field_[a-zA-Z0-9_-]{1,120}$/D', $field['key'] ) ||
			( $config['key'] !== '' && $config['key'] !== $field['key'] ) || ! empty( $field['conditional_logic'] ) ||
			! empty( $field['disabled'] ) || ! empty( $field['readonly'] ) ) {
			return array();
		}
		return $field;
	}

	/**
	 * Preserve explicit saved choices independently of provider availability.
	 *
	 * An explicit builder may never expose its retained editor after a provider
	 * failure. Unmanaged ordinary pages avoid field-group discovery entirely;
	 * only legacy builder templates or explicit project profiles need that lookup.
	 *
	 * @since 3.5.0
	 * @param \WP_Post $post Exact native document selected by the caller.
	 * @return string|null Explicit/known builder or editor, or null for the unmanaged native path.
	 */
	public static function analysis_source( \WP_Post $post ) {
		$source = get_post_meta( $post->ID, self::META_KEY, true );
		if ( in_array( $source, array( 'builder', 'editor' ), true ) ) {
			return $source;
		}
		if ( get_page_template_slug( $post ) !== 'templates/pagebuilder.php' && false === has_filter( 'skye_content_sources' ) ) {
			return null;
		}
		return self::field( $post ) ? self::mode( $post ) : null;
	}

	/**
	 * Verify the reviewed original validation window through Core's public hook records.
	 *
	 * Original menu/widget validators are registered globally but are inert for
	 * a real post form. Their active contexts, unknown releases and extra window
	 * callbacks retain complete native validation and both visible interfaces.
	 * A PRO field descriptor without its original implementation is insufficient.
	 *
	 * @since 3.5.0
	 * @return bool Whether the exact original ACF 6.8.11 post window is known and other contexts are inactive.
	 */
	public static function validation_supported() {
		if ( ! defined( 'ACF_VERSION' ) || ACF_VERSION !== '6.8.11' || ! self::provider_supported() || ! empty( $_POST['menu-item-acf'] ) ||
			isset( $_POST['_acf_widget_id'] ) || isset( $_GET['meta-box-loader'] ) ) {
			return false;
		}
		global $wp_filter;
		$hook = $wp_filter['acf/validate_save_post'] ?? null;
		if ( ! $hook instanceof \WP_Hook ) {
			return false;
		}
		$native = array( 'acf_validation' => 0, 'acf_form_nav_menu' => 0, 'acf_form_widget' => 0 );
		foreach ( $hook->callbacks as $priority => $callbacks ) {
			if ( $priority < 4 || $priority > 6 ) {
				continue;
			}
			foreach ( $callbacks as $record ) {
				$callback = $record['function'];
				if ( (int) $priority === 4 && $callback === array( self::class, 'begin_validation' ) ||
					(int) $priority === 6 && $callback === array( self::class, 'finish_validation' ) ) {
					continue;
				}
				if ( (int) $priority === 5 && is_array( $callback ) && is_object( $callback[0] ?? null ) &&
					array_key_exists( get_class( $callback[0] ), $native ) && ( $callback[1] ?? null ) === 'acf_validate_save_post' ) {
					$native[get_class( $callback[0] )]++;
					continue;
				}
				return false;
			}
		}
		return $native === array( 'acf_validation' => 1, 'acf_form_nav_menu' => 1, 'acf_form_widget' => 1 );
	}

	/**
	 * Share one authorized ready editor identity with visibility and analysis adapters.
	 *
	 * @since 3.5.0
	 * @param \WP_Post $post Exact current native document.
	 * @return array{fieldKey:string,source:string,postId:int}|array Bounded UI context or native fallback.
	 */
	public static function editor_context( \WP_Post $post ) {
		if ( ! self::validation_supported() || ! current_user_can( 'edit_post', $post->ID ) || ! self::position_supported( $post ) ) {
			return array();
		}
		$field = self::field( $post );
		return $field ? array( 'fieldKey' => $field['key'], 'source' => self::mode( $post ), 'postId' => $post->ID ) : array();
	}

	/**
	 * Add a standard metabox only when its actual builder field can be identified safely.
	 *
	 * @since 3.5.0
	 * @param string $post_type Native registration context.
	 * @param mixed $post Current Core document, or an unsupported caller value.
	 * @return void No field definitions, stored values or meta-box order preferences are changed.
	 */
	public static function metabox( $post_type, $post ) {
		if ( ! $post instanceof \WP_Post || $post->post_type !== $post_type || ! current_user_can( 'edit_post', $post->ID ) || ! self::field( $post ) ) {
			return;
		}
		$context = defined( 'ACF_VERSION' ) && ACF_VERSION === '6.8.11' ? 'acf_after_title' : 'normal';
		add_meta_box( 'skye-content-source', __( 'Inhalt bearbeiten mit', 'skye' ), array( self::class, 'render_metabox' ), $post_type, $context, 'high' );
	}

	/**
	 * Identify the exact registered original ACF box for the applicable root field.
	 *
	 * The public Core records bind callback, original arguments and group identity.
	 * A replaced, removed, duplicated or unknown renderer is never repositioned.
	 *
	 * @since 3.5.0
	 * @param \WP_Post $post Exact native editor document.
	 * @return array{record:array,context:string,priority:string}|array Verified native box record or refusal.
	 */
	private static function builder_box( \WP_Post $post ) {
		if ( ! defined( 'ACF_VERSION' ) || ACF_VERSION !== '6.8.11' ) {
			return array();
		}
		$field = self::field( $post );
		if ( ! $field ) {
			return array();
		}
		global $wp_meta_boxes;
		$matches = array();
		foreach ( $wp_meta_boxes[$post->post_type] ?? array() as $context => $priorities ) {
			foreach ( $priorities as $priority => $boxes ) {
				foreach ( $boxes as $record ) {
					$group = is_array( $record ) ? ( $record['args']['field_group'] ?? null ) : null;
					if ( ! is_array( $group ) || ! is_string( $group['key'] ?? null ) ||
						( $group['key'] !== ( $field['parent'] ?? null ) && ( empty( $group['ID'] ) || (string) $group['ID'] !== (string) ( $field['parent'] ?? '' ) ) ) ) {
						continue;
					}
					$callback = $record['callback'] ?? null;
					if ( ( $record['id'] ?? null ) !== 'acf-' . $group['key'] || ! is_array( $callback ) || ! is_object( $callback[0] ?? null ) ||
						get_class( $callback[0] ) !== 'ACF_Form_Post' || ( $callback[1] ?? null ) !== 'render_meta_box' ||
						! in_array( $context, array( 'normal', 'side', 'acf_after_title' ), true ) || ! in_array( $priority, array( 'high', 'core', 'default', 'low' ), true ) ) {
						return array();
					}
					$matches[] = array( 'record' => $record, 'context' => $context, 'priority' => $priority );
				}
			}
		}
		return count( $matches ) === 1 ? $matches[0] : array();
	}

	/**
	 * Place the known native ACF box after the title before either editor is initialized.
	 *
	 * Core's add_meta_box moves an existing identity between contexts itself. The
	 * original callback, title, arguments and priority survive; no stored group or
	 * already initialized DOM is modified.
	 *
	 * @since 3.5.0
	 * @param string $post_type Native ACF registration context.
	 * @param mixed $post Exact native document, or unsupported caller value.
	 * @return void Unknown records retain their original native position.
	 */
	public static function position_builder( $post_type, $post ) {
		if ( ! $post instanceof \WP_Post || $post->post_type !== $post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		$box = self::builder_box( $post );
		if ( ! $box || $box['context'] === 'acf_after_title' ) {
			return;
		}
		$record = $box['record'];
		add_meta_box( $record['id'], $record['title'], $record['callback'], $post_type, 'acf_after_title', $box['priority'], $record['args'] );
	}

	/**
	 * Require actual native after-title placement before hiding either editor interface.
	 *
	 * @since 3.5.0
	 * @param \WP_Post $post Exact current document.
	 * @return bool True only for the reviewed original box in its intended context.
	 */
	public static function position_supported( \WP_Post $post ) {
		$box = self::builder_box( $post );
		return $box && $box['context'] === 'acf_after_title';
	}

	/**
	 * Render only explicit chooser context through the shared component loader.
	 *
	 * @since 3.5.0
	 * @param \WP_Post $post Exact native metabox owner.
	 * @return void Unsupported late field changes retain the unmodified editors.
	 */
	public static function render_metabox( \WP_Post $post ) {
		$field = self::field( $post );
		if ( ! $field || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		get_component( 'editor/source', false, array( 'post_id' => $post->ID, 'source' => self::mode( $post ), 'supported' => self::validation_supported() && self::position_supported( $post ) ) );
	}

	/**
	 * Resolve one immutable owned editor asset independently of a child's asset manifest.
	 *
	 * @since 3.5.0
	 * @param string $filename Exact content-sources.js or content-sources.css entry.
	 * @return string Valid owned URL, or an empty incomplete-build refusal.
	 */
	public static function asset_uri( $filename ) {
		if ( ! in_array( $filename, array( 'content-sources.js', 'content-sources.css' ), true ) ) {
			return '';
		}
		$root = get_template_directory();
		$manifest = new Assets\JsonManifest( $root . '/dist/assets.json' );
		$relative = $manifest->getPath( $filename, '' );
		$extension = pathinfo( $filename, PATHINFO_EXTENSION );
		$directory = $extension === 'js' ? 'scripts' : 'styles';
		if ( ! is_string( $relative ) || ! preg_match( '~^' . $directory . '/content-sources-[a-f0-9]{16}\\.' . $extension . '$~D', $relative ) ) {
			return '';
		}
		$file = PathIdentity::canonical( $root . '/dist/' . $relative );
		$dist = PathIdentity::canonical( $root . '/dist' );
		return $file && $dist && str_starts_with( $file, $dist . DIRECTORY_SEPARATOR ) && is_file( $file ) && is_readable( $file )
			? get_template_directory_uri() . '/dist/' . $relative : '';
	}

	/**
	 * Enhance only an authorized native editor with a verified lossless provider contract.
	 *
	 * @since 3.5.0
	 * @param string $hook Actual native administration screen filename.
	 * @return void Incomplete/unknown providers retain both native interfaces and full validation.
	 */
	public static function enqueue( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! self::validation_supported() ) {
			return;
		}
		global $post;
		$screen = get_current_screen();
		if ( ! $screen || $screen->base !== 'post' || ! $post instanceof \WP_Post || $screen->post_type !== $post->post_type ||
			! current_user_can( 'edit_post', $post->ID ) || ! wp_script_is( 'acf-input', 'registered' ) ||
			wp_script_is( 'skye-content-sources', 'registered' ) || wp_style_is( 'skye-content-sources', 'registered' ) ) {
			return;
		}
		$context = self::editor_context( $post );
		$script = self::asset_uri( 'content-sources.js' );
		$style = self::asset_uri( 'content-sources.css' );
		if ( ! $context || $script === '' || $style === '' ) {
			return;
		}
		wp_enqueue_style( 'skye-content-sources', $style, array(), null );
		wp_enqueue_script( 'skye-content-sources', $script, array( 'jquery', 'acf-input' ), null, true );
		wp_localize_script( 'skye-content-sources', 'SkyeContentSourcesConfig', $context );
	}

	/**
	 * Verify source intent without consuming Core or ACF nonces or trusting an editable post attribution.
	 *
	 * @since 3.5.0
	 * @return array{post:\WP_Post,source:string,field:array}|array Exact authenticated form context, or refusal.
	 */
	private static function intent() {
		if ( ! empty( $_POST['menu-item-acf'] ) || isset( $_POST['_acf_widget_id'] ) || isset( $_GET['meta-box-loader'] ) ) {
			return array();
		}
		foreach ( array( 'post_ID', 'skye_content_source_post', '_acf_post_id' ) as $name ) {
			if ( ! isset( $_POST[$name] ) || ! is_string( $_POST[$name] ) || ! preg_match( '/^[1-9][0-9]*$/D', $_POST[$name] ) ) {
				return array();
			}
		}
		$id = (int) $_POST['post_ID'];
		if ( (string) $id !== $_POST['skye_content_source_post'] || (string) $id !== $_POST['_acf_post_id'] ||
			! is_string( $_POST['skye_content_source'] ?? null ) || ! in_array( $_POST['skye_content_source'], array( 'builder', 'editor' ), true ) ||
			! is_string( $_POST['skye_content_source_nonce'] ?? null ) || ! wp_verify_nonce( wp_unslash( $_POST['skye_content_source_nonce'] ), 'skye-content-source-' . $id ) ||
			! is_string( $_POST['_wpnonce'] ?? null ) || ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'update-post_' . $id ) ||
			( $_POST['_acf_screen'] ?? null ) !== 'post' || ! current_user_can( 'edit_post', $id ) ) {
			return array();
		}
		if ( wp_doing_ajax() ) {
			if ( ( $_POST['action'] ?? null ) !== 'acf/validate_save_post' || ! is_string( $_REQUEST['nonce'] ?? null ) ||
				! wp_verify_nonce( wp_unslash( $_REQUEST['nonce'] ), 'acf_nonce' ) ) {
				return array();
			}
		} elseif ( ( $_POST['action'] ?? null ) !== 'editpost' || ! array_key_exists( '_acf_nonce', $_POST ) || $_POST['_acf_nonce'] !== false ) {
			// The original ACF post handler has already verified and consumed this nonce.
			return array();
		}
		$post = get_post( $id );
		$field = $post instanceof \WP_Post ? self::field( $post ) : array();
		return $field ? array( 'post' => $post, 'source' => $_POST['skye_content_source'], 'field' => $field ) : array();
	}

	/**
	 * Shield only one inactive root during the original validator's public hook window.
	 *
	 * The snapshot is taken here, after native POST-save input restriction. No
	 * earlier errors are reset, and other fields keep their original validation.
	 *
	 * @since 3.5.0
	 * @return void Never changes field definitions, capabilities, nonces or saved values.
	 */
	public static function begin_validation() {
		self::$depth++;
		if ( self::$snapshot !== null || ! self::validation_supported() ) {
			return;
		}
		$intent = self::intent();
		if ( ! $intent || $intent['source'] !== 'editor' || ! is_array( $_POST['acf'] ?? null ) ||
			! array_key_exists( $intent['field']['key'], $_POST['acf'] ) ) {
			return;
		}
		$original = $_POST['acf'];
		unset( $_POST['acf'][$intent['field']['key']] );
		self::$snapshot = array( 'original' => $original, 'shielded' => $_POST['acf'], 'depth' => self::$depth );
	}

	/**
	 * Restore the exact restricted array after the matching native validation invocation.
	 *
	 * @since 3.5.0
	 * @return void An unexpected concurrent mutation adds an error and is never overwritten.
	 */
	public static function finish_validation() {
		if ( self::$snapshot !== null && self::$snapshot['depth'] === self::$depth ) {
			self::restore_interrupted();
		}
		self::$depth = max( 0, self::$depth - 1 );
	}

	/**
	 * Restore an interrupted validation snapshot only while its restricted-array identity still matches.
	 *
	 * Ordinary validation errors live in ACF's untouched error collector. A fatal
	 * or thrown validator can still leave request inputs intact at shutdown; this
	 * recovery never performs a save or reintroduces a raw pre-restriction array.
	 *
	 * @since 3.5.0
	 * @return void Clears only the owned request-local snapshot.
	 */
	public static function restore_interrupted() {
		$snapshot = self::$snapshot;
		self::$snapshot = null;
		if ( $snapshot === null ) {
			return;
		}
		if ( ( $_POST['acf'] ?? null ) === $snapshot['shielded'] ) {
			$_POST['acf'] = $snapshot['original'];
		} elseif ( function_exists( 'acf_add_validation_error' ) ) {
			acf_add_validation_error( '', __( 'Die Prüfung wurde abgebrochen, weil sich Formulardaten geändert haben. Sichere Deine ungespeicherten Eingaben und versuche es nochmals.', 'skye' ) );
		}
	}

	/**
	 * Persist only the independent source after a native authorized ACF save succeeds.
	 *
	 * Core and ACF retain both content stores, their sanitizers, revision handling
	 * and original write lifecycles. Background or revision saves cannot borrow
	 * another document's form intent. A root removed by native field restriction
	 * cannot authorize changing the active source while unrelated fields save.
	 *
	 * @since 3.5.0
	 * @param int|string $post_id Exact native ACF save owner.
	 * @return void Invalid, foreign, autosave or error contexts never change the chosen source.
	 */
	public static function save( $post_id ) {
		$intent = self::intent();
		if ( ! $intent || ! is_array( $_POST['acf'] ?? null ) || ! array_key_exists( $intent['field']['key'], $_POST['acf'] ) ||
			(string) $post_id !== (string) $intent['post']->ID || wp_is_post_autosave( $intent['post']->ID ) ||
			wp_is_post_revision( $intent['post']->ID ) || ( function_exists( 'acf_get_validation_errors' ) && acf_get_validation_errors() ) ) {
			return;
		}
		update_post_meta( $intent['post']->ID, self::META_KEY, $intent['source'] );
	}
}
