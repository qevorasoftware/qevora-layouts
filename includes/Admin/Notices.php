<?php
/**
 * Admin notices.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queue and print one-off admin notices.
 *
 * Notices are stored in a per-user transient so an action taken on one page
 * load can report its result on the next. They are always escaped when printed.
 *
 * @since 1.0.0
 */
class Notices {

	/**
	 * Transient prefix.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const PREFIX = 'qevora_layouts_notices_';

	/**
	 * Transient lifetime in seconds.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	const LIFETIME = 300;

	/**
	 * Maximum number of queued notices.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	const MAX_NOTICES = 6;

	/**
	 * Adds a notice for the current user.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $message Message (plain text, no HTML).
	 * @param string   $type    success|error|warning|info.
	 * @param string[] $details Optional extra lines.
	 * @return void
	 */
	public static function add( $message, $type = 'success', array $details = array() ) {
		$user_id = get_current_user_id();

		if ( 0 === $user_id ) {
			return;
		}

		$allowed = array( 'success', 'error', 'warning', 'info' );

		if ( ! in_array( $type, $allowed, true ) ) {
			$type = 'info';
		}

		$notices   = self::get();
		$details   = array_slice( array_filter( array_map( 'sanitize_text_field', $details ) ), 0, 10 );
		$notices[] = array(
			'message' => sanitize_text_field( (string) $message ),
			'type'    => $type,
			'details' => $details,
		);

		set_transient( self::PREFIX . $user_id, array_slice( $notices, -1 * self::MAX_NOTICES ), self::LIFETIME );
	}

	/**
	 * Returns the queued notices without consuming them.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get() {
		$user_id = get_current_user_id();

		if ( 0 === $user_id ) {
			return array();
		}

		$notices = get_transient( self::PREFIX . $user_id );

		return is_array( $notices ) ? $notices : array();
	}

	/**
	 * Prints and clears the queued notices. Runs on `admin_notices`.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function render() {
		$user_id = get_current_user_id();
		$notices = self::get();

		if ( empty( $notices ) ) {
			return;
		}

		delete_transient( self::PREFIX . $user_id );

		foreach ( $notices as $notice ) {
			if ( empty( $notice['message'] ) ) {
				continue;
			}

			$type = isset( $notice['type'] ) ? (string) $notice['type'] : 'info';
			$type = in_array( $type, array( 'success', 'error', 'warning', 'info' ), true ) ? $type : 'info';

			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p>', esc_attr( $type ), esc_html( (string) $notice['message'] ) );

			if ( ! empty( $notice['details'] ) && is_array( $notice['details'] ) ) {
				echo '<ul style="list-style:disc;margin:0 0 8px 20px;">';

				foreach ( $notice['details'] as $detail ) {
					echo '<li>' . esc_html( (string) $detail ) . '</li>';
				}

				echo '</ul>';
			}

			echo '</div>';
		}
	}
}
