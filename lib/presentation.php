<?php
/** Prepare classic-theme presentation data and scoped native Bootstrap navigation. */

/**
 * Check native passwords and optional Members rules before exposing custom fields.
 *
 * All custom presentation paths share this boundary; native the_content() still
 * supplies the corresponding password form or plugin denial message.
 *
 * @since 3.0.0
 * @param int $post_id Post identity, or zero for the current loop post.
 * @return string One of allowed, password or members.
 */
function skye_content_access_state( $post_id = 0 ) {
	$post_id = $post_id ?: get_the_ID();
	if ( ! $post_id ) {
		return 'allowed';
	}
	if ( function_exists( 'members_can_current_user_view_post' ) && ! members_can_current_user_view_post( $post_id ) ) {
		return 'members';
	}
	if ( post_password_required( $post_id ) ) {
		return 'password';
	}
	return 'allowed';
}

/**
 * Prepare the site identity once without making ACF a rendering dependency.
 *
 * Native custom logos take precedence; legacy ACF logo values remain supported.
 *
 * @since 3.0.0
 * @return array{name:string,url:string,logo:string,image:string} Escaped by the brand template.
 */
function skye_brand_model() {
	$logo = '';
	$logo_id = (int) get_theme_mod( 'custom_logo' );
	if ( $logo_id ) {
		$logo = get_image( $logo_id, 'full' ) ?: '';
	} else {
		$value = get( 'theme_logo', 'theme_options' );
		if ( is_array( $value ) ) {
			$logo_id = isset( $value['ID'] ) ? (int) $value['ID'] : 0;
			$logo = $logo_id ? ( get_image( $logo_id ) ?: '' ) : ( $value['url'] ?? '' );
		} elseif ( is_numeric( $value ) ) {
			$logo_id = (int) $value;
			$logo = get_image( $logo_id ) ?: '';
		} elseif ( is_string( $value ) ) {
			$logo = $value;
		}
	}
	return array( 'name' => get_bloginfo( 'name' ), 'url' => home_url( '/' ), 'logo' => $logo, 'image' => $logo_id ? wp_get_attachment_image( $logo_id, 'full', false, array( 'class' => 'custom-logo', 'alt' => get_bloginfo( 'name' ) ) ) : '' );
}

/**
 * Prepare a page heading without reading restricted custom-field content.
 *
 * Legacy title overrides remain plain text, and lead markup has a native KSES
 * boundary at output. Unknown header types keep the complete simple fallback.
 *
 * @since 3.0.0
 * @return array{title:string,lead:string,type:string,breadcrumbs:bool} Header context.
 */
function skye_page_header_model() {
	$header = array( 'title' => Rocketsky\Skye\Titles\title(), 'lead' => '', 'type' => 'simple', 'breadcrumbs' => false );
	if ( ! is_singular() || 'allowed' !== skye_content_access_state( get_queried_object_id() ) ) {
		return $header;
	}
	$value = function_exists( 'get_field' ) ? get_field( 'pageheader', get_queried_object_id() ) : get( 'pageheader', get_queried_object_id() );
	if ( ! is_array( $value ) ) {
		return $header;
	}
	if ( ! empty( $value['override'] ) && 'false' !== $value['override'] && is_string( $value['title'] ?? null ) ) {
		$header['title'] = $value['title'];
	}
	$header['lead'] = is_string( $value['lead'] ?? null ) ? $value['lead'] : '';
	$header['breadcrumbs'] = 'show' === ( $value['breadcrumbs'] ?? '' );
	$type = is_string( $value['type'] ?? null ) ? $value['type'] : '';
	if ( preg_match( '/^[a-zA-Z0-9_-]+$/D', $type ) && locate_template( 'components/pageheader/' . $type . '.php', false, false ) ) {
		$header['type'] = $type;
	}
	return $header;
}

/**
 * Prepare a bounded post-card context with a shared content-access boundary.
 *
 * Excerpts, custom fields, images and author data are omitted when restricted.
 * WordPress supplies image markup so responsive sizes and image filters apply.
 *
 * @since 3.0.0
 * @param int $post_id Native post identity.
 * @return array Post presentation values; templates never query another entity.
 */
function skye_post_model( $post_id ) {
	$model = skye_post_meta_model( $post_id );
	$model['id'] = (int) $post_id;
	$model['url'] = get_permalink( $post_id );
	$model['title'] = get_the_title( $post_id );
	$model['excerpt'] = $model['allowed'] ? ( get_excerpt( $post_id ) ?: '' ) : '';
	$model['image'] = $model['allowed'] ? get_the_post_thumbnail( $post_id, 'large' ) : '';
	return $model;
}

/**
 * Prepare only accessible post metadata, avoiding unused image/excerpt work.
 *
 * Single-post headers and archive cards share this contract without forcing a
 * full card's native responsive image generation into the metadata-only path.
 *
 * @since 3.0.0
 * @param int $post_id Native post identity.
 * @return array{date:string,datetime:string,author:string,allowed:bool} Display data.
 */
function skye_post_meta_model( $post_id ) {
	$allowed = 'allowed' === skye_content_access_state( $post_id );
	return array(
		'date' => $allowed ? get_the_date( '', $post_id ) : '',
		'datetime' => $allowed ? get_post_time( 'c', true, $post_id ) : '',
		'author' => $allowed ? get_the_author_meta( 'display_name', get_post_field( 'post_author', $post_id ) ) : '',
		'allowed' => $allowed,
	);
}

/**
 * Describe native menu arguments instead of caching visitor-specific HTML.
 *
 * Scoped public filters adapt the standard walker to Bootstrap 6 Alpha without editing
 * the bundled third-party walker, whose version resolver lacks a return value.
 *
 * @since 3.0.0
 * @param string $location Registered theme menu location.
 * @return array Native wp_nav_menu() arguments with the theme-specific marker.
 */
function skye_navigation_args( $location = 'primary_navigation' ) {
	return array( 'theme_location' => $location, 'container' => false, 'menu_class' => 'nav navbar-nav', 'depth' => 2, 'fallback_cb' => false, 'skye_bootstrap' => true );
}

/**
 * Add Bootstrap classes while preserving WordPress current and ancestor classes.
 *
 * Only explicitly marked theme menus receive these presentation classes.
 *
 * @since 3.0.0
 * @param string[] $classes Native menu-item classes.
 * @param WP_Post $item Native menu item.
 * @param object $args Native menu arguments.
 * @param int $depth Zero-based nesting depth.
 * @return string[] Combined native and Bootstrap classes.
 */
function skye_navigation_item_classes( $classes, $item, $args, $depth ) {
	if ( empty( $args->skye_bootstrap ) ) {
		return $classes;
	}
	$classes[] = 0 === $depth ? 'nav-item' : 'menu-item-container';
	if ( in_array( 'menu-item-has-children', $classes, true ) ) {
		$classes[] = 'skye-menu-parent';
	}
	return array_unique( $classes );
}
add_filter( 'nav_menu_css_class', 'skye_navigation_item_classes', 20, 4 );

/**
 * Apply Bootstrap dropdown attributes without removing native aria-current state.
 *
 * Parent links remain real destinations; a separate accessible toggle is added
 * after their native markup instead of preventing navigation to the parent page.
 *
 * @since 3.0.0
 * @param array $attributes Native link attributes.
 * @param WP_Post $item Native menu item.
 * @param object $args Native menu arguments.
 * @param int $depth Zero-based nesting depth.
 * @return array Native attributes with scoped Bootstrap link classes.
 */
function skye_navigation_link_attributes( $attributes, $item, $args, $depth ) {
	if ( empty( $args->skye_bootstrap ) ) {
		return $attributes;
	}
	$attributes['class'] = trim( ( $attributes['class'] ?? '' ) . ( 0 === $depth ? ' nav-link' : ' menu-item' ) );
	return $attributes;
}
add_filter( 'nav_menu_link_attributes', 'skye_navigation_link_attributes', 20, 4 );

/**
 * Add a sibling dropdown button so keyboard users can open submenus independently.
 *
 * Native item output is retained and the button is rendered by the component
 * loader, keeping regular HTML outside PHP string concatenation.
 *
 * @since 3.0.0
 * @param string $output Native menu item HTML.
 * @param WP_Post $item Native menu item.
 * @param int $depth Zero-based nesting depth.
 * @param object $args Native menu arguments.
 * @return string Original output plus a Bootstrap toggle when children exist.
 */
function skye_navigation_dropdown_toggle( $output, $item, $depth, $args ) {
	if ( empty( $args->skye_bootstrap ) || 0 !== $depth || ! in_array( 'menu-item-has-children', (array) $item->classes, true ) ) {
		return $output;
	}
	return $output . skye_component_html( 'navigation/toggle', false, array( 'label' => sprintf( __( 'Open submenu for %s', 'skye' ), wp_strip_all_tags( $item->title ) ) ) );
}
add_filter( 'walker_nav_menu_start_el', 'skye_navigation_dropdown_toggle', 20, 4 );

/**
 * Mark native submenus for Bootstrap without affecting other theme/plugin menus.
 *
 * @since 3.0.0
 * @param string[] $classes Native submenu classes.
 * @param object $args Native menu arguments.
 * @return string[] Combined native and Bootstrap submenu classes.
 */
function skye_navigation_submenu_classes( $classes, $args ) {
	if ( ! empty( $args->skye_bootstrap ) ) {
		$classes[] = 'menu';
	}
	return array_unique( $classes );
}
add_filter( 'nav_menu_submenu_css_class', 'skye_navigation_submenu_classes', 20, 2 );

/**
 * Render the selected view behind one password/Members boundary.
 *
 * This also protects custom pagebuilder templates before any ACF row is loaded.
 *
 * @since 3.0.0
 * @return void
 */
function skye_render_main_view() {
	$templates = new Rocketsky\Skye\Packages\Templates();
	$template = is_singular() && 'allowed' !== skye_content_access_state( get_queried_object_id() )
		? locate_template( 'views/restricted.php', false, false ) : $templates->template();
	if ( $template ) {
		include $template;
	}
}

/**
 * Render optional ACF flexible content through existing component overrides.
 *
 * Missing ACF or missing layouts retain native content; access is checked before
 * rows or custom values are read. A layout name cannot escape components/.
 *
 * @since 3.0.0
 * @return void
 */
function skye_render_pagebuilder() {
	if ( 'allowed' !== skye_content_access_state() ) {
		the_content();
		return;
	}
	$rendered = false;
	if ( function_exists( 'have_rows' ) && have_rows( 'pagebuilder' ) ) {
		while ( have_rows( 'pagebuilder' ) ) {
			the_row();
			$layout = get_row_layout();
			$rendered = get_component( 'pagebuilder', $layout, array( 'row' => get_row( true ) ) ) || $rendered;
		}
	}
	if ( ! $rendered ) {
		the_content();
	}
}

/**
 * Prepare native menu output for the enhanced navbar and a complete no-JS path.
 *
 * Each output is request-scoped, preserving plugin/language/current-page state.
 * The fallback deliberately omits interactive Bootstrap menu classes so all
 * nested links remain reachable when no JavaScript has enhanced the page.
 *
 * @since 3.0.0
 * @return array{html:string,fallback:string} Native serializer output for templates.
 */
function skye_navigation_model() {
	$args = skye_navigation_args();
	$args['echo'] = false;
	$html = wp_nav_menu( $args );
	$args['skye_bootstrap'] = false;
	$args['menu_id'] = 'skye-navigation-fallback';
	$args['menu_class'] = 'skye-menu-fallback';
	$fallback = wp_nav_menu( $args );
	return array( 'html' => is_string( $html ) ? $html : '', 'fallback' => is_string( $fallback ) ? $fallback : '' );
}

/**
 * Render native comments through a small loader adapter after access checks.
 *
 * WordPress prepares the real comment query/global contracts; the adapter then
 * loads the HTML component through the same explicit theme loader as other UI.
 *
 * @since 3.0.0
 * @return void
 */
function skye_render_comments() {
	if ( 'allowed' === skye_content_access_state() && ( comments_open() || get_comments_number() ) ) {
		comments_template( '/templates/comments.php' );
	}
}

/**
 * Obtain optional provider breadcrumb markup without making it a dependency.
 *
 * Only the installed provider serializes this HTML; templates do not rebuild
 * its hierarchy or issue duplicate breadcrumb data queries.
 *
 * @since 3.0.0
 * @return string Provider-owned HTML, or an empty string when unavailable.
 */
function skye_breadcrumbs_html() {
	return function_exists( 'yoast_breadcrumb' ) ? (string) yoast_breadcrumb( '', '', false ) : '';
}
