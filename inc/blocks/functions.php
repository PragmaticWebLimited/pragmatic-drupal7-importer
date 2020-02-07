<?php
/**
 * Blocks for the Content Entity Fields plugin.
 */

declare( strict_types = 1 );

namespace Pragmatic\Content_Entity_Fields\Blocks;

use Pragmatic\Content_Entity_Fields\Asset_Loader;

/**
 * Enqueue the JS and CSS for blocks in the editor.
 */
function enqueue_block_editor_assets() : void {
	Asset_Loader\autoregister(
		dirname( dirname( __DIR__ ) ) . '/dist/asset-manifest.json',
		'meow.js',
		[
			'scripts' => [ 'wp-blocks', 'wp-i18n', 'wp-element' ],
			'styles'  => [ 'wp-edit-blocks' ],
			'handle'  => 'cef-meow',
		]
	);
}

function helloworld() : void {
	\wp_set_script_translations( 'cef-meow', 'content-entity-fields' );

	\register_block_type( 'cgb/block-my-block', [
		'editor_script' => 'cef-meow',
		'editor_style'  => 'cef-meow',
	] );
}
