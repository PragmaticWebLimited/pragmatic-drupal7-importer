<?php
/**
 * Plugin Name: Drupal 7 Importer
 * Plugin URI: https://github.com/PragmaticWebLimited/pragmatic-drupal7-importer
 * Description: Developer framework for importing data from Drupal 7 into WordPress.
 * Author: Pragmatic
 * Author URI: https://pragmatic.agency
 * Version: 0.1.0-dev
 * License: GPL-3.0-only
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: pragmatic-drupal7-importer
 * Requires at least: 5.3
 * Requires PHP: 7.3.0
 */

declare( strict_types = 1 );

namespace Pragmatic\Drupal7_Importer;

use RuntimeException;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Load composer autoloader if not already loaded.
if ( ! class_exists( 'Users_Importer' ) ) {

	$plugin_composer_autoloader = __DIR__ . DIRECTORY_SEPARATOR . 'vendor/autoload.php';

	if ( ! \is_readable( $plugin_composer_autoloader ) ) {
		throw new RuntimeException( 'Error loading composer autoload.php. Plugin: Drupal 7 Importer' );
	}

	require_once $plugin_composer_autoloader;
}

require_once __DIR__ . '/inc/integration/functions.php';
require_once __DIR__ . '/inc/transforms/functions.php';
require_once __DIR__ . '/inc/functions.php';

\add_action( 'plugins_loaded', __NAMESPACE__ . '\set_up' );
