<?php

	// preparing data
	global $theme_options;
	$logo 					= get_image_url_by_size( $theme_options['theme_logo'] );

?>

    <a class="brand" href="<?= esc_url(home_url('/')); ?>">
	    <div>
		    <img id="brandlogo" src="<?php echo $logo; ?>" alt="">
	    </div>
	</a>
	<br>
	