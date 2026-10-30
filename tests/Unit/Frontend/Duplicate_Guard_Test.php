<?php
/**
 * Tests for the duplicate render guard.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Tests\Unit\Frontend;

use QevoraLayouts\Frontend\Duplicate_Guard;
use QevoraLayouts\Tests\TestCase;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One slot renders once per request, whatever a theme does.
 *
 * @since 1.0.0
 */
class Duplicate_Guard_Test extends TestCase {

	/**
	 * Starts every test with an empty guard.
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		Duplicate_Guard::reset();
	}

	/**
	 * Leaves the guard clean for the next test class.
	 *
	 * @return void
	 */
	public function tearDown() {
		Duplicate_Guard::reset();

		parent::tearDown();
	}

	/**
	 * A fresh slot may render, a used one may not.
	 *
	 * @return void
	 */
	public function test_a_slot_renders_once() {
		$this->assertTrue( Duplicate_Guard::should_render( 'header' ) );
		$this->assertFalse( Duplicate_Guard::has_rendered( 'header' ) );
		$this->assertSame( 0, Duplicate_Guard::rendered_layout_id( 'header' ) );

		Duplicate_Guard::mark_rendered( 'header', 42 );

		$this->assertFalse( Duplicate_Guard::should_render( 'header' ) );
		$this->assertTrue( Duplicate_Guard::has_rendered( 'header' ) );
		$this->assertSame( 42, Duplicate_Guard::rendered_layout_id( 'header' ) );
	}

	/**
	 * Slots are independent of each other.
	 *
	 * @return void
	 */
	public function test_slots_are_independent() {
		Duplicate_Guard::mark_rendered( 'header', 1 );

		$this->assertTrue( Duplicate_Guard::should_render( 'footer' ) );
		$this->assertTrue( Duplicate_Guard::should_render( 'section' ) );
		$this->assertFalse( Duplicate_Guard::has_rendered( 'footer' ) );

		Duplicate_Guard::mark_rendered( 'footer', 2 );

		$this->assertFalse( Duplicate_Guard::should_render( 'footer' ) );
		$this->assertSame( 1, Duplicate_Guard::rendered_layout_id( 'header' ) );
		$this->assertSame( 2, Duplicate_Guard::rendered_layout_id( 'footer' ) );
	}

	/**
	 * Slot names are normalized so casing cannot defeat the guard.
	 *
	 * @return void
	 */
	public function test_slot_names_are_normalized() {
		Duplicate_Guard::mark_rendered( 'Header', 7 );

		$this->assertTrue( Duplicate_Guard::has_rendered( 'header' ) );
		$this->assertTrue( Duplicate_Guard::has_rendered( 'HEADER' ) );
		$this->assertTrue( Duplicate_Guard::has_rendered( 'he ader' ) );
		$this->assertSame( 7, Duplicate_Guard::rendered_layout_id( 'header' ) );

		Duplicate_Guard::mark_rendered( 'section:hero-1', 8 );

		$this->assertTrue( Duplicate_Guard::has_rendered( 'section:hero-1' ) );

		$this->assertFalse( Duplicate_Guard::has_rendered( '' ), 'An empty slot never collides with a real one.' );
	}

	/**
	 * Unusable slot values fall back to a single bucket.
	 *
	 * @return void
	 */
	public function test_unusable_slots_share_a_bucket() {
		Duplicate_Guard::mark_rendered( array( 'not', 'scalar' ), 3 );

		$this->assertTrue( Duplicate_Guard::has_rendered( '!!!' ) );
		$this->assertSame( 3, Duplicate_Guard::rendered_layout_id( '' ) );
		$this->assertFalse( Duplicate_Guard::should_render( '' ) );
		$this->assertTrue( Duplicate_Guard::should_render( 'header' ), 'A real slot is untouched by the fallback.' );
	}

	/**
	 * A theme or plugin can keep its own markup.
	 *
	 * @return void
	 */
	public function test_the_filter_can_block_a_slot() {
		add_filter(
			'qevora_layouts_should_render',
			function ( $allowed, $slot ) {
				return 'footer' === $slot ? false : $allowed;
			},
			10,
			2
		);

		$this->assertTrue( Duplicate_Guard::should_render( 'header' ) );
		$this->assertFalse( Duplicate_Guard::should_render( 'footer' ) );
	}

	/**
	 * Rendering a slot announces it.
	 *
	 * @return void
	 */
	public function test_marking_a_slot_fires_an_action() {
		$seen = array();

		add_action(
			'qevora_layouts_slot_rendered',
			function ( $slot, $layout_id ) use ( &$seen ) {
				$seen[] = array( $slot, $layout_id );
			},
			10,
			2
		);

		Duplicate_Guard::mark_rendered( 'Header', 11 );
		Duplicate_Guard::mark_rendered( 'footer', 12 );

		$this->assertSame( array( array( 'header', 11 ), array( 'footer', 12 ) ), $seen );
	}

	/**
	 * Resetting makes every slot available again.
	 *
	 * @return void
	 */
	public function test_reset_clears_every_slot() {
		Duplicate_Guard::mark_rendered( 'header', 1 );
		Duplicate_Guard::mark_rendered( 'footer', 2 );

		Duplicate_Guard::reset();

		$this->assertTrue( Duplicate_Guard::should_render( 'header' ) );
		$this->assertTrue( Duplicate_Guard::should_render( 'footer' ) );
		$this->assertSame( 0, Duplicate_Guard::rendered_layout_id( 'header' ) );
	}
}
