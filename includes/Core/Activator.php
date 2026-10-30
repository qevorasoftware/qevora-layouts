<?php
/**
 * Activation routines.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Core;

use QevoraLayouts\Support\Requirements;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs once when the plugin is activated.
 *
 * Activation never creates demo content, never writes to theme files and never
 * changes how the theme renders. It only seeds the settings option and the
 * version marker used for future migrations.
 *
 * @since 1.0.0
 */
class Activator {

	/**
	 * Option that stores the installed plugin version.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const VERSION_OPTION = 'qevora_layouts_version';

	/**
	 * Activation callback.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $network_wide Whether the plugin is being activated network wide.
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		if ( ! self::environment_is_supported() ) {
			deactivate_plugins( QEVORA_LAYOUTS_BASENAME );

			wp_die(
				'<p>' . esc_html( implode( ' ', Requirements::get_unmet_requirements() ) ) . '</p>',
				esc_html__( 'Qevora Layouts could not be activated', 'qevora-layouts' ),
				array( 'back_link' => true )
			);
		}

		if ( is_multisite() && $network_wide ) {
			self::for_each_site( array( __CLASS__, 'activate_site' ) );

			return;
		}

		self::activate_site();
	}

	/**
	 * Seeds options for one site.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function activate_site() {
		$existing = get_option( QEVORA_LAYOUTS_OPTION, null );

		if ( ! is_array( $existing ) ) {
			add_option( QEVORA_LAYOUTS_OPTION, Settings::get_defaults(), '', false );
		}

		add_option( Cache::VERSION_OPTION, (int) gmdate( 'U' ), '', false );
		update_option( self::VERSION_OPTION, QEVORA_LAYOUTS_VERSION, false );

		Capabilities::grant();

		Cache::bump_version();
		Settings::flush_cache();

		/**
		 * Fires after Qevora Layouts has been activated on a site.
		 *
		 * @since 1.0.0
		 */
		do_action( 'qevora_layouts_activated' );
	}

	/**
	 * Whether the current environment can run the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	private static function environment_is_supported() {
		return Requirements::meets_minimum_requirements();
	}

	/**
	 * Runs a callback for every site of a multisite network.
	 *
	 * @since 1.0.0
	 *
	 * @param callable $callback Callback receiving no arguments.
	 * @return void
	 */
	private static function for_each_site( callable $callback ) {
		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 500,
			)
		);

		foreach ( (array) $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			$callback();
			restore_current_blog();
		}
	}
}
