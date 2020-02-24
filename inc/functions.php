<?php
declare(strict_types=1);

/**
 * Bootstraps the Drupal 7 Importer plugin.
 */
namespace Pragmatic\Drupal7_Importer;

use HMCI;
use Pragmatic\Autoloader as Autoloader;
use WP_CLI;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Initialise and set up the plugin.
 *
 * @return void
 */
function set_up() : void {

	Autoloader\register_class_path( __NAMESPACE__, __DIR__ );

	// We require humanmade/hm-content-import.
	if ( ! class_exists( '\HMCI\Master', false ) ) {
		return;
	}

	// This is a WP-CLI plugin.
	if ( ! defined( '\WP_CLI' ) || ! constant( '\WP_CLI' ) ) {
		return;
	}

	add_action( 'init', __NAMESPACE__ . '\load_importer' );

	// Default behaviours.
	add_filter( 'pragmatic.drupal7_importer.parse_item', __NAMESPACE__ . '\transforms\decode_html_entities', 6 );
	add_filter( 'pragmatic.drupal7_importer.parse_item', __NAMESPACE__ . '\transforms\format_post_date', 6 );
	add_filter( 'pragmatic.drupal7_importer.parse_item', __NAMESPACE__ . '\transforms\assign_post_author', 6 );
	add_filter( 'pragmatic.drupal7_importer.parse_item', __NAMESPACE__ . '\transforms\transform_url_aliases', 6 );
}

/**
 * Load the importer.
 */
function load_importer() : void {
	HMCI\Master::add_importer( 'drupal7-posts-importer', __NAMESPACE__ . '\Posts_Importer' );
	HMCI\Master::add_importer( 'drupal7-images-importer', __NAMESPACE__ . '\Images_Importer' );
	HMCI\Master::add_validator( 'drupal7-posts-validator', __NAMESPACE__ . '\Posts_Validator' );

	// Register the custom cli command for importing users. @todo create a HMCI importer for this.
	WP_CLI::add_command( 'drupal7-users-importer', __NAMESPACE__ . '\Users\Import_Users_Command' );
}
