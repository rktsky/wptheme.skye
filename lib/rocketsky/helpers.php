<?php
/** Shared component rendering and native metadata helpers; no persistent presentation cache. */

/** @var int Public missing-component resolver contract. @since 3.5.1 */
define( 'SKYE_COMPONENT_RESOLVER_VERSION', 1 );

/**
 * Render a child-overridable component with an isolated explicit context.
 *
 * One- and two-argument legacy calls remain valid. Local loader names cannot be
 * overwritten, and unsafe relative paths never reach the template resolver.
 *
 * @since 2018-09-05
 * @param string $name Component path relative to components/.
 * @param string|false $second Optional legacy subcomponent name.
 * @param array $args Small named presentation context; reserved keys are ignored.
 * @return bool Whether a valid component was found and rendered.
 */
function get_component( $name, $second = false, array $args = array() ) {
	if ( ! is_string( $name ) || ( false !== $second && ! is_string( $second ) ) ) {
		return false;
	}
	if ( false !== $second && '' !== $second ) {
		$name .= '/' . $second;
	}
	if ( ! preg_match( '~^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$~D', $name ) ) {
		return false;
	}
	$template = locate_template( array( 'components/' . $name . '.php' ), false, false );
	if ( ! $template && ( false === $second || '' === $second ) ) {
		$template = locate_template( array( 'components/' . $name . '/' . basename( $name ) . '.php' ), false, false );
	}
	if ( ! $template ) {
		try {
			/**
			 * Resolve an otherwise missing component through trusted local plugin code.
			 *
			 * Native child/theme files retain precedence. The validated combined name
			 * and explicit context never choose an include path without this filter.
			 * Callbacks are trusted PHP code, not a user-controlled template source.
			 *
			 * @since 3.5.1
			 * @param string|false $template Missing template; return a local readable PHP path.
			 * @param string $name Validated combined component path.
			 * @param array $args Explicit native presentation context, without secrets.
			 */
			$template = apply_filters( 'skye_component_template', false, $name, $args );
			if ( ! is_string( $template ) || '' === $template || strlen( $template ) > 4096 || str_contains( $template, "\0" ) || str_contains( $template, '://' ) || ! is_file( $template ) || ! is_readable( $template ) ) {
				return false;
			}
			$template = realpath( $template );
			if ( ! $template || ! str_ends_with( $template, '.php' ) ) {
				return false;
			}
		} catch ( \Throwable ) {
			return false;
		}
	}
	extract( $args, EXTR_SKIP );
	include $template;
	return true;
}

/**
 * Capture an existing component for native APIs that require an HTML string.
 *
 * The actual loader remains the sole resolver; failed rendering restores the
 * caller's buffer depth instead of leaking partial markup or another buffer.
 *
 * @since 3.0.0
 * @param string $name Component name relative to components/.
 * @param string|false $second Optional subcomponent name.
 * @param array $args Explicit named presentation context.
 * @return string Rendered markup, or an empty string for a missing component.
 * @throws Throwable Propagates a component failure after cleaning its buffers.
 */
function skye_component_html( $name, $second = false, array $args = array() ) {
	$level = ob_get_level();
	ob_start();
	try {
		get_component( $name, $second, $args );
		return ob_get_clean();
	} finally {
		while ( ob_get_level() > $level ) {
			ob_end_clean();
		}
	}
}

/**
 * Share Skye's external-link policy with explicit theme/component anchor attributes.
 *
 * Pass raw attributes and escape them at the existing output boundary. Internal,
 * relative and nonweb destinations retain every supplied value unchanged.
 *
 * @since 3.2.2
 * @param array $attributes Native anchor attributes with an optional raw href.
 * @return array Attributes with external target/rel defaults and all other values preserved.
 */
function skye_external_link_attributes( array $attributes ) {
	return \Rocketsky\Skye\Packages\ExternalLinks::attributes( $attributes );
}

/**
 * Read one raw field from its explicit native WordPress storage identity.
 *
 * This deliberately avoids ACF field-definition/formatting queries even when ACF
 * is active. The former format_value argument remains accepted for callers but
 * does not turn a scalar metadata value into a list. Call get_field() explicitly
 * when a presentation contract requires ACF formatting. Missing, false and zero
 * values never trigger a read from another user's metadata or global options.
 *
 * @since 2018-09-05
 * @param string $selector Native field name, not an ACF field key.
 * @param int|string|object|false $post_id Post ID/current post, WP_Post/WP_User/WP_Term/WP_Comment, user_N/term_N/comment_N or option(s)/theme_options.
 * @param bool $format_value Legacy compatibility argument; raw reads ignore it.
 * @return mixed One native value, or false for an invalid selector or identity.
 */
function get( $selector, $post_id = false, $format_value = true ) {
	if ( ! is_string( $selector ) || '' === $selector ) {
		return false;
	}
	if ( false === $post_id || null === $post_id || '' === $post_id ) {
		$post_id = get_the_ID();
	}
	if ( $post_id instanceof WP_Post ) {
		$post_id = $post_id->ID;
	} elseif ( $post_id instanceof WP_User ) {
		return get_user_meta( $post_id->ID, $selector, true );
	} elseif ( $post_id instanceof WP_Term ) {
		return get_term_meta( $post_id->term_id, $selector, true );
	} elseif ( $post_id instanceof WP_Comment ) {
		return get_comment_meta( $post_id->comment_ID, $selector, true );
	}
	if ( ( is_int( $post_id ) && $post_id > 0 ) || ( is_string( $post_id ) && preg_match( '/^[1-9][0-9]*$/D', $post_id ) ) ) {
		return get_post_meta( (int) $post_id, $selector, true );
	}
	if ( ! is_string( $post_id ) ) {
		return false;
	}
	if ( preg_match( '/^(user|term|comment)_([1-9][0-9]*)$/D', $post_id, $entity ) ) {
		return get_metadata( $entity[1], (int) $entity[2], $selector, true );
	}
	if ( in_array( $post_id, array( 'option', 'options', 'theme_options' ), true ) ) {
		$prefix = 'theme_options' === $post_id ? 'theme_options' : 'options';
		return get_option( $prefix . '_' . $selector, false );
	}
	return false;
}

/**
 * Resolve an image through WordPress so replacements and metadata stay coherent.
 *
 * No extra transient can outlive native attachment changes or cache invalidation.
 *
 * @since 2021-02-03
 * @param int $id Native attachment ID.
 * @param string|int[] $size Registered size or requested dimensions.
 * @return string|false Image URL, or false when no image can be resolved.
 */
function get_image( $id, $size = 'full' ) {
	if ( (int) $id < 1 ) {
		return false;
	}
	return wp_get_attachment_image_url( (int) $id, $size );
}

/**
 * Preserve the former ACF filter API without maintaining the removed image cache.
 *
 * WordPress already invalidates its attachment metadata; no extra write is needed.
 *
 * @since 2021-02-03
 * @param mixed $value Incoming field value.
 * @param int|string $post_id Field owner, retained for compatibility.
 * @param array $field Field definition, retained for compatibility.
 * @return mixed Unchanged incoming value.
 */
function get_image_reset( $value, $post_id, $field ) {
	return $value;
}

if ( ! function_exists( 'get_primary_taxonomy_id' ) ) {
	/**
	 * Resolve a Yoast primary term, falling back to the first assigned native term.
	 *
	 * Invalid taxonomies, plugin errors and empty term lists never become array access.
	 *
	 * @since 2018-09-05
	 * @param int $post_id Native post identity.
	 * @param string $taxonomy Registered taxonomy name.
	 * @return int|string Primary/fallback term ID, or an empty string when unavailable.
	 */
	function get_primary_taxonomy_id( $post_id, $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return '';
		}
		if ( class_exists( 'WPSEO_Primary_Term' ) ) {
			$primary = new WPSEO_Primary_Term( $taxonomy, $post_id );
			$term_id = $primary->get_primary_term();
			if ( ! is_wp_error( $term_id ) && (int) $term_id > 0 ) {
				$term = get_term( (int) $term_id, $taxonomy );
				if ( $term instanceof WP_Term && has_term( $term->term_id, $taxonomy, $post_id ) ) {
					return (int) $term->term_id;
				}
			}
		}
		$terms = wp_get_post_terms( $post_id, $taxonomy );
		return ! is_wp_error( $terms ) && ! empty( $terms[0]->term_id ) ? (int) $terms[0]->term_id : '';
	}
}

/**
 * Print escaped diagnostic values only during explicit WordPress debugging.
 *
 * The legacy helper cannot disclose arbitrary values on a production request.
 *
 * @since 2017-06-14
 * @param mixed $var Value to inspect; callers must still exclude secrets.
 * @param bool $die Whether to terminate after the debug output.
 * @return void
 */
function dump( $var, $die = false ) {
	if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	get_component( 'admin/debug', false, array( 'debug_value' => print_r( $var, true ) ) );
	if ( $die ) {
		exit;
	}
}

/**
 * Build a telephone URI while preserving the historical Swiss default prefix.
 *
 * A caller may supply an explicit country prefix for another project.
 *
 * @since 2017-06-14
 * @param string $number Human-readable telephone number.
 * @param string $nationalprefix International prefix for a local leading zero.
 * @return string Telephone URI containing digits and an optional leading plus.
 */
function telephone_url( $number, $nationalprefix = '+41' ) {
	$number = str_replace( '(0)', '', (string) $number );
	$number = preg_replace( '/[^0-9+]/', '', $number );
	if ( str_starts_with( $number, '00' ) ) {
		$number = '+' . substr( $number, 2 );
	} elseif ( str_starts_with( $number, '0' ) && ! str_starts_with( $number, '0800' ) ) {
		$number = $nationalprefix . substr( $number, 1 );
	}
	return 'tel:' . preg_replace( '/(?!^)\+/', '', $number );
}

/**
 * Return a bounded plain-text excerpt using WordPress word handling.
 *
 * Native excerpt filters retain password and plugin restrictions before trimming.
 *
 * @since 2021-02-28
 * @param int $id Native post identity.
 * @param int $length Maximum word count, clamped to 1–500.
 * @return string|false Trimmed excerpt, or false when none is available.
 */
function get_excerpt( $id, $length = 25 ) {
	if ( function_exists( 'skye_content_access_state' ) && 'allowed' !== skye_content_access_state( $id ) ) {
		return false;
	}
	$excerpt = get_the_excerpt( $id );
	return '' !== $excerpt ? wp_trim_words( $excerpt, max( 1, min( 500, (int) $length ) ), '…' ) : false;
}

/**
 * Normalize an explicit title into a native WordPress slug.
 *
 * The explicit input prevents stale global presentation data affecting another post.
 *
 * @since 2025-06-18
 * @param string|false $title Title to normalize.
 * @return string|false Slug, or false for an absent title.
 */
function make_slug( $title = false ) {
	return is_string( $title ) && '' !== $title ? sanitize_title( $title ) : false;
}
