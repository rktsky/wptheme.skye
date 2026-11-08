<?php
/** Render the exact native content pipeline and pagination without re-entering source selection. */
the_content();
wp_link_pages( array( 'before' => '<nav class="page-nav" aria-label="' . esc_attr__( 'Content pages', 'skye' ) . '">', 'after' => '</nav>' ) );
