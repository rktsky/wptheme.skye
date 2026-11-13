<?php
/** Exercise the real missing-component helper with original Core hooks and template lookup, without a site or database. */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}
$core = realpath( getenv( 'SKYE_WORDPRESS_CORE' ) ?: '' );
if ( ! $core || ! is_file( $core . '/wp-includes/template.php' ) ) {
	throw new RuntimeException( 'Set SKYE_WORDPRESS_CORE to an unchanged original WordPress directory.' );
}
define( 'ABSPATH', $core . '/' );
define( 'WPINC', 'wp-includes' );
require ABSPATH . WPINC . '/compat.php';
require ABSPATH . WPINC . '/plugin.php';
require ABSPATH . WPINC . '/functions.php';
require ABSPATH . WPINC . '/theme.php';
require ABSPATH . WPINC . '/template.php';
require dirname( __DIR__ ) . '/lib/rocketsky/helpers.php';

$root = sys_get_temp_dir() . '/skye-component-resolver-' . bin2hex( random_bytes( 8 ) );
$wp_stylesheet_path = $root . '/child';
$wp_template_path = $root . '/parent';
$checks = 0;
$calls = array();
$resolved = false;

/**
 * Assert one actual helper result without hiding native diagnostics.
 * @since 3.5.1
 * @param bool $condition Observed result.
 * @param string $message Required behavior.
 * @return void
 * @throws RuntimeException For a failed boundary.
 */
function resolver_check( $condition, $message ) {
	$GLOBALS['checks']++;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Resolve a controlled local path using the original native filter contract.
 * @since 3.5.1
 * @param string|false $missing Earlier missing template.
 * @param string $name Validated combined component name.
 * @param array $args Explicit context received by the real helper.
 * @return mixed Controlled valid or adverse resolver response.
 * @throws RuntimeException For the explicit callback-failure case.
 */
function resolver_fixture( $missing, $name, $args ) {
	$GLOBALS['calls'][] = array( $missing, $name, $args );
	if ( 'throw' === $GLOBALS['resolved'] ) {
		throw new RuntimeException( 'Synthetic resolver failure.' );
	}
	return $GLOBALS['resolved'];
}

try {
	mkdir( $wp_stylesheet_path . '/components/card', 0700, true );
	mkdir( $wp_template_path . '/components', 0700, true );
	file_put_contents( $root . '/plugin.php', '<?php echo $value; echo "|" . $name; echo "|" . basename( $template );' );
	file_put_contents( $root . '/plugin.txt', 'Never execute a non-PHP component.' );
	add_filter( 'skye_component_template', 'resolver_fixture', 10, 3 );
	resolver_check( 1 === SKYE_COMPONENT_RESOLVER_VERSION, 'The resolver contract is explicitly advertised.' );
	$resolved = $root . '/plugin.php';
	$context = array( 'value' => 'First row', 'name' => 'replace', 'template' => '/replaced.php', 'args' => array() );
	resolver_check( 'First row|missing|plugin.php' === skye_component_html( 'missing', false, $context ), 'One-part path preserves explicit data and protected loader locals.' );
	resolver_check( array( false, 'missing', $context ) === $calls[0], 'The callback receives the exact validated name and context.' );
	resolver_check( 'Second row|pagebuilder/missing|plugin.php' === skye_component_html( 'pagebuilder', 'missing', array( 'value' => 'Second row' ) ), 'Three-argument calls hand off each fresh explicit row.' );
	file_put_contents( $root . '/plain.php', '<?php echo "Plain component";' );
	$resolved = $root . '/plain.php';
	resolver_check( 'Plain component' === skye_component_html( 'legacy' ) && 'Plain component' === skye_component_html( 'legacy', '' ), 'One- and two-argument legacy calls retain their signatures.' );
	file_put_contents( $wp_template_path . '/components/card.php', '<?php echo "Parent component";' );
	file_put_contents( $wp_stylesheet_path . '/components/card.php', '<?php echo "Child component";' );
	$count = count( $calls );
	resolver_check( 'Child component' === skye_component_html( 'card' ) && $count === count( $calls ), 'Existing child files bypass every plugin fallback.' );
	unlink( $wp_stylesheet_path . '/components/card.php' );
	resolver_check( 'Parent component' === skye_component_html( 'card' ) && $count === count( $calls ), 'Existing parent files retain native precedence.' );
	file_put_contents( $wp_stylesheet_path . '/components/card/card.php', '<?php echo "Nested legacy component";' );
	unlink( $wp_template_path . '/components/card.php' );
	resolver_check( 'Nested legacy component' === skye_component_html( 'card' ) && $count === count( $calls ), 'The legacy nested theme fallback precedes plugins.' );
	foreach ( array( false, null, true, 12, array(), '', $root, $root . '/absent.php', $root . '/plugin.txt', 'php://filter/resource=' . $root . '/plugin.php', "bad\0path.php", str_repeat( 'x', 4097 ), 'throw' ) as $resolved ) {
		resolver_check( '' === skye_component_html( 'missing' ), 'Invalid, missing, non-PHP or failed fallback is refused.' );
	}
	$count = count( $calls );
	foreach ( array( '../escape', '/absolute', 'invalid.php', 'bad//name', 'bad\\name', '' ) as $invalid ) {
		resolver_check( false === get_component( $invalid ) && $count === count( $calls ), 'Invalid component names never reach a resolver.' );
	}
	file_put_contents( $root . '/failure.php', '<?php echo "Partial"; throw new RuntimeException("Template failure");' );
	$resolved = $root . '/failure.php';
	$level = ob_get_level();
	try {
		skye_component_html( 'missing' );
		throw new LogicException( 'A template exception must propagate.' );
	} catch ( RuntimeException $error ) {
		resolver_check( 'Template failure' === $error->getMessage() && $level === ob_get_level(), 'Only resolver failures are suppressed; failed template rendering restores output buffers.' );
	}
	echo json_encode( array( 'suite' => 'native-component-resolver', 'checks' => $checks, 'result' => 'passed' ), JSON_THROW_ON_ERROR ) . "\n";
} finally {
	remove_filter( 'skye_component_template', 'resolver_fixture', 10 );
	foreach ( array( $root . '/plugin.php', $root . '/plugin.txt', $root . '/plain.php', $root . '/failure.php', $wp_stylesheet_path . '/components/card/card.php', $wp_stylesheet_path . '/components/card.php', $wp_template_path . '/components/card.php' ) as $file ) {
		if ( is_file( $file ) ) {
			unlink( $file );
		}
	}
	foreach ( array( $wp_stylesheet_path . '/components/card', $wp_stylesheet_path . '/components', $wp_stylesheet_path, $wp_template_path . '/components', $wp_template_path, $root ) as $directory ) {
		if ( is_dir( $directory ) ) {
			rmdir( $directory );
		}
	}
}
