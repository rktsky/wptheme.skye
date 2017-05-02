<?php
/**
 * Register options page.
 */
function register_options() {
	
	// Theme options
	$page = array(
		'page_title' => 'Theme Optionen',
		'menu_title' => '',
		'menu_slug' => 'theme-options',
		'capability' => 'edit_posts',
		'position' => false,
		'parent_slug' => '',
		'icon_url' => false,
		'redirect' => true,
		'post_id' => 'theme_options',
		'autoload' => false,
	);
	
	acf_add_options_page( $page );

}

// Set warning in backend if acf is not installed
function set_theme_options_warning() {

	echo '
	<div class="notice notice-error">
		<p><strong>' . __( 'Fehler', THEME_TEXT_DOMAIN ) . ':</strong> ' . __( 'Für das aktive WordPress-Theme wird das Plugin Advanced Custom Fields PRO benötigt.', THEME_TEXT_DOMAIN ) . '</p>
	</div>';

}

if( function_exists( 'acf_add_options_page' ) ) {
	add_action( 'init', 'register_options' );
} else {
	add_action( 'admin_notices', 'set_theme_options_warning' );
}

// set theme options for global access
function set_theme_options(){
	
	global $theme_options;
	
	$theme_options = array();
	
	// define fields
	$fields = array( 'theme_logo' );
	
	// loop defined fields and set them in theme options array
	foreach( $fields as $field ) {
		
		$value = get_field( $field, 'theme_options' );
		
		if( $value !== NULL && $value !== false ){
			$theme_options[$field] = $value;			
		} else {
			$theme_options[$field] = false;
		}
		
	}
	
}

if( function_exists( 'get_field' ) ) {
	add_action( 'init', 'set_theme_options' );
}

// options fields
if( function_exists( 'acf_add_local_field_group' ) ):

acf_add_local_field_group( array (
	'key' => 'group_57dc049b82657',
	'title' => 'Grundinformationen zum Theme',
	'fields' => array (
		array (
			'key' => 'field_57dc04b32276e',
			'label' => 'Logo',
			'name' => 'theme_logo',
			'type' => 'image',
			'instructions' => 'Dieses Logo wird für das Theme verwendet und zum Beispiel im Kopfbereich der Webseite dargestellt. Für die Logos wird nur das vektorisierte Bildformat SVG erlaubt.',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => array (
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'return_format' => 'url',
			'preview_size' => 'thumbnail',
			'library' => 'all',
			'min_width' => '',
			'min_height' => '',
			'min_size' => '',
			'max_width' => '',
			'max_height' => '',
			'max_size' => '',
			'mime_types' => 'svg',
		),
	),
	'location' => array (
		array (
			array (
				'param' => 'options_page',
				'operator' => '==',
				'value' => 'theme-options',
			),
		),
	),
	'menu_order' => 0,
	'position' => 'normal',
	'style' => 'default',
	'label_placement' => 'top',
	'instruction_placement' => 'label',
	'hide_on_screen' => '',
	'active' => 1,
	'description' => '',
) );

endif;
	
?>