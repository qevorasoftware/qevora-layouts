<?php
/**
 * Tests for the layout repository.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Tests\Unit\Core;

use QevoraLayouts\Conditions\Context;
use QevoraLayouts\Core\Cache;
use QevoraLayouts\Core\Layout;
use QevoraLayouts\Core\Layout_Post_Type;
use QevoraLayouts\Core\Layout_Repository;
use QevoraLayouts\Core\Settings;
use QevoraLayouts\Tests\TestCase;
use Qevora_Test_Env;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The repository is the only place that writes layout data, so it is also the
 * only place that has to sanitize it and check capabilities.
 *
 * @since 1.0.0
 */
class Layout_Repository_Test extends TestCase {

	/**
	 * Repository under test.
	 *
	 * @var Layout_Repository
	 */
	private $repository;

	/**
	 * Prepares a fresh repository and settings cache.
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		Settings::flush_cache();

		$this->repository = new Layout_Repository();
	}

	/**
	 * Saves a layout and returns its ID.
	 *
	 * @param array<string,mixed> $data Layout data.
	 * @return int
	 */
	private function create( array $data = array() ) {
		$result = $this->repository->save( $data );

		$this->assertTrue( is_int( $result ), 'save() must return a layout ID.' );

		return $result;
	}

	/**
	 * Reads one meta value.
	 *
	 * @param int    $id  Layout ID.
	 * @param string $key Meta key.
	 * @return mixed
	 */
	private function meta( $id, $key ) {
		return get_post_meta( $id, $key, true );
	}

	/**
	 * Saving stores sanitized values in prefixed meta keys.
	 *
	 * @return void
	 */
	public function test_save_stores_sanitized_meta() {
		$id = $this->create(
			array(
				'title'      => '  Store header  ',
				'content'    => '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->',
				'type'       => 'footer',
				'status'     => 'publish',
				'priority'   => 1500,
				'conditions' => array(
					'include' => array(
						array(
							'type'  => 'post_type',
							'value' => 'post',
						),
					),
					'exclude' => array(
						array( 'type' => 'logged_in' ),
					),
				),
				'placement'  => 'not-a-placement',
				'hook'       => 'init',
				'builder'    => 'some-builder',
			)
		);

		$post = get_post( $id );

		$this->assertSame( Layout_Post_Type::POST_TYPE, $post->post_type );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( 'Store header', $post->post_title );
		$this->assertStringContainsString( 'wp:paragraph', $post->post_content );

		$this->assertSame( 'footer', $this->meta( $id, Layout_Post_Type::META_TYPE ) );
		$this->assertSame( 999, (int) $this->meta( $id, Layout_Post_Type::META_PRIORITY ), 'Priority is clamped.' );
		$this->assertSame( '', $this->meta( $id, Layout_Post_Type::META_PLACEMENT ), 'An unknown placement is dropped.' );
		$this->assertSame( '', $this->meta( $id, Layout_Post_Type::META_HOOK ), 'A core hook name may never be used.' );
		$this->assertSame( 'auto', $this->meta( $id, Layout_Post_Type::META_BUILDER ), 'An unknown builder falls back to auto.' );
		$this->assertSame( 'self', $this->meta( $id, Layout_Post_Type::META_CONTENT_SOURCE ) );
		$this->assertSame( 0, (int) $this->meta( $id, Layout_Post_Type::META_REFERENCE_ID ) );

		$conditions = $this->meta( $id, Layout_Post_Type::META_CONDITIONS );

		$this->assertCount( 1, $conditions['include'] );
		$this->assertCount( 1, $conditions['exclude'] );
		$this->assertSame( 'post_type', $conditions['include'][0]['type'] );
		$this->assertSame( 'post', $conditions['include'][0]['value'] );
		$this->assertSame( 'logged_in', $conditions['exclude'][0]['type'] );
	}

	/**
	 * Missing input falls back to safe defaults.
	 *
	 * @return void
	 */
	public function test_save_applies_defaults() {
		$id = $this->create( array() );

		$layout = Layout::from_post( $id );

		$this->assertNotNull( $layout );
		$this->assertSame( 'header', $layout->type );
		$this->assertSame( 'publish', $layout->status );
		$this->assertTrue( $layout->is_enabled() );
		$this->assertSame( 10, $layout->priority );
		$this->assertSame( 'auto', $layout->builder );
		$this->assertSame( 'wp_body_open', $layout->get_effective_placement() );
		$this->assertSame( 'Untitled layout', $layout->title );
		$this->assertSame( '', $layout->template_id );
		$this->assertFalse( $layout->is_imported() );
	}

	/**
	 * Creating and editing both require capabilities.
	 *
	 * @return void
	 */
	public function test_save_checks_capabilities() {
		Qevora_Test_Env::$caps['publish_qevora_layouts'] = false;

		$result = $this->repository->save( array( 'title' => 'Nope' ) );

		$this->assertIsError( $result );
		$this->assertSame( 'qevora_layouts_forbidden', $result->get_error_code() );
		$this->assertSame( 0, $this->repository->count_layouts() );

		Qevora_Test_Env::$caps['publish_qevora_layouts'] = true;
		$id = $this->create( array( 'title' => 'Allowed' ) );

		Qevora_Test_Env::$caps['edit_post'] = false;

		$update = $this->repository->save(
			array(
				'id'    => $id,
				'title' => 'Renamed',
			)
		);

		$this->assertIsError( $update );
		$this->assertSame( 'qevora_layouts_forbidden', $update->get_error_code() );
		$this->assertSame( 'Allowed', get_post( $id )->post_title, 'The layout was not touched.' );
	}

	/**
	 * Import provenance is only written when the caller provides it.
	 *
	 * @return void
	 */
	public function test_save_records_import_provenance() {
		$plain = $this->create( array( 'title' => 'Hand made' ) );

		$this->assertSame( '', (string) $this->meta( $plain, Layout_Post_Type::META_TEMPLATE_ID ) );
		$this->assertSame( '', (string) $this->meta( $plain, Layout_Post_Type::META_IMPORT_SOURCE ) );
		$this->assertSame( '', (string) $this->meta( $plain, Layout_Post_Type::META_IMPORTED_AT ) );

		$imported = $this->create(
			array(
				'title'         => 'Starter',
				'template_id'   => 'qevora-header-simple',
				'import_source' => 'bundled',
			)
		);

		$layout = Layout::from_post( $imported );

		$this->assertSame( 'qevora-header-simple', $layout->template_id );
		$this->assertSame( 'bundled', $layout->import_source );
		$this->assertTrue( $layout->is_imported() );
		$this->assertNotEmpty( $layout->imported_at, 'The import date is recorded automatically.' );

		$bogus = $this->create(
			array(
				'title'         => 'Bogus',
				'template_id'   => '"><script>alert(1)</script>',
				'import_source' => 'carrier-pigeon',
			)
		);

		$this->assertSame( '', (string) $this->meta( $bogus, Layout_Post_Type::META_TEMPLATE_ID ) );
		$this->assertSame( '', (string) $this->meta( $bogus, Layout_Post_Type::META_IMPORT_SOURCE ) );
	}

	/**
	 * Saving announces itself so caches and integrations can react.
	 *
	 * @return void
	 */
	public function test_save_fires_an_action() {
		$seen = array();

		add_action(
			'qevora_layouts_layout_saved',
			function ( $layout_id, $data ) use ( &$seen ) {
				$seen = array( $layout_id, $data );
			},
			10,
			2
		);

		$id = $this->create( array( 'title' => 'Announced' ) );

		$this->assertSame( $id, $seen[0] );
		$this->assertSame( 'Announced', $seen[1]['title'] );
	}

	/**
	 * PHP tags never reach the database, whatever the user may do.
	 *
	 * @return void
	 */
	public function test_sanitize_content_drops_php_tags() {
		$repository = $this->repository;

		$this->assertStringNotContainsString( '<?php', $repository->sanitize_content( '<p>a</p><?php echo "b"; ?>c' ) );
		$this->assertStringNotContainsString( '?>', $repository->sanitize_content( '<?php echo 1; ?>' ) );
		$this->assertStringNotContainsString( '<?=', $repository->sanitize_content( '<?= esc_html( $x ) ?>' ) );
		$this->assertStringNotContainsString( '<?', $repository->sanitize_content( 'text <? echo 1;' ) );
		$this->assertSame( "<p>kept</p>\n", $repository->sanitize_content( "<p>kept</p>\r\n" ), 'Line endings are normalized.' );
		$this->assertSame( '', $repository->sanitize_content( array( 'not', 'a', 'string' ) ) );
	}

	/**
	 * Users without `unfiltered_html` get the same KSES pass WordPress applies.
	 *
	 * @return void
	 */
	public function test_sanitize_content_uses_kses_without_unfiltered_html() {
		Qevora_Test_Env::$caps['unfiltered_html'] = false;

		add_filter(
			'wp_kses_allowed_html',
			function () {
				return array(
					'p' => array(),
					'a' => array( 'href' => true ),
				);
			}
		);

		$clean = $this->repository->sanitize_content( '<p>ok</p><script>alert(1)</script><div onclick="x()">y</div>' );

		$this->assertStringContainsString( '<p>ok</p>', $clean );
		$this->assertStringNotContainsString( '<script>', $clean );
		$this->assertStringNotContainsString( 'onclick', $clean );
	}

	/**
	 * Content is capped at the configured import size.
	 *
	 * @return void
	 */
	public function test_sanitize_content_caps_the_byte_length() {
		Qevora_Test_Env::$options[ QEVORA_LAYOUTS_OPTION ] = array( 'max_import_size_kb' => 16 );
		Settings::flush_cache();

		$clean = $this->repository->sanitize_content( str_repeat( 'a', 40000 ) );

		$this->assertSame( 16384, strlen( $clean ) );
	}

	/**
	 * Only the four editable statuses are accepted.
	 *
	 * @return void
	 */
	public function test_sanitize_status() {
		$repository = $this->repository;

		$this->assertSame( 'publish', $repository->sanitize_status( 'publish' ) );
		$this->assertSame( 'draft', $repository->sanitize_status( 'draft' ) );
		$this->assertSame( 'pending', $repository->sanitize_status( 'pending' ) );
		$this->assertSame( 'private', $repository->sanitize_status( 'private' ) );
		$this->assertSame( 'draft', $repository->sanitize_status( 'trash' ) );
		$this->assertSame( 'draft', $repository->sanitize_status( 'future' ) );
		$this->assertSame( 'draft', $repository->sanitize_status( array() ) );
	}

	/**
	 * Enabling and disabling only changes the post status.
	 *
	 * @return void
	 */
	public function test_set_enabled_toggles_the_status() {
		$id = $this->create( array( 'title' => 'Toggle me' ) );

		$this->assertTrue( $this->repository->set_enabled( $id, false ) );
		$this->assertSame( 'draft', get_post( $id )->post_status );
		$this->assertFalse( Layout::from_post( $id )->is_enabled() );

		$this->assertTrue( $this->repository->set_enabled( $id, true ) );
		$this->assertSame( 'publish', get_post( $id )->post_status );

		$this->assertTrue( $this->repository->set_enabled( $id, true ), 'A no-op still succeeds.' );

		$missing = $this->repository->set_enabled( 999999, true );

		$this->assertIsError( $missing );
		$this->assertSame( 'qevora_layouts_not_found', $missing->get_error_code() );
	}

	/**
	 * A clone keeps its content and settings but loses its provenance.
	 *
	 * @return void
	 */
	public function test_duplicate_copies_the_layout_without_provenance() {
		$id = $this->create(
			array(
				'title'         => 'Original',
				'content'       => '<p>body</p>',
				'type'          => 'footer',
				'priority'      => 42,
				'template_id'   => 'qevora-footer-minimal',
				'import_source' => 'bundled',
			)
		);

		$copy = $this->repository->duplicate( $id );

		$this->assertTrue( is_int( $copy ) );
		$this->assertTrue( $copy > $id );

		$layout = Layout::from_post( $copy );

		$this->assertSame( 'Original (copy)', $layout->title );
		$this->assertSame( 'draft', $layout->status, 'A clone starts disabled so nothing renders twice.' );
		$this->assertSame( 'footer', $layout->type );
		$this->assertSame( 42, $layout->priority );
		$this->assertSame( '<p>body</p>', get_post( $copy )->post_content );
		$this->assertSame( '', $layout->template_id, 'Provenance belongs to the original.' );
		$this->assertSame( '', $layout->import_source );
		$this->assertSame( '', $layout->imported_at );

		$missing = $this->repository->duplicate( 999999 );

		$this->assertIsError( $missing );
	}

	/**
	 * Deleting trashes by default and needs a capability.
	 *
	 * @return void
	 */
	public function test_delete_trashes_and_checks_capabilities() {
		$id = $this->create( array( 'title' => 'Delete me' ) );

		$this->assertTrue( $this->repository->delete( $id ) );
		$this->assertSame( 'trash', get_post( $id )->post_status );

		$forced = $this->create( array( 'title' => 'Delete me too' ) );

		$this->assertTrue( $this->repository->delete( $forced, true ) );
		$this->assertNull( get_post( $forced ) );

		Qevora_Test_Env::$caps['delete_post'] = false;

		$kept = $this->create( array( 'title' => 'Protected' ) );
		$result = $this->repository->delete( $kept );

		$this->assertIsError( $result );
		$this->assertSame( 'qevora_layouts_forbidden', $result->get_error_code() );
		$this->assertSame( 'publish', get_post( $kept )->post_status );
	}

	/**
	 * Candidate lists hold published layouts of one type, best priority first.
	 *
	 * @return void
	 */
	public function test_candidate_data_is_filtered_and_ordered() {
		$low      = $this->create( array( 'title' => 'Low', 'type' => 'header', 'priority' => 5 ) );
		$high     = $this->create( array( 'title' => 'High', 'type' => 'header', 'priority' => 50 ) );
		$middle   = $this->create( array( 'title' => 'Middle', 'type' => 'header', 'priority' => 20 ) );
		$same     = $this->create( array( 'title' => 'Same priority', 'type' => 'header', 'priority' => 20 ) );
		$footer   = $this->create( array( 'title' => 'Footer', 'type' => 'footer' ) );
		$disabled = $this->create( array( 'title' => 'Draft', 'type' => 'header', 'status' => 'draft' ) );

		unset( $footer );

		$candidates = $this->repository->get_candidate_data( 'header' );
		$ids        = wp_list_pluck( $candidates, 'id' );

		$this->assertSame( array( $high, $middle, $same, $low ), $ids, 'Priority DESC, then ID ASC.' );
		$this->assertNotContains( $disabled, $ids, 'Drafts are never candidates.' );
		$this->assertArrayHasKey( 'conditions', $candidates[0] );
		$this->assertArrayHasKey( 'placement', $candidates[0] );
	}

	/**
	 * Saving invalidates the cached candidate list.
	 *
	 * @return void
	 */
	public function test_saving_invalidates_the_candidate_cache() {
		$this->create( array( 'title' => 'First', 'type' => 'header' ) );

		$this->assertCount( 1, $this->repository->get_candidate_data( 'header' ) );

		$this->create( array( 'title' => 'Second', 'type' => 'header', 'priority' => 90 ) );

		$candidates = $this->repository->get_candidate_data( 'header' );

		$this->assertCount( 2, $candidates );
		$this->assertSame( 'Second', $candidates[0]['title'], 'The new layout is visible straight away.' );
	}

	/**
	 * Candidate caching can be switched off.
	 *
	 * @return void
	 */
	public function test_candidate_caching_is_optional() {
		Qevora_Test_Env::$options[ QEVORA_LAYOUTS_OPTION ] = array( 'cache_candidates' => false );
		Settings::flush_cache();

		$id = $this->create( array( 'title' => 'Uncached', 'type' => 'header' ) );

		$this->assertCount( 1, $this->repository->get_candidate_data( 'header' ) );
		$this->assertFalse( isset( Qevora_Test_Env::$cache[ QEVORA_LAYOUTS_CACHE_GROUP ][ Cache::key( 'candidates_header' ) ] ) );

		Qevora_Test_Env::$options[ QEVORA_LAYOUTS_OPTION ] = array( 'cache_candidates' => true );
		Settings::flush_cache();

		$this->repository->get_candidate_data( 'header' );

		$this->assertTrue( isset( Qevora_Test_Env::$cache[ QEVORA_LAYOUTS_CACHE_GROUP ][ Cache::key( 'candidates_header' ) ] ) );

		unset( $id );
	}

	/**
	 * Matching is ordered by priority first, then by how specific a rule is.
	 *
	 * @return void
	 */
	public function test_find_matching_orders_by_priority_then_specificity() {
		$target = Qevora_Test_Env::add_post(
			array(
				'post_type'  => 'post',
				'post_title' => 'A story',
			)
		);

		$broad  = $this->create(
			array(
				'title'      => 'Whole site header',
				'type'       => 'header',
				'priority'   => 10,
				'conditions' => array( 'include' => array(), 'exclude' => array() ),
			)
		);
		$exact  = $this->create(
			array(
				'title'      => 'One post header',
				'type'       => 'header',
				'priority'   => 10,
				'conditions' => array(
					'include' => array(
						array(
							'type'  => 'post',
							'value' => (string) $target,
						),
					),
					'exclude' => array(),
				),
			)
		);
		$urgent = $this->create(
			array(
				'title'    => 'High priority header',
				'type'     => 'header',
				'priority' => 50,
			)
		);
		$excluded = $this->create(
			array(
				'title'      => 'Excluded here',
				'type'       => 'header',
				'priority'   => 99,
				'conditions' => array(
					'include' => array(),
					'exclude' => array( array( 'type' => 'singular' ) ),
				),
			)
		);

		$context = Context::from_array(
			array(
				'is_singular' => true,
				'is_single'   => true,
				'post_id'     => $target,
				'post_type'   => 'post',
			)
		);

		$matching = $this->repository->find_matching( 'header', $context );
		$ids      = array();

		foreach ( $matching as $layout ) {
			$ids[] = $layout->id;
		}

		$this->assertSame( array( $urgent, $exact, $broad ), $ids );
		$this->assertNotContains( $excluded, $ids, 'The excluded layout is not a match.' );

		$active = $this->repository->get_active_layout( 'header', $context );

		$this->assertNotNull( $active );
		$this->assertSame( $urgent, $active->id );
	}

	/**
	 * The per-type switches are respected before anything renders.
	 *
	 * @return void
	 */
	public function test_get_active_layout_honours_the_type_switch() {
		$context = Context::from_array( array( 'is_front_page' => true ) );

		$this->create( array( 'title' => 'Header', 'type' => 'header' ) );
		$this->create( array( 'title' => 'Footer', 'type' => 'footer' ) );

		$this->assertNotNull( $this->repository->get_active_layout( 'header', $context ) );
		$this->assertNotNull( $this->repository->get_active_layout( 'footer', $context ) );

		Qevora_Test_Env::$options[ QEVORA_LAYOUTS_OPTION ] = array(
			'header_enabled' => false,
			'footer_enabled' => false,
		);
		Settings::flush_cache();

		$this->assertNull( $this->repository->get_active_layout( 'header', $context ) );
		$this->assertNull( $this->repository->get_active_layout( 'footer', $context ) );
	}

	/**
	 * Imports can find the layout a template already created.
	 *
	 * @return void
	 */
	public function test_find_by_template_id() {
		$id = $this->create(
			array(
				'title'       => 'Starter',
				'template_id' => 'qevora-section-hero',
			)
		);

		$this->assertSame( array( $id ), $this->repository->find_by_template_id( 'qevora-section-hero' ) );
		$this->assertSame( array(), $this->repository->find_by_template_id( 'qevora-section-cta' ) );
		$this->assertSame( array(), $this->repository->find_by_template_id( '' ) );
		$this->assertSame( array(), $this->repository->find_by_template_id( array( 'nope' ) ) );
	}

	/**
	 * Counts include drafts but not trashed layouts.
	 *
	 * @return void
	 */
	public function test_count_layouts() {
		$this->create( array( 'title' => 'Header', 'type' => 'header' ) );
		$this->create( array( 'title' => 'Draft header', 'type' => 'header', 'status' => 'draft' ) );
		$this->create( array( 'title' => 'Footer', 'type' => 'footer' ) );

		$this->assertSame( 3, $this->repository->count_layouts() );
		$this->assertSame( 2, $this->repository->count_layouts( 'header' ) );
		$this->assertSame( 1, $this->repository->count_layouts( 'footer' ) );
		$this->assertSame( 0, $this->repository->count_layouts( 'section' ) );
	}

	/**
	 * A layout of another post type is never returned.
	 *
	 * @return void
	 */
	public function test_get_rejects_other_post_types() {
		$post_id = Qevora_Test_Env::add_post(
			array(
				'post_type'  => 'post',
				'post_title' => 'Not a layout',
			)
		);

		$this->assertNull( $this->repository->get( $post_id ) );
		$this->assertNull( $this->repository->get( 999999 ) );

		$layout_id = $this->create( array( 'title' => 'Real layout' ) );
		$layout    = $this->repository->get( $layout_id );

		$this->assertInstanceOf( Layout::class, $layout );
		$this->assertSame( 'Real layout', $layout->title );
	}

	/**
	 * A WP_Error from WordPress is passed through unchanged.
	 *
	 * @return void
	 */
	public function test_save_passes_through_wordpress_errors() {
		add_filter(
			'wp_insert_post_empty_content',
			function () {
				return true;
			}
		);

		add_filter(
			'wp_insert_post_data',
			function () {
				return new WP_Error( 'qevora_test_blocked', 'Blocked by a filter.' );
			}
		);

		$result = $this->repository->save( array( 'title' => 'Blocked' ) );

		$this->assertIsError( $result );
		$this->assertSame( 'qevora_test_blocked', $result->get_error_code() );
	}
}
