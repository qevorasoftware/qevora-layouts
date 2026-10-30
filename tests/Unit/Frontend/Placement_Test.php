<?php
/**
 * Placement resolution tests: stored hooks versus the hooks the active
 * theme really fires.
 *
 * Block themes render through `template-canvas.php`, which never runs
 * `get_header`/`get_footer`. Without the resolver in `Frontend`, a footer
 * stored with the default `get_footer` placement silently never renders on
 * every block theme - which is what real WordPress 7.1 showed after
 * activation.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Tests\Unit\Frontend;

use QevoraLayouts\Core\Layout_Repository;
use QevoraLayouts\Core\Plugin;
use QevoraLayouts\Frontend\Duplicate_Guard;
use QevoraLayouts\Tests\TestCase;
use Qevora_Test_Env;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exercises the placement hooks end to end through the real Frontend class.
 *
 * @since 1.0.0
 */
class Placement_Test extends TestCase {

	/**
	 * The Plugin singleton caches its resolved plan, so every test needs a
	 * fresh instance.
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		$reflection = new \ReflectionClass( Plugin::class );
		$property   = $reflection->getProperty( 'instance' );

		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}

		$property->setValue( null, null );

		Duplicate_Guard::reset();
	}

	/**
	 * Creates one published header and one published footer layout.
	 *
	 * @param string $header_placement Header placement slug.
	 * @param string $footer_placement Footer placement slug.
	 * @return void
	 */
	private function create_layouts( $header_placement, $footer_placement ) {
		$repository = new Layout_Repository();

		$repository->save(
			array(
				'title'     => 'Test header',
				'type'      => 'header',
				'placement' => $header_placement,
				'status'    => 'publish',
				'content'   => '<p>header content</p>',
			)
		);

		$repository->save(
			array(
				'title'     => 'Test footer',
				'type'      => 'footer',
				'placement' => $footer_placement,
				'status'    => 'publish',
				'content'   => '<p>footer content</p>',
			)
		);
	}

	/**
	 * Runs the plan and one placement hook, returning the rendered markup.
	 *
	 * @param string $method Frontend hook callback name.
	 * @return string Rendered markup.
	 */
	private function render_hook( $method ) {
		// Frontend::should_run() waits for the main query, exactly like a
		// real request gets it before `template_redirect`.
		do_action( 'wp' );

		$frontend = Plugin::instance()->frontend();

		$frontend->resolve();

		ob_start();
		$frontend->{$method}();

		return (string) ob_get_clean();
	}

	/**
	 * On a block theme, `get_header`/`get_footer` placements move to
	 * `wp_body_open`/`wp_footer`, because the template canvas never fires
	 * the classic hooks.
	 *
	 * @return void
	 */
	public function test_block_theme_remaps_classic_placements() {
		Qevora_Test_Env::$block_theme = true;
		Qevora_Test_Env::$query       = array( 'is_front_page' => true );

		$this->create_layouts( 'get_header', 'get_footer' );

		$header = $this->render_hook( 'render_wp_body_open' );
		$footer = $this->render_hook( 'render_wp_footer' );

		$this->assertStringContainsString( 'qevora-layouts--header', $header );
		$this->assertStringContainsString( 'header content', $header );
		$this->assertStringContainsString( 'qevora-layouts--footer', $footer );
		$this->assertStringContainsString( 'footer content', $footer );

		// The classic hooks fire nothing on a block theme.
		$this->assertSame( '', $this->render_hook( 'render_get_footer' ) );
	}

	/**
	 * On a classic theme the stored hooks keep their meaning.
	 *
	 * @return void
	 */
	public function test_classic_theme_keeps_stored_placements() {
		Qevora_Test_Env::$block_theme = false;
		Qevora_Test_Env::$query       = array( 'is_front_page' => true );

		$this->create_layouts( 'get_header', 'get_footer' );

		$footer = $this->render_hook( 'render_get_footer' );
		$header = $this->render_hook( 'render_get_header' );

		$this->assertStringContainsString( 'qevora-layouts--footer', $footer );
		$this->assertStringContainsString( 'qevora-layouts--header', $header );

		// wp_footer must not double-render the same layout.
		$this->assertSame( '', $this->render_hook( 'render_wp_footer' ) );
	}
}
