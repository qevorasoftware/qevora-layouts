<?php
/**
 * Tests for the settings layer.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Tests\Unit\Core;

use QevoraLayouts\Core\Cache;
use QevoraLayouts\Core\Settings;
use QevoraLayouts\Tests\TestCase;
use Qevora_Test_Env;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The settings are the only place where a site decides how far the plugin goes.
 *
 * @since 1.0.0
 */
class Settings_Test extends TestCase {

	/**
	 * Drops the in-memory settings cache between tests.
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		Settings::flush_cache();
	}

	/**
	 * Nothing is switched on by default that changes how a theme renders.
	 *
	 * @return void
	 */
	public function test_safe_defaults() {
		$defaults = Settings::get_defaults();

		$this->assertTrue( $defaults['header_enabled'] );
		$this->assertTrue( $defaults['footer_enabled'] );
		$this->assertSame( 'wp_body_open', $defaults['default_header_placement'] );
		$this->assertSame( 'get_footer', $defaults['default_footer_placement'] );
		$this->assertSame( '', $defaults['hide_theme_header_selectors'], 'The theme header is never hidden without consent.' );
		$this->assertSame( '', $defaults['hide_theme_footer_selectors'] );
		$this->assertFalse( $defaults['remote_library_enabled'], 'No remote requests unless the site asks for them.' );
		$this->assertSame( '', $defaults['remote_library_url'] );
		$this->assertSame( 'skip', $defaults['duplicate_import_policy'], 'Imports never overwrite silently.' );
		$this->assertFalse( $defaults['debug_mode'] );
		$this->assertFalse( $defaults['delete_data_on_uninstall'], 'Uninstall keeps content unless asked.' );
	}

	/**
	 * Reading settings merges the stored option over the defaults.
	 *
	 * @return void
	 */
	public function test_get_merges_stored_values_over_defaults() {
		$this->assertTrue( Settings::is_enabled( 'header_enabled' ) );
		$this->assertSame( 2048, Settings::get( 'max_import_size_kb' ) );
		$this->assertSame( 'fallback', Settings::get( 'not_a_setting', 'fallback' ) );
		$this->assertNull( Settings::get( 'not_a_setting' ) );

		Qevora_Test_Env::$options[ QEVORA_LAYOUTS_OPTION ] = array(
			'header_enabled'     => false,
			'max_import_size_kb' => 512,
		);

		Settings::flush_cache();

		$this->assertFalse( Settings::is_enabled( 'header_enabled' ) );
		$this->assertSame( 512, Settings::get( 'max_import_size_kb' ) );
		$this->assertTrue( Settings::is_enabled( 'footer_enabled' ), 'Missing keys fall back to the defaults.' );
	}

	/**
	 * A corrupt option must not break the plugin.
	 *
	 * @return void
	 */
	public function test_non_array_option_falls_back_to_defaults() {
		Qevora_Test_Env::$options[ QEVORA_LAYOUTS_OPTION ] = 'not an array';

		Settings::flush_cache();

		$this->assertSame( Settings::get_defaults(), Settings::all() );
	}

	/**
	 * Booleans, enums and numbers are sanitized.
	 *
	 * @return void
	 */
	public function test_sanitize_normalizes_every_known_key() {
		$clean = Settings::sanitize(
			array(
				'header_enabled'             => '1',
				'footer_enabled'             => '0',
				'debug_mode'                 => 'true',
				'default_header_placement'   => 'wp_head',
				'default_footer_placement'   => 'wp_footer',
				'duplicate_import_policy'    => 'overwrite',
				'max_import_size_kb'         => 1,
				'cache_ttl_seconds'          => 999999,
				'remote_library_url'         => 'http://example.org/templates.json',
				'hide_theme_header_selectors' => '.site-header, </style><script>',
				'unknown_key'                => 'ignored',
			)
		);

		$this->assertTrue( $clean['header_enabled'] );
		$this->assertFalse( $clean['footer_enabled'] );
		$this->assertTrue( $clean['debug_mode'] );
		$this->assertSame( 'wp_body_open', $clean['default_header_placement'], 'An unknown placement falls back to the default.' );
		$this->assertSame( 'wp_footer', $clean['default_footer_placement'] );
		$this->assertSame( 'skip', $clean['duplicate_import_policy'], 'An unknown policy falls back to skip.' );
		$this->assertSame( 16, $clean['max_import_size_kb'], 'The size limit has a floor.' );
		$this->assertSame( 86400, $clean['cache_ttl_seconds'], 'The TTL has a ceiling.' );
		$this->assertSame( '', $clean['remote_library_url'], 'Only https endpoints are accepted.' );
		$this->assertStringNotContainsString( '<script>', $clean['hide_theme_header_selectors'] );
		$this->assertFalse( isset( $clean['unknown_key'] ), 'Unknown keys are dropped.' );
		$this->assertSame( count( Settings::get_defaults() ), count( $clean ) );
	}

	/**
	 * Writing settings sanitizes and invalidates the cache.
	 *
	 * @return void
	 */
	public function test_update_sanitizes_and_bumps_the_cache_version() {
		$before = Cache::get_version();

		Settings::update( array( 'cache_ttl_seconds' => 600, 'debug_mode' => true ) );

		$stored = Qevora_Test_Env::$options[ QEVORA_LAYOUTS_OPTION ];

		$this->assertSame( 600, $stored['cache_ttl_seconds'] );
		$this->assertTrue( $stored['debug_mode'] );
		$this->assertNotSame( $before, Cache::get_version() );
		$this->assertSame( 600, Settings::get( 'cache_ttl_seconds' ) );
	}

	/**
	 * CSS selectors cannot escape the declaration the plugin prints.
	 *
	 * @return void
	 */
	public function test_css_selectors_are_hardened() {
		$this->assertSame( '.site-header', Settings::sanitize_css_selectors( '.site-header' ) );
		$this->assertSame( '.a, .b', Settings::sanitize_css_selectors( ".a,\n.b" ) );
		$this->assertSame( '#masthead', Settings::sanitize_css_selectors( '#masthead' ) );
		$this->assertSame( 'header.site-header', Settings::sanitize_css_selectors( 'header.site-header' ) );
		$this->assertSame( '', Settings::sanitize_css_selectors( '.a } body { display: block' ) );
		$this->assertSame( '', Settings::sanitize_css_selectors( '.a; background: url(evil)' ) );
		$this->assertSame( '', Settings::sanitize_css_selectors( '</style><script>alert(1)</script>' ) );
		$this->assertSame( '', Settings::sanitize_css_selectors( '@import url(evil.css)' ) );
		$this->assertSame( '', Settings::sanitize_css_selectors( '1starts-with-a-number' ) );
		$this->assertSame( '', Settings::sanitize_css_selectors( array( '.a' ) ) );
		$this->assertSame( '', Settings::sanitize_css_selectors( str_repeat( '.a', 200 ) ) );
	}

	/**
	 * Only https endpoints with a host are accepted.
	 *
	 * @return void
	 */
	public function test_library_url_is_hardened() {
		$this->assertSame(
			'https://example.org/templates/index.json',
			Settings::sanitize_library_url( 'https://example.org/templates/index.json' )
		);
		$this->assertSame( '', Settings::sanitize_library_url( 'http://example.org/templates.json' ) );
		$this->assertSame( '', Settings::sanitize_library_url( 'file:///etc/passwd' ) );
		$this->assertSame( '', Settings::sanitize_library_url( 'javascript:alert(1)' ) );
		$this->assertSame( '', Settings::sanitize_library_url( 'example.org/templates.json' ) );
		$this->assertSame( '', Settings::sanitize_library_url( '   ' ) );
		$this->assertSame( '', Settings::sanitize_library_url( array( 'https://example.org' ) ) );
	}

	/**
	 * Third parties can add a default through the filter.
	 *
	 * @return void
	 */
	public function test_defaults_can_be_filtered() {
		add_filter(
			'qevora_layouts_default_settings',
			function ( $defaults ) {
				$defaults['debug_mode'] = true;

				return $defaults;
			}
		);

		Settings::flush_cache();

		$this->assertTrue( Settings::is_enabled( 'debug_mode' ) );
	}
}
