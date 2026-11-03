<?php
/** Display the shared header from explicitly prepared site identity and navigation. */
?>
<header class="header banner">
	<?php get_component( 'navigation', false, array( 'brand' => $brand, 'navigation' => $navigation ) ); ?>
</header>
