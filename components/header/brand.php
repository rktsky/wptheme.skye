<?php

	// preparing data
	global $theme_options;

	$logo = false;
	if( is_array( $theme_options ) && !empty( $theme_options['theme_logo'] ) )
		$logo = $theme_options['theme_logo'];

	$mobilelogo = false;
	if( is_array( $theme_options ) && !empty( $theme_options[ 'theme_logo_mark' ] ) )
		$mobilelogo = $theme_options[ 'theme_logo_mark' ]["url"];

?>

	<a class="brand" href="<?= esc_url(home_url('/')); ?>">
		<div class="desktop d-none d-md-block">
			<img class="brandlogo" src="<?php echo $logo; ?>" alt="">
		</div>
		<div class="mobile d-block d-md-none">
			<img class="brandlogo-mobile" src="<?php echo $mobilelogo; ?>" alt="Logo Mobile">
		</div>
	</a>
	