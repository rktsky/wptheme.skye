<?php

	// preparing data
	global $theme_options;

	$logo = false;
	if( is_array( $theme_options ) && !empty( $theme_options['theme_logo'] ) )
		$logo = $theme_options['theme_logo'];

?>

    <a class="brand" href="<?= esc_url(home_url('/')); ?>">
	    <div>
		    <img id="brandlogo" src="<?php echo $logo; ?>" alt="">
	    </div>
	</a>
	<br>
	