<?php the_content(); ?>

<style>
    iframe { width:100%; }
</style>
<script type="text/javascript" src="<?php echo get_stylesheet_directory_uri(); ?>/assets/scripts/iframeResizer.min.js"></script>
<iframe src="http://onx.sybille.dev.cubetech.ch/kontakt" scrolling="no" id="ct-iframe"></iframe>

<script>
//     iFrameResize({log:false, checkOrigin:false, heightCalculationMethod:'bodyScroll' })
iFrameResize({log:true, checkOrigin:false, heightCalculationMethod : 'lowestElement'}, '#ct-iframe')



</script>



<?php wp_link_pages(['before' => '<nav class="page-nav"><p>' . __('Pages:', THEME_TEXT_DOMAIN), 'after' => '</p></nav>']); ?>
