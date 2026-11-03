<?php
/** Display the shared site identity without requiring custom footer options. */
?>
<footer class="content-info py-4">
	<div class="container">
		<?php get_component( 'header', 'brand', array( 'brand' => $brand ) ); ?>
	</div>
</footer>
