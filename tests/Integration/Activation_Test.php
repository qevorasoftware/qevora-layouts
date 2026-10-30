<?php
/**
 * Activation and deactivation regression tests.
 *
 * These exist because the 1.0.0 archive shipped a fatal error that only a
 * real WordPress install could see: `Activator` referenced
 * `QevoraLayouts\Support\Requirements` from inside `namespace QevoraLayouts\Core`
 * without a leading backslash, so PHP looked for
 * `QevoraLayouts\Core\QevoraLayouts\Support\Requirements` and died the moment
 * the activation hook ran. Exercising the activation callback here keeps that
 * class of bug inside the suite.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Tests\Integration;

use QevoraLayouts\Core\Activator;
use QevoraLayouts\Core\Cache;
use QevoraLayouts\Core\Capabilities;
use QevoraLayouts\Core\Plugin;
use QevoraLayouts\Core\Deactivator;
use QevoraLayouts\Core\Settings;
use QevoraLayouts\Tests\TestCase;
use Qevora_Test_Env;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs the same callbacks WordPress runs when the plugin is (de)activated.
 *
 * @since 1.0.0
 */
class Activation_Test extends TestCase {

	/**
	 * Activation seeds the settings option, the version marker and a cache
	 * version, and never creates content.
	 *
	 * @return void
	 */
	public function test_activate_seeds_options_without_creating_content() {
		Activator::activate();

		$this->assertSame( Settings::get_defaults(), get_option( QEVORA_LAYOUTS_OPTION ) );
		$this->assertSame( QEVORA_LAYOUTS_VERSION, get_option( 'qevora_layouts_version' ) );
		$this->assertGreaterThan( 0, (int) get_option( Cache::VERSION_OPTION ) );
		$this->assertSame( 0, count( Qevora_Test_Env::$posts ) );
		$this->assertSame( 0, count( Qevora_Test_Env::$deactivated ) );
		$this->assertSame( 0, count( Qevora_Test_Env::$died ) );
	}

	/**
	 * Activating twice (for example on a re-upload) must not disturb an
	 * existing settings array.
	 *
	 * @return void
	 */
	public function test_activate_is_repeatable_and_keeps_existing_settings() {
		$custom          = Settings::get_defaults();
		$custom['debug_mode'] = true;

		Qevora_Test_Env::$options[ QEVORA_LAYOUTS_OPTION ] = $custom;
		Settings::flush_cache();

		Activator::activate();
		Activator::activate();

		$stored = get_option( QEVORA_LAYOUTS_OPTION );

		$this->assertTrue( is_array( $stored ) );
		$this->assertTrue( $stored['debug_mode'] );
	}

	/**
	 * On an environment that is too old the activation callback must explain
	 * itself through wp_die() and ask for deactivation - not fatal.
	 *
	 * @return void
	 */
	public function test_activate_on_unsupported_wordpress_dies_gracefully() {
		$original            = isset( $GLOBALS['wp_version'] ) ? $GLOBALS['wp_version'] : '';
		$GLOBALS['wp_version'] = '6.0';

		$message = '';

		try {
			Activator::activate();
			$this->fail( 'activate() should have stopped on an unsupported WordPress version' );
		} catch ( \RuntimeException $e ) {
			$message = $e->getMessage();
		} finally {
			$GLOBALS['wp_version'] = $original;
		}

		$this->assertStringContainsString( 'wp_die', $message );
		$this->assertSame( array( QEVORA_LAYOUTS_BASENAME ), Qevora_Test_Env::$deactivated );
		$this->assertNotEmpty( Qevora_Test_Env::$died );
		$this->assertStringContainsString( 'requires WordPress', implode( ' ', Qevora_Test_Env::$died ) );
	}

	/**
	 * Activation must grant the custom capabilities to the administrator
	 * role. On a real site every privileged request - admin menus, REST
	 * reads, imports - is denied without this step, which is exactly what
	 * the 1.0.0 archive did before the fix.
	 *
	 * @return void
	 */
	public function test_activate_grants_plugin_capabilities_to_administrators() {
		Activator::activate();

		$administrator = get_role( 'administrator' );
		$editor        = get_role( 'editor' );

		foreach ( Capabilities::plugin_capabilities() as $capability ) {
			$this->assertTrue( $administrator->has_cap( $capability ) );
			$this->assertFalse( $editor->has_cap( $capability ) );
		}
	}

	/**
	 * The granted-roles filter extends the grant, and revoke() cleans every
	 * role again even after the filter changed.
	 *
	 * @return void
	 */
	public function test_granted_roles_filter_and_revoke() {
		$with_editors = function ( $roles ) {
			$roles[] = 'editor';

			return $roles;
		};

		add_filter( 'qevora_layouts_granted_roles', $with_editors );
		Activator::activate();
		remove_filter( 'qevora_layouts_granted_roles', $with_editors );

		$editor = get_role( 'editor' );

		foreach ( Capabilities::plugin_capabilities() as $capability ) {
			$this->assertTrue( $editor->has_cap( $capability ) );
		}

		Capabilities::revoke();

		foreach ( Capabilities::plugin_capabilities() as $capability ) {
			$this->assertFalse( get_role( 'administrator' )->has_cap( $capability ) );
			$this->assertFalse( $editor->has_cap( $capability ) );
		}
	}

	/**
	 * A site that arrives from an older version receives the capabilities
	 * through the admin_init migration, not only through re-activation.
	 *
	 * @return void
	 */
	public function test_migration_grants_capabilities_for_older_installs() {
		Qevora_Test_Env::$options['qevora_layouts_version'] = '0.9.0';

		Plugin::instance()->maybe_migrate();

		$this->assertSame( QEVORA_LAYOUTS_VERSION, get_option( 'qevora_layouts_version' ) );
		$this->assertTrue( get_role( 'administrator' )->has_cap( Capabilities::EDIT_LAYOUTS ) );
		$this->assertNotEmpty( Qevora_Test_Env::$did_actions['qevora_layouts_upgraded'] ?? null );

		// A second run on the same version is a no-op.
		Qevora_Test_Env::$did_actions = array();

		Plugin::instance()->maybe_migrate();

		$this->assertEmpty( Qevora_Test_Env::$did_actions );
	}

	/**
	 * A site whose roles lost the capabilities (roles rebuilt, activation
	 * interrupted) gets them back on the next admin request.
	 *
	 * @return void
	 */
	public function test_migration_repairs_missing_capabilities_without_version_change() {
		Activator::activate();

		Capabilities::revoke();

		$this->assertFalse( get_role( 'administrator' )->has_cap( Capabilities::EDIT_LAYOUTS ) );

		Plugin::instance()->maybe_migrate();

		$this->assertTrue( get_role( 'administrator' )->has_cap( Capabilities::EDIT_LAYOUTS ) );
		$this->assertEmpty( Qevora_Test_Env::$did_actions['qevora_layouts_upgraded'] ?? array() );
	}

	/**
	 * Deactivation flushes caches and deletes nothing.
	 *
	 * @return void
	 */
	public function test_deactivate_deletes_nothing() {
		Activator::activate();

		$before_version = (int) get_option( Cache::VERSION_OPTION );

		Deactivator::deactivate();

		$this->assertSame( Settings::get_defaults(), get_option( QEVORA_LAYOUTS_OPTION ) );
		$this->assertGreaterThan( $before_version - 1, (int) get_option( Cache::VERSION_OPTION ) );
		$this->assertNotEmpty( Qevora_Test_Env::$did_actions['qevora_layouts_deactivated'] ?? null );
	}
}
