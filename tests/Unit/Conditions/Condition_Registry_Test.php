<?php
/**
 * Tests for the condition registry.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Tests\Unit\Conditions;

use QevoraLayouts\Conditions\Condition_Registry;
use QevoraLayouts\Tests\TestCase;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The registry defines the vocabulary of the condition engine.
 *
 * @since 1.0.0
 */
class Condition_Registry_Test extends TestCase {

	/**
	 * Every documented condition exists.
	 *
	 * @return void
	 */
	public function test_all_documented_conditions_are_registered() {
		$expected = array(
			'entire_site',
			'front_page',
			'posts_page',
			'singular',
			'archive',
			'search',
			'404',
			'post_type',
			'post_type_archive',
			'post',
			'page',
			'taxonomy',
			'term',
			'author',
			'logged_in',
			'logged_out',
			'user_role',
		);

		foreach ( $expected as $type ) {
			$this->assertTrue( Condition_Registry::exists( $type ), 'Missing condition: ' . $type );
		}

		$this->assertFalse( Condition_Registry::exists( 'url_contains' ) );
		$this->assertFalse( Condition_Registry::exists( '' ) );
	}

	/**
	 * Every condition declares a label, a description, a value type and a group.
	 *
	 * @return void
	 */
	public function test_every_condition_is_fully_described() {
		foreach ( Condition_Registry::all() as $type => $condition ) {
			$this->assertNotEmpty( $condition['label'], 'No label for ' . $type );
			$this->assertNotEmpty( $condition['description'], 'No description for ' . $type );
			$this->assertContains(
				$condition['value'],
				array( 'none', 'post_type', 'taxonomy', 'ids', 'term_ids', 'roles' ),
				'Unknown value type for ' . $type
			);
			$this->assertNotEmpty( $condition['group'], 'No group for ' . $type );
			$this->assertTrue( isset( $condition['specificity'] ), 'No specificity for ' . $type );
		}
	}

	/**
	 * Specificity grows with how narrow a condition is.
	 *
	 * @return void
	 */
	public function test_specificity_orders_conditions_from_broad_to_narrow() {
		$entire_site = Condition_Registry::specificity( 'entire_site' );
		$logged_in   = Condition_Registry::specificity( 'logged_in' );
		$front_page  = Condition_Registry::specificity( 'front_page' );
		$post_type   = Condition_Registry::specificity( 'post_type' );
		$archive     = Condition_Registry::specificity( 'post_type_archive' );
		$single_post = Condition_Registry::specificity( 'post' );

		$this->assertSame( 0, $entire_site );
		$this->assertGreaterThan( $entire_site, $logged_in );
		$this->assertGreaterThan( $logged_in, $front_page );
		$this->assertGreaterThan( $front_page, $post_type );
		$this->assertGreaterThan( $post_type, $archive );
		$this->assertGreaterThan( $archive, $single_post );

		// An unknown condition is never more specific than a known one.
		$this->assertSame( 0, Condition_Registry::specificity( 'not_a_condition' ) );
	}

	/**
	 * Value types match what the admin UI renders.
	 *
	 * @return void
	 */
	public function test_value_types_match_the_admin_ui() {
		$this->assertSame( 'none', Condition_Registry::value_type( 'entire_site' ) );
		$this->assertSame( 'post_type', Condition_Registry::value_type( 'post_type' ) );
		$this->assertSame( 'post_type', Condition_Registry::value_type( 'post_type_archive' ) );
		$this->assertSame( 'ids', Condition_Registry::value_type( 'post' ) );
		$this->assertSame( 'ids', Condition_Registry::value_type( 'page' ) );
		$this->assertSame( 'ids', Condition_Registry::value_type( 'author' ) );
		$this->assertSame( 'taxonomy', Condition_Registry::value_type( 'taxonomy' ) );
		$this->assertSame( 'term_ids', Condition_Registry::value_type( 'term' ) );
		$this->assertSame( 'roles', Condition_Registry::value_type( 'user_role' ) );
	}

	/**
	 * The grouped choices cover every condition exactly once.
	 *
	 * @return void
	 */
	public function test_grouped_choices_cover_every_condition_once() {
		$choices = Condition_Registry::grouped_choices();
		$seen    = array();

		$this->assertNotEmpty( $choices );

		foreach ( $choices as $group => $options ) {
			$this->assertNotEmpty( $group );
			$this->assertNotEmpty( $options );

			foreach ( $options as $slug => $label ) {
				$this->assertNotContains( $slug, $seen, 'Condition listed twice: ' . $slug );
				$this->assertNotEmpty( $label );
				$seen[] = $slug;
			}
		}

		$this->assertSame( count( Condition_Registry::all() ), count( $seen ) );
	}

	/**
	 * The payload for JavaScript keeps the keys the UI reads.
	 *
	 * @return void
	 */
	public function test_for_js_payload_has_the_expected_shape() {
		$payload = Condition_Registry::for_js();

		$this->assertArrayHasKey( 'front_page', $payload );

		foreach ( $payload as $entry ) {
			$this->assertArrayHasKey( 'label', $entry );
			$this->assertArrayHasKey( 'description', $entry );
			$this->assertArrayHasKey( 'value', $entry );
			$this->assertArrayHasKey( 'group', $entry );
		}
	}

	/**
	 * Labels are returned for known types only.
	 *
	 * @return void
	 */
	public function test_labels() {
		$this->assertSame( 'Front page', Condition_Registry::label( 'front_page' ) );

		// An unknown type falls back to the slug itself, never to an empty label.
		$this->assertSame( 'does_not_exist', Condition_Registry::label( 'does_not_exist' ) );
	}

	/**
	 * Third parties can add a condition through the filter.
	 *
	 * @return void
	 */
	public function test_conditions_can_be_extended_with_a_filter() {
		add_filter(
			'qevora_layouts_conditions',
			function ( $conditions ) {
				$conditions['membership_area'] = array(
					'label'       => 'Membership area',
					'description' => 'Pages that need a membership.',
					'value'       => 'none',
					'specificity' => 3,
					'group'       => 'Custom',
				);

				return $conditions;
			}
		);

		$this->assertTrue( Condition_Registry::exists( 'membership_area' ) );
		$this->assertSame( 3, Condition_Registry::specificity( 'membership_area' ) );
		$this->assertSame( 'Membership area', Condition_Registry::label( 'membership_area' ) );
	}
}
