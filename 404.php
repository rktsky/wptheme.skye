<?php
global $theme_options, $option_name;

// get name of website
$blog_name = get_bloginfo( 'name' );
$home_url = get_home_url();
?>
<div class="not-found-main-container">
    <div class="not-found-centered-container">
        <div class="not-found-header-container">
			<?php if( isset( $theme_options['theme_logo'] ) && $theme_options['theme_logo'] !== false ) : ?>
                <img src="<?php echo $theme_options['theme_logo']; ?>">
			<?php else : ?>
				<?php echo "<h1>".$blog_name."</h1>"; ?>
			<?php endif; ?>
        </div>
        <div class="not-found-title-container">
            <p><?php _e( 'Hier finden wir leider nichts...', 'sage' ); ?></p>
        </div>
        <div class="not-found-subtitle-container">
            <p><?php echo sprintf( __( 'In <span id="not-found-counter" data-label-single="Sekunde" data-label-multiple="Sekunden">10 Sekunden</span> werden Sie <a href="%s" title="Home">zur Startseite</a> weitergeleitet.</p>', 'sage' ), $home_url); ?>
        </div>
    </div>
</div>
<script>
    var count = 10;
    var counter = setInterval( timer, 1000 ); //1000 will run it every 1 second

    function timer() {

        count =	count-1;
        singleCount = jQuery( '#not-found-counter' ).data('label-single');
        multiCount = jQuery( '#not-found-counter' ).data('label-multiple');

        if(count === 1) {
            document.getElementById( 'not-found-counter' ).innerHTML=count + ' ' + singleCount; // watch for spelling
        } else {
            document.getElementById( 'not-found-counter' ).innerHTML=count + ' ' + multiCount; // watch for spelling
        }

        if (count === 1) {
            clearInterval(counter);
            window.location.replace('<?php echo $home_url ?>');
            return;
        }

    }
</script>