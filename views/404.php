<?php
/** Provide a complete lightweight not-found view without automatic URI-fragment queries. */
get_component( 'page', 'header', array( 'header' => skye_page_header_model() ) );
?>
<div class="container py-4">
	<p><?php esc_html_e( 'The page could not be found. Try searching for what you need.', 'skye' ); ?></p>
	<?php get_search_form(); ?>
</div>
