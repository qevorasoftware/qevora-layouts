<?php
/**
 * Tests for the condition matcher.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Tests\Unit\Conditions;

use QevoraLayouts\Conditions\Condition_Matcher;
use QevoraLayouts\Conditions\Context;
use QevoraLayouts\Tests\TestCase;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every decision is taken from the request context, never from the URL.
 *
 * @since 1.0.0
 */
class Condition_Matcher_Test extends TestCase {

	/**
	 * Builds a context.
	 *
	 * @param array<string,mixed> $data Context data.
	 * @return Context
	 */
	private function context( array $data = array() ) {
		return Context::from_array( $data );
	}

	/**
	 * An empty include list matches the entire site.
	 *
	 * @return void
	 */
	public function test_empty_conditions_match_every_request() {
		$conditions = array( 'include' => array(), 'exclude' => array() );

		$this->assertTrue( Condition_Matcher::matches( $conditions, $this->context() ) );
		$this->assertTrue( Condition_Matcher::matches( $conditions, $this->context( array( 'is_404' => true ) ) ) );
		$this->assertTrue( Condition_Matcher::matches( $conditions, $this->context( array( 'is_search' => true ) ) ) );
		$this->assertTrue( Condition_Matcher::matches( $conditions, $this->context( array( 'is_admin' => true ) ) ) );
	}

	/**
	 * A single include rule must match.
	 *
	 * @return void
	 */
	public function test_one_matching_include_rule_is_enough() {
		$conditions = array(
			'include' => array(
				array( 'type' => 'front_page' ),
				array( 'type' => 'search' ),
			),
		);

		$this->assertTrue( Condition_Matcher::matches( $conditions, $this->context( array( 'is_front_page' => true ) ) ) );
		$this->assertTrue( Condition_Matcher::matches( $conditions, $this->context( array( 'is_search' => true ) ) ) );
		$this->assertFalse( Condition_Matcher::matches( $conditions, $this->context( array( 'is_archive' => true ) ) ) );
	}

	/**
	 * Exclusions win over inclusions.
	 *
	 * @return void
	 */
	public function test_exclusions_always_win() {
		$conditions = array(
			'include' => array( array( 'type' => 'singular' ) ),
			'exclude' => array( array( 'type' => 'post_type', 'value' => 'page' ) ),
		);

		$single_post = $this->context(
			array(
				'is_singular' => true,
				'post_type'   => 'post',
				'post_id'     => 7,
			)
		);

		$single_page = $this->context(
			array(
				'is_singular' => true,
				'is_page'     => true,
				'post_type'   => 'page',
				'post_id'     => 9,
			)
		);

		$this->assertTrue( Condition_Matcher::matches( $conditions, $single_post ) );
		$this->assertFalse( Condition_Matcher::matches( $conditions, $single_page ) );
	}

	/**
	 * Exclusions alone still restrict a layout.
	 *
	 * @return void
	 */
	public function test_exclusions_without_inclusions() {
		$conditions = array(
			'include' => array(),
			'exclude' => array( array( 'type' => '404' ) ),
		);

		$this->assertTrue( Condition_Matcher::matches( $conditions, $this->context( array( 'is_front_page' => true ) ) ) );
		$this->assertFalse( Condition_Matcher::matches( $conditions, $this->context( array( 'is_404' => true ) ) ) );
	}

	/**
	 * Basic conditions read the WordPress conditionals.
	 *
	 * @return void
	 */
	public function test_basic_conditions() {
		$cases = array(
			'front_page' => array( 'is_front_page' => true ),
			'posts_page' => array( 'is_posts_page' => true ),
			'singular'   => array( 'is_singular' => true ),
			'archive'    => array( 'is_archive' => true ),
			'search'     => array( 'is_search' => true ),
			'404'        => array( 'is_404' => true ),
			'logged_in'  => array( 'is_user_logged_in' => true ),
		);

		foreach ( $cases as $type => $flags ) {
			$this->assertTrue(
				Condition_Matcher::rule_matches( array( 'type' => $type ), $this->context( $flags ) ),
				$type . ' should match'
			);
			$this->assertFalse(
				Condition_Matcher::rule_matches( array( 'type' => $type ), $this->context() ),
				$type . ' should not match an unrelated request'
			);
		}

		// logged_out is the inverse.
		$this->assertTrue( Condition_Matcher::rule_matches( array( 'type' => 'logged_out' ), $this->context() ) );
		$this->assertFalse(
			Condition_Matcher::rule_matches( array( 'type' => 'logged_out' ), $this->context( array( 'is_user_logged_in' => true ) ) )
		);
	}

	/**
	 * A singular post type condition needs the post type to match.
	 *
	 * @return void
	 */
	public function test_post_type_condition_needs_a_singular_view() {
		$rule = array( 'type' => 'post_type', 'value' => 'product' );

		$this->assertTrue(
			Condition_Matcher::rule_matches( $rule, $this->context( array( 'is_singular' => true, 'post_type' => 'product' ) ) )
		);
		$this->assertFalse(
			Condition_Matcher::rule_matches( $rule, $this->context( array( 'is_singular' => true, 'post_type' => 'post' ) ) )
		);
		$this->assertFalse(
			Condition_Matcher::rule_matches(
				$rule,
				$this->context( array( 'is_archive' => true, 'is_post_type_archive' => true, 'archive_post_type' => 'product' ) )
			),
			'An archive is not a singular view.'
		);
		$this->assertFalse( Condition_Matcher::rule_matches( array( 'type' => 'post_type' ), $this->context() ) );
	}

	/**
	 * A post type archive condition matches the archive only.
	 *
	 * @return void
	 */
	public function test_post_type_archive_condition() {
		$rule = array( 'type' => 'post_type_archive', 'value' => 'product' );

		$this->assertTrue(
			Condition_Matcher::rule_matches(
				$rule,
				$this->context( array( 'is_post_type_archive' => true, 'archive_post_type' => 'product' ) )
			)
		);
		$this->assertFalse(
			Condition_Matcher::rule_matches(
				$rule,
				$this->context( array( 'is_post_type_archive' => true, 'archive_post_type' => 'post' ) )
			)
		);
		$this->assertFalse(
			Condition_Matcher::rule_matches( $rule, $this->context( array( 'is_singular' => true, 'post_type' => 'product' ) ) )
		);
	}

	/**
	 * Specific post and page conditions compare IDs.
	 *
	 * @return void
	 */
	public function test_specific_post_and_page_conditions() {
		$rule = array( 'type' => 'post', 'value' => '12,48' );

		$this->assertTrue(
			Condition_Matcher::rule_matches( $rule, $this->context( array( 'is_singular' => true, 'post_id' => 48 ) ) )
		);
		$this->assertFalse(
			Condition_Matcher::rule_matches( $rule, $this->context( array( 'is_singular' => true, 'post_id' => 49 ) ) )
		);
		$this->assertFalse( Condition_Matcher::rule_matches( $rule, $this->context( array( 'is_singular' => true ) ) ) );

		$page_rule = array( 'type' => 'page', 'value' => '9' );

		$this->assertTrue(
			Condition_Matcher::rule_matches( $page_rule, $this->context( array( 'is_page' => true, 'post_id' => 9 ) ) )
		);
		$this->assertFalse(
			Condition_Matcher::rule_matches( $page_rule, $this->context( array( 'is_single' => true, 'post_id' => 9 ) ) ),
			'A single post is not a page.'
		);
	}

	/**
	 * A taxonomy archive condition may be narrowed to terms.
	 *
	 * @return void
	 */
	public function test_taxonomy_condition_with_and_without_terms() {
		$context = $this->context(
			array(
				'is_tax'    => true,
				'taxonomy'  => 'category',
				'term_ids'  => array( 5, 11 ),
			)
		);

		$this->assertTrue( Condition_Matcher::rule_matches( array( 'type' => 'taxonomy', 'value' => 'category' ), $context ) );
		$this->assertFalse( Condition_Matcher::rule_matches( array( 'type' => 'taxonomy', 'value' => 'post_tag' ), $context ) );
		$this->assertTrue(
			Condition_Matcher::rule_matches( array( 'type' => 'taxonomy', 'value' => 'category', 'terms' => '11' ), $context )
		);
		$this->assertFalse(
			Condition_Matcher::rule_matches( array( 'type' => 'taxonomy', 'value' => 'category', 'terms' => '99' ), $context )
		);
		$this->assertFalse(
			Condition_Matcher::rule_matches( array( 'type' => 'taxonomy', 'value' => 'category' ), $this->context( array( 'is_singular' => true ) ) )
		);
	}

	/**
	 * A term condition matches term archives and content filed under the term.
	 *
	 * @return void
	 */
	public function test_term_condition_matches_archives_and_content() {
		$rule = array( 'type' => 'term', 'value' => '5' );

		$this->assertTrue(
			Condition_Matcher::rule_matches( $rule, $this->context( array( 'is_tax' => true, 'term_ids' => array( 5 ) ) ) )
		);
		$this->assertTrue(
			Condition_Matcher::rule_matches( $rule, $this->context( array( 'is_singular' => true, 'post_term_ids' => array( 5, 6 ) ) ) )
		);
		$this->assertFalse(
			Condition_Matcher::rule_matches( $rule, $this->context( array( 'is_singular' => true, 'post_term_ids' => array( 8 ) ) ) )
		);
		$this->assertFalse( Condition_Matcher::rule_matches( array( 'type' => 'term' ), $this->context( array( 'is_tax' => true ) ) ) );
	}

	/**
	 * An author condition matches author archives and authored content.
	 *
	 * @return void
	 */
	public function test_author_condition() {
		$rule = array( 'type' => 'author', 'value' => '3' );

		$this->assertTrue( Condition_Matcher::rule_matches( $rule, $this->context( array( 'is_author' => true, 'author_id' => 3 ) ) ) );
		$this->assertTrue(
			Condition_Matcher::rule_matches( $rule, $this->context( array( 'is_singular' => true, 'post_author' => 3 ) ) )
		);
		$this->assertFalse(
			Condition_Matcher::rule_matches( $rule, $this->context( array( 'is_singular' => true, 'post_author' => 4 ) ) )
		);
	}

	/**
	 * A role condition only applies to logged in users holding the role.
	 *
	 * @return void
	 */
	public function test_role_condition() {
		$rule = array( 'type' => 'user_role', 'value' => 'editor,author' );

		$this->assertTrue(
			Condition_Matcher::rule_matches(
				$rule,
				$this->context( array( 'is_user_logged_in' => true, 'user_roles' => array( 'author' ) ) )
			)
		);
		$this->assertFalse(
			Condition_Matcher::rule_matches(
				$rule,
				$this->context( array( 'is_user_logged_in' => true, 'user_roles' => array( 'subscriber' ) ) )
			)
		);
		$this->assertFalse(
			Condition_Matcher::rule_matches( $rule, $this->context( array( 'user_roles' => array( 'editor' ) ) ) ),
			'A logged out visitor never matches a role condition.'
		);
	}

	/**
	 * Unknown rules never match.
	 *
	 * @return void
	 */
	public function test_unknown_rules_never_match() {
		$this->assertFalse( Condition_Matcher::rule_matches( array( 'type' => 'url_contains', 'value' => '/shop' ), $this->context() ) );
		$this->assertFalse( Condition_Matcher::rule_matches( array(), $this->context() ) );
	}

	/**
	 * Specificity is reported for the most specific matching rule.
	 *
	 * @return void
	 */
	public function test_specificity_of_a_matching_condition_set() {
		$context = $this->context(
			array(
				'is_front_page' => true,
				'is_singular'   => true,
				'is_page'       => true,
				'post_type'     => 'page',
				'post_id'       => 12,
			)
		);

		$conditions = array(
			'include' => array(
				array( 'type' => 'front_page' ),
				array( 'type' => 'page', 'value' => '12' ),
			),
		);

		$this->assertSame( 5, Condition_Matcher::specificity( $conditions, $context ) );
		$this->assertSame( 0, Condition_Matcher::specificity( array( 'include' => array() ), $context ) );
		$this->assertSame(
			0,
			Condition_Matcher::specificity( array( 'include' => array( array( 'type' => 'search' ) ) ), $context )
		);
	}

	/**
	 * Custom conditions can be implemented with the documented filter.
	 *
	 * @return void
	 */
	public function test_custom_condition_filter_can_take_over() {
		add_filter(
			'qevora_layouts_conditions',
			function ( $conditions ) {
				$conditions['weekend'] = array(
					'label'       => 'Weekends',
					'description' => 'Saturdays and Sundays.',
					'value'       => 'none',
					'specificity' => 2,
					'group'       => 'Custom',
				);

				return $conditions;
			}
		);

		add_filter(
			'qevora_layouts_condition_matches',
			function ( $matched, $type ) {
				return 'weekend' === $type ? true : $matched;
			},
			10,
			2
		);

		$this->assertTrue(
			Condition_Matcher::matches( array( 'include' => array( array( 'type' => 'weekend' ) ) ), $this->context() )
		);
	}

	/**
	 * Value lists tolerate arrays and junk.
	 *
	 * @return void
	 */
	public function test_list_helpers() {
		$this->assertSame( array( 1, 2, 3 ), Condition_Matcher::int_list( '1, 2,3' ) );
		$this->assertSame( array( 4 ), Condition_Matcher::int_list( array( '4', 'abc', -1, 0 ) ) );
		$this->assertSame( array(), Condition_Matcher::int_list( '' ) );
		$this->assertSame( array( 'a', 'b' ), Condition_Matcher::string_list( 'A, b' ) );
		$this->assertSame( array(), Condition_Matcher::string_list( null ) );
	}
}
