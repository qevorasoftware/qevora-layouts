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
				'demo_slug' => 'header-dark-bar',
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

		$this->assertStringContainsString( 'class="qhdb"', $html, 'The demo template markup rendered.' );
		$this->assertStringContainsString( 'qhdb__logo', $html );
		$this->assertStringContainsString( 'qhdb__cta', $html, 'The call-to-action button renders by default.' );
		$this->assertTrue( wp_style_is( 'qevora-demo-header-dark-bar' ), 'The demo stylesheet was enqueued.' );
		$this->assertTrue( wp_script_is( 'qevora-demo-header-dark-bar-js' ), 'The demo script was enqueued.' );
		$this->assertFalse( wp_style_is( 'qevora-demo-not-a-demo' ), 'No other demo assets load.' );
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

		$this->assertStringContainsString( '<ul class="qhdb__menu">', $html );
		$this->assertStringContainsString( '>Home<', $html );
		$this->assertStringContainsString( 'qhdb__toggle', $html, 'The mobile toggle renders with a menu.' );
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

		$this->assertStringContainsString( 'class="qhdb"', $html );
		$this->assertStringNotContainsString( 'qhdb__toggle', $html );
		$this->assertStringNotContainsString( 'qhdb__nav', $html );
	}

	/**
	 * The demo renders its own scoped markup and keeps its assets to
	 * itself - one demo, one PHP file, one pair of handles.
	 *
	 * @return void
	 */
	public function test_the_demo_renders_its_own_scoped_markup() {
		Qevora_Test_Env::$query = array( 'is_front_page' => true );

		$id     = $this->create_demo_layout();
		$layout = Layout::from_post( get_post( $id ) );

		$html = Plugin::instance()->builders()->render( $layout );

		$this->assertStringContainsString( 'class="qhdb"', $html );
		$this->assertStringContainsString( 'qhdb__bar', $html );
		$this->assertStringContainsString( '--qhdb-accent: #f4511e', $html, 'The default logo tile colour renders.' );
		$this->assertTrue( wp_style_is( 'qevora-demo-header-dark-bar' ) );
		$this->assertTrue( wp_script_is( 'qevora-demo-header-dark-bar-js' ) );
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

		$this->create_demo_layout();

		do_action( 'wp' );

		$frontend = Plugin::instance()->frontend();
		$frontend->resolve();

		$this->assertTrue( wp_style_is( 'qevora-demo-header-dark-bar' ), 'Queued at plan time, before wp_head.' );
		$this->assertTrue( wp_script_is( 'qevora-demo-header-dark-bar-js' ), 'The demo ships its own script.' );

		ob_start();
		$frontend->render_slot( 'header' );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'qevora-layouts--header', $html );
		$this->assertStringContainsString( 'class="qhdb"', $html );
	}

	/**
	 * The rendered markup follows the settings stored on the layout, so
	 * customising a demo in its metabox changes the front end.
	 *
	 * @return void
	 */
	public function test_render_uses_the_stored_demo_settings() {
		$id = $this->create_demo_layout();

		update_post_meta( $id, '_qevora_layout_demo_settings', array( 'accent_color' => '#123456', 'button_label' => 'Acme Holdings 2026' ) );

		$html = Plugin::instance()->builders()->render( Layout::from_post( get_post( $id ) ) );

		$this->assertStringContainsString( '--qhdb-accent: #123456', $html, 'The stored colour renders.' );
		$this->assertStringContainsString( 'Acme Holdings 2026', $html, 'The stored button label renders.' );
	}

	/**
	 * Switching the call-to-action off removes it from the markup.
	 *
	 * @return void
	 */
	public function test_the_button_setting_removes_the_call_to_action() {
		$id = $this->create_demo_layout();

		update_post_meta( $id, '_qevora_layout_demo_settings', array( 'show_button' => false ) );

		$html = Plugin::instance()->builders()->render( Layout::from_post( get_post( $id ) ) );

		$this->assertStringNotContainsString( 'qhdb__cta', $html, 'The button was switched off.' );
		$this->assertStringContainsString( 'class="qhdb"', $html, 'The rest of the header still renders.' );
	}

}
