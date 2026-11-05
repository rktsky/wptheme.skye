<?php
/** Verify the opt-in metadata-only image policy through native Core candidate selection and serialization. */

if ( PHP_SAPI !== 'cli' ) {
	http_response_code( 403 );
	exit;
}
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$candidates_root = dirname( __DIR__ );
$candidates_runtime = realpath( $candidates_root . '/.git/runtime' );
$candidates_site = realpath( getenv( 'SKYE_SITE_ROOT' ) ?: '' );
if ( ! $candidates_runtime || ! $candidates_site || ! str_starts_with( $candidates_site, $candidates_runtime . '/' ) || ! is_file( $candidates_site . '/wp-load.php' ) ) {
	throw new RuntimeException( 'Use a disposable native WordPress site inside this checkout .git/runtime.' );
}
require $candidates_site . '/wp-load.php';
ini_set( 'error_log', 'php://stderr' );
if ( wp_get_environment_type() !== 'local' || ! defined( 'DB_ENGINE' ) || DB_ENGINE !== 'sqlite' || ! $wpdb instanceof WP_SQLite_DB ||
	! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( '127.0.0.1', 'localhost' ), true ) ) {
	throw new RuntimeException( 'Image-candidate tests require native local SQLite on loopback.' );
}

use Rocketsky\Skye\Packages\ImageCandidates;

$candidates_assertions = 0;
$candidates_captured = null;
$candidates_http = 0;
$candidates_queries = 0;

/**
 * Fail on an actual native candidate or preservation boundary, without changing stored media.
 *
 * @since 3.4.0
 * @param bool $condition Observed outcome.
 * @param string $reason Concrete public contract.
 * @return void
 */
function candidates_check( $condition, $reason ) {
	$GLOBALS['candidates_assertions']++;
	if ( ! $condition ) {
		throw new RuntimeException( $reason );
	}
}

/**
 * Capture the complete native set before the explicit test-only policy adapter.
 *
 * @since 3.4.0
 * @param array $sources Native sources, returned without modification.
 * @return array Exactly the original candidate set.
 */
function candidates_capture( $sources ) {
	$GLOBALS['candidates_captured'] = $sources;
	return $sources;
}

/**
 * Opt only this disposable native API invocation into the pure package helper.
 *
 * Production attachment/request policy remains the caller's responsibility.
 *
 * @since 3.4.0
 * @param array $sources Native responsive candidates.
 * @param array $dimensions Requested native image dimensions.
 * @param string $src Native source URL for this format.
 * @param array $metadata Complete metadata for this delivered format.
 * @return array Sources after the explicit helper call.
 */
function candidates_apply( $sources, $dimensions, $src, $metadata ) {
	return ImageCandidates::prune( $sources, $src, $metadata );
}

/**
 * Observe all native SQL calls during pure-helper execution without exposing statements.
 *
 * @since 3.4.0
 * @param string $query Native SQL, returned unchanged and never logged.
 * @return string Unchanged native SQL.
 */
function candidates_query( $query ) {
	$GLOBALS['candidates_queries']++;
	return $query;
}

/**
 * Prevent every native HTTP request and record unexpected attempts.
 *
 * @since 3.4.0
 * @param mixed $previous Earlier transport preemption result.
 * @return WP_Error Local-test refusal, without transmitting a request.
 */
function candidates_block_http( $previous ) {
	$GLOBALS['candidates_http']++;
	return new WP_Error( 'image_candidates_no_http', 'Image candidate pruning performs no transport.' );
}

// Complete historical PNG/WebP metadata from the finished Rocketsky logo work.
// This preserves all six named sizes, including incompatible crops; native Core
// must independently select its actual three peers before any pruning occurs.
$candidates_snapshot = json_decode( <<<'JSON'
{
	"width": 830,
	"height": 200,
	"file": "2026/11/Rocketsky-Logo-2025.png",
	"filesize": 12245,
	"sizes": {
		"medium": {
			"file": "Rocketsky-Logo-2025-300x72.png",
			"width": 300,
			"height": 72,
			"mime-type": "image/png",
			"filesize": 7539
		},
		"thumbnail": {
			"file": "Rocketsky-Logo-2025-150x150.png",
			"width": 150,
			"height": 150,
			"mime-type": "image/png",
			"filesize": 4024
		},
		"medium_large": {
			"file": "Rocketsky-Logo-2025-768x185.png",
			"width": 768,
			"height": 185,
			"mime-type": "image/png",
			"filesize": 20848
		},
		"gform-image-choice-sm": {
			"file": "Rocketsky-Logo-2025-gform-image-choice-sm-skye-native-5-v1-8381f8b4dd27.png",
			"width": 300,
			"height": 200,
			"mime-type": "image/png",
			"filesize": 5127
		},
		"gform-image-choice-md": {
			"file": "Rocketsky-Logo-2025-gform-image-choice-md-skye-native-5-v1-1dfa36c95705.png",
			"width": 400,
			"height": 200,
			"mime-type": "image/png",
			"filesize": 6103
		},
		"gform-image-choice-lg": {
			"file": "Rocketsky-Logo-2025-gform-image-choice-lg-skye-native-5-v1-9dc23dcf24e9.png",
			"width": 600,
			"height": 200,
			"mime-type": "image/png",
			"filesize": 9035
		}
	},
	"skye_webp": {
		"version": 1,
		"quality": 82,
		"source_file": "2026/11/Rocketsky-Logo-2025.png",
		"full": {
			"file": "Rocketsky-Logo-2025-skye-webp-5-q82-aeccd54f376d.webp",
			"source": "Rocketsky-Logo-2025.png",
			"width": 830,
			"height": 200,
			"mime-type": "image/webp",
			"filesize": 7032,
			"source_sha256": "aeccd54f376d166e74a3c603ffce84ba54d0f381cea8527fd974af5016416ae0"
		},
		"sizes": {
			"medium": {
				"file": "Rocketsky-Logo-2025-300x72-skye-webp-5-q82-a74dab3e6c5f.webp",
				"source": "Rocketsky-Logo-2025-300x72.png",
				"width": 300,
				"height": 72,
				"mime-type": "image/webp",
				"filesize": 4442,
				"source_sha256": "a74dab3e6c5f3a8c4776a7615ea9aa29865c66b2e8ce18e1a63d6348b492c5fa"
			},
			"thumbnail": {
				"file": "Rocketsky-Logo-2025-150x150-skye-webp-5-q82-a1f1d8f506a6.webp",
				"source": "Rocketsky-Logo-2025-150x150.png",
				"width": 150,
				"height": 150,
				"mime-type": "image/webp",
				"filesize": 2316,
				"source_sha256": "a1f1d8f506a6b8619c209012274f9a64fd7c042ec99a8c60bace872a7abc9757"
			},
			"medium_large": {
				"file": "Rocketsky-Logo-2025-768x185-skye-webp-5-q82-6ca423b12539.webp",
				"source": "Rocketsky-Logo-2025-768x185.png",
				"width": 768,
				"height": 185,
				"mime-type": "image/webp",
				"filesize": 11466,
				"source_sha256": "6ca423b12539d246e14bd7b8a36f65be6a8862b714f5b66254692d84ec112d4f"
			},
			"gform-image-choice-sm": {
				"file": "Rocketsky-Logo-2025-gform-image-choice-s-skye-webp-5-q82-7bff73483250.webp",
				"source": "Rocketsky-Logo-2025-gform-image-choice-sm-skye-native-5-v1-8381f8b4dd27.png",
				"width": 300,
				"height": 200,
				"mime-type": "image/webp",
				"filesize": 2632,
				"source_sha256": "7bff73483250b7399af57842d5069968700bb9d96c225979342a15d4623d0ea1"
			},
			"gform-image-choice-md": {
				"file": "Rocketsky-Logo-2025-gform-image-choice-m-skye-webp-5-q82-4ba79f44aab3.webp",
				"source": "Rocketsky-Logo-2025-gform-image-choice-md-skye-native-5-v1-1dfa36c95705.png",
				"width": 400,
				"height": 200,
				"mime-type": "image/webp",
				"filesize": 3170,
				"source_sha256": "4ba79f44aab3914219ca13c8fb88474ae05026f3628c19aa1b8c16af4b916692"
			},
			"gform-image-choice-lg": {
				"file": "Rocketsky-Logo-2025-gform-image-choice-l-skye-webp-5-q82-ef5a7f7fd413.webp",
				"source": "Rocketsky-Logo-2025-gform-image-choice-lg-skye-native-5-v1-9dc23dcf24e9.png",
				"width": 600,
				"height": 200,
				"mime-type": "image/webp",
				"filesize": 4608,
				"source_sha256": "ef5a7f7fd4136b0d9f097bbce94579fd1129178e54dc4db5cedaacec702e4b67"
			}
		}
	}
}
JSON
	, true, 512, JSON_THROW_ON_ERROR );

candidates_check( class_exists( ImageCandidates::class ), 'The actual theme bootstrap must load the shared helper without constructing a service.' );
candidates_check( has_filter( 'wp_calculate_image_srcset', array( ImageCandidates::class, 'prune' ) ) === false, 'Loading the package must not automatically alter any attachment srcset.' );
$candidates_png = $candidates_snapshot;
unset( $candidates_png['skye_webp'] );
$candidates_webp = $candidates_png;
$candidates_webp['file'] = dirname( $candidates_png['file'] ) . '/' . $candidates_snapshot['skye_webp']['full']['file'];
$candidates_webp['filesize'] = $candidates_snapshot['skye_webp']['full']['filesize'];
$candidates_webp['sizes'] = $candidates_snapshot['skye_webp']['sizes'];
$candidates_uploads = wp_get_upload_dir();
$candidates_base = trailingslashit( $candidates_uploads['baseurl'] );
$candidates_source = $candidates_base . $candidates_webp['file'];
$candidates_evidence = array();
add_filter( 'pre_http_request', 'candidates_block_http', PHP_INT_MAX );
add_filter( 'wp_calculate_image_srcset', 'candidates_capture', 19 );

try {
	foreach ( array( 'png' => $candidates_png, 'webp' => $candidates_webp ) as $candidates_format => $candidates_metadata ) {
		$candidates_src = $candidates_base . $candidates_metadata['file'];
		$candidates_native = wp_calculate_image_srcset( array( 830, 200 ), $candidates_src, $candidates_metadata );
		$candidates_before = $candidates_captured;
		$candidates_widths = is_array( $candidates_before ) ? array_keys( $candidates_before ) : array();
		sort( $candidates_widths, SORT_NUMERIC );
		candidates_check( is_string( $candidates_native ) && $candidates_widths === array( 300, 768, 830 ), 'Native Core must produce the complete three-peer set from all six named sizes before the opt-in policy: ' . $candidates_format );
		$candidates_expected = $candidates_before;
		unset( $candidates_expected[768] );
		$candidates_without_hook = $GLOBALS['wp_filter']['wp_calculate_image_srcset']->callbacks;
		add_filter( 'wp_calculate_image_srcset', 'candidates_apply', 20, 4 );
		$candidates_serialized = wp_calculate_image_srcset( array( 830, 200 ), $candidates_src, $candidates_metadata );
		remove_filter( 'wp_calculate_image_srcset', 'candidates_apply', 20 );
		candidates_check( $GLOBALS['wp_filter']['wp_calculate_image_srcset']->callbacks === $candidates_without_hook, 'The explicit local adapter must leave no automatic production policy behind.' );
		candidates_check( ImageCandidates::prune( $candidates_before, $candidates_src, $candidates_metadata ) === $candidates_expected, 'Only the wider-and-byte-smaller dominated candidate may disappear; every surviving descriptor and source byte must remain identical.' );
		candidates_check( is_string( $candidates_serialized ) && str_contains( $candidates_serialized, '300w' ) && str_contains( $candidates_serialized, '830w' ) && ! str_contains( $candidates_serialized, '768w' ), 'Core must serialize both remaining widths after the explicit public-hook adapter.' );
		candidates_check( wp_calculate_image_srcset( array( 830, 200 ), $candidates_src, $candidates_metadata ) === $candidates_native, 'Removing the adapter must restore the complete native output exactly.' );
		$candidates_evidence[$candidates_format] = array( 'origin' => $candidates_src, 'metadata_records' => count( $candidates_metadata['sizes'] ) + 1, 'native_widths_before' => array_keys( $candidates_before ), 'widths_after' => array_keys( $candidates_expected ), 'recorded_bytes' => array( 300 => $candidates_metadata['sizes']['medium']['filesize'], 768 => $candidates_metadata['sizes']['medium_large']['filesize'], 830 => $candidates_metadata['filesize'] ) );
	}
	$candidates_input = $candidates_captured;
	candidates_check( $candidates_webp['filesize'] === 7032 && $candidates_webp['sizes']['medium_large']['filesize'] === 11466 && $candidates_png['filesize'] === 12245 && $candidates_png['sizes']['medium_large']['filesize'] === 20848, 'Native PNG and WebP pruning must use their own complete measured metadata instead of borrowing the other format byte sizes.' );
	add_filter( 'query', 'candidates_query' );
	foreach ( array( 'missing-bytes', 'zero-bytes', 'string-bytes', 'foreign-origin', 'query', 'fragment', 'credentials', 'foreign-directory', 'unknown-file', 'density', 'crop', 'equal-bytes', 'larger-is-heavier', 'width-mismatch', 'key-mismatch', 'string-width', 'format-mismatch', 'mime-mismatch', 'unknown-sizes', 'conflicting-full', 'conflicting-candidate', 'invalid-duplicate' ) as $candidates_case ) {
		$candidates_sources = $candidates_input;
		$candidates_meta = $candidates_webp;
		if ( $candidates_case === 'missing-bytes' ) {
			unset( $candidates_meta['filesize'] );
		} elseif ( $candidates_case === 'zero-bytes' ) {
			$candidates_meta['filesize'] = 0;
		} elseif ( $candidates_case === 'string-bytes' ) {
			$candidates_meta['filesize'] = '7032';
		} elseif ( $candidates_case === 'foreign-origin' ) {
			$candidates_sources[830]['url'] = 'https://foreign.test/' . wp_basename( $candidates_source );
		} elseif ( $candidates_case === 'query' ) {
			$candidates_sources[830]['url'] .= '?v=unknown';
		} elseif ( $candidates_case === 'fragment' ) {
			$candidates_sources[830]['url'] .= '#unknown';
		} elseif ( $candidates_case === 'credentials' ) {
			$candidates_sources[830]['url'] = 'http://user:password@127.0.0.1/' . wp_basename( $candidates_source );
		} elseif ( $candidates_case === 'foreign-directory' ) {
			$candidates_sources[830]['url'] = dirname( $candidates_source ) . '/other/' . wp_basename( $candidates_source );
		} elseif ( $candidates_case === 'unknown-file' ) {
			$candidates_sources[830]['url'] = dirname( $candidates_source ) . '/unknown.webp';
		} elseif ( $candidates_case === 'density' ) {
			$candidates_sources[830]['descriptor'] = 'x';
		} elseif ( $candidates_case === 'crop' ) {
			$candidates_meta['height'] = 830;
		} elseif ( $candidates_case === 'equal-bytes' ) {
			$candidates_meta['filesize'] = 11466;
		} elseif ( $candidates_case === 'larger-is-heavier' ) {
			$candidates_meta['filesize'] = 22000;
		} elseif ( $candidates_case === 'width-mismatch' ) {
			$candidates_meta['width'] = 829;
		} elseif ( $candidates_case === 'key-mismatch' ) {
			$candidates_sources[829] = $candidates_sources[830];
			unset( $candidates_sources[830] );
		} elseif ( $candidates_case === 'string-width' ) {
			$candidates_sources[830]['value'] = '830';
		} elseif ( $candidates_case === 'format-mismatch' ) {
			$candidates_sources[830]['url'] = dirname( $candidates_source ) . '/other.png';
			$candidates_meta['file'] = '2026/11/other.png';
		} elseif ( $candidates_case === 'mime-mismatch' ) {
			$candidates_meta['mime-type'] = 'image/png';
		} elseif ( $candidates_case === 'unknown-sizes' ) {
			$candidates_meta['sizes'] = 'unsupported';
		} elseif ( $candidates_case === 'conflicting-full' || $candidates_case === 'invalid-duplicate' ) {
			$candidates_meta['sizes']['duplicate'] = array( 'file' => wp_basename( $candidates_meta['file'] ), 'width' => 830, 'height' => 200, 'filesize' => $candidates_case === 'invalid-duplicate' ? false : 10 );
		} elseif ( $candidates_case === 'conflicting-candidate' ) {
			$candidates_meta['sizes']['duplicate'] = array_merge( $candidates_meta['sizes']['medium_large'], array( 'filesize' => 10 ) );
		}
		candidates_check( ImageCandidates::prune( $candidates_sources, $candidates_source, $candidates_meta ) === $candidates_sources, 'Unsupported or ambiguous metadata must preserve the complete actual set: ' . $candidates_case );
	}
	foreach ( array( false, null, 'native', array() ) as $candidates_bad_sources ) {
		candidates_check( ImageCandidates::prune( $candidates_bad_sources, $candidates_source, $candidates_webp ) === $candidates_bad_sources, 'Non-array and empty candidate input must remain exactly native.' );
	}
	foreach ( array( null, false, 'native' ) as $candidates_bad_meta ) {
		candidates_check( ImageCandidates::prune( $candidates_input, $candidates_source, $candidates_bad_meta ) === $candidates_input, 'Unknown metadata shapes cannot justify removing a native candidate.' );
	}
	foreach ( array( 'javascript:alert(1)', '//127.0.0.1/logo.webp', dirname( $candidates_source ) . '/unknown-origin.webp', $candidates_source . '?q=unknown', $candidates_source . '#unknown', 'http://127.0.0.1:0/logo.webp', 'http://127.0.0.1/uploads/%2e%2e/logo.webp', 'http://127.0.0.1/uploads%2fforeign/logo.webp', 'http://127.0.0.1/uploads/logo%00.webp' ) as $candidates_bad_origin ) {
		candidates_check( ImageCandidates::prune( $candidates_input, $candidates_bad_origin, $candidates_webp ) === $candidates_input, 'Unknown, rewritten or ambiguous root URLs must retain the complete native set.' );
	}
	$candidates_duplicate = $candidates_webp;
	$candidates_duplicate['sizes']['identical-full'] = $candidates_duplicate;
	unset( $candidates_duplicate['sizes']['identical-full']['sizes'] );
	$candidates_expected = $candidates_input;
	unset( $candidates_expected[768] );
	candidates_check( ImageCandidates::prune( $candidates_input, $candidates_source, $candidates_duplicate ) === $candidates_expected, 'Equivalent repeated native records are unambiguous and retain the same comparison result.' );
	$candidates_unknown = $candidates_input;
	$candidates_unknown[900] = array( 'url' => 'https://unknown.test/unknown.webp', 'descriptor' => 'w', 'value' => 900, 'opaque' => array( 'keep' => true ) );
	$candidates_unknown_expected = $candidates_unknown;
	unset( $candidates_unknown_expected[768] );
	candidates_check( ImageCandidates::prune( $candidates_unknown, $candidates_source, $candidates_webp ) === $candidates_unknown_expected, 'An unrelated unknown candidate must survive unchanged while independent proven peers may still be pruned.' );
	$candidates_bounded = $candidates_input;
	for ( $candidates_index = 0; count( $candidates_bounded ) < 128; $candidates_index++ ) {
		$candidates_width = 1000 + $candidates_index;
		$candidates_bounded[$candidates_width] = array( 'url' => 'https://unknown.test/unknown-' . $candidates_width . '.webp', 'descriptor' => 'w', 'value' => $candidates_width );
	}
	$candidates_bounded_expected = $candidates_bounded;
	unset( $candidates_bounded_expected[768] );
	candidates_check( ImageCandidates::prune( $candidates_bounded, $candidates_source, $candidates_webp ) === $candidates_bounded_expected, 'Exactly 128 candidates must retain bounded comparison and preserve every unknown peer.' );
	$candidates_bounded[9000] = array( 'url' => 'https://unknown.test/over-limit.webp', 'descriptor' => 'w', 'value' => 9000 );
	candidates_check( ImageCandidates::prune( $candidates_bounded, $candidates_source, $candidates_webp ) === $candidates_bounded, 'More than 128 candidates must bypass pruning entirely.' );
	$candidates_many_meta = $candidates_webp;
	for ( $candidates_index = 0; count( $candidates_many_meta['sizes'] ) < 255; $candidates_index++ ) {
		$candidates_many_meta['sizes']['unknown-' . $candidates_index] = array( 'file' => 'unknown-' . $candidates_index . '.webp', 'width' => 1000 + $candidates_index, 'height' => 300, 'filesize' => 100 );
	}
	candidates_check( ImageCandidates::prune( $candidates_input, $candidates_source, $candidates_many_meta ) === $candidates_expected, 'Exactly 256 metadata records including the full image must remain within the declared lookup bound.' );
	$candidates_many_meta['sizes']['over-limit'] = array( 'file' => 'over-limit.webp', 'width' => 9000, 'height' => 300, 'filesize' => 100 );
	candidates_check( ImageCandidates::prune( $candidates_input, $candidates_source, $candidates_many_meta ) === $candidates_input, 'More than 256 metadata records must retain the entire native candidate set.' );
	candidates_check( $candidates_queries === 0 && $candidates_http === 0, 'The pure helper must make zero SQL and HTTP calls throughout positive, negative and bounded-input paths.' );
} finally {
	remove_filter( 'wp_calculate_image_srcset', 'candidates_capture', 19 );
	remove_filter( 'wp_calculate_image_srcset', 'candidates_apply', 20 );
	remove_filter( 'query', 'candidates_query' );
	remove_filter( 'pre_http_request', 'candidates_block_http', PHP_INT_MAX );
}

echo wp_json_encode( array( 'status' => 'pass', 'assertions' => $candidates_assertions, 'wordpress' => $wp_version, 'php' => PHP_VERSION, 'sql_queries_from_helper' => $candidates_queries, 'http_requests' => $candidates_http, 'filesystem_work_by_helper' => false, 'candidate_evidence' => $candidates_evidence, 'page_speed_benchmark' => false ) ) . "\n";
