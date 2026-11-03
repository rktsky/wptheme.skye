<?php
/** Enforce classic editing through public Core and optional Classic Editor hooks. */

namespace Rocketsky\Skye\Packages;

/** Keep editor policy local to Skye without carrying an unmaintained plugin copy. */
class ClassicEditing {
	/**
	 * Register editor decisions after the optional Classic Editor's priority 100.
	 *
	 * Core remains responsible for rendering, capabilities and stored content.
	 * No user preferences or WordPress files are changed by activating Skye.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function __construct() {
		add_filter( 'use_block_editor_for_post_type', '__return_false', 110 );
		add_filter( 'use_block_editor_for_post', '__return_false', 110 );
		add_filter( 'use_widgets_block_editor', '__return_false', 110 );
		add_filter( 'classic_editor_enabled_editors_for_post_type', array( $this, 'enabled_editors' ), 110 );
		add_filter( 'classic_editor_enabled_editors_for_post', array( $this, 'enabled_editors' ), 110 );
		add_action( 'after_setup_theme', array( $this, 'theme_support' ), 110 );
	}

	/**
	 * Remove widget block editing while retaining the native widget interface.
	 *
	 * Removing all widget support would also remove usable classic widgets.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function theme_support() {
		remove_theme_support( 'widgets-block-editor' );
	}

	/**
	 * Suppress optional plugin switch links without enabling unsupported editors.
	 *
	 * The official Classic Editor consults these public filters when constructing
	 * post actions. Its earlier plugins_loaded settings cache is not overwritten.
	 *
	 * @since 3.0.0
	 * @param array $editors Native editor-name to availability map.
	 * @return array Original availability with block editing disabled.
	 */
	public function enabled_editors( $editors ) {
		$editors['block_editor'] = false;
		return $editors;
	}
}
