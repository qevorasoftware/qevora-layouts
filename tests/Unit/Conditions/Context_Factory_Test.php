<?php
/**
 * Tests for the request context factory.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Tests\Unit\Conditions;

use QevoraLayouts\Conditions\Context;
use QevoraLayouts\Conditions\Context_Factory;
use QevoraLayouts\Tests\TestCase;
use Qevora_Test_Env;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The context is built from WordPress query and user APIs, so a condition can
 * never depend on the URL of a request.
 *
 * @since 1.0.0
 */
class Context_Factory_Test extends TestCase {

	/**
	 * Drops the cached context, which is a static property that outlives a test.
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		Context_Factory::reset();
	}

	/**
	 * Drops the cached context after a test as well.
	 *
	 * @return void
	 */
	public function tearDown() {
		Context_Factory::reset();

		parent::tearDown();
	}

	/**
	 * A singular request exposes the queried post.
	 *
	 * @return void
	 */
	public function test_singular_context_is_built_from_the_queried_post() {
		$post_id = Qevora_Test_Env::add_post(
			array(
				'post_title'  => 'About us',
				'post_type'   => 'page',
				'post_author' => 4,
			)
		);

		Qevora_Test_Env::$query['singular']           = true;
		Qevora_Test_Env::$query['page']                = true;
		Qevora_Test_Env::$query['queried_object']      = Qevora_Test_Env::$posts[ $post_id ];
		Qevora_Test_Env::$query['queried_object_id']   = $post_id;

		$context = Context_Factory::build();

		$this->assertTrue( $context->is_singular );
		$this->assertTrue( $context->is_page );
		$this->assertSame( $post_id, $context->post_id );
		$this->assertSame( 'page', $context->post_type );
		$this->assertSame( 4, $context->post_author );
		$this->assertSame( 'frontend', $context->request_kind );
	}

	/**
	 * The front page and the posts page are told apart.
	 *
	 * @return void
	 */
	public function test_front_page_and_posts_page() {
		Qevora_Test_Env::$query['front_page'] = true;
		$context = Context_Factory::build();

		$this->assertTrue( $context->is_front_page );
		$this->assertFalse( $context->is_posts_page );

		Qevora_Test_Env::$query['front_page'] = false;
		Qevora_Test_Env::$query['home']       = true;
		$context = Context_Factory::build();

		$this->assertFalse( $context->is_front_page );
		$this->assertTrue( $context->is_posts_page );
	}

	/**
	 * A taxonomy archive exposes the term and its descendants.
	 *
	 * @return void
	 */
	public function test_taxonomy_archive_context_includes_descendant_terms() {
		$parent = new \WP_Term( array( 'term_id' => 5, 'name' => 'News', 'taxonomy' => 'category', 'parent' => 0 ) );
		$child  = new \WP_Term( array( 'term_id' => 6, 'name' => 'Local', 'taxonomy' => 'category', 'parent' => 5 ) );

		Qevora_Test_Env::$terms               = array( $parent, $child );
		Qevora_Test_Env::$query['archive']    = true;
		Qevora_Test_Env::$query['tax']        = true;
		Qevora_Test_Env::$query['category']   = true;
		Qevora_Test_Env::$query['queried_object'] = $parent;

		$context = Context_Factory::build();

		$this->assertTrue( $context->is_tax );
		$this->assertSame( 'category', $context->taxonomy );
		$this->assertContains( 5, $context->term_ids );
		$this->assertContains( 6, $context->term_ids, 'Child terms belong to a parent term archive.' );
	}

	/**
	 * Singular content inherits the ancestors of its terms.
	 *
	 * @return void
	 */
	public function test_singular_content_inherits_ancestor_terms() {
		$parent = new \WP_Term( array( 'term_id' => 5, 'name' => 'News', 'taxonomy' => 'category', 'parent' => 0 ) );
		$child  = new \WP_Term( array( 'term_id' => 6, 'name' => 'Local', 'taxonomy' => 'category', 'parent' => 5 ) );

		$post_id = Qevora_Test_Env::add_post( array( 'post_title' => 'A story', 'post_type' => 'post' ) );

		Qevora_Test_Env::$terms            = array( $parent, $child );
		Qevora_Test_Env::$query['singular'] = true;
		Qevora_Test_Env::$query['single']   = true;
		Qevora_Test_Env::$query['post_terms'] = array( $post_id => array( 6 ) );
		Qevora_Test_Env::$query['queried_object'] = Qevora_Test_Env::$posts[ $post_id ];

		$context = Context_Factory::build();

		$this->assertTrue( $context->is_singular );
		$this->assertContains( 6, $context->post_term_ids );
		$this->assertContains( 5, $context->post_term_ids, 'The parent term must match content in a child term.' );
	}

	/**
	 * A post type archive exposes the archived post type.
	 *
	 * @return void
	 */
	public function test_post_type_archive_context() {
		Qevora_Test_Env::$query['archive']            = true;
		Qevora_Test_Env::$query['post_type_archive']  = true;
		Qevora_Test_Env::$query['query_vars']         = array( 'post_type' => 'product' );

		$context = Context_Factory::build();

		$this->assertTrue( $context->is_archive );
		$this->assertTrue( $context->is_post_type_archive );
		$this->assertSame( 'product', $context->archive_post_type );
	}

	/**
	 * Search, 404 and author archives are reported.
	 *
	 * @return void
	 */
	public function test_search_404_and_author_contexts() {
		Qevora_Test_Env::$query['search'] = true;
		$this->assertTrue( Context_Factory::build()->is_search );

		Qevora_Test_Env::$query['search'] = false;
		Qevora_Test_Env::$query['is_404'] = true;
		$this->assertTrue( Context_Factory::build()->is_404 );

		Qevora_Test_Env::$query['is_404']         = false;
		Qevora_Test_Env::$query['author']         = true;
		Qevora_Test_Env::$query['queried_object'] = new \WP_User( array( 'ID' => 3, 'display_name' => 'Ana' ) );

		$context = Context_Factory::build();

		$this->assertTrue( $context->is_author );
		$this->assertSame( 3, $context->author_id );
	}

	/**
	 * The logged in state and the roles of the visitor are exposed.
	 *
	 * @return void
	 */
	public function test_visitor_state() {
		$context = Context_Factory::build();

		$this->assertFalse( $context->is_user_logged_in );
		$this->assertSame( array(), $context->user_roles );

		Qevora_Test_Env::$query['logged_in'] = true;
		Qevora_Test_Env::$users              = array(
			new \WP_User( array( 'ID' => 1, 'display_name' => 'Ada', 'roles' => array( 'editor' ) ) ),
		);

		$context = Context_Factory::build();

		$this->assertTrue( $context->is_user_logged_in );
		$this->assertSame( array( 'editor' ), $context->user_roles );
	}

	/**
	 * An admin request is marked, so layouts can stay out of wp-admin.
	 *
	 * @return void
	 */
	public function test_admin_request_kind() {
		Qevora_Test_Env::$query['admin'] = true;

		$context = Context_Factory::build();

		$this->assertTrue( $context->is_admin );
		$this->assertSame( 'admin', $context->request_kind );
	}

	/**
	 * The current context is resolved once per request and can be replaced.
	 *
	 * @return void
	 */
	public function test_current_context_is_cached_and_replaceable() {
		Qevora_Test_Env::$query['front_page'] = true;

		$first  = Context_Factory::current();
		$second = Context_Factory::current();

		$this->assertSame( $first, $second );
		$this->assertTrue( $first->is_front_page );

		$replacement = Context::from_array( array( 'is_search' => true ) );

		Context_Factory::set_current( $replacement );

		$this->assertSame( $replacement, Context_Factory::current() );

		Context_Factory::reset();
		$this->assertTrue( Context_Factory::current()->is_front_page, 'After a reset the context is rebuilt.' );
	}

	/**
	 * A context survives a round trip through an array (the cache stores it).
	 *
	 * @return void
	 */
	public function test_context_array_round_trip() {
		$original = Context::from_array(
			array(
				'is_singular'   => true,
				'post_id'       => 12,
				'post_type'     => 'page',
				'term_ids'      => array( 3, 4 ),
				'is_user_logged_in' => true,
				'user_roles'    => array( 'editor' ),
			)
		);

		$copy = Context::from_array( $original->to_array() );

		$this->assertSame( $original->to_array(), $copy->to_array() );
		$this->assertNotEmpty( $original->describe() );
	}

	/**
	 * The context can be filtered by other plugins.
	 *
	 * @return void
	 */
	public function test_context_filter() {
		add_filter(
			'qevora_layouts_context',
			function ( $context ) {
				$context->is_search = true;

				return $context;
			}
		);

		Context_Factory::reset();

		$context = Context_Factory::current();

		$this->assertTrue( $context->is_search, 'The qevora_layouts_context filter is applied by Context_Factory::current().' );
	}
}
