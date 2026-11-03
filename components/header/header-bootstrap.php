<?php
/** Preserve the named legacy entry through the shared Bootstrap 6 Alpha component. */
get_component( 'header', false, array( 'brand' => $brand, 'navigation' => $navigation ) );
