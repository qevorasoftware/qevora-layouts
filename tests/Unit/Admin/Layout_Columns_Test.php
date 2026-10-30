<?php
/**
 * Tests for the layout list table, including the live preview column.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Tests\Unit\Admin;

use QevoraLayouts\Admin\Layout_Columns;
use QevoraLayouts\Core\Layout_Repository;
use QevoraLayouts\Import\Demo_Registry;
use QevoraLayouts\Tests\TestCase;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every listed layout shows what it will look like: demo layouts carry a
 * small live preview straight from their PHP file.
 *
 * @since 1.0.0
 */
class Layout_Columns_Test extends TestCase {

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
	 * The list table offers a preview column next to the title.
	 *
	 * @return void
	 */
	public function test_the_list_table_has_a_preview_column() {
		$columns = Layout_Columns::columns( array() );

		$this->assertArrayHasKey( 'ql_preview', $columns );
		$keys = array_keys( $columns );
		$this->assertSame( 'title', $keys[ array_search( 'ql_preview', $keys, true ) - 1 ], 'The preview sits right after the layout name.' );
	}

	/**
	 * Demo layout rows render the bundled demo live, with their stored
	 * settings; other rows fall back to a dash.
	 *
	 * @return void
	 */
	public function test_demo_rows_show_a_live_preview() {
		$demo = $this->create_layout( array( 'builder' => 'demo', 'demo_slug' => 'header-dark-bar' ) );
		update_post_meta( $demo, '_qevora_layout_demo_settings', array( 'button_label' => 'Listed label' ) );

		ob_start();
		Layout_Columns::render_column( 'ql_preview', $demo );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'qevora-demo-live--row', $html );
		$this->assertStringContainsString( 'class="qhdb"', $html );
		$this->assertStringContainsString( 'Listed label', $html, 'The row preview uses the stored settings.' );

		$plain = $this->create_layout();

		ob_start();
		Layout_Columns::render_column( 'ql_preview', $plain );
		$dash = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'qevora-demo-live', $dash );
	}
}
