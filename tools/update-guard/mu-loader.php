<?php
/**
 * Load the independent reviewed-update boundary before ordinary plugins and themes.
 *
 * Plugin Name: Skye Verified Provider Updates
 * Description: Restrict explicitly protected plugin updates to reviewed original files and local integration prerequisites.
 * Version: 1.0.0
 * Requires at least: 6.8.12
 * Requires PHP: 8.3
 * Author: Rocketsky GmbH
 * License: BSD-3-Clause
 */

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/skye-update-guard/bootstrap.php';
