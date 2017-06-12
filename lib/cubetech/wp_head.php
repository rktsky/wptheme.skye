<?php

/**
 * If there is an option value for 'ct_facebookpixel' set, then add the 
 * relevant HTML snippet to the head of every page. Use ACF to add the 
 * option field to the WordPress admin area. (Code not included here.)
 *
 * Facebook code version 2.0
 *
 * @since 12.6.2017 mhm
 */
add_action( 'wp_head', function(){
    $ct_facebookpixel_id = get_option('ct_facebookpixel_id');
    if(!empty($ct_facebookpixel_id)){
        printf(
            '<!-- Facebook Pixel Code -->
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version=\'2.0\';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,\'script\',\'https://connect.facebook.net/en_US/fbevents.js\');
fbq(\'init\', \'%1$s\');
fbq(\'track\', \'PageView\');
</script>
<noscript><img height="1" width="1" src="https://www.facebook.com/tr?id=%1$s&ev=PageView&noscript=1"/></noscript>
<!-- End Facebook Pixel Code -->',
            trim($ct_facebookpixel_id)
        );
    }
});