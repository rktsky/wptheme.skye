<?php
/** Adapt the native comments_template() lifecycle to the explicit component loader. */
get_component( 'comments', false, array( 'comments_allowed' => 'allowed' === skye_content_access_state() ) );
