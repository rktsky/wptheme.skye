<?php

/*
 * Custom Taxonomies
 * URL: https://codex.wordpress.org/Function_Reference/register_taxonomy
 *
 * Include this file in functions.php of this theme.
 *
 * Example below.
 *
 * Custom taxonomies have to be registered in the init hook of WordPress.
 *
 */

function register_sage_taxonomies()	{

	$labels = array(
		'name'              => _x( 'Kategorie', 'taxonomy general name', 'sage' ),
		'singular_name'     => _x( 'Kategorie', 'taxonomy singular name', 'sage' ),
		'search_items'      => __( 'Suche Kategorien', 'sage' ),
		'all_items'         => __( 'Alle Kategorien', 'sage' ),
		'parent_item'       => __( 'Übergeordnete Kategorie', 'sage' ),
		'parent_item_colon' => __( 'Übergeordnete Downloadkategorie:', 'sage' ),
		'edit_item'         => __( 'Kategorie bearbeiten', 'sage' ),
		'update_item'       => __( 'Kategorie aktualisieren', 'sage' ),
		'add_new_item'      => __( 'Neue Kategorie hinzufügen', 'sage' ),
		'new_item_name'     => __( 'Neuer Kategoriename', 'sage' ),
		'menu_name'         => __( 'Kategorien' ),
	);

	$args = array(
		'hierarchical'      => true,
		'labels'            => $labels,
		'show_ui'           => true,
		'show_admin_column' => true,
		'query_var'         => true,
		'rewrite'           => array( 'slug' => 'kategorie' ),
	);

	register_taxonomy( 'custom_category', array( 'custom_post' ), $args );

}

add_action('init', 'register_sage_taxonomies');

?>