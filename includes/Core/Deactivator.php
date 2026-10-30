<?php
/**
 * Deactivation routines.
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
 * Runs once when the plugin is deactivated.
 *
 * Deactivation removes no content: layouts are ordinary posts and stay in the
 * database so reactivating the plugin restores everything. Only caches are
 * dropped. Data removal happens in `uninstall.php` and only when the user
 * explicitly enabled it in Settings.
 *
 * @since 1.0.0
 */
class Deactivator {

	/**
	 * Deactivation callback.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function deactivate() {
		Cache::bump_version();
		Settings::flush_cache();

		/**
		 * Fires when Qevora Layouts is deactivated.
		 *
		 * @since 1.0.0
		 */
		do_action( 'qevora_layouts_deactivated' );
	}
}
