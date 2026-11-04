<?php
/** Verify external-link attributes through native Core parsing, filters and complete menu serialization. */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$external_source = dirname( __DIR__ );
$external_site = realpath( getenv( 'SKYE_SITE_ROOT' ) ?: dirname( $external_source ) . '/rocketsky-website/.runtime/wordpress' );
if ( ! $external_site || ! is_file( $external_site . '/wp-load.php' ) || ( ! str_contains( $external_site, '/.git/runtime/' ) && ! str_contains( $external_site, '/.runtime/wordpress' ) ) ) {
	throw new RuntimeException( 'Use an isolated local native WordPress fixture.' );
}
define( 'ABSPATH', $external_site . '/' );
require_once ABSPATH . 'wp-includes/plugin.php';

/**
 * Load the owned source theme through Core's public directory boundary without changing installed packages.
 *
 * @since 3.2.2
 * @param string $directory Original installed parent directory.
 * @return string Source checkout used only for this isolated PHP process.
 */
function external_source_directory( $directory ) {
	global $external_source;
	return $external_source;
}

add_filter( 'template_directory', 'external_source_directory' );
require ABSPATH . 'wp-load.php';
ini_set( 'error_log', 'php://stderr' );
if ( wp_get_environment_type() !== 'staging' || ! in_array( wp_parse_url( site_url(), PHP_URL_HOST ), array( '127.0.0.1', 'localhost' ), true ) ) {
	throw new RuntimeException( 'External-link fixtures require isolated localhost staging.' );
}

use Rocketsky\Skye\Packages\ExternalLinks;
use Rocketsky\Skye\Packages\MobileNavigation;

$external_assertions = 0;
$external_http = 0;
$external_menu = 0;
$external_home = 'https://example.test';

/**
 * Fail on observed native serialization or origin-boundary regressions.
 *
 * @since 3.2.2
 * @param bool $condition Required actual behavior.
 * @param string $message Concrete failure without private site data.
 * @return void
 */
function external_assert( $condition, $message ) {
	global $external_assertions;
	++$external_assertions;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Control the comparison origin through a native read filter without altering site options.
 *
 * @since 3.2.2
 * @param mixed $previous Earlier native option preemption value.
 * @return string Controlled home origin for deterministic protocol and port boundaries.
 */
function external_home_option( $previous ) {
	global $external_home;
	return $external_home;
}

/**
 * Block and count all transport attempts so no link test can fetch a destination.
 *
 * @since 3.2.2
 * @param mixed $previous Earlier native HTTP guard response.
 * @return WP_Error Controlled refusal without a real network request.
 */
function external_no_http( $previous ) {
	global $external_http;
	++$external_http;
	return new WP_Error( 'external_links_no_http', 'Native link QA never fetches destinations.' );
}

/**
 * Inspect native serialized anchors with Core's own HTML parser rather than replacing its serializer.
 *
 * @since 3.2.2
 * @param string $html Actual content or menu output.
 * @return array Ordered native anchor attributes, including null for omitted attributes.
 */
function external_anchors( $html ) {
	$processor = new WP_HTML_Tag_Processor( $html );
	$anchors = array();
	while ( $processor->next_tag( 'A' ) ) {
		$attributes = array();
		foreach ( array( 'id', 'href', 'target', 'rel', 'class', 'download', 'aria-label', 'data-keep' ) as $name ) {
			$attributes[$name] = $processor->get_attribute( $name );
		}
		$anchors[] = $attributes;
	}
	return $anchors;
}

/**
 * Read semantic rel tokens case-insensitively while retaining the original string for preservation checks.
 *
 * @since 3.2.2
 * @param mixed $rel Native rel attribute or absent value.
 * @return array Lowercase nonempty tokens in their serialized order.
 */
function external_rel_tokens( $rel ) {
	return is_string( $rel ) ? preg_split( '/\s+/', strtolower( trim( $rel ) ), -1, PREG_SPLIT_NO_EMPTY ) : array();
}

/**
 * Locate a real serialized menu destination without relying on generated numeric item IDs.
 *
 * @since 3.2.2
 * @param array $anchors Native serialized anchor attributes.
 * @param string $href Expected unchanged destination.
 * @return array First exact destination, or an empty array when Core omitted it.
 */
function external_find_anchor( $anchors, $href ) {
	foreach ( $anchors as $anchor ) {
		if ( $anchor['href'] === $href ) {
			return $anchor;
		}
	}
	return array();
}

add_filter( 'pre_option_home', 'external_home_option' );
add_filter( 'pre_http_request', 'external_no_http', PHP_INT_MAX );

try {
	external_assert( class_exists( ExternalLinks::class ) && function_exists( 'skye_external_link_attributes' ) && class_exists( WP_HTML_Tag_Processor::class ), 'The actual source-theme bootstrap must expose the service, helper and native Core parser.' );
	$external_service = null;
	foreach ( $GLOBALS['wp_filter']['the_content']->callbacks[90] ?? array() as $external_callback ) {
		if ( is_array( $external_callback['function'] ) && $external_callback['function'][0] instanceof ExternalLinks ) {
			$external_service = $external_callback['function'][0];
		}
	}
	external_assert( $external_service instanceof ExternalLinks && has_filter( 'acf_the_content', array( $external_service, 'content' ) ) === 90 && has_filter( 'nav_menu_link_attributes', array( ExternalLinks::class, 'attributes' ) ) === 90, 'The actual initialized service must register normal content, ACF and native menu attribute filters at priority 90.' );
	$external_base = array( 'href' => 'https://Other.test/KeepCase?q=A&other=B', 'target' => '_self', 'rel' => 'sponsored UGC nofollow NOOPENER', 'class' => 'keep-link', 'download' => 'report.pdf', 'aria-label' => 'Keep accessible text', 'data-keep' => 'unchanged' );
	$external_attributes = skye_external_link_attributes( $external_base );
	$external_tokens = external_rel_tokens( $external_attributes['rel'] );
	external_assert( $external_attributes['target'] === '_blank' && count( array_keys( $external_tokens, 'noopener', true ) ) === 1 && count( array_keys( $external_tokens, 'noreferrer', true ) ) === 1, 'An external link must override its target and contain each required relation once, respecting mixed-case existing tokens.' );
	external_assert( str_contains( $external_attributes['rel'], 'sponsored UGC nofollow NOOPENER' ) && array_diff_key( $external_attributes, array( 'target' => true, 'rel' => true ) ) === array_diff_key( $external_base, array( 'target' => true, 'rel' => true ) ), 'The helper must preserve original rel spelling and every unrelated destination, class, download, ARIA and data attribute.' );
	external_assert( skye_external_link_attributes( $external_attributes ) === $external_attributes && ExternalLinks::attributes( $external_base ) === $external_attributes, 'The public helper must delegate to the shared implementation and remain strictly idempotent.' );
	$external_cases = array(
		array( '/relative/path', false ), array( 'relative/path', false ), array( '?query=one', false ), array( '#section', false ),
		array( 'mailto:person@example.test', false ), array( 'tel:+41311234567', false ), array( 'javascript:void(0)', false ), array( 'data:text/plain,hello', false ),
		array( 'https://example.test/Keep?x=A', false ), array( 'HTTPS://ExAmPlE.TeSt:443/Keep?x=A', false ), array( '//example.test/Keep?x=A', false ),
		array( 'https://else.test@example.test/authority', false ), array( 'https://other.test/Keep?x=A', true ), array( '//other.test/Keep?x=A', true ),
		array( 'http://example.test/Keep', true ), array( 'https://example.test:8443/Keep', true ), array( 'https://example.test.evil.test/Keep', true ),
		array( 'https://example.test@other.test/authority', true ), array( 'https://user:password@other.test/authority', true ),
	);
	foreach ( $external_cases as $external_case ) {
		$external_input = array( 'href' => $external_case[0], 'class' => 'native-keep', 'rel' => 'ugc', 'target' => '_self' );
		$external_result = skye_external_link_attributes( $external_input );
		external_assert( $external_case[1] ? ( $external_result['href'] === $external_case[0] && $external_result['target'] === '_blank' && in_array( 'noopener', external_rel_tokens( $external_result['rel'] ), true ) && in_array( 'noreferrer', external_rel_tokens( $external_result['rel'] ), true ) ) : $external_result === $external_input, 'The actual origin boundary must classify and preserve this destination: ' . $external_case[0] );
	}
	$external_home = 'http://EXAMPLE.test:80/base/';
	external_assert( skye_external_link_attributes( array( 'href' => 'http://example.test/path' ) ) === array( 'href' => 'http://example.test/path' ) && skye_external_link_attributes( array( 'href' => '//example.test/path' ) ) === array( 'href' => '//example.test/path' ), 'Default HTTP ports and protocol-relative links must normalize against the configured home scheme.' );
	$external_home = 'https://example.test';
	$external_fragment = '<p data-keep="paragraph"><a id="external" href="https://Other.test/KeepCase?q=A&amp;other=B" target="named-window" rel="sponsored UGC nofollow NOOPENER" class="keep-link" download="report.pdf" aria-label="Keep accessible text" data-keep="unchanged">Keep <strong>nested text</strong></a><a id="internal" href="/relative" target="_self" rel="author">Internal</a><a href="mailto:person@example.test">Mail</a></p><img src="https://other.test/image.png" data-keep="image"><!-- <a href="https://comment.test/">comment</a> --><script>const keep = \'<a href="https://script.test/">script</a>\';</script>';
	$external_content = $external_service->content( $external_fragment );
	$external_content_anchors = external_anchors( $external_content );
	external_assert( count( $external_content_anchors ) === 3 && $external_content_anchors[0]['target'] === '_blank' && $external_content_anchors[1]['target'] === '_self' && $external_content_anchors[2]['target'] === null, 'The actual Core fragment parser must modify external anchors while preserving internal and nonweb anchors.' );
	external_assert( $external_content_anchors[0]['href'] === $external_base['href'] && $external_content_anchors[0]['class'] === 'keep-link' && $external_content_anchors[0]['download'] === 'report.pdf' && $external_content_anchors[0]['aria-label'] === 'Keep accessible text' && $external_content_anchors[0]['data-keep'] === 'unchanged', 'Native serialization must retain URL case/query and all unrelated external-anchor attributes.' );
	external_assert( str_contains( $external_content, 'Keep <strong>nested text</strong>' ) && str_contains( $external_content, '<img src="https://other.test/image.png" data-keep="image">' ) && str_contains( $external_content, '<!-- <a href="https://comment.test/">comment</a> -->' ) && str_contains( $external_content, '<script>const keep' ), 'Native transformation must preserve nested content, non-anchor tags, comments and script text.' );
	external_assert( $external_service->content( $external_content ) === $external_content && count( array_keys( external_rel_tokens( $external_content_anchors[0]['rel'] ), 'noopener', true ) ) === 1 && count( array_keys( external_rel_tokens( $external_content_anchors[0]['rel'] ), 'noreferrer', true ) ) === 1, 'Repeated native content filtering must neither rewrite already-correct output nor duplicate required relation tokens.' );
	foreach ( array( null, false, 23, array(), '' ) as $external_nonstring ) {
		external_assert( $external_service->content( $external_nonstring ) === $external_nonstring, 'Non-string or empty content must remain unchanged without parser errors.' );
	}
	foreach ( array( 'the_content', 'acf_the_content' ) as $external_hook ) {
		$external_filtered = external_anchors( apply_filters( $external_hook, '<a href="https://other.test/native-filter" rel="ugc">Native filter</a>' ) );
		external_assert( count( $external_filtered ) === 1 && $external_filtered[0]['target'] === '_blank' && external_rel_tokens( $external_filtered[0]['rel'] ) === array( 'ugc', 'noopener', 'noreferrer' ), 'The actual registered native rich-text filter must invoke shared attribute behavior: ' . $external_hook );
	}

	$external_menu = wp_create_nav_menu( 'Disposable external-link QA ' . bin2hex( random_bytes( 6 ) ) );
	external_assert( ! is_wp_error( $external_menu ) && $external_menu > 0, 'The native disposable menu must be created without changing a theme location.' );
	$external_destinations = array( 'https://other.test/Menu?Case=A', '/native-internal', '//else.test/protocol-relative', 'https://nested.test/child', 'mailto:person@example.test' );
	$external_parent = 0;
	foreach ( $external_destinations as $external_index => $external_destination ) {
		$external_item = wp_update_nav_menu_item( $external_menu, 0, array( 'menu-item-title' => 'Native item ' . $external_index, 'menu-item-url' => $external_destination, 'menu-item-type' => 'custom', 'menu-item-status' => 'publish', 'menu-item-target' => '_self', 'menu-item-xfn' => 'sponsored ugc nofollow', 'menu-item-parent-id' => $external_index === 3 ? $external_parent : 0 ) );
		if ( $external_index === 0 ) {
			$external_parent = $external_item;
		}
		external_assert( ! is_wp_error( $external_item ) && $external_item > 0, 'Each real menu item must save in the disposable native menu.' );
	}
	$external_menus = array();
	foreach ( array( 'desktop', 'mobile', 'fallback' ) as $external_scope ) {
		$external_arguments = MobileNavigation::menu_args( array( 'menu' => $external_menu, 'echo' => false, 'depth' => 0, 'container' => false, 'fallback_cb' => false, 'skye_bootstrap' => false ), 'external-' . $external_scope );
		$external_menus[$external_scope] = wp_nav_menu( $external_arguments );
		$external_links = external_anchors( $external_menus[$external_scope] );
		$external_destination = external_find_anchor( $external_links, $external_destinations[0] );
		$external_internal = external_find_anchor( $external_links, $external_destinations[1] );
		$external_mail = external_find_anchor( $external_links, $external_destinations[4] );
		external_assert( count( $external_links ) === 5 && $external_destination['target'] === '_blank' && $external_internal['target'] === '_self' && $external_mail['target'] === '_self' && external_find_anchor( $external_links, $external_destinations[2] )['target'] === '_blank' && external_find_anchor( $external_links, $external_destinations[3] )['target'] === '_blank', 'Actual Core serialization must preserve all nested menu destinations and external-only targets: ' . $external_scope );
		external_assert( external_rel_tokens( $external_destination['rel'] ) === array( 'sponsored', 'ugc', 'nofollow', 'noopener', 'noreferrer' ) && external_rel_tokens( $external_internal['rel'] ) === array( 'sponsored', 'ugc', 'nofollow' ), 'Actual Core menu attributes must preserve existing relations while adding security tokens only externally: ' . $external_scope );
	}
	$external_component = skye_component_html( 'navigation/mobile', false, array( 'navigation' => array( 'html' => $external_menus['desktop'], 'mobile' => $external_menus['mobile'], 'fallback' => $external_menus['fallback'] ), 'mobile_navigation' => MobileNavigation::model() ) );
	$external_component_links = external_anchors( $external_component );
	external_assert( count( $external_component_links ) === 15 && count( external_anchors( $external_menus['desktop'] ) ) === 5, 'The actual desktop, mobile dialog and native details fallback must all retain five Core-generated destinations.' );
	foreach ( array( null, false, 17, array(), '', 'https://', 'https:///missing-host', 'http://[invalid]', 'https://other.test:99999/' ) as $external_invalid ) {
		$external_input = array( 'href' => $external_invalid, 'class' => 'keep', 'target' => '_self', 'rel' => 'ugc' );
		external_assert( skye_external_link_attributes( $external_input ) === $external_input, 'Malformed or non-string href values must remain unchanged safely: ' . wp_json_encode( $external_invalid ) );
	}
	external_assert( $external_http === 0, 'Native attribute handling and serialization must make no HTTP requests.' );
} finally {
	if ( is_int( $external_menu ) && $external_menu > 0 ) {
		wp_delete_nav_menu( $external_menu );
	}
	remove_filter( 'pre_option_home', 'external_home_option' );
	remove_filter( 'pre_http_request', 'external_no_http', PHP_INT_MAX );
	remove_filter( 'template_directory', 'external_source_directory' );
}

echo wp_json_encode( array( 'status' => 'pass', 'assertions' => $external_assertions, 'wordpress' => $wp_version, 'php' => PHP_VERSION, 'native_menu_walker' => true, 'http_requests' => $external_http, 'persistent_site_changes' => false ) ) . "\n";
