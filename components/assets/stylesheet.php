<?php
/** Display native serialized preload and original stylesheet tags with a no-script fallback. */
echo $preload_html;
?>
<noscript><?php echo $stylesheet_html; ?></noscript>
