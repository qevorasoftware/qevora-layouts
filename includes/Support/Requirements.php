<?php
/**
 * Environment requirements guard.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Support;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks that the current environment can run the plugin.
 *
 * The class is deliberately dependency free: it is loaded before the
 * autoloader so a too-old PHP version can never trigger a parse or class
 * loading fatal error.
 *
 * @since 1.0.0
 */
class Requirements {

	/**
	 * Whether the environment meets the minimum requirements.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function meets_minimum_requirements() {
		global $wp_version;

		if ( version_compare( PHP_VERSION, QEVORA_LAYOUTS_MIN_PHP, '<' ) ) {
			return false;
		}

		$wp_version = isset( $wp_version ) ? $wp_version : '';

		if ( '' !== $wp_version && version_compare( $wp_version, QEVORA_LAYOUTS_MIN_WP, '<' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns a human readable list of unmet requirements.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public static function get_unmet_requirements() {
		global $wp_version;

		$unmet = array();

		if ( version_compare( PHP_VERSION, QEVORA_LAYOUTS_MIN_PHP, '<' ) ) {
			/* translators: 1: required PHP version, 2: current PHP version. */
			$unmet[] = sprintf( __( 'Qevora Layouts requires PHP %1$s or newer. This site runs PHP %2$s.', 'qevora-layouts' ), QEVORA_LAYOUTS_MIN_PHP, PHP_VERSION );
		}

		$current_wp = isset( $wp_version ) ? $wp_version : '';

		if ( '' !== $current_wp && version_compare( $current_wp, QEVORA_LAYOUTS_MIN_WP, '<' ) ) {
			/* translators: 1: required WordPress version, 2: current WordPress version. */
			$unmet[] = sprintf( __( 'Qevora Layouts requires WordPress %1$s or newer. This site runs WordPress %2$s.', 'qevora-layouts' ), QEVORA_LAYOUTS_MIN_WP, $current_wp );
		}

		return $unmet;
	}

	/**
	 * Renders the admin notice shown when requirements are not met.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function render_unmet_notice() {
		$unmet = self::get_unmet_requirements();

		if ( empty( $unmet ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Qevora Layouts is inactive:', 'qevora-layouts' ) . '</strong></p><ul style="list-style:disc;padding-left:20px;">';

		foreach ( $unmet as $message ) {
			echo '<li>' . esc_html( $message ) . '</li>';
		}

		echo '</ul></div>';
	}
}
