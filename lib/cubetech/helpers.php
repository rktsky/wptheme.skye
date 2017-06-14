<?php
	
/**
 * custom dump function with pre tag and parameter to interrupt script.
 * 
 * @access public
 * @param mixed $var
 * @param bool $die (default: false)
 * @return void
 */
function dump($var, $die = false)
{
    echo '<pre>' . print_r($var, 1) . '</pre>';
    if ($die) {
        die();
    }
}

/**
 * get_image_url_by_size function.
 * 
 * @access public
 * @param bool $post_ID (default: false)
 * @param string $size (default: '')
 * @return void
 */
function get_image_url_by_size($post_ID = false, $size = '')
{
    if (empty($post_ID)) {
        return false;
    }
    if ($size === '') {
        $size = 'full';
    }
    $image = wp_get_attachment_image_src($post_ID, $size);
    $image = $image[0];

    return $image;
}
	
/**
 * telephone_url function.
 * 
 * @access public
 * @param mixed $number
 * @return void
 */
function telephone_url($number)
{
    $nationalprefix = '+41';
    $protocol       = 'tel:';

    $formattedNumber = $number;
    if ($formattedNumber !== '') {
        // add national dialing code prefix to tel: link if it's not already set
        if (strpos($formattedNumber, '00') !== 0 && strpos($formattedNumber, '0800') !== 0 && strpos($formattedNumber, '+') !== 0 && strpos($formattedNumber, $nationalprefix) !== 0) {
            $formattedNumber = preg_replace('/^0/', $nationalprefix, $formattedNumber);
        }
    }

    $formattedNumber = str_replace('(0)', '', $formattedNumber);
    $formattedNumber = preg_replace('~[^0-9\+]~', '', $formattedNumber);
    $formattedNumber = trim($formattedNumber);

    return $protocol . $formattedNumber;
}
