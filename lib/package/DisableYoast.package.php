<?php

namespace Cubetech\Theme\Packages;

/**
 * Disable Yoast for CPT without single view
 *
 * @author  Christoph S. Ackermann <christoph.ackermann@cubetech.ch>
 * @version 1.0
 */
class DisableYoast
{

	/**
	 * Hooks the removeMetaBox action.
	 *
	 * @return void
	 */
	public function __construct()
	{
		add_action('add_meta_boxes', array($this, 'removeMetaBox'));
	}

	/**
	 * Remove meta box
	 *
	 * @return void
	 */
	public function removeMetaBox()
	{
		remove_meta_box('wpseo_meta', 	'ct-demo', 	'normal');
	}

}
