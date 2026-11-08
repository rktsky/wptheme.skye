<?php
/** Exercise actual editor and ACF authorization callbacks using Core's hook engine. */

if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit;
}


$core = getenv( 'SKYE_WORDPRESS_CORE' );
if ( ! $core || ! is_file( $core . '/wp-includes/plugin.php' ) ) {
	throw new RuntimeException( 'Set SKYE_WORDPRESS_CORE to an unmodified WordPress source directory.' );
}
require $core . '/wp-includes/plugin.php';
$GLOBALS['settings_admin'] = false;
$GLOBALS['settings_pages'] = $GLOBALS['settings_errors'] = $GLOBALS['settings_writes'] = array();
$GLOBALS['native_options'] = array( 'theme_options_theme_logo' => '0', 'theme_options_favicon' => 0 );
$GLOBALS['checks'] = 0;

/**
 * Assert observed behavior so authorization failures cannot be silent.
 *
 * @since 3.0.0
 * @param bool $condition Observed native-boundary result.
 * @param string $message Required contract.
 * @return void
 */
function security_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	$GLOBALS['checks']++;
}

/**
 * Provide identity translation because no site locale or database is booted.
 *
 * @since 3.0.0
 * @param string $text Source label.
 * @param string $domain Translation domain.
 * @return string Unchanged label.
 */
function __( $text, $domain ) {
	return $text;
}

/**
 * Supply escaped translation without replacing the tested authorization logic.
 *
 * @since 3.0.0
 * @param string $text Source label.
 * @param string $domain Translation domain.
 * @return string Escaped label.
 */
function esc_html__( $text, $domain ) {
	return htmlspecialchars( $text );
}

/**
 * Supply a native false callback for actual Core filter dispatch.
 *
 * @since 3.0.0
 * @return bool Always false.
 */
function __return_false() {
	return false;
}

/**
 * Model the current principal while leaving the production capability choice intact.
 *
 * @since 3.0.0
 * @param string $capability Requested native capability.
 * @return bool Whether the fixture principal owns administrative settings rights.
 */
function current_user_can( $capability ) {
	return 'manage_options' === $capability && $GLOBALS['settings_admin'];
}

/**
 * Capture registration so the production menu's native capability can be inspected.
 *
 * @since 3.0.0
 * @param array $page Actual ACF option page descriptor.
 * @return void
 */
function acf_add_options_page( $page ) {
	$GLOBALS['settings_pages'][] = $page;
}

/**
 * Capture validation failures without booting or submitting a real ACF form.
 *
 * @since 3.0.0
 * @param string $input Native field selector.
 * @param string $message Native validation message.
 * @return void
 */
function acf_add_validation_error( $input, $message ) {
	$GLOBALS['settings_errors'][] = $message;
}

/**
 * Surface the production denial as a catchable event without exiting the suite.
 *
 * @since 3.0.0
 * @param string $message Native denial message.
 * @param string $title Native response title.
 * @param array $args Native response arguments.
 * @return void
 */
function wp_die( $message, $title, $args ) {
	throw new RuntimeException( $message, $args['response'] );
}

/**
 * Match Core's scalar unslashing boundary for the submitted fixed identity.
 *
 * @since 3.0.0
 * @param string $value Posted owner identity.
 * @return string Unslashed identity.
 */
function wp_unslash( $value ) {
	return stripslashes( $value );
}

/**
 * Read the fixture native option store, preserving intentionally empty values.
 *
 * @since 3.0.0
 * @param string $key Native option key.
 * @param mixed $default Missing-option default.
 * @return mixed Stored value or the supplied default.
 */
function get_option( $key, $default = false ) {
	return $GLOBALS['native_options'][ $key ] ?? $default;
}

/**
 * Observe the finite native priming request without emulating its database cache.
 *
 * Real cache/query semantics are verified separately in the native site fixture.
 *
 * @since 3.5.0
 * @param string[] $options Explicit native option names requested by production.
 * @return void
 */
function wp_prime_option_caches( $options ) {
	security_check( array( 'theme_options_theme_logo', 'theme_options_favicon', 'theme_options_gmakey' ) === $options, 'Only the three owned legacy options are primed.' );
}

/**
 * Supply blog identity so the actual request cache can be tested across switches.
 *
 * @since 3.0.0
 * @return int Current isolated blog identity.
 */
function get_current_blog_id() {
	return $GLOBALS['fixture_blog'] ?? 1;
}

/**
 * Record option writes instead of mutating any real site configuration.
 *
 * @since 3.0.0
 * @param string $key Native option key.
 * @param mixed $value New value.
 * @return void
 */
function update_option( $key, $value ) {
	$GLOBALS['settings_writes'][] = array( $key, $value );
}

/**
 * Record legacy invalidation as an observable storage effect.
 *
 * @since 3.0.0
 * @param string $key Historical transient identity.
 * @return void
 */
function delete_transient( $key ) {
	$GLOBALS['settings_writes'][] = array( 'delete_transient', $key );
}

/**
 * Supply Core's unsigned ID normalization for local scalar fixture values.
 *
 * @since 3.0.0
 * @param mixed $value Native identity value.
 * @return int Nonnegative ID.
 */
function absint( $value ) {
	return abs( (int) $value );
}

/**
 * Reject unknown image IDs instead of making fixture files or metadata.
 *
 * @since 3.0.0
 * @param int $id Native attachment ID.
 * @return bool Whether the fixture represents an image.
 */
function wp_attachment_is_image( $id ) {
	return 12 === $id;
}

/**
 * Record the public support removal, preserving unrelated widget support.
 *
 * @since 3.0.0
 * @param string $feature Native support name.
 * @return void
 */
function remove_theme_support( $feature ) {
	$GLOBALS['removed_support'][] = $feature;
}

/**
 * Represent an earlier plugin's conflicting preference for the block editor.
 *
 * @since 3.0.0
 * @param bool $enabled Earlier editor decision.
 * @return bool A conflicting plugin decision.
 */
function external_block_preference( $enabled ) {
	return true;
}

/**
 * Observe the native priority-10 writer rather than duplicating authorization.
 *
 * @since 3.0.0
 * @param int|string $owner Canonical ACF storage owner.
 * @return void
 */
function acf_native_writer( $owner ) {
	$GLOBALS['settings_writes'][] = array( 'acf_values', $owner );
}

require dirname( __DIR__ ) . '/lib/package/ClassicEditing.php';
require dirname( __DIR__ ) . '/lib/rocketsky/acf/options.php';
new Rocketsky\Skye\Packages\ClassicEditing();
foreach ( array( 'use_block_editor_for_post', 'use_block_editor_for_post_type', 'use_widgets_block_editor' ) as $hook ) {
	add_filter( $hook, 'external_block_preference', 100 );
	security_check( false === apply_filters( $hook, true, (object) array() ), $hook . ' enforces classic editing after the earlier preference' );
}
foreach ( array( 'classic_editor_enabled_editors_for_post', 'classic_editor_enabled_editors_for_post_type' ) as $hook ) {
	$editors = apply_filters( $hook, array( 'classic_editor' => true, 'block_editor' => true ) );
	security_check( true === $editors['classic_editor'] && false === $editors['block_editor'], 'Optional plugin switch actions only offer classic editing' );
	$editors = apply_filters( $hook, array( 'classic_editor' => false, 'block_editor' => true ) );
	security_check( false === $editors['classic_editor'], 'Unsupported classic editors are not enabled' );
}
do_action( 'after_setup_theme' );
security_check( array( 'widgets-block-editor' ) === $GLOBALS['removed_support'], 'Classic widget support survives editor policy' );
do_action( 'acf/init' );
security_check( 3 === count( $GLOBALS['settings_pages'] ), 'Historical settings pages remain available' );
foreach ( $GLOBALS['settings_pages'] as $page ) {
	security_check( 'manage_options' === $page['capability'], 'All shared settings pages require administrative rights' );
}
add_action( 'acf/save_post', 'acf_native_writer', 10 );
foreach ( array( '_acf_post_id', 'post_id' ) as $key ) {
	$_POST = array( $key => 'theme_options' );
	$GLOBALS['settings_errors'] = array();
	do_action( 'acf/validate_save_post' );
	security_check( 1 === count( $GLOBALS['settings_errors'] ), 'Both native identity keys reject unauthorized settings' );
}
try {
	do_action( 'acf/save_post', 'theme_options' );
	throw new RuntimeException( 'Expected settings denial was absent' );
} catch ( RuntimeException $error ) {
	security_check( 403 === $error->getCode(), 'Canonical save denies unauthorized principal' );
}
security_check( array() === $GLOBALS['settings_writes'], 'Denied save never reaches priority-10 values or favicon writes' );
$GLOBALS['settings_admin'] = true;
$GLOBALS['settings_errors'] = array();
do_action( 'acf/validate_save_post' );
do_action( 'acf/save_post', 'theme_options' );
security_check( array() === $GLOBALS['settings_errors'], 'Administrator form validation passes' );
security_check( array( 'acf_values', 'theme_options' ) === $GLOBALS['settings_writes'][0], 'Authorized native values are stored before invalidation' );
security_check( array( 'site_icon', 0 ) === end( $GLOBALS['settings_writes'] ), 'Authorized favicon deletion preserves the native value' );
unset( $GLOBALS['native_options']['theme_options_favicon'] );
$GLOBALS['settings_writes'] = array();
do_action( 'acf/save_post', 'theme_options' );
foreach ( $GLOBALS['settings_writes'] as $write ) {
	security_check( 'site_icon' !== $write[0], 'An absent legacy favicon preserves the independently configured native site icon' );
}
$GLOBALS['settings_admin'] = false;
$GLOBALS['settings_writes'] = array();
do_action( 'acf/save_post', 123 );
security_check( array( array( 'acf_values', 123 ) ) === $GLOBALS['settings_writes'], 'Ordinary records retain their separate native authorization path' );
security_check( '0' === get_theme_options()['theme_logo'], 'Native option reader preserves stored zero' );
$GLOBALS['fixture_blog'] = 2;
$GLOBALS['native_options']['theme_options_theme_logo'] = 'Second blog';
do_action( 'switch_blog', 2, 1, 'switch' );
security_check( 'Second blog' === get_theme_options()['theme_logo'], 'Native blog switch refreshes both the request cache and legacy global' );
$GLOBALS['fixture_blog'] = 1;
$GLOBALS['native_options']['theme_options_theme_logo'] = '0';
do_action( 'switch_blog', 1, 2, 'restore' );
security_check( '0' === $GLOBALS['theme_options']['theme_logo'], 'Restoring a blog never reuses another blog settings' );
$writes_before_native_hooks = $GLOBALS['settings_writes'];
foreach ( array( 'added_option', 'updated_option', 'deleted_option' ) as $hook ) {
	security_check( 10 === has_action( $hook, 'skye_invalidate_theme_options_cache' ), 'The actual cache invalidator is registered on ' . $hook );
	foreach ( array( 'theme_logo' => '0', 'favicon' => false, 'gmakey' => '' ) as $key => $value ) {
		$GLOBALS['native_options'][ 'theme_options_' . $key ] = 'BEFORE_NATIVE_HOOK';
		$GLOBALS['theme_options'] = null;
		get_theme_options();
		if ( 'deleted_option' === $hook ) {
			unset( $GLOBALS['native_options'][ 'theme_options_' . $key ] );
		} else {
			$GLOBALS['native_options'][ 'theme_options_' . $key ] = $value;
		}
		if ( 'deleted_option' === $hook ) {
			do_action( $hook, 'theme_options_' . $key );
		} elseif ( 'added_option' === $hook ) {
			do_action( $hook, 'theme_options_' . $key, $value );
		} else {
			do_action( $hook, 'theme_options_' . $key, 'BEFORE_NATIVE_HOOK', $value );
		}
		security_check( ( 'deleted_option' === $hook ? false : $value ) === get_theme_options()[ $key ], $hook . ' invalidates the exact ' . $key . ' read cache while preserving raw empty values' );
	}
	$cached_options = get_theme_options();
	$GLOBALS['native_options']['theme_options_gmakey'] = 'UNRELATED_OPTION_MUST_NOT_REFRESH';
	do_action( $hook, 'theme_options_gmakey_extra', 'before', 'after' );
	security_check( $cached_options === get_theme_options(), 'Unrelated option names retain the bounded read cache on ' . $hook );
}
security_check( $writes_before_native_hooks === $GLOBALS['settings_writes'], 'Native cache invalidation has no favicon, transient, or policy write side effects' );
echo 'Security passed (' . $GLOBALS['checks'] . " checks).\n";
