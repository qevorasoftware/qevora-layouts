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
 * The layout post type itself uses `capability_type => 'post'` with
 * `map_meta_cap`, so object level checks (`edit_post`, `delete_post`) resolve
 * through the core post capabilities. The plugin level gates in this class use
 * custom primitive capabilities, which WordPress does not grant to anybody on
 * its own: {@see Capabilities::grant()} adds them to the administrator role
 * during activation and {@see Capabilities::revoke()} removes them again on
 * uninstall. Without that step every privileged request would be denied even
 * for administrators.
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

	/**
	 * The custom primitive capabilities this plugin adds to roles.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int,string>
	 */
	public static function plugin_capabilities() {
		return array(
			self::EDIT_LAYOUTS,
			self::PUBLISH_LAYOUTS,
			self::DELETE_LAYOUTS,
		);
	}

	/**
	 * The roles that receive the plugin capabilities on activation.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int,string> Role names.
	 */
	public static function granted_roles() {
		$roles = array( 'administrator' );

		/**
		 * Filters the roles that receive the Qevora Layouts capabilities.
		 *
		 * Granting `edit_qevora_layouts`, `publish_qevora_layouts` and
		 * `delete_qevora_layouts` to additional roles (for example `editor`)
		 * lets their users manage layouts. Settings always stay with
		 * `manage_options`.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int,string> $roles Role names. Default `administrator`.
		 */
		return (array) apply_filters( 'qevora_layouts_granted_roles', $roles );
	}

	/**
	 * Adds the plugin capabilities to the granted roles.
	 *
	 * Runs on activation and during version migrations, so sites that upgrade
	 * from an older release receive the capabilities too.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function grant() {
		foreach ( self::granted_roles() as $role_name ) {
			$role = get_role( (string) $role_name );

			if ( ! $role ) {
				continue;
			}

			foreach ( self::plugin_capabilities() as $capability ) {
				$role->add_cap( $capability );
			}
		}
	}

	/**
	 * Removes the plugin capabilities from every role that has them.
	 *
	 * Iterates all roles rather than the granted list so capabilities survive
	 * a changed filter until uninstall cleans them up.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function revoke() {
		foreach ( wp_roles()->roles as $role_name => $role_data ) {
			$role = get_role( (string) $role_name );

			if ( ! $role ) {
				continue;
			}

			foreach ( self::plugin_capabilities() as $capability ) {
				if ( $role->has_cap( $capability ) ) {
					$role->remove_cap( $capability );
				}
			}
		}
	}
}
