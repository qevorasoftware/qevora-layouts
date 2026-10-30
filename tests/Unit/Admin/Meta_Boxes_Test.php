<?php
/**
 * Tests for the per-demo settings metabox and the editor switch.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Tests\Unit\Admin;

use QevoraLayouts\Admin\Meta_Boxes;
use QevoraLayouts\Core\Layout_Post_Type;
use QevoraLayouts\Core\Layout_Repository;
use QevoraLayouts\Import\Demo_Registry;
use QevoraLayouts\Tests\TestCase;
use Qevora_Test_Env;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Demo layouts are customised through a metabox built from each demo's own
 * settings schema - never through the block editor.
 *
 * @since 1.0.0
 */
class Meta_Boxes_Test extends TestCase {

	/**
	 * Repository used to create layouts.
	 *
	 * @var Layout_Repository
	 */
	private $repository;

	/**
	 * Fresh registry and repository per test.
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		Demo_Registry::reset();

		$this->repository = new Layout_Repository();
	}

	/**
	 * Saves a layout post.
	 *
	 * @param array<string,mixed> $overrides Save data overrides.
	 * @return int Post ID.
	 */
	private function create_layout( array $overrides = array() ) {
		$data = array_merge(
			array(
				'title'   => 'A layout',
				'type'    => 'header',
				'status'  => 'publish',
				'content' => '',
				'builder' => 'auto',
			),
			$overrides
		);

		return (int) $this->repository->save( $data );
	}

	/**
	 * Demo layouts never open in the block editor; everything else does.
	 *
	 * @return void
	 */
	public function test_demo_layouts_leave_the_block_editor() {
		Meta_Boxes::register();

		$demo  = get_post( $this->create_layout( array( 'builder' => 'demo', 'demo_slug' => 'header-classic' ) ) );
		$plain = get_post( $this->create_layout() );

		$this->assertFalse( Meta_Boxes::use_block_editor( true, $demo ), 'A demo layout has no block content to edit.' );
		$this->assertTrue( Meta_Boxes::use_block_editor( true, $plain ), 'Other layouts keep the editor they had.' );
	}

	/**
	 * The settings box appears only for layouts that render a bundled demo,
	 * and is titled after that demo.
	 *
	 * @return void
	 */
	public function test_the_settings_box_is_per_demo() {
		$demo  = get_post( $this->create_layout( array( 'builder' => 'demo', 'demo_slug' => 'footer-simple' ) ) );
		$plain = get_post( $this->create_layout() );

		Meta_Boxes::add_boxes( $demo );

		$ids    = wp_list_pluck( Qevora_Test_Env::$admin['meta_boxes'], 'id' );
		$titles = wp_list_pluck( Qevora_Test_Env::$admin['meta_boxes'], 'title' );

		$this->assertContains( 'qevora-layout-demo-settings', $ids );
		$this->assertTrue( (bool) array_filter( $titles, function ( $title ) {
			return false !== strpos( $title, 'Simple Footer Bar' );
		} ), 'The box is titled after the demo it customises.' );

		Qevora_Test_Env::$admin['meta_boxes'] = array();

		Meta_Boxes::add_boxes( $plain );

		$this->assertNotContains( 'qevora-layout-demo-settings', wp_list_pluck( Qevora_Test_Env::$admin['meta_boxes'], 'id' ) );
	}

	/**
	 * The box renders exactly the fields of the demo's own schema, with
	 * stored values pre-filled and a nonce for saving.
	 *
	 * @return void
	 */
	public function test_the_box_renders_the_demo_schema() {
		$id   = $this->create_layout( array( 'builder' => 'demo', 'demo_slug' => 'header-classic' ) );
		$post = get_post( $id );

		update_post_meta( $id, Layout_Post_Type::META_DEMO_SETTINGS, array( 'site_title' => 'Acme Corp' ) );

		ob_start();
		Meta_Boxes::render_demo_settings_box( $post );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="qevora_layout_demo_settings[site_title]"', $html );
		$this->assertStringContainsString( 'value="Acme Corp"', $html, 'Stored values come back pre-filled.' );
		$this->assertStringContainsString( 'type="color"', $html );
		$this->assertStringContainsString( 'type="checkbox"', $html );
		$this->assertStringContainsString( 'qevora_layouts_demo_settings_nonce', $html );
		$this->assertStringContainsString( 'templates/demos/header-classic/template.php', $html, 'The box says where the markup lives.' );
		$this->assertStringNotContainsString( 'qevora_layout_demo_settings[heading]', $html, 'Other demos\' fields never leak in.' );
	}

	/**
	 * Saving runs every value through the demo schema: unknown keys and
	 * markup are dropped, types are coerced.
	 *
	 * @return void
	 */
	public function test_save_stores_only_sanitised_schema_values() {
		$id   = $this->create_layout( array( 'builder' => 'demo', 'demo_slug' => 'header-classic' ) );
		$post = get_post( $id );

		$_POST['qevora_layouts_demo_settings_nonce'] = wp_create_nonce( 'qevora_layouts_demo_settings' );
		$_POST['qevora_layout_demo_settings']        = array(
			'site_title'    => 'Acme <script>alert(1)</script>',
			'accent_color'  => 'red-ish',
			'sticky_header' => '1',
			'not_a_field'   => 'drop me',
		);

		Meta_Boxes::save_demo_settings( $id, $post );

		$saved = (array) get_post_meta( $id, Layout_Post_Type::META_DEMO_SETTINGS, true );

		$this->assertSame( array( 'site_title', 'accent_color', 'sticky_header' ), array_keys( $saved ) );
		$this->assertStringNotContainsString( '<script>', $saved['site_title'] );
		$this->assertSame( '#2271b1', $saved['accent_color'] );
		$this->assertTrue( $saved['sticky_header'] );

		$_POST = array();
	}

	/**
	 * A save without a valid nonce changes nothing.
	 *
	 * @return void
	 */
	public function test_save_requires_a_valid_nonce() {
		$id   = $this->create_layout( array( 'builder' => 'demo', 'demo_slug' => 'header-classic' ) );
		$post = get_post( $id );

		$_POST['qevora_layouts_demo_settings_nonce'] = 'wrong';
		$_POST['qevora_layout_demo_settings']        = array( 'site_title' => 'Nope' );

		Meta_Boxes::save_demo_settings( $id, $post );

		$this->assertSame( '', get_post_meta( $id, Layout_Post_Type::META_DEMO_SETTINGS, true ), 'Nothing is stored without a valid nonce.' );

		$_POST = array();
	}

	/**
	 * The edit screen explains why there is no content editor, and hides
	 * the (empty) content textarea for demo layouts only.
	 *
	 * @return void
	 */
	public function test_the_edit_screen_notice_hides_the_content_editor() {
		$demo  = get_post( $this->create_layout( array( 'builder' => 'demo', 'demo_slug' => 'section-hero' ) ) );
		$plain = get_post( $this->create_layout() );

		ob_start();
		Meta_Boxes::demo_editor_notice( $demo );
		$html = ob_get_clean();

		$this->assertStringContainsString( '#postdivrich', $html );
		$this->assertStringContainsString( 'Hero Section', $html );

		ob_start();
		Meta_Boxes::demo_editor_notice( $plain );
		$this->assertSame( '', ob_get_clean(), 'Other layouts keep their editor untouched.' );
	}
}
