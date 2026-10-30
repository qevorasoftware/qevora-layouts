<?php
/**
 * Capability helpers.
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
 * Central place for the capabilities every privileged action requires.
 *
 * The layout post type uses `capability_type => 'post'` with `map_meta_cap`,
 * so `edit_qevora_layouts` resolves to `edit_posts` for the current user and
 * no custom capabilities have to be added to roles.
 *
 * @since 1.0.0
 */
class Capabilities {

	/**
	 * Capability needed to view and edit layouts.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const EDIT_LAYOUTS = 'edit_qevora_layouts';

	/**
	 * Capability needed to publish layouts and import templates.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const PUBLISH_LAYOUTS = 'publish_qevora_layouts';

	/**
	 * Capability needed to delete layouts.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const DELETE_LAYOUTS = 'delete_qevora_layouts';

	/**
	 * Capability needed for settings and system status.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const MANAGE_SETTINGS = 'manage_options';

	/**
	 * Whether the current user may edit layouts.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function can_edit_layouts() {
		return current_user_can( self::EDIT_LAYOUTS );
	}

	/**
	 * Whether the current user may publish layouts and import templates.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function can_publish_layouts() {
		return current_user_can( self::PUBLISH_LAYOUTS );
	}

	/**
	 * Whether the current user may manage plugin settings.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function can_manage_settings() {
		return current_user_can( self::MANAGE_SETTINGS );
	}

	/**
	 * Whether the current user may edit one specific layout.
	 *
	 * The layout post type is registered with `map_meta_cap => true`, so the
	 * generic `edit_post` meta capability is translated into the post type
	 * specific capability for the given object.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Layout ID.
	 * @return bool
	 */
	public static function can_edit_layout( $post_id ) {
		return current_user_can( 'edit_post', (int) $post_id );
	}

	/**
	 * Whether the current user may delete one specific layout.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Layout ID.
	 * @return bool
	 */
	public static function can_delete_layout( $post_id ) {
		return current_user_can( 'delete_post', (int) $post_id );
	}

	/**
	 * Verifies a nonce and a capability, returning a WP_Error on failure.
	 *
	 * @since 1.0.0
	 *
	 * @param string $nonce      Nonce value.
	 * @param string $action     Nonce action.
	 * @param string $capability Required capability.
	 * @return true|\WP_Error
	 */
	public static function verify_request( $nonce, $action, $capability = self::EDIT_LAYOUTS ) {
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, $action ) ) {
			return new \WP_Error(
				'qevora_layouts_invalid_nonce',
				__( 'Your session has expired. Please reload the page and try again.', 'qevora-layouts' ),
				array( 'status' => 403 )
			);
		}

		if ( ! current_user_can( $capability ) ) {
			return new \WP_Error(
				'qevora_layouts_forbidden',
				__( 'You are not allowed to perform this action.', 'qevora-layouts' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}
}
