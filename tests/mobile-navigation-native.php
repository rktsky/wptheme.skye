<?php
/** Verify real Core menu serialization, distinct DOM identities and parent-owned navigation delivery. */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
ini_set( 'error_log', 'php://stderr' );
$site = realpath( getenv( 'SKYE_SITE_ROOT' ) ?: '' );
if ( ! $site || ! is_file( $site . '/wp-load.php' ) || ! str_contains( $site, '/.git/runtime/' ) ) {
	throw new RuntimeException( 'Use an isolated Skye .git/runtime WordPress fixture.' );
}
require $site . '/wp-load.php';
ini_set( 'display_errors', '1' );
error_reporting( E_ALL );

use Rocketsky\Skye\Packages\MobileNavigation;

$navigation_checks = 0;

/**
 * Assert actual native output and public hooks without replacing the production menu walker.
 *
 * @since 3.2.0
 * @param bool $condition Observed native effect.
 * @param string $message Required boundary.
 * @return void
 */
function navigation_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	$GLOBALS['navigation_checks']++;
}

/**
 * Describe IDs and native link destinations through the actual rendered document tree.
 *
 * @since 3.2.0
 * @param string $html Native Core/component markup.
 * @return array{ids:array,links:array,xpath:DOMXPath} Read-only DOM evidence.
 */
function navigation_dom( $html ) {
	$document = new DOMDocument();
	$previous = libxml_use_internal_errors( true );
	$document->loadHTML( '<?xml encoding="UTF-8"><!doctype html><html><body>' . $html . '</body></html>' );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );
	$xpath = new DOMXPath( $document );
	$ids = array();
	$links = array();
	foreach ( $xpath->query( '//*[@id]' ) as $element ) {
		$ids[] = $element->getAttribute( 'id' );
	}
	foreach ( $xpath->query( '//a[@href]' ) as $element ) {
		$links[] = $element->getAttribute( 'href' );
	}
	return array( 'ids' => $ids, 'links' => $links, 'xpath' => $xpath );
}

navigation_check( class_exists( MobileNavigation::class ) && MobileNavigation::enabled(), 'The actual parent registers its standard navigation service.' );
navigation_check( has_nav_menu( 'primary_navigation' ), 'The native fixture has an assigned menu.' );
$model = MobileNavigation::model();
navigation_check( $model['breakpoint'] === 1200 && $model['id'] === 'skye-navigation', 'The standard collapse boundary matches the reviewed spacious menu.' );
$invalid = MobileNavigation::model( array( 'id' => '../foreign', 'breakpoint' => 999, 'location' => array(), 'open_text' => array(), 'unknown' => 'never emitted' ) );
navigation_check( $invalid['id'] === $model['id'] && $invalid['breakpoint'] === $model['breakpoint'] && $invalid['location'] === $model['location'] && $invalid['open_text'] === $model['open_text'] && ! isset( $invalid['unknown'] ), 'Unknown types, IDs and breakpoint values retain bounded native defaults.' );
$bounded = MobileNavigation::model( array( 'id' => str_repeat( 'n', 64 ) ) );
navigation_check( $bounded['id'] === $model['id'], 'Instance IDs reserve room for distinct menu identity suffixes.' );
$plain = MobileNavigation::model( array( 'title' => '<strong>Plain title</strong>' ) );
navigation_check( $plain['title'] === 'Plain title', 'Filtered labels remain plain UI strings.' );
$arguments = skye_navigation_args();
$arguments['echo'] = false;
$arguments['depth'] = 0;
$arguments['skye_bootstrap'] = false;
$menus = array();
foreach ( array( 'desktop', 'mobile', 'fallback' ) as $scope ) {
	$scoped = MobileNavigation::menu_args( $arguments, 'fixture-' . $scope );
	navigation_check( $scoped['theme_location'] === $arguments['theme_location'] && $scoped['skye_bootstrap'] === false, 'Scoping preserves actual native/provider arguments: ' . $scope );
	$menus[$scope] = wp_nav_menu( $scoped );
	$dom = navigation_dom( $menus[$scope] );
	navigation_check( count( $dom['links'] ) >= 4 && count( $dom['ids'] ) >= 5 && count( array_unique( $dom['ids'] ) ) === count( $dom['ids'] ), 'Real Core nested links and scoped item identities survive repeated serialization: ' . $scope );
	foreach ( $dom['ids'] as $id ) {
		navigation_check( str_starts_with( $id, 'fixture-' . $scope ), 'Core applied the public ID namespace to every serialized item.' );
	}
}
$combined = navigation_dom( implode( '', $menus ) );
navigation_check( count( $combined['ids'] ) === count( array_unique( $combined['ids'] ) ), 'Simultaneous desktop, modal and fallback menus have no duplicate DOM IDs.' );
navigation_check( navigation_dom( $menus['desktop'] )['links'] === navigation_dom( $menus['mobile'] )['links'] && navigation_dom( $menus['mobile'] )['links'] === navigation_dom( $menus['fallback'] )['links'], 'All three native paths preserve every destination including children.' );
navigation_check( MobileNavigation::menu_args( $arguments, '../invalid' ) === $arguments, 'Invalid namespaces cannot change unmarked menu serialization.' );
$unrelated = navigation_dom( wp_nav_menu( $arguments ) );
navigation_check( count( $unrelated['links'] ) === count( navigation_dom( $menus['desktop'] )['links'] ), 'Unrelated provider menus retain every native destination.' );
$service = new MobileNavigation();
navigation_check( $service->item_id( 'provider-id', (object) array( 'ID' => 123 ), (object) $arguments, 0 ) === 'provider-id' && $service->item_id( '', (object) array( 'ID' => 123 ), (object) $arguments, 0 ) === '', 'Unmarked menus preserve both provider IDs and Core omitted-repeat IDs.' );
$navigation = array( 'html' => $menus['desktop'], 'mobile' => $menus['mobile'], 'fallback' => $menus['fallback'] );
$component = skye_component_html( 'navigation/mobile', false, array( 'navigation' => $navigation, 'mobile_navigation' => $model ) );
$dom = navigation_dom( $component );
navigation_check( count( $dom['ids'] ) === count( array_unique( $dom['ids'] ) ), 'The actual complete component adds no duplicate controls or title IDs.' );
navigation_check( $dom['xpath']->query( '//dialog[not(@open)]' )->length === 1 && $dom['xpath']->query( '//details/summary' )->length === 1, 'The modal starts closed and a native non-JavaScript summary remains available.' );
navigation_check( $dom['xpath']->query( '//details[not(@hidden)]//a[@href]' )->length >= 4, 'The actual native details fallback contains every usable link.' );
navigation_check( $dom['xpath']->query( '//button[@data-skye-navigation-open and @hidden and @aria-expanded="false"]' )->length === 1, 'Only successful enhancement exposes the accessible JavaScript opener.' );
$incomplete_navigation = $navigation;
$incomplete_navigation['mobile'] = '';
$incomplete_component = skye_component_html( 'navigation/mobile', false, array( 'navigation' => $incomplete_navigation, 'mobile_navigation' => $model ) );
navigation_check( str_contains( $incomplete_component, 'skye-navigation-static' ) && ! str_contains( $incomplete_component, '<dialog' ) && count( navigation_dom( $incomplete_component )['links'] ) >= 4, 'A provider withholding modal output retains the complete static native menu.' );
$parent_model = skye_navigation_model();
$parent = navigation_dom( skye_component_html( 'navigation', false, array( 'brand' => skye_brand_model(), 'navigation' => $parent_model ) ) );
navigation_check( count( $parent['ids'] ) === count( array_unique( $parent['ids'] ) ), 'The default parent integration retains distinct native identities.' );
navigation_check( $parent['xpath']->query( '//*[@data-skye-navigation-brand-for="skye-navigation"]' )->length === 1, 'The parent supplies one stable brand source rather than duplicate logos.' );
navigation_check( $parent['xpath']->query( '//dialog//*[@data-skye-navigation-brand-slot]' )->length === 1, 'The actual modal exposes the optional native brand slot.' );

wp_dequeue_script( 'skye/mobile-navigation' );
wp_dequeue_style( 'skye/mobile-navigation-style' );
add_filter( 'skye_mobile_navigation_enabled', '__return_false' );
$service->enqueue();
navigation_check( ! wp_script_is( 'skye/mobile-navigation', 'enqueued' ) && ! wp_style_is( 'skye/mobile-navigation-style', 'enqueued' ), 'A public opt-out enqueues neither navigation asset.' );
remove_filter( 'skye_mobile_navigation_enabled', '__return_false' );
add_filter( 'has_nav_menu', '__return_false' );
$service->enqueue();
navigation_check( ! wp_script_is( 'skye/mobile-navigation', 'enqueued' ) && ! wp_style_is( 'skye/mobile-navigation-style', 'enqueued' ), 'An absent menu adds no unused asset.' );
remove_filter( 'has_nav_menu', '__return_false' );
$service->enqueue();
navigation_check( wp_script_is( 'skye/mobile-navigation', 'enqueued' ) && wp_style_is( 'skye/mobile-navigation-style', 'enqueued' ), 'An actual opted-in assigned menu uses both independently registered small assets.' );
$script = wp_scripts()->registered['skye/mobile-navigation'];
$style = wp_styles()->registered['skye/mobile-navigation-style'];
navigation_check( $script->deps === array() && wp_scripts()->get_data( 'skye/mobile-navigation', 'strategy' ) === 'defer', 'Native deferred navigation has no second Bootstrap, jQuery or provider dependency.' );
navigation_check( str_starts_with( $script->src, get_template_directory_uri() . '/dist/scripts/' ) && str_starts_with( $style->src, get_template_directory_uri() . '/dist/styles/' ), 'Both actual native handles resolve the parent deployment boundary.' );
navigation_check( MobileNavigation::asset_uri( '../foreign.js' ) === '' && MobileNavigation::asset_uri( 'main.js' ) === '', 'The resolver cannot expose arbitrary parent or child source files.' );

/**
 * Supply only a separate child directory through the native Core filter, without changing site options.
 *
 * @since 3.2.0
 * @param string $directory Original stylesheet directory.
 * @return string Explicit nonexistent child fixture path; no files are created or replaced.
 */
function navigation_child_directory( $directory ) {
	return dirname( $directory ) . '/navigation-isolated-child';
}
add_filter( 'stylesheet_directory', 'navigation_child_directory' );
try {
	navigation_check( ! MobileNavigation::enabled(), 'Existing child-theme headers do not silently opt into the new standard.' );
	add_filter( 'skye_mobile_navigation_enabled', '__return_true' );
	navigation_check( MobileNavigation::enabled(), 'A child opts in through the native public filter.' );
	navigation_check( MobileNavigation::asset_uri( 'mobile-navigation.js' ) === $script->src && MobileNavigation::asset_uri( 'mobile-navigation.css' ) === $style->src, 'A separate child directory cannot redirect parent package assets.' );
} finally {
	remove_filter( 'stylesheet_directory', 'navigation_child_directory' );
	remove_filter( 'skye_mobile_navigation_enabled', '__return_true' );
}
echo wp_json_encode( array( 'status' => 'pass', 'assertions' => $navigation_checks, 'wordpress' => $wp_version, 'php' => PHP_VERSION, 'native_menu_walker' => true, 'persistent_site_changes' => false ) ) . "\n";
