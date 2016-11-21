<?php

/*
 * Custom Post Types
 * URL: https://codex.wordpress.org/Function_Reference/register_post_type
 *
 * Include this file in functions.php of this theme.
 *
 * Example below.
 * After registering new post types with this example, you have to add the capabilities for administrator
 * and the other needed roles with the Members plugin (https://de.wordpress.org/plugins/members/).
 *
 * Custom post types have to be registered in the init hook of WordPress.
 *
 * Icons for post types (menu_icon) are available here: https://developer.wordpress.org/resource/dashicons/
 *
 */

function sage_post_types() {

	$labels = array(
		'name'                => _x( 'Beiträge', 'Beitrag', 'sage' ),
		'singular_name'       => _x( 'Beitrag', 'Beitrag', 'sage' ),
		'menu_name'           => __( 'Beiträge', 'sage' ),
		'name_admin_bar'      => __( 'Beitrag', 'sage' ),
		'parent_item_colon'   => __( 'Übergeordnete Beiträge', 'sage' ),
		'all_items'           => __( 'Alle Beiträge', 'sage' ),
		'add_new_item'        => __( 'Neuer Beitrag', 'sage' ),
		'add_new'             => __( 'Neuer Beitrag', 'sage' ),
		'new_item'            => __( 'Neuer Beitrag', 'sage' ),
		'edit_item'           => __( 'Beitrag bearbeiten', 'sage' ),
		'update_item'         => __( 'Beitrag aktualisieren', 'sage' ),
		'view_item'           => __( 'Beitrag ansehen', 'sage' ),
		'search_items'        => __( 'Beitrag durchsuchen', 'sage' ),
		'not_found'           => __( 'Keine Beiträge gefunden', 'sage' ),
		'not_found_in_trash'  => __( 'Keine Beiträge im Papierkorb gefunden', 'sage' ),
	);

	$args = array(
		'label'               => __( 'Beitrag', 'sage' ),
		'description'         => __( 'Beitrag', 'sage' ),
		'labels'              => $labels,
		'supports'            => array( 'title', 'thumbnail' ),
		'hierarchical'        => false,
		'public'              => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'menu_position'       => 30,
		'show_in_admin_bar'   => true,
		'show_in_nav_menus'   => true,
		'can_export'          => true,
		'has_archive'         => true,
		'exclude_from_search' => true,
		'publicly_queryable'  => false,
		'map_meta_cap'        => true,
		'capability_type'     => 'custom_post',
		'menu_icon'			  => 'dashicons-admin-post'
	);

	register_post_type( 'custom_post', $args );

}
add_action( 'init', 'sage_post_types', 0 );

?>