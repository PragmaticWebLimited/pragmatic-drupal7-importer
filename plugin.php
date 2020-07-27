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

namespace Pragmatic\Drupal7Importer;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/inc/integration/functions.php';
require_once __DIR__ . '/inc/transforms/functions.php';
require_once __DIR__ . '/inc/functions.php';

\add_action( 'plugins_loaded', __NAMESPACE__ . '\set_up' );
