<?php
/**
 * Rendering tests for the bundled PHP demos.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Tests\Unit\Frontend;

use QevoraLayouts\Core\Layout;
use QevoraLayouts\Core\Layout_Repository;
use QevoraLayouts\Core\Plugin;
use QevoraLayouts\Frontend\Duplicate_Guard;
use QevoraLayouts\Import\Demo_Registry;
use QevoraLayouts\Tests\TestCase;
use Qevora_Test_Env;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Demo layouts render their own PHP template with their own assets, and
 * never touch editor content or another demo's files.
 *
 * @since 1.0.0
 */
class Demo_Render_Test extends TestCase {

	/**
	 * Repository used to create layouts.
	 *
	 * @var Layout_Repository
	 */
	private $repository;

	/**
	 * Fresh plugin instance per test, like the other frontend tests.
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		Demo_Registry::reset();

		$reflection = new \ReflectionClass( Plugin::class );
		$property   = $reflection->getProperty( 'instance' );

		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}

		$property->setValue( null, null );

		Duplicate_Guard::reset();

		$this->repository = new Layout_Repository();
	}

	/**
	 * Saves a published demo layout.
	 *
	 * @param array<string,mixed> $overrides Save data overrides.
	 * @return int Layout ID.
	 */
	private function create_demo_layout( array $overrides = array() ) {
		$data = array_merge(
			array(
				'title'     => 'Demo header',
				'type'      => 'header',
				'status'    => 'publish',
				'content'   => '',
				'builder'   => 'demo',
				'demo_slug' => 'header-classic',
				'menu_id'   => 0,
			),
			$overrides
		);

		$saved = $this->repository->save( $data );

		$this->assertTrue( is_int( $saved ) || ctype_digit( (string) $saved ), 'Layout was saved.' );

		return (int) $saved;
	}

	/**
	 * The demo adapter renders the plugin's own template and loads only
	 * that demo's assets.
	 *
	 * @return void
	 */
	public function test_demo_adapter_renders_its_own_template() {
		Qevora_Test_Env::$query = array( 'is_front_page' => true );

		$id     = $this->create_demo_layout();
		$layout = Layout::from_post( get_post( $id ) );

		$this->assertSame( 'demo', $layout->builder );

		$html = Plugin::instance()->builders()->render( $layout );

		$this->assertStringContainsString( 'class="qdhc"', $html, 'The demo template markup rendered.' );
		$this->assertStringContainsString( 'qdhc__brand', $html );
		$this->assertTrue( wp_style_is( 'qevora-demo-header-classic' ), 'The demo stylesheet was enqueued.' );
		$this->assertTrue( wp_script_is( 'qevora-demo-header-classic-js' ), 'The demo script was enqueued.' );
		$this->assertFalse( wp_style_is( 'qevora-demo-footer-simple' ), 'No other demo assets load.' );
	}

	/**
	 * A menu created for the demo renders inside the demo markup.
	 *
	 * @return void
	 */
	public function test_demo_renders_its_menu() {
		Qevora_Test_Env::$query = array( 'is_front_page' => true );

		$menu_id = (int) wp_update_nav_menu_object( 0, array( 'menu-name' => 'Classic Header menu' ) );

		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'  => 'Home',
				'menu-item-url'    => 'http://example.com/',
				'menu-item-type'   => 'custom',
				'menu-item-status' => 'publish',
			)
		);

		$id     = $this->create_demo_layout( array( 'menu_id' => $menu_id ) );
		$layout = Layout::from_post( get_post( $id ) );

		$html = Plugin::instance()->builders()->render( $layout );

		$this->assertStringContainsString( '<ul class="qdhc__menu">', $html );
		$this->assertStringContainsString( '>Home<', $html );
		$this->assertStringContainsString( 'qdhc__toggle', $html, 'The mobile toggle renders with a menu.' );
	}

	/**
	 * Without a menu the toggle and nav do not render at all.
	 *
	 * @return void
	 */
	public function test_demo_without_menu_renders_no_nav() {
		Qevora_Test_Env::$query = array( 'is_front_page' => true );

		$id     = $this->create_demo_layout();
		$layout = Layout::from_post( get_post( $id ) );

		$html = Plugin::instance()->builders()->render( $layout );

		$this->assertStringContainsString( 'class="qdhc"', $html );
		$this->assertStringNotContainsString( 'qdhc__toggle', $html );
		$this->assertStringNotContainsString( 'qdhc__nav', $html );
	}

	/**
	 * The footer demo renders its own scoped markup - every demo keeps its
	 * own data and stylesheet separate.
	 *
	 * @return void
	 */
	public function test_footer_demo_renders_separately() {
		Qevora_Test_Env::$query = array( 'is_front_page' => true );

		$id = $this->create_demo_layout(
			array(
				'title'     => 'Demo footer',
				'type'      => 'footer',
				'demo_slug' => 'footer-simple',
			)
		);

		$layout = Layout::from_post( get_post( $id ) );

		$html = Plugin::instance()->builders()->render( $layout );

		$this->assertStringContainsString( 'class="qdfs"', $html );
		$this->assertStringContainsString( 'qdfs__top', $html );
		$this->assertTrue( wp_style_is( 'qevora-demo-footer-simple' ) );
		$this->assertTrue( wp_script_is( 'qevora-demo-footer-simple-js' ) );
		$this->assertFalse( wp_style_is( 'qevora-demo-header-classic' ) );
	}

	/**
	 * An unknown demo slug renders nothing instead of failing loudly.
	 *
	 * @return void
	 */
	public function test_unknown_demo_slug_renders_nothing() {
		Qevora_Test_Env::$query = array( 'is_front_page' => true );

		$id     = $this->create_demo_layout( array( 'demo_slug' => 'not-a-demo' ) );
		$layout = Layout::from_post( get_post( $id ) );

		$html = Plugin::instance()->builders()->render( $layout );

		$this->assertSame( '', trim( (string) $html ) );
	}

	/**
	 * Demo assets are queued while the plan resolves, so the stylesheet
	 * reaches wp_head before the demo markup is printed.
	 *
	 * @return void
	 */
	public function test_plan_enqueues_demo_assets_before_render() {
		Qevora_Test_Env::$query = array( 'is_front_page' => true );

		$this->create_demo_layout(
			array(
				'title'     => 'Demo footer',
				'type'      => 'footer',
				'demo_slug' => 'footer-columns',
			)
		);

		do_action( 'wp' );

		$frontend = Plugin::instance()->frontend();
		$frontend->resolve();

		$this->assertTrue( wp_style_is( 'qevora-demo-footer-columns' ), 'Queued at plan time, before wp_head.' );
		$this->assertFalse( wp_script_is( 'qevora-demo-footer-columns-js' ), 'This demo ships no script.' );

		ob_start();
		$frontend->render_get_footer();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'qevora-layouts--footer', $html );
		$this->assertStringContainsString( 'class="qdfc"', $html );
	}
}
