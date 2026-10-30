<?php
/**
 * Tests for the bundled PHP demo importer.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Tests\Unit\Import;

use QevoraLayouts\Core\Layout;
use QevoraLayouts\Core\Layout_Repository;
use QevoraLayouts\Core\Plugin;
use QevoraLayouts\Core\Settings;
use QevoraLayouts\Import\Demo_Importer;
use QevoraLayouts\Import\Demo_Registry;
use QevoraLayouts\Tests\TestCase;
use Qevora_Test_Env;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A demo import has to create a self-contained layout: the demo slug, the
 * optional real nav menu and the display conditions all belong to that one
 * layout and never leak between demos.
 *
 * @since 1.0.0
 */
class Demo_Importer_Test extends TestCase {

	/**
	 * Importer under test.
	 *
	 * @var Demo_Importer
	 */
	private $importer;

	/**
	 * Repository used by the importer.
	 *
	 * @var Layout_Repository
	 */
	private $repository;

	/**
	 * Builds the importer and resets shared state.
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		Settings::flush_cache();
		Demo_Registry::reset();

		$reflection = new \ReflectionClass( Plugin::class );
		$property   = $reflection->getProperty( 'instance' );

		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}

		$property->setValue( null, null );

		$this->repository = new Layout_Repository();
		$this->importer   = new Demo_Importer( $this->repository );
	}

	/**
	 * Creates two published pages the sample menu can point at.
	 *
	 * @return int[] Page IDs.
	 */
	private function create_pages() {
		$ids = array();

		foreach ( array( 'About', 'Contact' ) as $title ) {
			$ids[] = (int) wp_insert_post(
				array(
					'post_title'   => $title,
					'post_content' => 'Content of ' . $title . '.',
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);
		}

		return $ids;
	}

	/**
	 * A menu demo import creates the layout, the menu and its items.
	 *
	 * @return void
	 */
	public function test_import_creates_layout_and_sample_menu() {
		$pages  = $this->create_pages();
		$result = $this->importer->import( 'header-dark-bar' );

		$this->assertCount( 1, $result['imported'] );
		$this->assertSame( 'created', $result['imported'][0]['action'] );

		$layout = Layout::from_post( get_post( (int) $result['imported'][0]['id'] ) );

		$this->assertInstanceOf( Layout::class, $layout );
		$this->assertSame( 'header', $layout->type );
		$this->assertSame( 'demo', $layout->builder );
		$this->assertSame( 'header-dark-bar', $layout->demo_slug );
		$this->assertSame( 'qevora-demo-header-dark-bar', $layout->template_id );
		$this->assertSame( 'publish', $layout->status );
		$this->assertSame( array(), $layout->conditions['include'], 'Demos display on the entire site by default.' );

		// A real menu with the home link plus both published pages.
		$this->assertGreaterThan( 0, $layout->menu_id );
		$this->assertArrayHasKey( $layout->menu_id, Qevora_Test_Env::$menus );

		$items = Qevora_Test_Env::$menus[ $layout->menu_id ]['items'];

		$this->assertCount( 3, $items );

		$titles = array();

		foreach ( $items as $item ) {
			$titles[] = $item['menu-item-title'];
		}

		$this->assertContains( 'Home', $titles );
		$this->assertContains( 'About', $titles );
		$this->assertContains( 'Contact', $titles );
		$this->assertSame( $pages[0], (int) $items[2]['menu-item-object-id'] );
	}

	/**
	 * Each demo keeps its own data: a second demo never reuses the first
	 * demo's slug or menu.
	 *
	 * @return void
	 */
	public function test_every_demo_keeps_separate_data() {
		$first  = $this->importer->import( 'header-dark-bar' );
		$second = $this->importer->import( 'header-dark-bar', array( 'on_duplicate' => 'copy' ) );

		$first_layout  = Layout::from_post( get_post( (int) $first['imported'][0]['id'] ) );
		$second_layout = Layout::from_post( get_post( (int) $second['imported'][0]['id'] ) );

		$this->assertNotSame( $first_layout->id, $second_layout->id );
		$this->assertSame( 'header-dark-bar', $first_layout->demo_slug );
		$this->assertSame( 'header-dark-bar', $second_layout->demo_slug );
		$this->assertSame( 'header', $first_layout->type );

		update_post_meta( $first_layout->id, '_qevora_layout_demo_settings', array( 'button_label' => 'First only' ) );

		$second_settings = get_post_meta( $second_layout->id, '_qevora_layout_demo_settings', true );

		$this->assertNotSame( 'First only', is_array( $second_settings ) ? ( $second_settings['button_label'] ?? '' ) : '', 'Settings stay per layout, never shared between copies.' );
	}

	/**
	 * Re-importing a demo is skipped, and the copy policy makes an explicit
	 * second copy.
	 *
	 * @return void
	 */
	public function test_duplicate_policy() {
		$first = $this->importer->import( 'header-dark-bar' );

		$this->assertSame( 'created', $first['imported'][0]['action'] );

		$again = $this->importer->import( 'header-dark-bar' );

		$this->assertCount( 0, $again['imported'] );
		$this->assertCount( 1, $again['skipped'] );
		$this->assertStringContainsString( 'already exists', $again['skipped'][0]['reason'] );

		$copy = $this->importer->import( 'header-dark-bar', array( 'on_duplicate' => 'copy' ) );

		$this->assertSame( 'copied', $copy['imported'][0]['action'] );
	}

	/**
	 * Unknown demos fail closed.
	 *
	 * @return void
	 */
	public function test_unknown_demo_is_rejected() {
		$this->assertIsError( $this->importer->import( 'does-not-exist' ) );
		$this->assertIsError( $this->importer->import( '../starter' ) );
	}

	/**
	 * The menu is only created when asked for, and only for demos that
	 * support one.
	 *
	 * @return void
	 */
	public function test_menu_creation_is_optional() {
		$without = $this->importer->import( 'header-dark-bar', array( 'create_menu' => false ) );

		$layout = Layout::from_post( get_post( (int) $without['imported'][0]['id'] ) );

		$this->assertSame( 0, $layout->menu_id );

		// Asking again for the same demo never creates a second menu.
		$again = $this->importer->import( 'header-dark-bar' );

		$this->assertSame( 'skipped', $again['imported'][0]['action'] ?? 'skipped' );
		$this->assertCount( 0, Qevora_Test_Env::$menus, 'No menu is created unless the import asks for one.' );
	}

	/**
	 * Without the menu capability the demo still imports, with a warning.
	 *
	 * @return void
	 */
	public function test_missing_menu_capability_warns_but_imports() {
		Qevora_Test_Env::$caps['edit_theme_options'] = false;

		$result = $this->importer->import( 'header-dark-bar' );

		$this->assertCount( 1, $result['imported'] );

		$layout = Layout::from_post( get_post( (int) $result['imported'][0]['id'] ) );

		$this->assertSame( 0, $layout->menu_id );
		$this->assertNotEmpty( $result['warnings'] );
	}

	/**
	 * Display scope and the hide-theme-part flag work like everywhere else.
	 *
	 * @return void
	 */
	public function test_scope_and_hide_flag_apply_to_demos() {
		$result = $this->importer->import(
			'header-dark-bar',
			array(
				'display_scope'   => 'front_page',
				'hide_theme_part' => '1',
				'create_menu'     => false,
			)
		);

		$layout = Layout::from_post( get_post( (int) $result['imported'][0]['id'] ) );

		$this->assertCount( 1, $layout->conditions['include'] );
		$this->assertSame( 'front_page', $layout->conditions['include'][0]['type'] );
		$this->assertTrue( $layout->hide_theme_part );
	}

	/**
	 * Importing needs the plugin capability.
	 *
	 * @return void
	 */
	public function test_capability_is_required() {
		Qevora_Test_Env::$caps['publish_qevora_layouts'] = false;

		$result = $this->importer->import( 'header-dark-bar' );

		$this->assertCount( 0, $result['imported'] );
		$this->assertCount( 1, $result['failed'] );
	}

	/**
	 * An existing menu name never blocks the import: a numbered name is used.
	 *
	 * @return void
	 */
	public function test_menu_names_stay_unique() {
		$first  = $this->importer->import( 'header-dark-bar' );
		$second = $this->importer->import( 'header-dark-bar', array( 'on_duplicate' => 'copy' ) );

		$first_layout  = Layout::from_post( get_post( (int) $first['imported'][0]['id'] ) );
		$second_layout = Layout::from_post( get_post( (int) $second['imported'][0]['id'] ) );

		$this->assertGreaterThan( 0, $second_layout->menu_id );
		$this->assertNotSame( $first_layout->menu_id, $second_layout->menu_id );
		$this->assertNotSame(
			Qevora_Test_Env::$menus[ $first_layout->menu_id ]['name'],
			Qevora_Test_Env::$menus[ $second_layout->menu_id ]['name']
		);
	}
}
