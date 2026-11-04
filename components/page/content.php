<?php
/** Render the current native post content, including provider/password filters and pagination. */
the_content();
wp_link_pages( array( 'before' => '<nav class="page-nav" aria-label="' . esc_attr__( 'Content pages', 'skye' ) . '">', 'after' => '</nav>' ) );
