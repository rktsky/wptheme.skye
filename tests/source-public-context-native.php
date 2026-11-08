<?php
/** Verify active public source producers through original Core, REST and optional original ACF/Yoast. */

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit;
}
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$public_site = realpath( getenv( 'SKYE_SITE_ROOT' ) ?: '' );
if ( ! $public_site || ! is_file( $public_site . '/wp-load.php' ) ) {
	throw new RuntimeException( 'Set SKYE_SITE_ROOT to the disposable native fixture.' );
}
require $public_site . '/wp-load.php';
$public_database = defined( 'DB_PATH' ) ? realpath( DB_PATH ) : false;
$public_fixture = realpath( dirname( $public_site ) );
if ( wp_get_environment_type() !== 'local' || ! defined( 'DB_ENGINE' ) || DB_ENGINE !== 'sqlite' || ! $wpdb instanceof WP_SQLite_DB ||
	! $public_database || ! $public_fixture || ! str_starts_with( $public_database, $public_fixture . '/' ) ||
	! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( '127.0.0.1', 'localhost' ), true ) ) {
	throw new RuntimeException( 'Only the isolated loopback SQLite fixture is authorized.' );
}
require_once dirname( __DIR__ ) . '/lib/package/ContentSources.php';
require_once dirname( __DIR__ ) . '/lib/package/YoastAnalysis.php';
require_once dirname( __DIR__ ) . '/lib/package/SourcePublicContext.php';

use Rocketsky\Skye\Packages\ContentSources;
use Rocketsky\Skye\Packages\SourcePublicContext;

$public_checks = 0;
$public_id = 0;
$public_projection = '<h2>Active builder heading</h2><p>Active builder semantic text.</p>';
$public_projection_calls = 0;
$public_order = array();
$public_native_calls = 0;
$public_content_inputs = array();
$public_earlier = false;
$public_variable_override = false;
$public_http = 0;
$public_mail = 0;
$public_during_install = null;
$public_shortcode_calls = 0;
$public_editor_body = '<p>INACTIVE_EDITOR_PUBLIC_FIXTURE [skye_source_public_probe]</p>';
$public_raw_excerpts = array();
$public_rest_observation = null;

/**
 * Observe harmless native shortcode execution without transport or persistent side effects.
 * @since 3.5.0
 * @return string Distinct disposable output, never intended as a public builder projection.
 */
function public_source_shortcode() {
	$GLOBALS['public_shortcode_calls']++;
	return 'INACTIVE_SHORTCODE_RESULT';
}

/**
 * Assert a native observable contract without printing private fixture contents.
 * @since 3.5.0
 * @param bool $condition Actual result.
 * @param string $message Required invariant.
 * @return void
 */
function public_source_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	$GLOBALS['public_checks']++;
}

/**
 * Bind one independent original flexible root to this disposable post only.
 * @since 3.5.0
 * @param array $configuration Earlier project profile.
 * @param WP_Post $post Actual owner.
 * @return array Original profile or this test's exact independent identity.
 */
function public_source_configuration( $configuration, $post ) {
	return $post->ID === $GLOBALS['public_id'] ? array( 'name' => 'source_public_builder', 'key' => 'field_source_public_builder' ) : $configuration;
}

/**
 * Supply the same explicit project semantic HTML used by native saved Yoast analysis.
 * Count actual mapper calls to distinguish required excerpt work from unrequested REST body hydration.
 * @since 3.5.0
 * @param mixed $previous Earlier projection.
 * @param WP_Post $post Exact source owner.
 * @return mixed This fixture's HTML/null or the unchanged unrelated project decision.
 */
function public_source_projection( $previous, $post ) {
	if ( $post->ID === $GLOBALS['public_id'] ) {
		$GLOBALS['public_projection_calls']++;
	}
	if ( $post->ID === $GLOBALS['public_id'] && $GLOBALS['public_projection'] instanceof Throwable ) {
		throw $GLOBALS['public_projection'];
	}
	return $post->ID === $GLOBALS['public_id'] ? $GLOBALS['public_projection'] : $previous;
}

/**
 * Attempt installation during the real native filter to verify the explicit execution boundary.
 * @since 3.5.0
 * @param string $excerpt Earlier native value.
 * @return string Unchanged value; only the install decision is observed.
 */
function public_source_during_install( $excerpt ) {
	$GLOBALS['public_during_install'] = SourcePublicContext::install_excerpt();
	return $excerpt;
}

/**
 * Observe an existing native-priority callback before the original Core producer.
 * @since 3.5.0
 * @param string $excerpt Earlier excerpt.
 * @return string The unchanged value.
 */
function public_source_before( $excerpt ) {
	$GLOBALS['public_order'][] = 'before';
	return $excerpt;
}

/**
 * Observe a callback after the producer at the same priority and retain both arguments.
 * @since 3.5.0
 * @param string $excerpt Native result.
 * @param WP_Post $post Exact owner.
 * @return string The unchanged result.
 */
function public_source_after( $excerpt, $post ) {
	$GLOBALS['public_order'][] = 'after';
	return $excerpt;
}

/**
 * Count the real Core producer, which must execute exactly once per excerpt.
 * @since 3.5.0
 * @param string $excerpt Native output.
 * @param string $raw Native raw excerpt.
 * @return string Unchanged native output.
 */
function public_source_native_excerpt( $excerpt, $raw ) {
	$GLOBALS['public_native_calls']++;
	$GLOBALS['public_order'][] = 'native';
	$GLOBALS['public_raw_excerpts'][] = $raw;
	return $excerpt;
}

/**
 * Record only in-memory test inputs to verify that inactive copy never reaches excerpt formatting.
 * @since 3.5.0
 * @param string $content Native formatter input.
 * @return string Unchanged content.
 */
function public_source_content_input( $content ) {
	$GLOBALS['public_content_inputs'][] = $content;
	return $content;
}

/**
 * Represent an earlier deliberate excerpt decision without modifying stored posts.
 * @since 3.5.0
 * @param string $excerpt Earlier native value.
 * @return string Explicit prior text when enabled, otherwise the original value.
 */
function public_source_earlier_excerpt( $excerpt ) {
	return $GLOBALS['public_earlier'] ? 'Earlier independent excerpt.' : $excerpt;
}

/**
 * Represent an earlier explicit Yoast variable decision, preserved by the owned adapter.
 * @since 3.5.0
 * @param array $values Native replacement values.
 * @return array Original values with an optional independent explicit excerpt.
 */
function public_source_earlier_variable( $values ) {
	if ( $GLOBALS['public_variable_override'] && array_key_exists( '%%excerpt%%', $values ) ) {
		$values['%%excerpt%%'] = 'Earlier independent variable.';
	}
	return $values;
}

/**
 * Refuse all transport from this disposable native regression.
 * @since 3.5.0
 * @param mixed $previous Previous native HTTP result.
 * @return WP_Error No actual network request is allowed.
 */
function public_source_block_http( $previous ) {
	$GLOBALS['public_http']++;
	return new WP_Error( 'public_source_no_transport', 'Private fixture transport disabled.' );
}

/**
 * Block fixture notifications while retaining an observable attempt count.
 * @since 3.5.0
 * @param mixed $previous Prior mail result.
 * @return bool False prevents delivery.
 */
function public_source_block_mail( $previous ) {
	$GLOBALS['public_mail']++;
	return false;
}

/**
 * Execute one original Core REST read with an explicit representation context.
 * @since 3.5.0
 * @param int $id Disposable page identity.
 * @param string $context Native view/embed/edit representation.
 * @param array $parameters Optional actual native field selection.
 * @return WP_REST_Response|WP_Error Original server response.
 */
function public_source_rest( $id, $context = 'view', $parameters = array() ) {
	$request = new WP_REST_Request( 'GET', '/wp/v2/pages/' . $id );
	$request->set_param( 'context', $context );
	foreach ( $parameters as $name => $value ) {
		$request->set_param( $name, $value );
	}
	return rest_do_request( $request );
}

/**
 * Observe native selected-field data immediately before the public source response adapter.
 * @since 3.5.0
 * @param WP_REST_Response $response Original native response.
 * @param WP_Post $post Exact native owner.
 * @param WP_REST_Request $request Actual native field-selection request.
 * @return WP_REST_Response Unchanged original response; only bounded fixture observations remain in memory.
 */
function public_source_observe_rest( $response, $post, $request ) {
	if ( $post->ID === $GLOBALS['public_id'] && in_array( $request->get_param( '_fields' ), array( 'id', 'excerpt.rendered' ), true ) ) {
		$GLOBALS['public_rest_observation'] = array( 'data' => $response->get_data(), 'status' => $response->get_status(), 'mapper_calls' => $GLOBALS['public_projection_calls'] );
	}
	return $response;
}

/**
 * Delegate a deliberately custom route to Core to verify that native-reader scope is not borrowed.
 * @since 3.5.0
 * @param WP_REST_Request $request Actual custom-route request.
 * @return WP_REST_Response Original Core preparation within the custom callback context.
 */
function public_source_custom_rest( $request ) {
	$controller = new WP_REST_Posts_Controller( 'page' );
	return $controller->prepare_item_for_response( get_post( (int) $request['id'] ), $request );
}

/**
 * Check malformed ACF attribution inside an actual native read without changing provider input.
 * @since 3.5.0
 * @param mixed $response Earlier native dispatch result.
 * @param array $handler Original matched handler.
 * @param WP_REST_Request $request Original request identity.
 * @return mixed Unchanged dispatch result; all probes are independent owned-method calls.
 */
function public_source_attribution( $response, $handler, $request ) {
	$post = get_post( $GLOBALS['public_id'] );
	$root = ContentSources::field( $post );
	$fields = array( $root, 'Unrelated uncertain field record.' );
	$resource = array( 'type' => 'post', 'sub_type' => 'page' );
	$GLOBALS['post'] = $post;
	public_source_check( SourcePublicContext::rest_fields( $fields, $resource, 'GET' ) === $fields, 'A missing resource ID cannot borrow the ambient editor post.' );
	$resource['id'] = 0;
	public_source_check( SourcePublicContext::rest_fields( $fields, $resource, 'GET' ) === $fields, 'A zero resource ID cannot borrow the ambient editor post.' );
	$resource['id'] = '0' . $post->ID;
	public_source_check( SourcePublicContext::rest_fields( $fields, $resource, 'GET' ) === $fields, 'Noncanonical IDs retain the native uncertain resource contract.' );
	$resource['id'] = $post->ID;
	$filtered = SourcePublicContext::rest_fields( $fields, $resource, 'GET' );
	public_source_check( $filtered === array( 1 => 'Unrelated uncertain field record.' ), 'A valid exact resource removes only its root and preserves uncertain foreign field records/order.' );
	return $response;
}

$public_original_excerpt = $GLOBALS['wp_filter']['get_the_excerpt']->callbacks ?? array();
$public_previous_user = get_current_user_id();
$public_previous_post = $GLOBALS['post'] ?? null;
$public_previous_query = $GLOBALS['wp_query'] ?? null;
$public_admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( ! $public_admin ) {
	throw new RuntimeException( 'The disposable site requires a native administrator.' );
}
add_filter( 'pre_http_request', 'public_source_block_http', PHP_INT_MAX );
add_filter( 'pre_wp_mail', 'public_source_block_mail', PHP_INT_MAX );
add_filter( 'skye_content_sources', 'public_source_configuration', 10, 2 );
add_filter( 'skye_yoast_analysis_content', 'public_source_projection', 10, 2 );
add_filter( 'wp_trim_excerpt', 'public_source_native_excerpt', 10, 2 );
add_filter( 'the_content', 'public_source_content_input', PHP_INT_MIN );
add_filter( 'wpseo_replacements', 'public_source_earlier_variable', 5 );
if ( shortcode_exists( 'skye_source_public_probe' ) ) {
	throw new RuntimeException( 'The independent shortcode fixture identity is already occupied.' );
}
add_shortcode( 'skye_source_public_probe', 'public_source_shortcode' );
wp_set_current_user( (int) $public_admin[0] );

try {
	$public_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Disposable public source fixture', 'post_content' => $public_editor_body ), true );
	if ( is_wp_error( $public_id ) ) {
		throw new RuntimeException( 'Cannot create disposable fixture.' );
	}
	$public_id = (int) $public_id;
	update_post_meta( $public_id, ContentSources::META_KEY, 'builder' );
	$document = get_post( $public_id );
	remove_all_filters( 'get_the_excerpt' );
	add_filter( 'get_the_excerpt', 'public_source_before', 10, 1 );
	add_filter( 'get_the_excerpt', 'wp_trim_excerpt', 10, 2 );
	add_filter( 'get_the_excerpt', 'public_source_after', 10, 2 );
	new SourcePublicContext();
	$records = array_values( $GLOBALS['wp_filter']['get_the_excerpt']->callbacks[10] );
	public_source_check( array_column( $records, 'function' ) === array( 'public_source_before', array( SourcePublicContext::class, 'excerpt' ), 'public_source_after' ), 'Native priority-ten callback order is retained exactly.' );
	public_source_check( array_column( $records, 'accepted_args' ) === array( 1, 2, 2 ), 'Every existing callback keeps its original accepted argument count.' );
	public_source_check( SourcePublicContext::install_excerpt(), 'Repeated installation neither moves nor duplicates the producer.' );
	$GLOBALS['post'] = (object) array( 'ID' => -99 );
	$excerpt = get_the_excerpt( $document );
	public_source_check( str_contains( $excerpt, 'Active builder semantic text.' ) && ! str_contains( $excerpt, 'INACTIVE_EDITOR' ), 'The real Core excerpt receives active builder semantics with an unrelated ambient post.' );
	public_source_check( $public_order === array( 'before', 'native', 'after' ) && $public_native_calls === 1, 'Core trimming executes once in the original callback position.' );
	public_source_check( count( $public_content_inputs ) === 1 && ! str_contains( $public_content_inputs[0], 'INACTIVE_EDITOR' ), 'Inactive editor HTML never enters the excerpt content formatter.' );
	public_source_check( $public_shortcode_calls === 0, 'The automatic builder excerpt never executes the retained editor shortcode.' );
	public_source_check( $document->post_content === $public_editor_body && get_post_field( 'post_content', $public_id ) === $document->post_content, 'The original object and stored inactive editor draft remain unchanged.' );
	add_filter( 'get_the_excerpt', 'public_source_earlier_excerpt', 5 );
	$public_earlier = true;
	public_source_check( get_the_excerpt( $document ) === 'Earlier independent excerpt.', 'A nonempty earlier excerpt decision retains the original native producer.' );
	$public_earlier = false;
	$manual = clone $document;
	$manual->post_excerpt = 'Explicit editorial excerpt.';
	public_source_check( get_the_excerpt( $manual ) === 'Explicit editorial excerpt.', 'Manual editorial excerpts remain independent of source selection.' );
	wp_update_post( array( 'ID' => $public_id, 'post_excerpt' => " \t\n " ) );
	$whitespace = get_post_field( 'post_excerpt', $public_id );
	$excerpt = get_the_excerpt( get_post( $public_id ) );
	public_source_check( $whitespace !== '' && trim( $whitespace ) === '' && str_contains( $excerpt, 'Active builder semantic text.' ) && ! str_contains( $excerpt, 'INACTIVE_EDITOR' ), 'A genuinely stored whitespace-only excerpt uses Core automatic semantics with the active builder input.' );
	public_source_check( end( $public_raw_excerpts ) === $whitespace, 'The native wp_trim_excerpt filter receives the unchanged actual whitespace raw argument.' );
	wp_update_post( array( 'ID' => $public_id, 'post_excerpt' => '' ) );
	update_post_meta( $public_id, ContentSources::META_KEY, 'editor' );
	public_source_check( str_contains( get_the_excerpt( get_post( $public_id ) ), 'INACTIVE_EDITOR' ), 'An explicit active editor retains its native Core excerpt.' );
	delete_post_meta( $public_id, ContentSources::META_KEY );
	public_source_check( str_contains( get_the_excerpt( get_post( $public_id ) ), 'INACTIVE_EDITOR' ), 'An unmanaged native document retains the original excerpt producer.' );
	update_post_meta( $public_id, ContentSources::META_KEY, 'builder' );
	add_filter( 'get_the_excerpt', 'public_source_during_install', 0 );
	get_the_excerpt( $document );
	remove_filter( 'get_the_excerpt', 'public_source_during_install', 0 );
	public_source_check( $public_during_install === false, 'An executing native excerpt pipeline cannot be reordered by installation.' );
	$public_projection = null;
	public_source_check( get_the_excerpt( $document ) === '', 'An absent semantic mapper cannot fall back to inactive editor copy.' );
	$public_projection = new RuntimeException( 'Disposable mapper refusal.' );
	public_source_check( get_the_excerpt( $document ) === '', 'A throwing semantic mapper cannot fall back to inactive editor copy.' );
	$public_projection = '<p>Active builder semantic text.</p>';
	$unsafe = clone $document;
	$unsafe->post_password = 'fixture-only';
	public_source_check( SourcePublicContext::projection( $unsafe ) === '', 'A refused password-protected builder never inherits the analysis native-body fallback.' );
	$unsafe->post_password = '';
	$unsafe->post_status = 'draft';
	public_source_check( SourcePublicContext::projection( $unsafe ) === '', 'A nonpublic builder cannot export its semantic projection.' );
	if ( defined( 'WPSEO_VERSION' ) && WPSEO_VERSION === '28.8' && class_exists( 'WPSEO_Replace_Vars' ) ) {
		$variables = new WPSEO_Replace_Vars();
		public_source_check( $variables->replace( 'Description: %%excerpt%%', $document ) === 'Description: Active builder semantic text.', 'Original Yoast automatic excerpt variables use the same builder projection.' );
		public_source_check( $variables->replace( 'Pinned literal description.', $document ) === 'Pinned literal description.', 'An explicit literal SEO or social description remains unchanged.' );
		public_source_check( $variables->replace( '%%excerpt_only%%', $manual ) === 'Explicit editorial excerpt.', 'The independent manual excerpt_only variable remains native.' );
		$public_variable_override = true;
		public_source_check( $variables->replace( '%%excerpt%%', $document ) === 'Earlier independent variable.', 'An earlier independently replaced excerpt variable is retained.' );
		$public_variable_override = false;
		$alternate = clone $document;
		$alternate->post_content = '<p>Explicit alternative project context.</p>';
		public_source_check( $variables->replace( '%%excerpt%%', $alternate ) === 'Explicit alternative project context.', 'An intentionally different project body context is not rebound to the stored builder projection.' );
		$variable_whitespace = clone $document;
		$variable_whitespace->post_excerpt = '   ';
		public_source_check( $variables->replace( 'Prefix %%excerpt%% Suffix', $variable_whitespace ) === 'Prefix Suffix', 'The original Yoast whitespace-manual variable rule remains independent of Core autoexcerpt semantics.' );
		$public_projection = null;
		public_source_check( $variables->replace( '%%excerpt%%', $document ) === '', 'An unmapped builder variable excludes inactive editor content.' );
		$public_projection = '<p>Active builder semantic text.</p>';
	} else {
		$arguments = (object) array( 'ID' => $public_id, 'post_content' => $document->post_content, 'post_excerpt' => '' );
		$values = apply_filters( 'wpseo_replacements', array( '%%excerpt%%' => 'Unreviewed native automatic result.', '%%title%%' => 'Independent title.' ), $arguments );
		public_source_check( $values === array( '%%excerpt%%' => '', '%%title%%' => 'Independent title.' ), 'An unreviewed automatic variable producer fails closed for a verified native builder body.' );
		$public_variable_override = true;
		$values = apply_filters( 'wpseo_replacements', array( '%%excerpt%%' => 'Unreviewed native automatic result.' ), $arguments );
		public_source_check( $values['%%excerpt%%'] === 'Earlier independent variable.', 'A captured unknown producer still preserves an actual earlier independent replacement.' );
		$public_variable_override = false;
	}
	$direct_request = new WP_REST_Request( 'GET', '/wp/v2/pages/' . $public_id );
	$direct_response = new WP_REST_Response( array( 'content' => array( 'rendered' => 'Independent direct renderer.' ) ) );
	public_source_check( SourcePublicContext::rest_response( $direct_response, $document, $direct_request )->get_data()['content']['rendered'] === 'Independent direct renderer.', 'A direct response-filter call cannot borrow a native reader context.' );
	wp_set_current_user( 0 );
	$shortcodes_before_rest = $public_shortcode_calls;
	$response = public_source_rest( $public_id );
	$data = $response->get_data();
	public_source_check( $response->get_status() === 200 && ( $data['content']['rendered'] ?? null ) === $public_projection, 'Original anonymous REST view exports active semantic builder HTML.' );
	public_source_check( ! str_contains( $data['excerpt']['rendered'] ?? '', 'INACTIVE_EDITOR' ) && ! isset( $data['content']['raw'] ), 'Public REST excerpt/raw output excludes the retained editor draft.' );
	public_source_check( $public_shortcode_calls > $shortcodes_before_rest && ! str_contains( $data['content']['rendered'], 'INACTIVE_SHORTCODE_RESULT' ), 'The late REST boundary excludes inactive shortcode output but honestly preserves the earlier original Core execution.' );
	$response = public_source_rest( $public_id, 'view', array( '_fields' => 'id,title' ) );
	public_source_check( ! isset( $response->get_data()['content'] ) && ! isset( $response->get_data()['excerpt'] ), 'REST field selection is preserved without adding omitted content.' );
	add_filter( 'rest_prepare_page', 'public_source_observe_rest', -1, 3 );
	$projections_before = $public_projection_calls;
	$response = public_source_rest( $public_id, 'view', array( '_fields' => 'id' ) );
	public_source_check( $response->get_status() === 200 && $response->get_status() === $public_rest_observation['status'] && $response->get_data() === array( 'id' => $public_id ) && $response->get_data() === $public_rest_observation['data'] && $public_projection_calls === $projections_before, 'An actual ID-only REST response preserves its exact payload/status without invoking the builder mapper.' );
	$expected_excerpt = apply_filters( 'the_excerpt', get_the_excerpt( $document ) );
	$projections_before = $public_projection_calls;
	$response = public_source_rest( $public_id, 'view', array( '_fields' => 'excerpt.rendered' ) );
	public_source_check( $response->get_status() === 200 && $response->get_status() === $public_rest_observation['status'] && $response->get_data() === $public_rest_observation['data'] && ( $response->get_data()['excerpt']['rendered'] ?? null ) === $expected_excerpt && $public_projection_calls === $projections_before + 1 && $public_projection_calls === $public_rest_observation['mapper_calls'], 'An actual excerpt-only REST response preserves native data/status and invokes only its required native excerpt mapper, with no extra body projection.' );
	remove_filter( 'rest_prepare_page', 'public_source_observe_rest', -1 );
	register_rest_route( 'skye-public-fixture/v1', '/source/(?P<id>[1-9][0-9]*)', array( 'methods' => 'GET', 'callback' => 'public_source_custom_rest', 'permission_callback' => '__return_true' ) );
	$custom_request = new WP_REST_Request( 'GET', '/skye-public-fixture/v1/source/' . $public_id );
	$custom_request->set_param( 'context', 'view' );
	public_source_check( str_contains( rest_do_request( $custom_request )->get_data()['content']['rendered'], 'INACTIVE_EDITOR' ), 'A custom REST callback delegating to Core preparation retains its separately owned content contract.' );
	$head = new WP_REST_Request( 'HEAD', '/wp/v2/pages/' . $public_id );
	public_source_check( rest_do_request( $head )->get_data() === array(), 'Native HEAD responses remain empty.' );
	wp_set_current_user( (int) $public_admin[0] );
	$edit = public_source_rest( $public_id, 'edit' )->get_data();
	public_source_check( $edit['content']['raw'] === $document->post_content && str_contains( $edit['content']['rendered'], 'INACTIVE_EDITOR' ), 'Authorized REST edit preserves the exact native raw and rendered editor contract.' );
	public_source_check( str_contains( $edit['excerpt']['rendered'], 'INACTIVE_EDITOR' ) && ! str_contains( $edit['excerpt']['rendered'], 'Active builder semantic text.' ), 'Authorized REST edit also retains the native automatic excerpt representation.' );
	wp_set_current_user( 0 );
	$denied = public_source_rest( $public_id, 'edit' );
	public_source_check( $denied->get_status() >= 400, 'Unauthorized edit remains refused by native permissions.' );
	if ( function_exists( 'acf_add_local_field_group' ) && acf_get_field_type( 'flexible_content' ) instanceof acf_field ) {
		acf_add_local_field_group( array(
			'key' => 'group_source_public_fixture', 'title' => 'Disposable public source fields', 'show_in_rest' => 1,
			'fields' => array(
				array( 'key' => 'field_source_public_builder', 'name' => 'source_public_builder', 'label' => 'Builder', 'type' => 'flexible_content', 'layouts' => array(
					'layout_source_public_fixture' => array( 'key' => 'layout_source_public_fixture', 'name' => 'fixture', 'label' => 'Fixture', 'sub_fields' => array(
						array( 'key' => 'field_source_public_text', 'name' => 'text', 'label' => 'Text', 'type' => 'text' ),
					) ),
				) ),
				array( 'key' => 'field_source_public_note', 'name' => 'source_public_note', 'label' => 'Independent public note', 'type' => 'text' ),
			),
			'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'page' ), array( 'param' => 'post', 'operator' => '==', 'value' => (string) $public_id ) ) ),
		) );
		update_field( 'field_source_public_builder', array( array( 'acf_fc_layout' => 'fixture', 'field_source_public_text' => 'Retained inactive builder draft.' ) ), $public_id );
		update_field( 'field_source_public_note', 'Independent published note.', $public_id );
		update_post_meta( $public_id, ContentSources::META_KEY, 'editor' );
		add_filter( 'rest_request_before_callbacks', 'public_source_attribution', 1, 3 );
		$data = public_source_rest( $public_id )->get_data();
		remove_filter( 'rest_request_before_callbacks', 'public_source_attribution', 1 );
		public_source_check( ! array_key_exists( 'source_public_builder', $data['acf'] ?? array() ) && ( $data['acf']['source_public_note'] ?? null ) === 'Independent published note.', 'Public editor-mode REST omits only the exact inactive ACF root before hydration.' );
		public_source_check( str_contains( $data['content']['rendered'], 'INACTIVE_EDITOR' ), 'The active editor remains the native public REST producer.' );
		wp_set_current_user( (int) $public_admin[0] );
		$edit = public_source_rest( $public_id, 'edit' )->get_data();
		public_source_check( isset( $edit['acf']['source_public_builder'] ) && $edit['acf']['source_public_note'] === 'Independent published note.', 'Authorized native edit retains both builder drafts and independent ACF values.' );
		wp_set_current_user( 0 );
		update_post_meta( $public_id, ContentSources::META_KEY, 'builder' );
		$data = public_source_rest( $public_id )->get_data();
		public_source_check( isset( $data['acf']['source_public_builder'] ) && $data['acf']['source_public_note'] === 'Independent published note.', 'The active builder and unrelated public ACF values remain exposed by the original provider.' );
		wp_update_post( array( 'ID' => $public_id, 'post_password' => 'public-source-fixture' ) );
		$data = public_source_rest( $public_id, 'view', array( 'password' => 'public-source-fixture' ) )->get_data();
		public_source_check( ( $data['content']['rendered'] ?? null ) === '' && ! isset( $data['acf']['source_public_builder'] ) && ( $data['acf']['source_public_note'] ?? null ) === 'Independent published note.', 'A password-accepted public representation still refuses the protected builder projection/root while preserving independent native fields.' );
		wp_set_current_user( (int) $public_admin[0] );
		$data = public_source_rest( $public_id, 'edit', array( 'password' => 'public-source-fixture' ) )->get_data();
		public_source_check( isset( $data['acf']['source_public_builder'] ) && $data['content']['raw'] === $document->post_content, 'Authorized protected edit retains its native root and raw draft.' );
		wp_update_post( array( 'ID' => $public_id, 'post_password' => '', 'post_status' => 'private' ) );
		$data = public_source_rest( $public_id )->get_data();
		public_source_check( ( $data['content']['rendered'] ?? null ) === '' && ! isset( $data['acf']['source_public_builder'] ), 'A readable private post still exports no public builder projection/root.' );
		wp_update_post( array( 'ID' => $public_id, 'post_status' => 'publish' ) );
		wp_set_current_user( 0 );
	}
	$feed_query = new WP_Query( array( 'post_type' => 'page', 'post__in' => array( $public_id ), 'posts_per_page' => 1 ) );
	$feed_query->is_feed = true;
	$GLOBALS['wp_query'] = $feed_query;
	$feed_query->the_post();
	$shortcodes_before_feed = $public_shortcode_calls;
	$feed = get_the_content_feed( 'rss2' );
	public_source_check( str_contains( $feed, $public_projection ) && ! str_contains( $feed, 'INACTIVE_EDITOR' ), 'The actual native feed loop exports the active builder and retains later native feed filters.' );
	public_source_check( $public_shortcode_calls > $shortcodes_before_feed && ! str_contains( $feed, 'INACTIVE_SHORTCODE_RESULT' ), 'The late feed boundary excludes inactive shortcode output without claiming to prevent earlier native execution.' );
	$public_projection = null;
	$feed = get_the_content_feed( 'rss2' );
	public_source_check( ! str_contains( $feed, 'INACTIVE_EDITOR' ) && ! str_contains( $feed, 'Active builder semantic text.' ), 'An unmapped builder feed excludes both inactive copy and a stale active projection while retaining native feed filters.' );
	$feed_query->is_feed = false;
	public_source_check( SourcePublicContext::feed( 'Ordinary independent content.' ) === 'Ordinary independent content.', 'A non-feed caller is never rewritten.' );
	remove_filter( 'get_the_excerpt', array( SourcePublicContext::class, 'excerpt' ), 10 );
	public_source_check( ! SourcePublicContext::install_excerpt(), 'A deliberately removed or foreign excerpt producer is not silently reintroduced.' );
	add_filter( 'get_the_excerpt', 'wp_trim_excerpt', 10, 1 );
	public_source_check( ! SourcePublicContext::install_excerpt() && has_filter( 'get_the_excerpt', 'wp_trim_excerpt' ) === 10, 'A changed native accepted-argument contract stays untouched.' );
	remove_filter( 'get_the_excerpt', 'wp_trim_excerpt', 10 );
	add_filter( 'get_the_excerpt', 'wp_trim_excerpt', 9, 2 );
	add_filter( 'get_the_excerpt', 'wp_trim_excerpt', 10, 2 );
	public_source_check( ! SourcePublicContext::install_excerpt(), 'Duplicate native producers remain outside the owned replacement contract.' );
	public_source_check( $public_http === 0, 'Public source resolution and native REST reads perform no HTTP.' );
	echo 'PASS ' . $public_checks . " native public source, excerpt order, original Yoast variables, REST and feed assertions; mail blocked.\n";
} finally {
	if ( $public_id ) {
		wp_delete_post( $public_id, true );
	}
	if ( function_exists( 'acf_remove_local_field_group' ) ) {
		acf_remove_local_field_group( 'group_source_public_fixture' );
	}
	remove_all_filters( 'get_the_excerpt' );
	foreach ( $public_original_excerpt as $priority => $records ) {
		foreach ( $records as $record ) {
			add_filter( 'get_the_excerpt', $record['function'], $priority, $record['accepted_args'] );
		}
	}
	remove_filter( 'wp_trim_excerpt', 'public_source_native_excerpt', 10 );
	remove_filter( 'the_content', 'public_source_content_input', PHP_INT_MIN );
	remove_filter( 'skye_content_sources', 'public_source_configuration', 10 );
	remove_filter( 'skye_yoast_analysis_content', 'public_source_projection', 10 );
	remove_filter( 'wpseo_replacements', 'public_source_earlier_variable', 5 );
	remove_filter( 'pre_http_request', 'public_source_block_http', PHP_INT_MAX );
	remove_filter( 'pre_wp_mail', 'public_source_block_mail', PHP_INT_MAX );
	remove_filter( 'rest_prepare_page', 'public_source_observe_rest', -1 );
	remove_shortcode( 'skye_source_public_probe' );
	$GLOBALS['post'] = $public_previous_post;
	$GLOBALS['wp_query'] = $public_previous_query;
	wp_set_current_user( $public_previous_user );
}
