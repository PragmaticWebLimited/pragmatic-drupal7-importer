<?php
/**
 * Plugin Name: Content Entity Fields
 * Plugin URI: https://github.com/PragmaticWebLimited/content-entity-fields/
 * Description: Content Entity Fields helps developers add custom fields to content types, utilising the best of WordPress' block editor.
 * Author: Pragmatic Web Limited
 * Author URI: https://pragmatic.agency
 * Version: 0.1.0-dev
 * License: GPL-3.0-only
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: content-entity-fields
 * Requires at least: 5.3
 * Requires PHP: 7.3.0
 */

declare( strict_types = 1 );

namespace Pragmatic\Content_Entity_Fields;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/asset-loader/manifest/functions.php';
require_once __DIR__ . '/inc/asset-loader/paths/functions.php';
require_once __DIR__ . '/inc/asset-loader/functions.php';
require_once __DIR__ . '/inc/blocks.php';

\add_action( 'plugins_loaded', __NAMESPACE__ . '\init_plugin' );
