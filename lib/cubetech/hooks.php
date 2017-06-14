<?php
	
// remove paragraph tags around single image
add_filter('the_content', function ($content) {
    return preg_replace('/<p>\s*(<a .*>)?\s*(<img .* \/>)\s*(<\/a>)?\s*<\/p>/iU', '\1\2\3', $content);
});

// remove comments and remove tools if user role not equal administrator
add_action('admin_menu', function () {
    remove_menu_page('edit-comments.php');

    if (!current_user_can('administrator')) {
        remove_menu_page('tools.php');
    }	    
});

// Lower the priority order of the Yoast meta box in the backend, so that it appears lower down the page
add_filter('wpseo_metabox_prio', function(){
    return 'low';
}, 10, 0);

// Remove unwanted admin dashboard panels
add_action('wp_dashboard_setup', function() {
    
    // Core
    remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' );
    remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
    remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );

    // From plugins
    remove_meta_box( 'wp_user_log_dashboard_widget', 'dashboard', 'normal' );
    remove_meta_box( 'ual_dashboard_widget', 'dashboard', 'normal' );

    // Debug only
    // global $wp_meta_boxes;
    // dump($wp_meta_boxes, true);

});

// Never show the admin welcome panel
remove_action('welcome_panel', 'wp_welcome_panel');