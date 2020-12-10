<?php
	
	global $theme_options;

	$favicon = '';
	if( is_array( $theme_options ) && !empty( $theme_options['favicon' ] ) ) {
		$image = $theme_options['favicon' ];
		$favicon = '
	<link rel="shortcut icon" sizes="16x16 24x24 32x32 48x48 64x64 128x128 256x256 512x512" type="image/png" href="' . $image . '">
	<link rel="apple-touch-icon" sizes="57x57 72x72 114x114 120x120 144x144 152x152" href="' . $image . '">
	<link rel="apple-touch-icon-precomposed" sizes="57x57" href="' . $image . '">
	<meta content="yes" name="apple-mobile-web-app-capable">
		';
	}

?>

<head>
	<meta charset="utf-8">
	<meta http-equiv="x-ua-compatible" content="ie=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php echo $favicon; ?>
	<?php wp_head(); ?>
</head>
