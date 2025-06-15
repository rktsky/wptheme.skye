<?php

	// Helper functions for managing rights on site
	// $role		- String or array of roles
	// $template	- String of an existing template which shows up if we fail (false for returning false on failure)
	function rights( $role = true, $template = '404' ) {

		// get actual user
		global $current_user;

		// Check if we have an user
		if( empty( $current_user ) ) {
			// See if we can get that damn user
			$current_user = wp_get_current_user();
		}		

		// Check if the given role(s) is in the user's roles array
		$inrole = false;
		if( !empty( $current_user ) ) {
			// If we have an array as input, else if we have a string
			if( is_array( $role ) ) {
				foreach( $role as $r ) {
					if( in_array( $r, (array) $current_user->roles ) )
						$inrole = true;
				}
			} else {
				if( in_array( (string) $role, (array) $current_user->roles ) )
					$inrole = true;
			}
		}

		if (
			// when role is set to public, all or the given role is found in user's role - continue and do nothing
			$role === 'public'
			||
			$role === 'all'
			||
			$inrole === true
		) {
			return true;
		} elseif (
			// when role is set to loggedin, left empty and the user is loggedin - continue and do nothing
			$role === 'public_only'
			&&
			! $current_user->exists()
		) {
			return true;
		} elseif (
			// when role is set to loggedin, left empty and the user is loggedin - continue and do nothing
			(
				$role === 'loggedin'
				||
				$role === ''
				||
				$role === true
				||
				( is_array( $role ) && count( $role ) === 1 && $role[0] === '' )
			)
			&&
			$current_user->exists()
		) {
			return true;
		} elseif (
			// when role is not found in user's role - show 404 template (or given template)
			$inrole === false
			||
			$role === false
		) {
			if( !empty( $template ) ) {
				get_template_part( $template );
				wp_footer();
				exit;
			} else {
				return false;
			}
		}

		// when failing everything - dead end
		if( !empty( $template ) && !empty( locate_template ( $template ) ) ) {
			get_template_part( $template );
			wp_footer();
			exit;
		} else {
			return false;
		}

	}
	