<?php

	// preparing data
	global $theme_options;

	$logo = false;

	if( is_array( $theme_options ) && !empty( $theme_options['theme_logo'] ) )
		$logo = $theme_options['theme_logo'];

?>

	<a class="brand" href="<?= esc_url(home_url('/')); ?>" aria-label="Zurück zur Startseite">
		<div class="d-flex align-items-center">
			<img class="brandlogo" alt="logo" src="<?php echo $logo; ?>" width="326" height="17.208">
		</div>
	</a>
	