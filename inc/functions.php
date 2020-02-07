<?php
/**
 * Content Entity Fields loader.
 */

declare( strict_types = 1 );

namespace Pragmatic\Content_Entity_Fields;

/**
 * Set up plugin.
 *
 * Register actions and filters.
 */
function init_plugin() : void {

	// Blocks.
	\add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\Blocks\enqueue_block_editor_assets' );
	\add_action( 'init', __NAMESPACE__ . '\Blocks\helloworld' );
}
