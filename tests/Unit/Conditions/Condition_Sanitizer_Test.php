<?php
/**
 * Tests for the condition sanitizer.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Tests\Unit\Conditions;

use QevoraLayouts\Conditions\Condition_Sanitizer;
use QevoraLayouts\Tests\TestCase;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The sanitizer is the only place where untrusted condition data becomes
 * something the matcher trusts, so it gets a lot of attention.
 *
 * @since 1.0.0
 */
class Condition_Sanitizer_Test extends TestCase {

	/**
	 * Empty input means "the entire site".
	 *
	 * @return void
	 */
	public function test_empty_conditions_are_normalized_to_both_lists() {
		$conditions = Condition_Sanitizer::normalize( array() );

		$this->assertArrayHasKey( 'include', $conditions );
		$this->assertArrayHasKey( 'exclude', $conditions );
		$this->assertSame( array(), $conditions['include'] );
		$this->assertSame( array(), $conditions['exclude'] );
		$this->assertTrue( Condition_Sanitizer::is_entire_site( $conditions ) );
	}

	/**
	 * Junk input must never produce a fatal or a wrong structure.
	 *
	 * @return void
	 */
	public function test_non_array_input_is_normalized() {
		foreach ( array( null, '', 'nonsense', 12, 4.2, true, new \stdClass() ) as $input ) {
			$conditions = Condition_Sanitizer::normalize( $input );

			$this->assertSame( array( 'include' => array(), 'exclude' => array() ), $conditions );
		}
	}

	/**
	 * A JSON encoded condition set (how meta may arrive) is accepted.
	 *
	 * @return void
	 */
	public function test_json_string_input_is_decoded() {
		$conditions = Condition_Sanitizer::normalize( '{"include":[{"type":"front_page"}],"exclude":[]}' );

		$this->assertCount( 1, $conditions['include'] );
		$this->assertSame( 'front_page', $conditions['include'][0]['type'] );
	}

	/**
	 * Unknown condition types are dropped instead of being stored.
	 *
	 * @return void
	 */
	public function test_unknown_condition_types_are_dropped() {
		$conditions = Condition_Sanitizer::normalize(
			array(
				'include' => array(
					array( 'type' => 'front_page' ),
					array( 'type' => 'not_a_real_condition' ),
					array( 'type' => 'url_contains', 'value' => '/shop' ),
				),
			)
		);

		$this->assertCount( 1, $conditions['include'] );
		$this->assertSame( 'front_page', $conditions['include'][0]['type'] );
	}

	/**
	 * A condition that needs a value is dropped without one.
	 *
	 * @return void
	 */
	public function test_conditions_without_a_required_value_are_dropped() {
		$conditions = Condition_Sanitizer::normalize(
			array(
				'include' => array(
					array( 'type' => 'post' ),
					array( 'type' => 'user_role' ),
					array( 'type' => 'post', 'value' => '7' ),
				),
			)
		);

		$this->assertCount( 1, $conditions['include'] );
		$this->assertSame( '7', $conditions['include'][0]['value'] );
	}

	/**
	 * ID lists are reduced to positive integers.
	 *
	 * @return void
	 */
	public function test_id_lists_are_reduced_to_positive_integers() {
		$conditions = Condition_Sanitizer::normalize(
			array(
				'include' => array(
					array( 'type' => 'post', 'value' => '12, abc, -4, 0, 48 ,48' ),
				),
			)
		);

		$this->assertSame( '12,48', $conditions['include'][0]['value'] );
	}

	/**
	 * Arrays of IDs are accepted as well.
	 *
	 * @return void
	 */
	public function test_id_lists_accept_arrays() {
		$conditions = Condition_Sanitizer::normalize(
			array(
				'include' => array(
					array( 'type' => 'page', 'value' => array( '3', 9, 'x' ) ),
				),
			)
		);

		$this->assertSame( '3,9', $conditions['include'][0]['value'] );
	}

	/**
	 * Role values are reduced to known role slugs.
	 *
	 * @return void
	 */
	public function test_role_values_are_reduced_to_known_roles() {
		$conditions = Condition_Sanitizer::normalize(
			array(
				'include' => array(
					array( 'type' => 'user_role', 'value' => 'editor, <script>, subscriber, not a role!' ),
				),
			)
		);

		$this->assertSame( 'editor,subscriber', $conditions['include'][0]['value'] );
	}

	/**
	 * A taxonomy rule keeps its optional term list.
	 *
	 * @return void
	 */
	public function test_taxonomy_rules_keep_their_terms() {
		$conditions = Condition_Sanitizer::normalize(
			array(
				'include' => array(
					array( 'type' => 'taxonomy', 'value' => 'Category', 'terms' => '3, x, 11' ),
				),
			)
		);

		$this->assertSame( 'category', $conditions['include'][0]['value'] );
		$this->assertSame( '3,11', $conditions['include'][0]['terms'] );
	}

	/**
	 * Exclude rules survive normalization untouched.
	 *
	 * @return void
	 */
	public function test_exclude_rules_are_kept_separately() {
		$conditions = Condition_Sanitizer::normalize(
			array(
				'include' => array( array( 'type' => 'entire_site' ) ),
				'exclude' => array( array( 'type' => 'post_type', 'value' => 'post' ) ),
			)
		);

		$this->assertCount( 1, $conditions['include'] );
		$this->assertCount( 1, $conditions['exclude'] );
		$this->assertSame( 'post', $conditions['exclude'][0]['value'] );
		$this->assertFalse( Condition_Sanitizer::is_entire_site( $conditions ) );
	}

	/**
	 * Exclusions are kept even when there are no include rules.
	 *
	 * @return void
	 */
	public function test_exclusions_alone_still_restrict_the_layout() {
		$conditions = Condition_Sanitizer::normalize(
			array(
				'exclude' => array( array( 'type' => 'search' ) ),
			)
		);

		$this->assertSame( array(), $conditions['include'] );
		$this->assertCount( 1, $conditions['exclude'] );
		$this->assertFalse( Condition_Sanitizer::is_entire_site( $conditions ) );
	}

	/**
	 * A plain string rule is accepted for conditions without a value.
	 *
	 * @return void
	 */
	public function test_string_rules_are_converted() {
		$conditions = Condition_Sanitizer::normalize(
			array(
				'include' => array( 'front_page' ),
			)
		);

		$this->assertSame( array( 'type' => 'front_page' ), $conditions['include'][0] );
	}

	/**
	 * Value types that do not apply to a condition are never stored.
	 *
	 * @return void
	 */
	public function test_values_of_the_wrong_type_are_removed() {
		$conditions = Condition_Sanitizer::normalize(
			array(
				'include' => array(
					array( 'type' => 'front_page', 'value' => '12,48' ),
					array( 'type' => 'post', 'value' => '12', 'terms' => '5' ),
				),
			)
		);

		$this->assertArrayHasKey( 'type', $conditions['include'][0] );
		$this->assertFalse( isset( $conditions['include'][0]['value'] ), 'A "none" condition must not store a value.' );
		$this->assertFalse( isset( $conditions['include'][1]['terms'] ), 'Only taxonomy rules may store terms.' );
	}

	/**
	 * The meta callback keeps the normalized shape.
	 *
	 * @return void
	 */
	public function test_sanitize_callback_returns_a_normalized_array() {
		$value = Condition_Sanitizer::sanitize( array( 'include' => array( array( 'type' => '404' ) ) ) );

		$this->assertArrayHasKey( 'include', $value );
		$this->assertSame( '404', $value['include'][0]['type'] );
	}

	/**
	 * The human readable summary names the conditions.
	 *
	 * @return void
	 */
	public function test_summary_names_the_conditions() {
		$summary = Condition_Sanitizer::summarize(
			array(
				'include' => array( array( 'type' => 'front_page' ), array( 'type' => 'search' ) ),
				'exclude' => array( array( 'type' => 'post_type', 'value' => 'post' ) ),
			),
			2
		);

		$this->assertStringContainsString( 'Front page', $summary );
		$this->assertStringContainsString( '1 exclusion', $summary );
	}

	/**
	 * Slugs are sanitized for taxonomy and post type values.
	 *
	 * @return void
	 */
	public function test_slugs_are_sanitized() {
		$this->assertSame( 'product-cat', Condition_Sanitizer::sanitize_slug( 'Product-Cat' ) );
		$this->assertSame( 'category', Condition_Sanitizer::sanitize_slug( array( 'category' ) ) );
		$this->assertSame( '', Condition_Sanitizer::sanitize_slug( 'not a slug' ) );
		$this->assertSame( '', Condition_Sanitizer::sanitize_slug( '<script>alert(1)</script>' ) );
	}
}
