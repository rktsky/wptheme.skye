jQuery(document).ready(function() {	
	/*
	 * Responsive Nav
	 */
	var mainMenu = '.primary-navigation .nav';
	 
	jQuery('.nav-toggle').on('click', function() {
		jQuery(this).toggleClass('open');
	});
	 
	function initResponsiveNavigation() {
		if(jQuery(window).width() <= 991){
			
			jQuery(mainMenu + ' > .menu-item-has-children > a').unbind('click');
			
			jQuery(mainMenu + ' > .menu-item-has-children > a').on('click', function(e) {
				
				e.preventDefault();
				
				var parent = jQuery(this).parent().clone();
				
				jQuery(mainMenu).addClass('second-level-active');
				jQuery(parent).addClass('el-remove').addClass('depth-2');
				
				jQuery(this).siblings('ul').addClass('active');
				jQuery(this).siblings('ul').prepend('<li class="depth-2 back"><a>Back</a></li>' + parent[0].outerHTML);
				
				
				subnavHeight = jQuery(this).siblings('ul').height();
				jQuery('.primary-navigation').css('cssText', 'height: ' + subnavHeight + 'px !important');
		
			});
			
			jQuery(mainMenu).on('click', 'li.back', function() {
				jQuery(mainMenu).toggleClass('second-level-active');
				jQuery('.primary-navigation').css('cssText', 'height: auto !important');
				jQuery('.sub-menu').removeClass('active');
				jQuery('li.back, li.el-remove').remove();
			});
		} else {
			
			jQuery(mainMenu + ', ' + mainMenu + ' > .menu-item-has-children > a').unbind('click');
			jQuery('.depth-2.back').remove();
			
		}
	}
	
	function showRoot() {
		jQuery(mainMenu).toggleClass('second-level-active');
	}
	
	 
	initResponsiveNavigation();
	jQuery(window).on('resize', function(){
		if(!jQuery('.nav-toggle').hasClass('has-click-event')) {
			initResponsiveNavigation();
		}
	});

});