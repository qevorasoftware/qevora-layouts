<?php
/**
 * PSR-4 style autoloader.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps the `QevoraLayouts\` namespace onto the plugin directory tree.
 *
 * `QevoraLayouts\Core\Plugin`  => `includes/Core/Plugin.php`
 * `QevoraLayouts\Tests\TestCase` => `tests/TestCase.php` (development only)
 *
 * @since 1.0.0
 */
class Autoloader {

	/**
	 * Registered namespace prefixes.
	 *
	 * @since 1.0.0
	 * @var   array<string,string>
	 */
	private static $prefixes = array();

	/**
	 * Whether the autoloader has been registered.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	private static $registered = false;

	/**
	 * Registers the autoloader with the SPL stack.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register() {
		if ( self::$registered ) {
			return;
		}

		self::$prefixes = array(
			'QevoraLayouts\\Tests\\' => QEVORA_LAYOUTS_DIR . 'tests/',
			'QevoraLayouts\\'       => QEVORA_LAYOUTS_DIR . 'includes/',
		);

		spl_autoload_register( array( __CLASS__, 'autoload' ) );

		self::$registered = true;
	}

	/**
	 * Loads a class file if it belongs to one of the registered prefixes.
	 *
	 * @since 1.0.0
	 *
	 * @param string $class Fully qualified class name.
	 * @return bool True when a file was loaded.
	 */
	public static function autoload( $class ) {
		$class = ltrim( (string) $class, '\\' );

		foreach ( self::$prefixes as $prefix => $base_dir ) {
			if ( 0 !== strncmp( $class, $prefix, strlen( $prefix ) ) ) {
				continue;
			}

			$relative = substr( $class, strlen( $prefix ) );

			// Only allow plain namespace/class characters. Prevents traversal.
			if ( 1 !== preg_match( '/^[A-Za-z0-9_\\\\]+$/', $relative ) ) {
				return false;
			}

			$file = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

			if ( is_readable( $file ) ) {
				require_once $file;

				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the file path the autoloader would use for a class.
	 *
	 * Exposed for tests and debugging.
	 *
	 * @since 1.0.0
	 *
	 * @param string $class Fully qualified class name.
	 * @return string Path or empty string when the class is not ours.
	 */
	public static function get_path_for_class( $class ) {
		$class = ltrim( (string) $class, '\\' );

		foreach ( self::$prefixes as $prefix => $base_dir ) {
			if ( 0 === strncmp( $class, $prefix, strlen( $prefix ) ) ) {
				return $base_dir . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
			}
		}

		return '';
	}
}
