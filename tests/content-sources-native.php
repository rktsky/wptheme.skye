<?php
/** Verify owned source selection through native Core saves and unmodified ACF validation on a disposable site. */

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit;
}
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$source_site = realpath( getenv( 'SKYE_SITE_ROOT' ) ?: '' );
if ( ! $source_site || ! is_file( $source_site . '/wp-load.php' ) ) {
	throw new RuntimeException( 'Set SKYE_SITE_ROOT to an isolated local WordPress fixture.' );
}
require $source_site . '/wp-load.php';
$source_database = defined( 'DB_PATH' ) ? realpath( DB_PATH ) : false;
$source_fixture = realpath( dirname( $source_site ) );
if ( wp_get_environment_type() !== 'local' || ! defined( 'DB_ENGINE' ) || DB_ENGINE !== 'sqlite' || ! $wpdb instanceof WP_SQLite_DB ||
	! $source_database || ! $source_fixture || ! str_starts_with( $source_database, $source_fixture . '/' ) ||
	! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( '127.0.0.1', 'localhost' ), true ) ) {
	throw new RuntimeException( 'Source tests require the isolated native local SQLite fixture on loopback.' );
}
require_once dirname( __DIR__ ) . '/lib/package/ContentSources.php';

use Rocketsky\Skye\Packages\ContentSources;

new ContentSources();
$source_checks = 0;
$source_post_id = 0;
$source_http = 0;
$source_mail = 0;
$source_root = dirname( __DIR__ );
$source_child = '';
$source_profile_missing = false;

/**
 * Assert actual native behavior without exposing fixture contents.
 * @since 3.5.0
 * @param bool $condition Observed invariant.
 * @param string $message Concrete required behavior.
 * @return void
 */
function source_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	$GLOBALS['source_checks']++;
}

/**
 * Bind only this test's independent root name and key; unrelated native groups stay untouched.
 * @since 3.5.0
 * @param array $config Earlier native project identity.
 * @param WP_Post $post Exact current document.
 * @return array Owned fixture binding or the original unrelated configuration.
 */
function source_configuration( $config, $post ) {
	if ( $post->ID === $GLOBALS['source_post_id'] && $GLOBALS['source_profile_missing'] ) {
		return null;
	}
	return $post->ID === $GLOBALS['source_post_id'] ? array( 'name' => 'skye_sources_fixture', 'key' => 'field_skye_sources_fixture' ) : $config;
}

/**
 * Stop and count unexpected network calls during fixture-only saves.
 * @since 3.5.0
 * @param mixed $previous Earlier preemption value.
 * @return WP_Error No actual HTTP transport is allowed.
 */
function source_block_http( $previous ) {
	$GLOBALS['source_http']++;
	return new WP_Error( 'source_no_http', 'No source fixture may perform transport.' );
}

/**
 * Stop every possible native message rather than allowing fixture notifications.
 * @since 3.5.0
 * @param mixed $previous Earlier native mail decision.
 * @return bool False prevents all PHP/SMTP transport.
 */
function source_block_mail( $previous ) {
	$GLOBALS['source_mail']++;
	return false;
}

/**
 * Expose the owned candidate through Core's native parent-directory boundary.
 * @since 3.5.0
 * @param string $directory Original native theme directory.
 * @return string Current candidate source root, without changing stored theme options.
 */
function source_parent_directory( $directory ) {
	return $GLOBALS['source_root'];
}

/**
 * Provide one temporary first-party layout override through the existing child-theme loader.
 * @since 3.5.0
 * @param string $directory Original native stylesheet directory.
 * @return string Isolated fixture layout root or the original directory.
 */
function source_child_directory( $directory ) {
	return $GLOBALS['source_child'] ?: $directory;
}

/**
 * Add a prior independent validation error, never an owned root-field exception.
 * @since 3.5.0
 * @return void The production adapter must leave this native error intact.
 */
function source_earlier_error() {
	acf_add_validation_error( 'independent-field', 'Earlier independent error.' );
}

/**
 * Represent an unknown callback inside the strictly reviewed native validation window.
 * @since 3.5.0
 * @return void No mutation is needed to make the provider contract unknown.
 */
function source_unknown_validator() {
}

/**
 * Remove only this fixture's root through the original provider's public restriction filter.
 * @since 3.5.0
 * @param array $keys Native allowed top-level keys.
 * @param int|string $post_id Exact native save owner.
 * @return array Other keys remain native; the omitted builder may not authorize a source switch.
 */
function source_drop_builder_root( $keys, $post_id ) {
	return (string) $post_id === (string) $GLOBALS['source_post_id'] ? array_values( array_diff( $keys, array( 'field_skye_sources_fixture' ) ) ) : $keys;
}

/**
 * Simulate an unexpected request mutation from an independent native field validator.
 * @since 3.5.0
 * @param mixed $valid Earlier native validation decision, preserved exactly.
 * @param mixed $value Original sibling input.
 * @param array $field Actual native sibling definition.
 * @param string $input Native field input identity.
 * @return mixed The earlier decision; the source adapter must detect rather than overwrite the mutation.
 */
function source_conflicting_value( $valid, $value, $field, $input ) {
	$_POST['acf']['field_skye_sources_retained_sibling'] = 'Unexpected changed sibling input.';
	return $valid;
}

/**
 * Prepare a real native Core/ACF form context without consuming either provider's nonce.
 * @since 3.5.0
 * @param string $mode Explicit builder or editor source.
 * @param string $title Unsaved flexible-layout leaf.
 * @return array Exact raw POST fixture before native restriction and validation.
 */
function source_posted_values( $mode, $title ) {
	$id = $GLOBALS['source_post_id'];
	return array(
		'action' => 'editpost', 'post_ID' => (string) $id, 'skye_content_source_post' => (string) $id,
		'_acf_post_id' => (string) $id, '_acf_screen' => 'post', '_acf_nonce' => wp_create_nonce( 'post' ),
		'_wpnonce' => wp_create_nonce( 'update-post_' . $id ),
		'skye_content_source_nonce' => wp_create_nonce( 'skye-content-source-' . $id ), 'skye_content_source' => $mode,
		'acf' => array(
			'field_skye_sources_fixture' => array( array( 'acf_fc_layout' => 'fixture', 'field_skye_sources_fixture_title' => $title ) ),
			'field_skye_sources_retained_sibling' => 'Retained sibling value.',
			'field_skye_sources_unrelated_unrendered' => 'Never restored after native input restriction.',
		),
	);
}

/**
 * Start one fresh simulated request and let the actual native ACF post handler verify and save it.
 * @since 3.5.0
 * @param string $mode Chosen source.
 * @param string $title Current unsaved builder leaf.
 * @param string $body Current unsaved native editor body.
 * @return array Observed provider errors after the real save hook sequence.
 */
function source_native_save( $mode, $title, $body ) {
	// Only the test resets state between distinct simulated HTTP requests; production never clears errors.
	acf_reset_validation_errors();
	$_POST = source_posted_values( $mode, $title );
	$result = wp_update_post( array( 'ID' => $GLOBALS['source_post_id'], 'post_content' => $body ), true );
	if ( is_wp_error( $result ) ) {
		throw new RuntimeException( $result->get_error_message() );
	}
	return acf_get_validation_errors() ?: array();
}

$source_previous_post = $_POST;
$source_previous_request = $_REQUEST;
$source_previous_get = $_GET;
$source_previous_user = get_current_user_id();
$source_previous_global = $GLOBALS['post'] ?? null;
$source_admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( ! $source_admins ) {
	throw new RuntimeException( 'The disposable installation requires its native administrator.' );
}
add_filter( 'pre_http_request', 'source_block_http', PHP_INT_MAX );
add_filter( 'pre_wp_mail', 'source_block_mail', PHP_INT_MAX );
add_filter( 'template_directory', 'source_parent_directory' );
add_filter( 'stylesheet_directory', 'source_child_directory' );
add_filter( 'skye_content_sources', 'source_configuration', 10, 2 );
wp_set_current_user( (int) $source_admins[0] );
$_POST = array();

try {
	$source_post_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Disposable Skye source fixture', 'post_content' => '<p>Native editor baseline.</p>' ), true );
	if ( is_wp_error( $source_post_id ) ) {
		throw new RuntimeException( $source_post_id->get_error_message() );
	}
	$source_post_id = (int) $source_post_id;
	source_check( ContentSources::mode( $source_post_id ) === 'editor', 'An ordinary existing editor page keeps native editor mode.' );
	update_post_meta( $source_post_id, '_wp_page_template', 'templates/pagebuilder.php' );
	source_check( ContentSources::mode( $source_post_id ) === 'builder', 'The historical builder template retains builder as its inferred source.' );
	update_post_meta( $source_post_id, ContentSources::META_KEY, 'editor' );
	source_check( ContentSources::mode( $source_post_id ) === 'editor', 'An explicit native editor choice applies to the builder template.' );
	update_post_meta( $source_post_id, ContentSources::META_KEY, 'unsupported' );
	source_check( ContentSources::mode( $source_post_id ) === 'builder', 'Unknown source metadata retains the historical template decision.' );
	delete_post_meta( $source_post_id, ContentSources::META_KEY );
	update_post_meta( $source_post_id, '_wp_page_template', 'default' );
	source_check( ContentSources::mode( $source_post_id ) === 'editor' && get_post_meta( $source_post_id, ContentSources::META_KEY, true ) === '', 'Reading a default never creates a metadata migration.' );
	$source_provider = function_exists( 'acf_get_field_type' ) ? acf_get_field_type( 'flexible_content' ) : null;
	$source_has_provider = $source_provider instanceof acf_field && get_class( $source_provider ) === 'acf_field_flexible_content' &&
		$source_provider->name === 'flexible_content';
	if ( ! defined( 'ACF_VERSION' ) || ACF_VERSION !== '6.8.11' || ! $source_has_provider ) {
		source_check( ContentSources::validation_supported() === false, 'Without the reviewed original ACF PRO the full native path remains active.' );
		if ( ! $source_has_provider && function_exists( 'acf_add_local_field_group' ) ) {
			acf_add_local_field_group( array(
				'key' => 'group_skye_sources_fixture', 'title' => 'Unsupported disposable Skye source fields',
				'fields' => array( array( 'key' => 'field_skye_sources_fixture', 'name' => 'skye_sources_fixture', 'label' => 'Fixture sections', 'type' => 'flexible_content' ) ),
				'location' => array( array( array( 'param' => 'post', 'operator' => '==', 'value' => (string) $source_post_id ) ) ),
			) );
			source_check( ( acf_get_field( 'field_skye_sources_fixture' )['type'] ?? null ) === 'flexible_content' &&
				ContentSources::field( get_post( $source_post_id ) ) === array(), 'An imported PRO definition without its original field implementation never enables source handling.' );
		}
		echo 'PASS ' . $source_checks . " native source defaults; ACF PRO 6.8.11 lifecycle not available in this fixture.\n";
		return;
	}
	source_check( get_class( $source_provider ) === 'acf_field_flexible_content', 'The actual native registry exposes the implemented original flexible-content field rather than a descriptor.' );
	acf_add_local_field_group( array(
		'key' => 'group_skye_sources_fixture', 'title' => 'Disposable Skye source fields',
		'fields' => array( array(
			'key' => 'field_skye_sources_fixture', 'name' => 'skye_sources_fixture', 'label' => 'Fixture sections', 'type' => 'flexible_content', 'required' => 1,
			'layouts' => array( 'layout_skye_sources_fixture' => array(
				'key' => 'layout_skye_sources_fixture', 'name' => 'fixture', 'label' => 'Fixture',
				'sub_fields' => array( array( 'key' => 'field_skye_sources_fixture_title', 'name' => 'fixture_title', 'label' => 'Fixture heading', 'type' => 'text', 'required' => 1 ) ),
			) ),
		), array( 'key' => 'field_skye_sources_retained_sibling', 'name' => 'fixture_sibling', 'label' => 'Unrelated fixture field', 'type' => 'text' ) ),
		'location' => array( array( array( 'param' => 'post', 'operator' => '==', 'value' => (string) $source_post_id ) ) ),
	) );
	$post = get_post( $source_post_id );
	source_check( ( ContentSources::field( $post )['key'] ?? null ) === 'field_skye_sources_fixture', 'The exact applicable configured root field is selected through native ACF groups.' );
	source_check( ContentSources::validation_supported(), 'Only the exact original 6.8.11 post validator, inert menu/widget callbacks and owned window are admitted.' );
	$values = source_posted_values( 'editor', '' )['acf']['field_skye_sources_fixture'];
	$errors = source_native_save( 'editor', '', '<p>New unsaved editor draft.</p>' );
	source_check( ! $errors, 'An unfinished inactive builder does not block a valid native editor save.' );
	source_check( $_POST['acf']['field_skye_sources_fixture'] === $values, 'The exact own builder array is restored after native validation.' );
	source_check( ! isset( $_POST['acf']['field_skye_sources_unrelated_unrendered'] ), 'Already rejected native field keys are never restored.' );
	source_check( get_post_field( 'post_content', $source_post_id ) === '<p>New unsaved editor draft.</p>', 'Core persists the new native editor value.' );
	$stored = get_field( 'field_skye_sources_fixture', $source_post_id, false );
	source_check( is_array( $stored ) && $stored[0]['field_skye_sources_fixture_title'] === '', 'Original ACF persists the new unfinished inactive builder draft.' );
	source_check( ContentSources::mode( $source_post_id ) === 'editor', 'Source choice is saved only after the native ACF save.' );
	$errors = source_native_save( 'builder', '', '<p>Another retained editor draft.</p>' );
	source_check( ! empty( $errors ) && ContentSources::mode( $source_post_id ) === 'editor', 'Activating the unfinished builder re-enables original child validation and keeps the previous source on failure.' );
	$errors = source_native_save( 'builder', 'New unsaved builder heading', '<p>Retained editor after switch.</p>' );
	source_check( ! $errors && ContentSources::mode( $source_post_id ) === 'builder', 'A completed builder can become the active source through the native save.' );
	$stored = get_field( 'field_skye_sources_fixture', $source_post_id, false );
	source_check( $stored[0]['field_skye_sources_fixture_title'] === 'New unsaved builder heading' && get_post_field( 'post_content', $source_post_id ) === '<p>Retained editor after switch.</p>', 'Both newly edited sources remain stored after activation and readback.' );
	add_filter( 'acf/form/post/allowed_field_keys', 'source_drop_builder_root', 10, 2 );
	$errors = source_native_save( 'editor', 'This root must be dropped.', '<p>Core retained while the builder root is restricted.</p>' );
	source_check( ! $errors && ! array_key_exists( 'field_skye_sources_fixture', $_POST['acf'] ), 'The original provider genuinely removes the builder root while accepting another applicable field.' );
	source_check( get_field( 'field_skye_sources_retained_sibling', $source_post_id, false ) === 'Retained sibling value.', 'The native ACF save actually persists the unrelated allowed field.' );
	source_check( ContentSources::mode( $source_post_id ) === 'builder', 'A root omitted by native restriction cannot authorize changing the saved source.' );
	$stored = get_field( 'field_skye_sources_fixture', $source_post_id, false );
	source_check( $stored[0]['field_skye_sources_fixture_title'] === 'New unsaved builder heading', 'The removed posted root never replaces the existing builder draft.' );
	remove_filter( 'acf/form/post/allowed_field_keys', 'source_drop_builder_root', 10 );
	add_filter( 'acf/validate_value/key=field_skye_sources_retained_sibling', 'source_conflicting_value', 10, 4 );
	$errors = source_native_save( 'editor', '', '<p>Core draft before validation conflict.</p>' );
	$messages = implode( '\n', array_column( $errors, 'message' ) );
	source_check( str_contains( $messages, 'Die Prüfung wurde abgebrochen' ) && ! str_contains( $messages, 'lade die Seite' ) && ! str_contains( $messages, 'neu laden' ), 'Recovery explains the aborted validation without asking the user to discard unsaved inputs by reloading.' );
	source_check( $_POST['acf']['field_skye_sources_retained_sibling'] === 'Unexpected changed sibling input.' && ! array_key_exists( 'field_skye_sources_fixture', $_POST['acf'] ), 'An unknown mutation is neither overwritten nor claimed to have been recovered.' );
	source_check( ContentSources::mode( $source_post_id ) === 'builder', 'A recovery conflict never changes the published source.' );
	remove_filter( 'acf/validate_value/key=field_skye_sources_retained_sibling', 'source_conflicting_value', 10 );
	$_POST = source_posted_values( 'editor', '' );
	$_POST['_acf_widget_id'] = 'foreign-widget';
	source_check( ! ContentSources::validation_supported(), 'The original widget validator is admitted only while its actual request context is inactive.' );
	unset( $_POST['_acf_widget_id'] );
	$_POST['menu-item-acf'] = array( '123' => array( 'field_other' => 'foreign' ) );
	source_check( ! ContentSources::validation_supported(), 'The original menu validator is admitted only while its actual request context is inactive.' );
	unset( $_POST['menu-item-acf'] );
	$_GET['meta-box-loader'] = '1';
	source_check( ! ContentSources::validation_supported(), 'A Gutenberg metabox-loader request cannot borrow the Classic Editor validation contract.' );
	unset( $_GET['meta-box-loader'] );
	add_action( 'acf/validate_save_post', 'source_earlier_error', 1 );
	$errors = source_native_save( 'editor', '', '<p>Core retained during independent error.</p>' );
	source_check( in_array( 'Earlier independent error.', array_column( $errors, 'message' ), true ), 'Earlier independent errors survive without reset or replacement.' );
	source_check( ContentSources::mode( $source_post_id ) === 'builder', 'An unrelated validation error prevents publishing the new source choice.' );
	remove_action( 'acf/validate_save_post', 'source_earlier_error', 1 );
	add_action( 'acf/validate_save_post', 'source_unknown_validator', 5 );
	source_check( ! ContentSources::validation_supported() && ContentSources::editor_context( get_post( $source_post_id ) ) === array(), 'A foreign callback inside the validation window retains both visible interfaces.' );
	$errors = source_native_save( 'editor', '', '<p>Core draft with unknown provider.</p>' );
	source_check( ! empty( $errors ), 'An unknown window retains the complete native required-field validation.' );
	remove_action( 'acf/validate_save_post', 'source_unknown_validator', 5 );
	remove_filter( 'acf/form/post/allowed_field_keys', 'source_drop_builder_root', 10 );
	remove_filter( 'acf/validate_value/key=field_skye_sources_retained_sibling', 'source_conflicting_value', 10 );
	acf_reset_validation_errors();
	$_POST = source_posted_values( 'editor', '' );
	$_POST['skye_content_source_nonce'] = 'invalid';
	$_POST['_acf_nonce'] = false;
	acf_validate_save_post();
	source_check( (bool) acf_get_validation_errors(), 'An invalid independent source nonce cannot shield native validation.' );
	source_check( $_POST['acf']['field_skye_sources_fixture'] === $values, 'A refused intent never removes the posted root values.' );
	acf_reset_validation_errors();
	$_POST = source_posted_values( 'editor', '' );
	$_POST['_acf_nonce'] = false;
	$_POST['skye_content_source_post'] = (string) ( $source_post_id + 1 );
	acf_validate_save_post();
	source_check( (bool) acf_get_validation_errors(), 'A foreign post attribution cannot borrow the current source nonce.' );
	acf_reset_validation_errors();
	$_POST = source_posted_values( 'editor', '' );
	$_POST['_acf_nonce'] = false;
	wp_set_current_user( 0 );
	acf_validate_save_post();
	source_check( (bool) acf_get_validation_errors(), 'An unauthorized caller retains full native validation.' );
	wp_set_current_user( (int) $source_admins[0] );
	acf_reset_validation_errors();
	$_POST = array();
	$source_child = sys_get_temp_dir() . '/skye-source-layout-' . bin2hex( random_bytes( 8 ) );
	mkdir( $source_child . '/components/pagebuilder', 0700, true );
	$layout = '<?php /** Render the disposable native source fixture. */ ?><p>Fixture builder: <?php echo esc_html( $row["fixture_title"] ?? "" ); ?></p>';
	file_put_contents( $source_child . '/components/pagebuilder/fixture.php', $layout );
	wp_set_template_globals();
	$GLOBALS['post'] = get_post( $source_post_id );
	ob_start();
	get_component( 'page', 'content' );
	$rendered = ob_get_clean();
	source_check( str_contains( $rendered, 'Fixture builder: New unsaved builder heading' ) && ! str_contains( $rendered, 'Core draft' ), 'The actual component dispatcher renders the builder without inactive native content.' );
	$source_profile_missing = true;
	source_check( ContentSources::analysis_source( get_post( $source_post_id ) ) === 'builder', 'A stored builder choice stays bound when its field profile becomes unavailable.' );
	ob_start();
	get_component( 'page', 'content' );
	$rendered = ob_get_clean();
	source_check( ! str_contains( $rendered, 'Core draft' ) && ! str_contains( $rendered, 'Fixture builder:' ), 'A missing builder profile exposes neither an ambiguous alias nor the inactive editor.' );
	$source_profile_missing = false;
	update_field( 'field_skye_sources_fixture', array(), $source_post_id );
	ob_start();
	get_component( 'page', 'content' );
	$rendered = ob_get_clean();
	source_check( ! str_contains( $rendered, 'Core draft' ), 'A known empty active builder never exposes the retained inactive editor.' );
	update_post_meta( $source_post_id, ContentSources::META_KEY, 'editor' );
	$GLOBALS['post'] = get_post( $source_post_id );
	ob_start();
	get_component( 'page', 'content' );
	$rendered = ob_get_clean();
	source_check( substr_count( $rendered, 'Core draft with unknown provider.' ) === 1 && ! str_contains( $rendered, 'Fixture builder:' ), 'The actual native content fallback renders once without source recursion.' );
	source_check( $source_http === 0, 'Source resolution, native saves and rendering perform no HTTP.' );
	echo 'PASS ' . $source_checks . " native source, ACF save/validation, restricted-input, error and actual-template assertions; all mail blocked.\n";
} finally {
	$_POST = array();
	if ( $source_post_id ) {
		wp_delete_post( $source_post_id, true );
	}
	if ( function_exists( 'acf_remove_local_field_group' ) ) {
		acf_remove_local_field_group( 'group_skye_sources_fixture' );
	}
	if ( $source_child ) {
		unlink( $source_child . '/components/pagebuilder/fixture.php' );
		rmdir( $source_child . '/components/pagebuilder' );
		rmdir( $source_child . '/components' );
		rmdir( $source_child );
	}
	remove_filter( 'skye_content_sources', 'source_configuration', 10 );
	remove_filter( 'pre_http_request', 'source_block_http', PHP_INT_MAX );
	remove_filter( 'pre_wp_mail', 'source_block_mail', PHP_INT_MAX );
	remove_filter( 'template_directory', 'source_parent_directory' );
	remove_filter( 'stylesheet_directory', 'source_child_directory' );
	wp_set_template_globals();
	remove_action( 'acf/validate_save_post', 'source_earlier_error', 1 );
	remove_action( 'acf/validate_save_post', 'source_unknown_validator', 5 );
	$_POST = $source_previous_post;
	$_REQUEST = $source_previous_request;
	$_GET = $source_previous_get;
	$GLOBALS['post'] = $source_previous_global;
	wp_set_current_user( $source_previous_user );
}
