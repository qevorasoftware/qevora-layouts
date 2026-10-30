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

		foreach ( array( 'header-classic', 'header-centered', 'footer-columns', 'footer-simple' ) as $slug ) {
			$this->assertArrayHasKey( $slug, $demos );
			$this->assertSame( $slug, $demos[ $slug ]['id'] );
			$this->assertNotSame( '', $demos[ $slug ]['name'] );
			$this->assertContains( $demos[ $slug ]['type'], array( 'header', 'footer' ) );
		}

		$this->assertTrue( $demos['header-classic']['menu'] );
		$this->assertTrue( $demos['header-centered']['menu'] );
		$this->assertTrue( $demos['footer-columns']['menu'] );
		$this->assertFalse( $demos['footer-simple']['menu'], 'The simple footer needs no menu.' );
	}

	/**
	 * File access is limited to allow-listed names inside the demo folder.
	 *
	 * @return void
	 */
	public function test_path_resolves_only_allow_listed_files() {
		$template = Demo_Registry::path( 'header-classic', 'template.php' );

		$this->assertNotSame( '', $template );
		$this->assertTrue( is_file( $template ) );

		$this->assertSame( '', Demo_Registry::path( 'header-classic', 'evil.php' ) );
		$this->assertSame( '', Demo_Registry::path( 'header-classic', '../../starter-footer-minimal.json' ) );
		$this->assertSame( '', Demo_Registry::path( 'header-classic', '../header-centered/template.php' ) );
		$this->assertSame( '', Demo_Registry::path( 'does-not-exist', 'template.php' ) );
		$this->assertSame( '', Demo_Registry::path( '../starter', 'template.php' ) );
	}

	/**
	 * URLs only resolve for files that exist; assets are per demo.
	 *
	 * @return void
	 */
	public function test_urls_are_per_demo_and_only_for_existing_files() {
		$style = Demo_Registry::url( 'header-classic', 'style.css' );

		$this->assertStringContainsString( 'templates/demos/header-classic/style.css', $style );
		$this->assertStringContainsString( 'templates/demos/header-centered/style.css', Demo_Registry::url( 'header-centered', 'style.css' ) );
		$this->assertSame( '', Demo_Registry::url( 'header-classic', 'preview.png' ), 'Missing files resolve to nothing.' );
		$this->assertSame( '', Demo_Registry::url( 'nope', 'style.css' ) );

		// Every demo keeps its own stylesheet and template.
		foreach ( array_keys( Demo_Registry::demos() ) as $slug ) {
			$this->assertNotSame( '', Demo_Registry::path( (string) $slug, 'template.php' ), $slug . ' template' );
			$this->assertNotSame( '', Demo_Registry::path( (string) $slug, 'style.css' ), $slug . ' stylesheet' );
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
					'id'          => 'extra-demo',
					'name'        => 'Extra',
					'type'        => 'header',
					'description' => '',
					'menu'        => false,
					'menu_label'  => '',
					'tags'        => array(),
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

		foreach ( array( 'id', 'name', 'type', 'type_label', 'description', 'menu', 'menu_label', 'preview_url' ) as $key ) {
			$this->assertArrayHasKey( $key, $first );
		}
	}
}
