<?php
/**
 * Tests for the bundled PHP demo registry.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Tests\Unit\Import;

use QevoraLayouts\Import\Demo_Registry;
use QevoraLayouts\Tests\TestCase;
use Qevora_Test_Env;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The registry is the only door to demo files, so its allow list has to be
 * airtight and every bundled demo has to validate.
 *
 * @since 1.0.0
 */
class Demo_Registry_Test extends TestCase {

	/**
	 * Clears the request cache between tests.
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		Demo_Registry::reset();
	}

	/**
	 * Clears the request cache after tests.
	 *
	 * @return void
	 */
	public function tearDown() {
		Demo_Registry::reset();

		parent::tearDown();
	}

	/**
	 * Every bundled demo folder validates and exposes the documented shape.
	 *
	 * @return void
	 */
	public function test_discovers_the_bundled_demos() {
		$demos = Demo_Registry::demos();

		foreach ( array( 'header-classic', 'header-centered', 'footer-columns', 'footer-simple', 'section-hero', 'section-cta', 'section-faq' ) as $slug ) {
			$this->assertArrayHasKey( $slug, $demos );
			$this->assertSame( $slug, $demos[ $slug ]['id'] );
			$this->assertNotSame( '', $demos[ $slug ]['name'] );
			$this->assertContains( $demos[ $slug ]['type'], array( 'header', 'footer', 'section' ) );
		}

		$this->assertCount( 7, $demos, 'Every bundled demo is exactly one PHP file in templates/demos/.' );
		$this->assertTrue( $demos['header-classic']['menu'] );
		$this->assertTrue( $demos['header-centered']['menu'] );
		$this->assertTrue( $demos['footer-columns']['menu'] );
		$this->assertFalse( $demos['footer-simple']['menu'], 'The simple footer needs no menu.' );
		$this->assertFalse( $demos['section-faq']['menu'], 'Sections never ask for a menu.' );
	}

	/**
	 * File access is limited to allow-listed names inside the demo folder.
	 *
	 * @return void
	 */
	public function test_path_resolves_only_the_demo_file() {
		$file = Demo_Registry::path( 'header-classic' );

		$this->assertNotSame( '', $file );
		$this->assertTrue( is_file( $file ) );
		$this->assertStringContainsString( 'header-classic.php', $file, 'One demo is one PHP file.' );

		$this->assertSame( '', Demo_Registry::path( 'does-not-exist' ) );
		$this->assertSame( '', Demo_Registry::path( '../qevora-layouts' ) );
		$this->assertSame( '', Demo_Registry::path( '' ) );
	}

	/**
	 * URLs only resolve for files that exist; assets are per demo.
	 *
	 * @return void
	 */
	public function test_assets_are_inlined_per_demo() {
		Demo_Registry::enqueue_assets( 'header-classic' );

		$this->assertTrue( wp_style_is( 'qevora-demo-header-classic' ) );
		$this->assertTrue( wp_script_is( 'qevora-demo-header-classic-js' ) );
		$this->assertStringContainsString( '.qdhc', Qevora_Test_Env::$assets['qevora-demo-header-classic']['inline'], 'The stylesheet lives inside the demo file and prints inline.' );
		$this->assertStringContainsString( 'qdhc', Qevora_Test_Env::$assets['qevora-demo-header-classic-js']['inline'] );

		Demo_Registry::enqueue_assets( 'footer-columns' );

		$this->assertFalse( wp_script_is( 'qevora-demo-footer-columns-js' ), 'Demos without JavaScript register none.' );
		$this->assertStringNotContainsString( '.qdhc', Qevora_Test_Env::$assets['qevora-demo-footer-columns']['inline'], 'No CSS leaks between demos.' );
	}

	/**
	 * The library previews render the real demo markup, so what a user
	 * selects is literally what the site will show.
	 *
	 * @return void
	 */
	public function test_preview_html_renders_the_real_demo() {
		$preview = Demo_Registry::preview_html( 'header-classic', 0 );

		$this->assertStringContainsString( 'class="qdhc', $preview );
		$this->assertStringContainsString( '<style id="qevora-demo-preview-css-header-classic-0">', $preview );
		$this->assertStringNotContainsString( '<nav', $preview, 'Previews render without a menu.' );

		$dialog = Demo_Registry::preview_html( 'header-classic', 1 );

		$this->assertStringContainsString( 'qevora-demo-preview-css-header-classic-1', $dialog, 'Repeated previews keep unique ids.' );
		$this->assertSame( '', Demo_Registry::preview_html( 'nope' ) );
	}

	/**
	 * Legacy placeholder so the old test name stays meaningful.
	 *
	 * @return void
	 */
	public function test_every_demo_keeps_its_own_file() {
		// Every demo keeps its own single PHP file.
		foreach ( array_keys( Demo_Registry::demos() ) as $slug ) {
			$this->assertNotSame( '', Demo_Registry::path( (string) $slug ), $slug . ' demo file' );
		}
	}

	/**
	 * The demo list is filterable, like every other list in the plugin.
	 *
	 * @return void
	 */
	public function test_demos_are_filterable() {
		add_filter(
			'qevora_layouts_demos',
			function ( $demos ) {
				$demos['extra-demo'] = array(
					'manifest' => array(
						'id'          => 'extra-demo',
						'name'        => 'Extra',
						'type'        => 'header',
						'description' => '',
						'menu'        => false,
						'menu_label'  => '',
						'tags'        => array(),
					),
					'settings' => array(),
					'css'      => '',
					'js'       => '',
					'render'   => static function () {
						return '<div class="extra"></div>';
					},
				);

				return $demos;
			}
		);

		Demo_Registry::reset();

		$this->assertTrue( Demo_Registry::exists( 'extra-demo' ) );
		$this->assertNull( Demo_Registry::get( 'still-unknown' ) );
	}

	/**
	 * Display cards carry everything the library screen prints.
	 *
	 * @return void
	 */
	public function test_for_display_cards() {
		$cards = Demo_Registry::for_display();

		$this->assertNotEmpty( $cards );

		$first = $cards[0];

		foreach ( array( 'id', 'name', 'type', 'type_label', 'description', 'menu', 'menu_label' ) as $key ) {
			$this->assertArrayHasKey( $key, $first );
		}
	}

	/**
	 * Every demo keeps its settings schema in its own PHP file, and the
	 * schema drives both the metabox and the render.
	 *
	 * @return void
	 */
	public function test_settings_schemas_load_per_demo() {
		$classic = Demo_Registry::settings_schema( 'header-classic' );

		$this->assertArrayHasKey( 'site_title', $classic );
		$this->assertSame( 'text', $classic['site_title']['type'] );
		$this->assertSame( '', $classic['site_title']['default'] );
		$this->assertSame( 'color', $classic['accent_color']['type'] );
		$this->assertSame( '#2271b1', $classic['accent_color']['default'] );
		$this->assertSame( 'checkbox', $classic['sticky_header']['type'] );
		$this->assertFalse( $classic['sticky_header']['default'] );

		$hero = Demo_Registry::settings_schema( 'section-hero' );

		$this->assertSame( 'select', $hero['align']['type'] );
		$this->assertSame( 'center', $hero['align']['default'] );
		$this->assertArrayHasKey( 'left', $hero['align']['options'] );

		$this->assertSame( array(), Demo_Registry::settings_schema( 'does-not-exist' ), 'Unknown demos have no settings.' );
	}

	/**
	 * Submitted settings are cleaned per field type; anything the demo
	 * does not declare is dropped entirely.
	 *
	 * @return void
	 */
	public function test_sanitize_settings_is_type_aware_and_strict() {
		$clean = Demo_Registry::sanitize_settings(
			'header-classic',
			array(
				'site_title'    => "  Acme <script>alert(1)</script> & sons  ",
				'accent_color'  => '#ZZZZZZ',
				'sticky_header' => '1',
				'evil'          => 'drop me',
			)
		);

		$this->assertSame( array( 'site_title', 'accent_color', 'sticky_header' ), array_keys( $clean ), 'Unknown keys never survive.' );
		$this->assertStringNotContainsString( '<script>', $clean['site_title'] );
		$this->assertSame( '#2271b1', $clean['accent_color'], 'An invalid colour falls back to the default.' );
		$this->assertTrue( $clean['sticky_header'] );

		$clean = Demo_Registry::sanitize_settings(
			'section-hero',
			array(
				'align'      => 'sideways',
				'button_url' => 'javascript:alert(1)',
			)
		);

		$this->assertSame( 'center', $clean['align'], 'An unknown select value falls back to the default.' );
		$this->assertStringNotContainsString( 'javascript:', $clean['button_url'] );

		$this->assertSame( array(), Demo_Registry::sanitize_settings( 'header-classic', 'not-an-array' ) );
	}

	/**
	 * Rendering always sees a complete, sanitised settings array: stored
	 * values win, everything else falls back to the demo's defaults.
	 *
	 * @return void
	 */
	public function test_get_settings_merges_stored_values_over_defaults() {
		$settings = Demo_Registry::get_settings(
			'footer-simple',
			array( 'copyright' => 'Acme 2026' )
		);

		$this->assertSame( 'Acme 2026', $settings['copyright'] );
		$this->assertTrue( $settings['show_back_to_top'], 'Untouched checkboxes keep their default.' );
		$this->assertSame( '', $settings['top_label'] );

		$defaults = Demo_Registry::get_settings( 'footer-simple' );

		$this->assertSame( Demo_Registry::settings_defaults( 'footer-simple' ), $defaults );
	}
}
