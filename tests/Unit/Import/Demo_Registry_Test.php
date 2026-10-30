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

		$this->assertCount( 2, $demos, 'Every bundled demo is one focused header, one PHP file.' );
		$this->assertArrayHasKey( 'header-dark-bar', $demos );
		$this->assertSame( 'header-dark-bar', $demos['header-dark-bar']['id'] );
		$this->assertNotSame( '', $demos['header-dark-bar']['name'] );
		$this->assertSame( 'header', $demos['header-dark-bar']['type'] );
		$this->assertTrue( $demos['header-dark-bar']['menu'] );
		$this->assertArrayHasKey( 'header-book-store', $demos );
		$this->assertSame( 'header-book-store', $demos['header-book-store']['id'] );
		$this->assertNotSame( '', $demos['header-book-store']['name'] );
		$this->assertSame( 'header', $demos['header-book-store']['type'] );
		$this->assertTrue( $demos['header-book-store']['menu'] );
	}

	/**
	 * File access is limited to allow-listed names inside the demo folder.
	 *
	 * @return void
	 */
	public function test_path_resolves_only_the_demo_file() {
		$file = Demo_Registry::path( 'header-dark-bar' );

		$this->assertNotSame( '', $file );
		$this->assertTrue( is_file( $file ) );
		$this->assertStringContainsString( 'header-dark-bar.php', $file, 'One demo is one PHP file.' );

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
		Demo_Registry::enqueue_assets( 'header-dark-bar' );

		$this->assertTrue( wp_style_is( 'qevora-demo-header-dark-bar' ) );
		$this->assertFalse( wp_script_is( 'qevora-demo-header-dark-bar-js' ), 'The demo ships no enqueued script; its toggle behaviour prints with the markup.' );
		$this->assertStringContainsString( '.qhdb', Qevora_Test_Env::$assets['qevora-demo-header-dark-bar']['inline'], 'The stylesheet lives inside the demo file and prints inline.' );
		$this->assertFalse( array_key_exists( 'qevora-demo-header-dark-bar-js', Qevora_Test_Env::$assets ), 'No script handle is registered for the demo.' );
	}

	/**
	 * The library previews render the real demo markup, so what a user
	 * selects is literally what the site will show.
	 *
	 * @return void
	 */
	public function test_preview_html_renders_the_real_demo() {
		$preview = Demo_Registry::preview_html( 'header-dark-bar', 0 );

		$this->assertStringContainsString( 'class="qhdb"', $preview );
		$this->assertStringContainsString( '<style id="qevora-demo-preview-css-header-dark-bar-0">', $preview );
		$this->assertStringContainsString( '<ul class="qhdb__menu">', $preview, 'Previews show a representative sample menu.' );
		$this->assertStringContainsString( 'Home', $preview );
		$this->assertStringNotContainsString( 'class="qhdb__toggle"', $preview, 'Previews never show the mobile toggle button.' );
		$this->assertStringContainsString( 'qhdb__cta', $preview, 'The call-to-action button previews too.' );
		$this->assertStringContainsString( 'class="qhdb__search-toggle"', $preview, 'The search button previews by default.' );
		$this->assertStringContainsString( 'data-qhdb-rest', $preview, 'The search panel knows the suggestions endpoint.' );
		$this->assertStringNotContainsString( '<div class="qhdb qhdb--sticky">', $preview, 'The fixed header is opt-in.' );

		$options = Demo_Registry::preview_html(
			'header-dark-bar',
			5,
			array(
				'sticky_header' => true,
				'show_search'   => false,
			)
		);

		$this->assertStringContainsString( '<div class="qhdb qhdb--sticky">', $options, 'The fixed-header switch adds the sticky class.' );
		$this->assertStringNotContainsString( 'class="qhdb__search-toggle"', $options, 'The search button can be switched off.' );

		$dialog = Demo_Registry::preview_html( 'header-dark-bar', 1 );

		$this->assertStringContainsString( 'qevora-demo-preview-css-header-dark-bar-1', $dialog, 'Repeated previews keep unique ids.' );

		$custom = Demo_Registry::preview_html( 'header-dark-bar', 3, array( 'button_label' => 'Call the team' ) );

		$this->assertStringContainsString( 'Call the team', $custom, 'Previews can render stored settings.' );
		$this->assertSame( '', Demo_Registry::preview_html( 'nope' ) );
	}

	/**
	 * The second bundled demo previews with its own shop chrome.
	 *
	 * @return void
	 */
	public function test_book_store_preview_shows_the_shop_chrome() {
		$preview = Demo_Registry::preview_html( 'header-book-store', 0 );

		$this->assertStringContainsString( 'class="qbs', $preview );
		$this->assertStringContainsString( 'qbs__menu', $preview, 'Previews show a representative sample menu.' );
		$this->assertStringContainsString( 'Shop', $preview );
		$this->assertStringContainsString( 'qbs__search', $preview, 'The category search previews too.' );
		$this->assertStringContainsString( 'qbs__cats', $preview, 'The categories dropdown previews too.' );
		$this->assertStringNotContainsString( 'class="qbs__toggle"', $preview, 'Previews never show the mobile toggle button.' );
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
		$demo = Demo_Registry::settings_schema( 'header-dark-bar' );

		$this->assertSame( array( 'logo_image', 'menu', 'button_label', 'button_url', 'show_button', 'sticky_header', 'show_search' ), array_keys( $demo ), 'The demo ships only the fields it actually renders.' );
		$this->assertSame( 'image', $demo['logo_image']['type'] );
		$this->assertSame( '', $demo['logo_image']['default'] );
		$this->assertSame( 'select', $demo['menu']['type'] );
		$this->assertSame( '', $demo['menu']['default'] );
		$this->assertArrayHasKey( '', $demo['menu']['options'], 'The menu chooser always offers the layout default.' );
		$this->assertSame( 'text', $demo['button_label']['type'] );
		$this->assertSame( 'url', $demo['button_url']['type'] );
		$this->assertSame( 'checkbox', $demo['show_button']['type'] );
		$this->assertTrue( $demo['show_button']['default'] );
		$this->assertSame( 'checkbox', $demo['sticky_header']['type'] );
		$this->assertFalse( $demo['sticky_header']['default'], 'The fixed header is opt-in.' );
		$this->assertSame( 'checkbox', $demo['show_search']['type'] );
		$this->assertTrue( $demo['show_search']['default'], 'The search button ships on by default.' );

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
			'header-dark-bar',
			array(
				'button_label' => "  Acme <script>alert(1)</script> & sons  ",
				'logo_image'   => 'javascript:alert(1)',
				'menu'         => '999',
				'show_button'  => '1',
				'evil'         => 'drop me',
			)
		);

		$this->assertSame( array( 'logo_image', 'menu', 'button_label', 'show_button' ), array_keys( $clean ), 'Unknown keys never survive.' );
		$this->assertStringNotContainsString( '<script>', $clean['button_label'] );
		$this->assertSame( '', $clean['logo_image'], 'A non-image URL is dropped from the logo field.' );
		$this->assertSame( '', $clean['menu'], 'An unknown menu falls back to the layout default.' );
		$this->assertTrue( $clean['show_button'] );

		$clean = Demo_Registry::sanitize_settings(
			'header-dark-bar',
			array(
				'button_url' => 'javascript:alert(1)',
				'logo_image' => 'https://example.org/logo.png',
			)
		);

		$this->assertStringNotContainsString( 'javascript:', $clean['button_url'] );
		$this->assertSame( 'https://example.org/logo.png', $clean['logo_image'] );

		$this->assertSame( array(), Demo_Registry::sanitize_settings( 'header-dark-bar', 'not-an-array' ) );
	}

	/**
	 * Rendering always sees a complete, sanitised settings array: stored
	 * values win, everything else falls back to the demo's defaults.
	 *
	 * @return void
	 */
	public function test_get_settings_merges_stored_values_over_defaults() {
		$settings = Demo_Registry::get_settings(
			'header-dark-bar',
			array( 'button_label' => 'Call the team' )
		);

		$this->assertSame( 'Call the team', $settings['button_label'] );
		$this->assertTrue( $settings['show_button'], 'Untouched checkboxes keep their default.' );
		$this->assertSame( '', $settings['button_url'] );

		$defaults = Demo_Registry::get_settings( 'header-dark-bar' );

		$this->assertSame( Demo_Registry::settings_defaults( 'header-dark-bar' ), $defaults );
	}
}
