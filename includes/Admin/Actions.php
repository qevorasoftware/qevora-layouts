<?php
/**
 * Privileged admin-post actions.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Admin;

use QevoraLayouts\Core\Cache;
use QevoraLayouts\Core\Capabilities;
use QevoraLayouts\Core\Layout_Post_Type;
use QevoraLayouts\Core\Logger;
use QevoraLayouts\Core\Plugin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the nonce-protected links and form posts in wp-admin.
 *
 * Every action verifies a nonce and a capability before doing anything, and
 * always ends in a redirect with a notice, so no state change happens through a
 * GET link without a valid nonce.
 *
 * @since 1.0.0
 */
class Actions {

	/**
	 * Nonce action: clone a layout.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const CLONE_ACTION = 'qevora_layouts_clone';

	/**
	 * Nonce action: enable a layout.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const ENABLE_ACTION = 'qevora_layouts_enable';

	/**
	 * Nonce action: disable a layout.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const DISABLE_ACTION = 'qevora_layouts_disable';

	/**
	 * Nonce action: flush caches.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const FLUSH_CACHE_ACTION = 'qevora_layouts_flush_cache';

	/**
	 * Nonce action: refresh the remote library.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const REFRESH_LIBRARY_ACTION = 'qevora_layouts_refresh_library';

	/**
	 * Nonce action: import (form fallback).
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const IMPORT_ACTION = 'qevora_layouts_import';

	/**
	 * Registers the admin-post handlers.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_post_qevora_layouts_clone', array( __CLASS__, 'handle_clone' ) );
		add_action( 'admin_post_qevora_layouts_enable', array( __CLASS__, 'handle_toggle' ) );
		add_action( 'admin_post_qevora_layouts_disable', array( __CLASS__, 'handle_toggle' ) );
		add_action( 'admin_post_' . self::FLUSH_CACHE_ACTION, array( __CLASS__, 'handle_flush_cache' ) );
		add_action( 'admin_post_' . self::REFRESH_LIBRARY_ACTION, array( __CLASS__, 'handle_refresh_library' ) );
		add_action( 'admin_post_' . self::IMPORT_ACTION, array( __CLASS__, 'handle_import' ) );
	}

	/**
	 * Clones a layout.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function handle_clone() {
		$layout_id = self::authorize( self::CLONE_ACTION, Capabilities::PUBLISH_LAYOUTS );

		if ( 0 === $layout_id ) {
			return;
		}

		$result = Plugin::instance()->repository()->duplicate( $layout_id );

		if ( is_wp_error( $result ) ) {
			self::finish( $result->get_error_message(), 'error' );

			return;
		}

		self::finish(
			__( 'The layout was cloned as a draft.', 'qevora-layouts' ),
			'success',
			array(),
			get_edit_post_link( (int) $result, 'raw' )
		);
	}

	/**
	 * Enables or disables a layout.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function handle_toggle() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce action depends on the requested action and is verified below.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		$enable    = 'qevora_layouts_enable' === $action;
		$layout_id = self::requested_layout_id();

		if ( $layout_id <= 0 ) {
			self::finish( __( 'No layout was selected.', 'qevora-layouts' ), 'error' );

			return;
		}

		$nonce_action = ( $enable ? self::ENABLE_ACTION : self::DISABLE_ACTION ) . '_' . $layout_id;
		$nonce        = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified on the next line.

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			Logger::warning( 'Toggle rejected: invalid nonce.', array( 'layout_id' => $layout_id ) );

			self::finish( __( 'Your session has expired. Please try again.', 'qevora-layouts' ), 'error' );

			return;
		}

		if ( ! current_user_can( Capabilities::EDIT_LAYOUTS ) || ! Capabilities::can_edit_layout( $layout_id ) ) {
			self::finish( __( 'You are not allowed to change this layout.', 'qevora-layouts' ), 'error' );

			return;
		}

		$result = Plugin::instance()->repository()->set_enabled( $layout_id, $enable );

		if ( is_wp_error( $result ) ) {
			self::finish( $result->get_error_message(), 'error' );

			return;
		}

		self::finish(
			$enable
				? __( 'The layout is now enabled.', 'qevora-layouts' )
				: __( 'The layout is now disabled (draft).', 'qevora-layouts' ),
			'success'
		);
	}

	/**
	 * Flushes plugin caches.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function handle_flush_cache() {
		self::authorize( self::FLUSH_CACHE_ACTION, Capabilities::MANAGE_SETTINGS, false );

		Cache::bump_version();

		self::finish(
			sprintf(
				/* translators: %d: new cache version. */
				__( 'Caches cleared. New cache version: %d.', 'qevora-layouts' ),
				Cache::get_version()
			),
			'success'
		);
	}

	/**
	 * Refreshes the remote template index.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function handle_refresh_library() {
		self::authorize( self::REFRESH_LIBRARY_ACTION, Capabilities::PUBLISH_LAYOUTS, false );

		Plugin::instance()->library()->clear_cache();
		$templates = Plugin::instance()->library()->remote( true );

		if ( empty( $templates ) ) {
			$problems = Plugin::instance()->library()->get_problems();

			self::finish(
				__( 'The remote library returned no templates.', 'qevora-layouts' ),
				'warning',
				array_slice( $problems, 0, 5 )
			);

			return;
		}

		self::finish(
			sprintf(
				/* translators: %d: number of templates. */
				__( 'Remote library refreshed: %d templates available.', 'qevora-layouts' ),
				count( $templates )
			),
			'success'
		);
	}

	/**
	 * Imports a template submitted through the plain form fallback.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function handle_import() {
		self::authorize( self::IMPORT_ACTION, Capabilities::PUBLISH_LAYOUTS, false );

		$params = isset( $_POST ) ? wp_unslash( $_POST ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Verified above, sanitized in the handler.
		$files  = isset( $_FILES ) ? $_FILES : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Verified above, validated in the handler.

		$result = Import_Handler::handle( is_array( $params ) ? $params : array(), is_array( $files ) ? $files : array() );
		$summary = Import_Handler::summarize( $result );

		self::finish( (string) $summary['message'], (string) $summary['type'], (array) $summary['details'] );
	}

	/**
	 * Verifies the nonce, the capability and the layout ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $nonce_action Nonce action.
	 * @param string $capability   Required capability.
	 * @param bool   $per_layout   Whether the nonce is scoped to a layout ID.
	 * @return int Layout ID, or 0 when the request was rejected (already redirected).
	 */
	private static function authorize( $nonce_action, $capability, $per_layout = true ) {
		$layout_id = self::requested_layout_id();

		if ( $per_layout && $layout_id <= 0 ) {
			self::finish( __( 'No layout was selected.', 'qevora-layouts' ), 'error' );

			return 0;
		}

		$action = $per_layout ? $nonce_action . '_' . $layout_id : $nonce_action;

		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified next.

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, $action ) ) {
			Logger::warning( 'Admin action rejected: invalid nonce.', array( 'action' => (string) $nonce_action ) );

			self::finish( __( 'Your session has expired. Please try again.', 'qevora-layouts' ), 'error' );

			return 0;
		}

		if ( ! current_user_can( $capability ) ) {
			Logger::warning( 'Admin action rejected: missing capability.', array( 'action' => (string) $nonce_action ) );

			self::finish( __( 'You are not allowed to perform this action.', 'qevora-layouts' ), 'error' );

			return 0;
		}

		return $layout_id;
	}

	/**
	 * Returns the layout ID from the request.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	private static function requested_layout_id() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the caller.
		$raw = isset( $_REQUEST['layout'] ) ? absint( wp_unslash( $_REQUEST['layout'] ) ) : 0;

		return (int) $raw;
	}

	/**
	 * Stores a notice and redirects back.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $message Notice message.
	 * @param string   $type    Notice type.
	 * @param string[] $details Optional detail lines.
	 * @param string   $redirect Optional explicit redirect target.
	 * @return void
	 */
	private static function finish( $message, $type = 'success', array $details = array(), $redirect = '' ) {
		Notices::add( (string) $message, (string) $type, $details );

		$target = '' !== $redirect && is_string( $redirect ) ? $redirect : self::referer();

		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Returns a safe redirect target inside wp-admin.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private static function referer() {
		$referer = wp_get_referer();

		if ( is_string( $referer ) && '' !== $referer ) {
			$admin_url = wp_parse_url( admin_url() );
			$referer_parts = wp_parse_url( $referer );

			if ( ! empty( $admin_url['host'] ) && ! empty( $referer_parts['host'] ) && $admin_url['host'] === $referer_parts['host'] ) {
				return $referer;
			}
		}

		return admin_url( 'edit.php?post_type=' . Layout_Post_Type::POST_TYPE );
	}

}
