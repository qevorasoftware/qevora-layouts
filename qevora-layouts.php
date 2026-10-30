<?php
/**
 * Qevora Layouts
 *
 * Create, manage, import and display dynamic headers, footers and reusable
 * layout templates in WordPress.
 *
 * @package   QevoraLayouts
 * @author    Qevora Software
 * @copyright Copyright (c) Qevora Software
 * @license   GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Qevora Layouts
 * Plugin URI:        https://github.com/qevorasoftware/qevora-layouts
 * Description:       Build dynamic headers, footers and reusable layout templates with a condition engine, a builder adapter layer and a validated starter template library.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Qevora Software
 * Author URI:        https://qevora.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       qevora-layouts
 * Domain Path:       /languages
 * Update URI:        https://github.com/qevorasoftware/qevora-layouts
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * -------------------------------------------------------------------------
 * Constants
 * -------------------------------------------------------------------------
 */

defined( 'QEVORA_LAYOUTS_VERSION' ) || define( 'QEVORA_LAYOUTS_VERSION', '1.0.0' );
defined( 'QEVORA_LAYOUTS_FILE' ) || define( 'QEVORA_LAYOUTS_FILE', __FILE__ );
defined( 'QEVORA_LAYOUTS_BASENAME' ) || define( 'QEVORA_LAYOUTS_BASENAME', plugin_basename( __FILE__ ) );
defined( 'QEVORA_LAYOUTS_DIR' ) || define( 'QEVORA_LAYOUTS_DIR', plugin_dir_path( __FILE__ ) );
defined( 'QEVORA_LAYOUTS_URL' ) || define( 'QEVORA_LAYOUTS_URL', plugin_dir_url( __FILE__ ) );
defined( 'QEVORA_LAYOUTS_MIN_PHP' ) || define( 'QEVORA_LAYOUTS_MIN_PHP', '7.4' );
defined( 'QEVORA_LAYOUTS_MIN_WP' ) || define( 'QEVORA_LAYOUTS_MIN_WP', '6.4' );

/** Post type used to store layouts. */
defined( 'QEVORA_LAYOUTS_POST_TYPE' ) || define( 'QEVORA_LAYOUTS_POST_TYPE', 'qevora_layout' );

/** Option name used for plugin settings. */
defined( 'QEVORA_LAYOUTS_OPTION' ) || define( 'QEVORA_LAYOUTS_OPTION', 'qevora_layouts_settings' );

/** Object cache group. Non persistent by default, safe to flush. */
defined( 'QEVORA_LAYOUTS_CACHE_GROUP' ) || define( 'QEVORA_LAYOUTS_CACHE_GROUP', 'qevora_layouts' );

/*
 * -------------------------------------------------------------------------
 * Requirements guard.
 *
 * The plugin never fatals on an unsupported environment: it registers an
 * admin notice and stops loading its own code.
 * -------------------------------------------------------------------------
 */

require_once QEVORA_LAYOUTS_DIR . 'includes/Support/Requirements.php';

if ( ! QevoraLayouts\Support\Requirements::meets_minimum_requirements() ) {
	add_action( 'admin_notices', array( 'QevoraLayouts\Support\Requirements', 'render_unmet_notice' ) );

	return;
}

/*
 * -------------------------------------------------------------------------
 * Autoloader and bootstrap.
 * -------------------------------------------------------------------------
 */

require_once QEVORA_LAYOUTS_DIR . 'includes/Core/Autoloader.php';

QevoraLayouts\Core\Autoloader::register();

register_activation_hook( __FILE__, array( 'QevoraLayouts\Core\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'QevoraLayouts\Core\Deactivator', 'deactivate' ) );

/**
 * Returns the single plugin instance.
 *
 * @since 1.0.0
 *
 * @return QevoraLayouts\Core\Plugin
 */
function qevora_layouts() {
	return QevoraLayouts\Core\Plugin::instance();
}

// Boot on `plugins_loaded` so translations and other plugins are available.
add_action( 'plugins_loaded', 'qevora_layouts', 5 );

/*
 * -------------------------------------------------------------------------
 * Uninstall / cleanup helpers.
 * -------------------------------------------------------------------------
 */

/**
 * Removes plugin data during uninstall.
 *
 * Delegated to uninstall.php which performs the capability checks itself.
 * Nothing to do here; the function exists so `uninstall.php` can be included
 * by WordPress without additional bootstrap logic.
 *
 * @since 1.0.0
 * @codeCoverageIgnore
 */
function qevora_layouts_uninstall_stub() {
	// Intentionally left empty. See uninstall.php.
}
