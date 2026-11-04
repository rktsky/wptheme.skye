<?php
/** Verify environment mail boundaries through real Core hooks and wp_mail without any transport. */

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit;
}
$core = getenv( 'SKYE_WORDPRESS_CORE' );
if ( ! is_string( $core ) || ! is_file( $core . '/wp-includes/pluggable.php' ) ) {
	throw new RuntimeException( 'Set SKYE_WORDPRESS_CORE to an official unmodified WordPress source directory.' );
}
define( 'ABSPATH', rtrim( $core, '/' ) . '/' );
define( 'WPINC', 'wp-includes' );
define( 'WP_RUN_CORE_TESTS', true );
require ABSPATH . WPINC . '/plugin.php';
require ABSPATH . WPINC . '/load.php';
require ABSPATH . WPINC . '/pluggable.php';
require dirname( __DIR__ ) . '/lib/package/EnvironmentMail.php';
new Rocketsky\Skye\Packages\EnvironmentMail();
$mail_checks = 0;

/**
 * Assert observable native effects without reading or exposing mail data.
 *
 * @since 3.1.0
 * @param bool $condition Actual native result.
 * @param string $reason Required public policy boundary.
 * @return void
 */
function environment_mail_check( $condition, $reason ) {
	global $mail_checks;
	if ( ! $condition ) {
		throw new RuntimeException( $reason );
	}
	$mail_checks++;
}

/**
 * Stop the actual Core function after observing only its preemption decision.
 *
 * This final test-only guard prevents every transport, including production
 * and opt-out cases. It never receives recipients or message attributes.
 *
 * @since 3.1.0
 * @param mixed $result Actual native result after the production observer.
 * @return bool Always false so no PHP/SMTP transport can run in a regression.
 */
function environment_mail_transport_probe( $result ) {
	$GLOBALS['mail_probe_result'] = $result;
	return false;
}

/**
 * Exercise the actual unmodified wp_mail implementation with transport stopped.
 *
 * @since 3.1.0
 * @return mixed Observed native policy result before the final safety interceptor.
 */
function environment_mail_native_result() {
	add_filter( 'pre_wp_mail', 'environment_mail_transport_probe', PHP_INT_MAX );
	try {
		wp_mail( 'fixture@example.invalid', 'Synthetic fixture', 'Synthetic fixture' );
		return $GLOBALS['mail_probe_result'];
	} finally {
		remove_filter( 'pre_wp_mail', 'environment_mail_transport_probe', PHP_INT_MAX );
	}
}

/**
 * Return a configurable policy through the actual public filter.
 *
 * @since 3.1.0
 * @param bool $enabled Existing default policy.
 * @return bool Controlled opt-out or opt-in value.
 */
function environment_mail_policy( $enabled ) {
	$GLOBALS['mail_policy_calls']++;
	return $GLOBALS['mail_policy_value'];
}

$email = array( 'abort_email' => false, 'opaque' => new stdClass() );
$previous_environment = getenv( 'WP_ENVIRONMENT_TYPE' );
try {
	foreach ( array( 'production', 'local', 'development' ) as $environment ) {
		putenv( 'WP_ENVIRONMENT_TYPE=' . $environment );
		environment_mail_check( environment_mail_native_result() === null, 'Other environments retain native mail: ' . $environment );
		environment_mail_check( apply_filters( 'gform_pre_send_email', $email ) === $email, 'Other environments preserve complete GF arguments: ' . $environment );
	}
	putenv( 'WP_ENVIRONMENT_TYPE=staging' );
	environment_mail_check( environment_mail_native_result() === false, 'Actual native wp_mail is preempted on staging.' );
	environment_mail_check( apply_filters( 'gform_pre_send_email', $email ) === array( 'abort_email' => true, 'opaque' => $email['opaque'] ), 'Only the native GF abort flag changes on staging.' );
	environment_mail_check( apply_filters( 'pre_wp_mail', true ) === true && apply_filters( 'pre_wp_mail', false ) === false, 'Earlier native preemption results remain unchanged.' );
	$aborted = array( 'abort_email' => true, 'opaque' => $email['opaque'] );
	environment_mail_check( apply_filters( 'gform_pre_send_email', $aborted ) === $aborted, 'An existing provider abort survives staging unchanged.' );
	environment_mail_check( apply_filters( 'gform_pre_send_email', null ) === null, 'Unknown provider argument shapes remain native.' );
	$GLOBALS['mail_policy_calls'] = 0;
	$GLOBALS['mail_policy_value'] = false;
	add_filter( 'skye_block_staging_mail', 'environment_mail_policy' );
	environment_mail_check( environment_mail_native_result() === null, 'The native public filter can opt staging back into its original transport.' );
	environment_mail_check( apply_filters( 'gform_pre_send_email', $email ) === $email, 'The opt-out also preserves native GF arguments.' );
	environment_mail_check( apply_filters( 'gform_pre_send_email', $aborted ) === $aborted, 'Opt-out never clears an earlier provider abort.' );
	putenv( 'WP_ENVIRONMENT_TYPE=production' );
	$GLOBALS['mail_policy_calls'] = 0;
	$GLOBALS['mail_policy_value'] = true;
	environment_mail_check( environment_mail_native_result() === null && apply_filters( 'gform_pre_send_email', $email ) === $email, 'The staging policy cannot force a production block.' );
	environment_mail_check( $GLOBALS['mail_policy_calls'] === 0, 'The staging policy filter is not evaluated in production.' );
	echo 'PASS ' . $mail_checks . " native environment-mail assertions; no transport invoked.\n";
} finally {
	putenv( $previous_environment === false ? 'WP_ENVIRONMENT_TYPE' : 'WP_ENVIRONMENT_TYPE=' . $previous_environment );
}
