jQuery(document).ready(function() {

	var body_has_class = false;

	jQuery('.navbar-toggle').on( 'click', function() {
		
		if( !body_has_class ) {
		    jQuery('body').addClass( 'nav-open' );
		    body_has_class = true;
		}
		else {
		    jQuery('body').removeClass( 'nav-open' );
		    body_has_class = false;
		}
	});

});