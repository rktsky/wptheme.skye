<?php
/** Regress actual core helpers, isolated component contracts, routing and protected render paths. */

if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit;
}

if ( in_array( '--without-acf', $argv, true ) ) {
	define( 'SKYE_CORE_WITHOUT_ACF', true );
}
require __DIR__ . '/core-fixture.php';

/**
 * Turn diagnostics into failures so missing template values cannot pass silently.
 *
 * @since 3.0.0
 * @param int $level Native diagnostic level.
 * @param string $message Native diagnostic description.
 * @param string $file Source filename.
 * @param int $line Source line.
 * @return bool Whether a suppressed diagnostic was handled.
 * @throws ErrorException For an enabled diagnostic.
 */
function core_diagnostic( $level, $message, $file, $line ) {
	if ( error_reporting() & $level ) {
		throw new ErrorException( $message, 0, $level, $file, $line );
	}
	return false;
}
set_error_handler( 'core_diagnostic' );
error_reporting( E_ALL );
CoreFixture::$theme = dirname( __DIR__ );
CoreFixture::$child = sys_get_temp_dir() . '/skye-core-' . bin2hex( random_bytes( 6 ) );
CoreFixture::$user = new WP_User();
$checks = 0;

/**
 * Assert actual observable behavior rather than a duplicate implementation.
 *
 * @since 3.0.0
 * @param bool $condition Observed behavior.
 * @param string $message Required contract.
 * @return void
 * @throws RuntimeException When the production behavior violates the contract.
 */
function core_check( $condition, $message ) {
	$GLOBALS['checks']++;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Count real API-boundary invocations so unintended queries/writes stay visible.
 *
 * @since 3.0.0
 * @param string $name Exact boundary function name.
 * @return int Number of recorded calls.
 */
function core_calls( $name ) {
	$count = 0;
	foreach ( CoreFixture::$calls as $call ) {
		if ( $name === $call[0] ) {
			$count++;
		}
	}
	return $count;
}

require dirname( __DIR__ ) . '/lib/rocketsky/helpers.php';
require dirname( __DIR__ ) . '/lib/titles.php';
require dirname( __DIR__ ) . '/lib/presentation.php';
require dirname( __DIR__ ) . '/lib/rocketsky/rights.php';
require dirname( __DIR__ ) . '/lib/rocketsky/redirectchild.php';
require dirname( __DIR__ ) . '/lib/package/Templates.php';
require dirname( __DIR__ ) . '/lib/package/MenuCache.php';

/**
 * Export child-process boundary calls separately from the actual HTTP body.
 *
 * @since 3.0.0
 * @return void
 */
function core_probe_shutdown() {
	fwrite( STDERR, json_encode( CoreFixture::$calls, JSON_THROW_ON_ERROR ) );
}

/**
 * Execute a real exit-producing handler in an isolated PHP process.
 *
 * The subprocess uses only local fixtures, so a real exit can be distinguished
 * from continued template output without intercepting production control flow.
 *
 * @since 3.0.0
 * @param string $mode Explicit local probe mode.
 * @return array{body:string,calls:array} Captured body and native-boundary trace.
 * @throws RuntimeException On subprocess failure or invalid response evidence.
 */
function core_response_probe( $mode ) {
	$process = proc_open( array( PHP_BINARY, __FILE__, $mode ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
	if ( ! is_resource( $process ) ) {
		throw new RuntimeException( 'Unable to start native response probe.' );
	}
	fclose( $pipes[0] );
	$body = stream_get_contents( $pipes[1] );
	$trace = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	$exit = proc_close( $process );
	if ( 0 !== $exit ) {
		throw new RuntimeException( 'Response probe failed: ' . $trace );
	}
	return array( 'body' => $body, 'calls' => json_decode( $trace, true, 512, JSON_THROW_ON_ERROR ) );
}

if ( in_array( '--redirect-probe', $argv, true ) ) {
	CoreFixture::$flags = array( 'is_page' => true );
	CoreFixture::$template = 'templates/redirectchild.php';
	CoreFixture::$children = array( (object) array( 'ID' => 9 ) );
	register_shutdown_function( 'core_probe_shutdown' );
	skye_redirect_first_child();
	echo 'UNEXPECTED CONTINUED OUTPUT';
	exit;
}
if ( in_array( '--rights-denied-probe', $argv, true ) ) {
	$wp_query = new CoreQuery();
	register_shutdown_function( 'core_probe_shutdown' );
	rights( 'administrator' );
	echo 'UNEXPECTED CONTINUED OUTPUT';
	exit;
}
mkdir( CoreFixture::$child . '/components/probe', 0700, true );
mkdir( CoreFixture::$child . '/views', 0700, true );

try {
	CoreFixture::$meta = array(
		'post' => array( 7 => array( 'zero' => '0', 'false' => false, 'empty' => '', 'array' => array() ) ),
		'user' => array( 7 => array( 'zero' => 'Wrong user value' ), 8 => array( 'field' => 'User value' ) ),
		'term' => array( 9 => array( 'field' => 'Term value' ) ),
		'comment' => array( 10 => array( 'field' => 'Comment value' ) ),
	);
	CoreFixture::$options = array( 'zero' => 'Wrong global option', 'theme_options_field' => 'Theme option', 'options_field' => 'Native ACF option' );
	foreach ( array( 'zero' => '0', 'false' => false, 'empty' => '', 'array' => array() ) as $selector => $expected ) {
		core_check( get( $selector, 7 ) === $expected, 'Preserve explicit post ' . $selector . '.' );
		core_check( get( $selector, 7, false ) === $expected, 'Legacy format flag never changes scalar/list shape.' );
	}
	core_check( 0 === core_calls( 'get_field' ), 'The raw path performs no ACF formatting queries even when present.' );
	core_check( 0 === core_calls( 'get_user_meta' ), 'Post metadata never falls through to a user with the same numeric identity.' );
	core_check( '' === get( 'absent', 7 ), 'Missing native metadata remains native empty metadata.' );
	core_check( 'User value' === get( 'field', 'user_8' ), 'Explicit user identity resolves only user metadata.' );
	core_check( 'Term value' === get( 'field', 'term_9' ), 'Explicit term identity resolves only term metadata.' );
	core_check( 'Comment value' === get( 'field', new WP_Comment() ), 'Native comment objects resolve their own identity.' );
	core_check( 'Theme option' === get( 'field', 'theme_options' ), 'Legacy theme-option identity keeps its native prefix.' );
	core_check( 'Native ACF option' === get( 'field', 'options' ), 'Native options identity keeps its ACF storage prefix.' );
	foreach ( array( 0, -1, 7.5, true, '7e2', '../user_8', array() ) as $invalid ) {
		core_check( false === get( 'zero', $invalid ), 'Malformed identities cannot silently address another record.' );
	}
	core_check( '0' === get( 'zero' ), 'The default identity resolves the actual loop post.' );
	core_check( 'https://example.invalid/image.jpg' === get_image( 4 ), 'Native image resolution stays available.' );
	core_check( false === get_image( 0 ) && false === get_image( 99 ), 'Absent/invalid native images fail without array offsets.' );
	core_check( '' === get_primary_taxonomy_id( 7, 'invalid' ), 'Invalid taxonomy has a defined empty result.' );
	core_check( '' === get_primary_taxonomy_id( 7, 'error' ), 'WP_Error cannot become array access.' );
	core_check( 23 === get_primary_taxonomy_id( 7, 'category' ), 'Absent Yoast uses a single native fallback query.' );
	class_alias( CorePrimaryTerm::class, 'WPSEO_Primary_Term' );
	CoreFixture::$primary = new WP_Error();
	$term_queries = core_calls( 'wp_get_post_terms' );
	core_check( 23 === get_primary_taxonomy_id( 7, 'category' ) && $term_queries + 1 === core_calls( 'wp_get_post_terms' ), 'Provider errors fall back through one actual native term query.' );
	CoreFixture::$primary = 23;
	$term_queries = core_calls( 'wp_get_post_terms' );
	core_check( 23 === get_primary_taxonomy_id( 7, 'category' ) && $term_queries === core_calls( 'wp_get_post_terms' ), 'A valid assigned primary term avoids a second native query.' );
	CoreFixture::$primary = 999;
	core_check( 23 === get_primary_taxonomy_id( 7, 'category' ), 'An unassigned primary term cannot override the native fallback.' );

	file_put_contents( CoreFixture::$child . '/components/probe/probe.php', '<?php echo $value; echo isset( $stale ) ? "LEAK" : ""; echo $name === "probe" ? "SAFE" : "REPLACED";' );
	$stale = 'Caller-only secret';
	$reserved_context = array( 'value' => 'zero', 'name' => 'replace', 'template' => '/tmp/replace' );
	core_check( 'zeroSAFE' === skye_component_html( 'probe', false, $reserved_context ), 'Child override resolves with explicit data and reserved loader names.' );
	core_check( '' === skye_component_html( '../probe', false, array( 'value' => 'bad' ) ), 'Component traversal is rejected before native resolution.' );
	core_check( '' === skye_component_html( 'missing' ), 'Missing components return an empty string without public diagnostics.' );
	file_put_contents( CoreFixture::$child . '/components/probe/fail.php', '<?php echo "partial"; throw new RuntimeException("component failure");' );
	$level = ob_get_level();
	try {
		skye_component_html( 'probe', 'fail' );
		throw new LogicException( 'Missing component exception.' );
	} catch ( RuntimeException $error ) {
		core_check( 'component failure' === $error->getMessage() && $level === ob_get_level(), 'Component exceptions restore caller output buffers.' );
	}
	core_check( rights( 'public', false ) && rights( 'public_only', false ), 'Anonymous public paths remain admitted.' );
	core_check( ! rights( true, false ) && ! rights( 'administrator', false ), 'Anonymous users cannot pass logged-in/role gates.' );
	CoreFixture::$user->authenticated = true;
	CoreFixture::$user->roles = array( 'editor' );
	core_check( rights( true, false ) && rights( array( 'editor' ), false ) && ! rights( 'administrator', false ), 'Legacy login/role behavior stays strict and explicit.' );

	$_GET['ctmenu'] = 'delete';
	ob_start();
	$menu = new Rocketsky\Skye\Packages\MenuCache( array( 'theme_location' => 'primary_navigation', 'active' => true ) );
	$menu_html = ob_get_clean();
	core_check( $menu->data === $menu_html && str_contains( $menu_html, 'primary_navigation' ), 'Menu compatibility delegates to actual native serialization once.' );
	Rocketsky\Skye\Packages\MenuCache::purge();
	core_check( 0 === core_calls( 'delete_transient' ) && 0 === core_calls( 'set_transient' ), 'Anonymous query strings and legacy purge do not mutate cache storage.' );
	unset( $_GET['ctmenu'] );
	$args = (object) skye_navigation_args();
	$other_args = (object) array();
	$item = (object) array( 'classes' => array( 'menu-item-has-children' ), 'title' => 'Parent <unsafe>' );
	$classes = skye_navigation_item_classes( array( 'current-menu-item' ), $item, $args, 0 );
	core_check( in_array( 'current-menu-item', $classes, true ) && in_array( 'nav-item', $classes, true ), 'Native current state is preserved by Bootstrap presentation.' );
	core_check( array( 'foreign' ) === skye_navigation_item_classes( array( 'foreign' ), $item, $other_args, 0 ), 'Unmarked plugin menus retain their native classes.' );
	$native_classes = array( 'menu-item', 'menu-item-type-post_type', 'current-menu-item', 'current-menu-ancestor', 'menu-item-has-children' );
	$marked_classes = skye_navigation_item_classes( $native_classes, $item, $args, 0 );
	core_check( ! in_array( 'menu-item', $marked_classes, true ) && in_array( 'skye-menu-item', $marked_classes, true ), 'Owned LI does not inherit Bootstrap6 full-width interactive menu-item styling.' );
	core_check( ! array_diff( array( 'menu-item-type-post_type', 'current-menu-item', 'current-menu-ancestor', 'menu-item-has-children' ), $marked_classes ), 'Exact base-class replacement retains native state and provider selectors.' );
	$nested_classes = skye_navigation_item_classes( $native_classes, $item, $args, 1 );
	core_check( ! in_array( 'menu-item', $nested_classes, true ) && in_array( 'skye-menu-item', $nested_classes, true ), 'Owned nested LI uses the same non-conflicting base class.' );
	core_check( $native_classes === skye_navigation_item_classes( $native_classes, $item, $other_args, 0 ), 'Unmarked native/plugin menus preserve the original LI class contract.' );
	$nested_attributes = skye_navigation_link_attributes( array(), $item, $args, 1 );
	core_check( 'menu-item' === trim( $nested_attributes['class'] ), 'Actual submenu links retain Bootstrap6 interactive menu-item styling.' );
	$attrs = skye_navigation_link_attributes( array( 'aria-current' => 'page' ), $item, $args, 0 );
	core_check( 'page' === $attrs['aria-current'] && str_contains( $attrs['class'], 'nav-link' ), 'Native aria-current survives Bootstrap link classes.' );
	$toggle = skye_navigation_dropdown_toggle( '<a href="/parent">Parent</a>', $item, 0, $args );
	core_check( str_starts_with( $toggle, '<a href="/parent">Parent</a>' ) && str_contains( $toggle, 'data-bs-toggle="menu"' ) && ! str_contains( $toggle, '<unsafe>' ), 'Parent destination remains navigable beside the escaped Bootstrap6 menu toggle.' );

	foreach ( array(
		array( array( 'is_home' => true, 'is_front_page' => true ), 'views/archive.php' ),
		array( array( 'is_404' => true ), 'views/404.php' ),
		array( array( 'is_search' => true ), 'views/search.php' ),
		array( array( 'is_page' => true, 'is_singular' => true ), 'views/page.php' ),
		array( array( 'is_singular' => true ), 'views/single.php' ),
		array( array(), 'views/default.php' ),
	) as $route ) {
		CoreFixture::$flags = $route[0];
		$router = new Rocketsky\Skye\Packages\Templates();
		core_check( CoreFixture::$theme . '/' . $route[1] === $router->template(), 'Actual route resolves ' . $route[1] . '.' );
	}
	CoreFixture::$flags = array( 'is_page' => true, 'is_singular' => true );
	CoreFixture::$template = 'templates/pagebuilder.php';
	core_check( CoreFixture::$theme . '/templates/pagebuilder.php' === ( new Rocketsky\Skye\Packages\Templates() )->template(), 'Selectable pagebuilder path resolves its actual template.' );
	CoreFixture::$template = '../../outside.php';
	core_check( CoreFixture::$theme . '/views/page.php' === ( new Rocketsky\Skye\Packages\Templates() )->template(), 'Malformed stored template slug falls back locally.' );
	foreach ( array( 'base.php', 'index.php', 'functions.php', 'lib/package/init.php' ) as $entrypoint ) {
		CoreFixture::$template = $entrypoint;
		core_check( CoreFixture::$theme . '/views/page.php' === ( new Rocketsky\Skye\Packages\Templates() )->template(), 'Unregistered stored entrypoint cannot recursively include ' . $entrypoint . '.' );
	}
	mkdir( CoreFixture::$child . '/templates', 0700, true );
	file_put_contents( CoreFixture::$child . '/templates/book.php', '<?php /** Template fixture; no native data access. */' );
	CoreFixture::$selectable['templates/book.php'] = 'Book';
	CoreFixture::$flags = array( 'is_singular' => true );
	CoreFixture::$type = 'book';
	CoreFixture::$template = 'templates/book.php';
	core_check( CoreFixture::$child . '/templates/book.php' === ( new Rocketsky\Skye\Packages\Templates() )->template(), 'A registered custom singular template retains its child-theme override.' );
	CoreFixture::$flags = array( 'is_page' => true, 'is_singular' => true );
	CoreFixture::$type = 'page';
	CoreFixture::$flags = array( 'is_archive' => true );
	CoreFixture::$type = 'book';
	$post_type_reads = core_calls( 'get_post_type' );
	core_check( CoreFixture::$theme . '/views/archive.php' === ( new Rocketsky\Skye\Packages\Templates() )->template() && $post_type_reads === core_calls( 'get_post_type' ), 'Term/user archive identity is not misread as a post with the same numeric ID.' );
	mkdir( CoreFixture::$child . '/views/book', 0700, true );
	file_put_contents( CoreFixture::$child . '/views/book/archive.php', '<?php /** Native post-type archive override fixture. */' );
	CoreFixture::$flags = array( 'is_archive' => true, 'is_post_type_archive' => true );
	CoreFixture::$query['post_type'] = 'book';
	core_check( CoreFixture::$child . '/views/book/archive.php' === ( new Rocketsky\Skye\Packages\Templates() )->template(), 'Actual post-type archives retain explicit type-specific child views.' );
	CoreFixture::$flags = array( 'is_page' => true, 'is_singular' => true );
	CoreFixture::$type = 'page';
	CoreFixture::$template = '';
	CoreFixture::$denied = array( 7 );
	$before = core_calls( 'get_field' );
	$model = skye_post_model( 7 );
	$header = skye_page_header_model();
	core_check( ! $model['allowed'] && '' === $model['image'] && '' === $model['excerpt'] && '' === $header['lead'], 'Restricted fields/images/excerpts remain absent from all presentation models.' );
	core_check( $before === core_calls( 'get_field' ), 'Members denial occurs before optional custom-field reads.' );
	CoreFixture::$denied = array();
	CoreFixture::$passwords = array( 7 );
	core_check( 'password' === skye_content_access_state( 7 ) && ! skye_post_model( 7 )['allowed'], 'Password restrictions protect custom presentation as well as native content.' );
	CoreFixture::$passwords = array();
	CoreFixture::$children = array( (object) array( 'ID' => 8 ), (object) array( 'ID' => 9 ) );
	CoreFixture::$denied = array( 8 );
	core_check( 'https://example.invalid/p/9' === skye_first_child_url( 7 ), 'First eligible published child respects the shared optional-provider permission boundary.' );
	$last_query = null;
	foreach ( CoreFixture::$calls as $call ) {
		if ( 'get_posts' === $call[0] ) {
			$last_query = $call[1][0];
		}
	}
	core_check( 20 === $last_query['posts_per_page'] && 'publish' === $last_query['post_status'] && array( 'menu_order' => 'ASC', 'ID' => 'ASC' ) === $last_query['orderby'], 'Child query is bounded and deterministically ordered.' );
	$redirect_response = core_response_probe( '--redirect-probe' );
	core_check( '' === $redirect_response['body'], 'Actual redirect exits before emitting a response body.' );
	$redirect_call = null;
	foreach ( $redirect_response['calls'] as $call ) {
		if ( 'wp_safe_redirect' === $call[0] ) {
			$redirect_call = $call[1];
		}
	}
	core_check( array( 'https://example.invalid/p/9', 302, 'Skye' ) === $redirect_call, 'Actual handler uses the native safe temporary redirect contract.' );
	$denied_response = core_response_probe( '--rights-denied-probe' );
	core_check( str_contains( $denied_response['body'], 'The page could not be found.' ) && ! str_contains( $denied_response['body'], 'UNEXPECTED' ), 'Legacy denial renders a complete local not-found response and terminates.' );
	$denied_status = null;
	foreach ( $denied_response['calls'] as $call ) {
		if ( 'status_header' === $call[0] ) {
			$denied_status = $call[1][0];
		}
	}
	core_check( 404 === $denied_status, 'Legacy denied response sends a real not-found status boundary.' );
	CoreFixture::$denied = array();
	CoreFixture::$flags = array();
	$queries = core_calls( 'get_posts' );
	foreach ( array( '404', 'archive', 'search', 'single', 'page', 'default', 'restricted' ) as $view ) {
		ob_start();
		include CoreFixture::$theme . '/views/' . $view . '.php';
		$html = ob_get_clean();
		core_check( str_contains( $html, 'Fixture title' ), 'Actual ' . $view . ' view renders its complete header without missing components.' );
	}
	core_check( $queries === core_calls( 'get_posts' ), 'Ordinary views do not launch suggestion/random/archive-configuration queries.' );
	core_check( 0 === core_calls( 'update_field' ) && 0 === core_calls( 'delete_post_meta' ), 'Actual public single rendering performs no legacy content migration.' );
	ob_start();
	skye_render_pagebuilder();
	core_check( 'Native content' === ob_get_clean(), 'Absent optional row API retains complete native content.' );
	$navigation = skye_navigation_model();
	$brand = skye_brand_model();
	$nav_html = skye_component_html( 'navigation', false, array( 'navigation' => $navigation, 'brand' => $brand ) );
	core_check( str_contains( $nav_html, 'data-bs-toggle="drawer"' ) && str_contains( $nav_html, '<dialog' ) && str_contains( $nav_html, 'md:navbar-expand' ), 'Real navigation uses Bootstrap6 Alpha dialog/navbar contracts.' );
	core_check( str_contains( $nav_html, '<noscript>' ) && str_contains( $nav_html, 'skye-no-script-navigation' ), 'Real navigation provides a complete no-JS link path.' );
	$sidebar_calls = core_calls( 'dynamic_sidebar' );
	core_check( '' === skye_footer_sidebar_html() && $sidebar_calls === core_calls( 'dynamic_sidebar' ), 'An inactive footer does not invoke widget callbacks or emit a placeholder.' );
	CoreFixture::$flags['is_active_sidebar'] = true;
	CoreFixture::$sidebar_html = 'NATIVE_WIDGET_FIXTURE';
	$sidebar_html = skye_footer_sidebar_html();
	core_check( 'NATIVE_WIDGET_FIXTURE' === $sidebar_html && $sidebar_calls + 1 === core_calls( 'dynamic_sidebar' ), 'An active footer delegates exactly once to the native sidebar renderer.' );
	$footer = skye_component_html( 'footer', false, array( 'brand' => $brand, 'sidebar_html' => $sidebar_html ) );
	core_check( str_contains( $footer, 'footer-widgets' ) && str_contains( $footer, 'NATIVE_WIDGET_FIXTURE' ), 'The actual footer component includes its explicit native widget HTML.' );
	$empty_footer = skye_component_html( 'footer', false, array( 'brand' => $brand, 'sidebar_html' => '' ) );
	core_check( ! str_contains( $empty_footer, 'footer-widgets' ), 'The actual empty footer contains no widget wrapper.' );
	core_check( 0 === core_calls( 'set_transient' ), 'Native rendering does not create persistent presentation transients.' );
	echo 'Core regression passed (' . $checks . ' checks; ACF ' . ( defined( 'SKYE_CORE_WITHOUT_ACF' ) ? 'absent' : 'present' ) . ").\n";
} finally {
	foreach ( array( '/components/probe/probe.php', '/components/probe/fail.php', '/templates/book.php', '/views/book/archive.php' ) as $file ) {
		if ( is_file( CoreFixture::$child . $file ) ) {
			unlink( CoreFixture::$child . $file );
		}
	}
	rmdir( CoreFixture::$child . '/components/probe' );
	rmdir( CoreFixture::$child . '/components' );
	if ( is_dir( CoreFixture::$child . '/views/book' ) ) {
		rmdir( CoreFixture::$child . '/views/book' );
	}
	rmdir( CoreFixture::$child . '/views' );
	if ( is_dir( CoreFixture::$child . '/templates' ) ) {
		rmdir( CoreFixture::$child . '/templates' );
	}
	rmdir( CoreFixture::$child );
}
