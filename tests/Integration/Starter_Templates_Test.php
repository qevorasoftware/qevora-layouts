<?php
/**
 * End-to-end tests for the bundled starter templates.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Tests\Integration;

use QevoraLayouts\Conditions\Context;
use QevoraLayouts\Core\Layout_Repository;
use QevoraLayouts\Core\Settings;
use QevoraLayouts\Import\Importer;
use QevoraLayouts\Import\Template_Library;
use QevoraLayouts\Tests\TestCase;
use Qevora_Test_Env;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Walks the whole path a real site takes: read the bundled files, validate
 * them, create layouts, then let the condition engine decide what renders.
 *
 * @since 1.0.0
 */
class Starter_Templates_Test extends TestCase {

	/**
	 * Repository used by the importer.
	 *
	 * @var Layout_Repository
	 */
	private $repository;

	/**
	 * Importer under test.
	 *
	 * @var Importer
	 */
	private $importer;

	/**
	 * Library under test.
	 *
	 * @var Template_Library
	 */
	private $library;

	/**
	 * Builds the collaborators.
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		Settings::flush_cache();

		$this->repository = new Layout_Repository();
		$this->library    = new Template_Library();
		$this->importer   = new Importer( $this->repository, $this->library );
	}

	/**
	 * Imports every bundled starter template.
	 *
	 * @param array<string,mixed> $options Import options.
	 * @return array<string,array<string,mixed>> Results keyed by template ID.
	 */
	private function import_all( array $options = array() ) {
		$results = array();

		foreach ( array_keys( $this->library->bundled() ) as $id ) {
			$result = $this->importer->import_from_library( $id, $options );

			$this->assertTrue( is_array( $result ), 'Importing ' . $id . ' must not fail with an error object.' );

			$results[ $id ] = $result;
		}

		return $results;
	}

	/**
	 * Asserts that no result reported an error.
	 *
	 * @param array<string,array<string,mixed>> $results Import results.
	 * @return void
	 */
	private function assert_no_errors( array $results ) {
		foreach ( $results as $id => $result ) {
			$this->assertSame( array(), (array) $result['errors'], 'Importing ' . $id . ' reported an error.' );
		}
	}

	/**
	 * Every bundled starter template imports into a usable layout.
	 *
	 * @return void
	 */
	public function test_every_bundled_starter_imports() {
		$bundled = $this->library->bundled();
		$results = $this->import_all();

		$this->assertCount( 8, $results );
		$this->assert_no_errors( $results );

		foreach ( $results as $id => $result ) {
			$template = $bundled[ $id ];

			$this->assertSame( 'created', $result['action'], $id . ' should be created.' );

			$layout = Importer::layout_from_result( $result );

			$this->assertNotNull( $layout, $id . ' produced no layout.' );
			$this->assertSame( $template->name, $layout->title );
			$this->assertSame( $template->type, $layout->type );
			$this->assertSame( $template->priority, $layout->priority );
			$this->assertSame( $template->placement, $layout->placement );
			$this->assertSame( 'block', $layout->builder );
			$this->assertTrue( $layout->is_enabled(), $id . ' should be published after import.' );
			$this->assertSame( $id, $layout->template_id );
			$this->assertSame( 'bundled', $layout->import_source );
			$this->assertNotEmpty( $layout->imported_at );
			$this->assertCount( count( $template->conditions['include'] ), $layout->conditions['include'] );
			$this->assertCount( count( $template->conditions['exclude'] ), $layout->conditions['exclude'] );

			$stored = get_post( $layout->id )->post_content;

			$this->assertStringContainsString( 'wp:group', $stored, $id . ' lost its block markup.' );
			$this->assertStringNotContainsString( '<?php', $stored );
			$this->assertStringNotContainsString( '<script', $stored );
			$this->assertSame( strlen( $template->content ), strlen( $stored ), $id . ' was altered while saving.' );
		}

		$this->assertSame( 8, $this->repository->count_layouts() );
		$this->assertSame( 3, $this->repository->count_layouts( 'header' ) );
		$this->assertSame( 2, $this->repository->count_layouts( 'footer' ) );
		$this->assertSame( 3, $this->repository->count_layouts( 'section' ) );
	}

	/**
	 * Importing the whole library a second time changes nothing.
	 *
	 * @return void
	 */
	public function test_the_library_can_be_imported_repeatedly() {
		$this->import_all();

		$again = $this->import_all();

		$this->assert_no_errors( $again );

		foreach ( $again as $id => $result ) {
			$this->assertSame( 'skipped', $result['action'], $id . ' must not be imported twice.' );
			$this->assertNotEmpty( $result['existing_ids'], $id . ' should point at the layout that exists.' );
			$this->assertStringContainsString( 'already exists', $result['reason'] );
		}

		$this->assertSame( 8, $this->repository->count_layouts(), 'A repeat import created or changed nothing.' );
	}

	/**
	 * The copy policy is the only way a second set appears.
	 *
	 * @return void
	 */
	public function test_the_copy_policy_creates_a_second_set() {
		$this->import_all();
		$copies = $this->import_all( array( 'on_duplicate' => 'copy' ) );

		foreach ( $copies as $id => $result ) {
			$this->assertSame( 'copied', $result['action'], $id . ' should be copied.' );
		}

		$this->assertSame( 16, $this->repository->count_layouts() );
	}

	/**
	 * The condition engine picks the header and footer a visitor should see.
	 *
	 * @return void
	 */
	public function test_conditions_decide_what_renders() {
		$this->import_all();

		$page_id = Qevora_Test_Env::add_post(
			array(
				'post_type'  => 'page',
				'post_title' => 'Home',
			)
		);
		$post_id = Qevora_Test_Env::add_post(
			array(
				'post_type'  => 'post',
				'post_title' => 'A story',
			)
		);

		$front_page = Context::from_array(
			array(
				'is_front_page' => true,
				'is_singular'   => true,
				'is_page'       => true,
				'post_id'       => $page_id,
				'post_type'     => 'page',
			)
		);
		$single_post = Context::from_array(
			array(
				'is_singular' => true,
				'is_single'   => true,
				'post_id'     => $post_id,
				'post_type'   => 'post',
			)
		);
		$not_found = Context::from_array( array( 'is_404' => true ) );

		// Highest priority first: the announcement header wins on the front page.
		$header = $this->repository->get_active_layout( 'header', $front_page );

		$this->assertNotNull( $header );
		$this->assertSame( 'qevora-header-announcement', $header->template_id );
		$this->assertSame( 'wp_body_open', $header->get_effective_placement() );

		// On a single post that header excludes itself, the centered header only
		// wants the front page, so the simple header is the match.
		$header = $this->repository->get_active_layout( 'header', $single_post );

		$this->assertNotNull( $header );
		$this->assertSame( 'qevora-header-simple', $header->template_id );

		// A 404 is not a post, so the announcement header applies again.
		$header = $this->repository->get_active_layout( 'header', $not_found );

		$this->assertNotNull( $header );
		$this->assertSame( 'qevora-header-announcement', $header->template_id );

		// Footers: the minimal footer only wants singular views.
		$footer = $this->repository->get_active_layout( 'footer', $single_post );

		$this->assertNotNull( $footer );
		$this->assertSame( 'qevora-footer-minimal', $footer->template_id );
		$this->assertSame( 'get_footer', $footer->get_effective_placement() );

		$footer = $this->repository->get_active_layout( 'footer', $not_found );

		$this->assertNotNull( $footer );
		$this->assertSame( 'qevora-footer-columns', $footer->template_id );

		// Every matching layout, best first, is available to the renderer.
		$matching = $this->repository->find_matching( 'header', $front_page );

		$this->assertCount( 3, $matching );
		$this->assertSame( 'qevora-header-announcement', $matching[0]->template_id );
		$this->assertSame( 'qevora-header-centered', $matching[1]->template_id );
		$this->assertSame( 'qevora-header-simple', $matching[2]->template_id );
	}

	/**
	 * Switching a type off stops it rendering even when templates exist.
	 *
	 * @return void
	 */
	public function test_the_type_switches_win_over_imported_templates() {
		$this->import_all();

		$context = Context::from_array( array( 'is_front_page' => true ) );

		Qevora_Test_Env::$options[ QEVORA_LAYOUTS_OPTION ] = array( 'header_enabled' => false );
		Settings::flush_cache();

		$this->assertNull( $this->repository->get_active_layout( 'header', $context ) );
		$this->assertNotNull( $this->repository->get_active_layout( 'footer', $context ) );
	}

	/**
	 * Every starter ships a readable preview and no third-party requirement.
	 *
	 * @return void
	 */
	public function test_starters_are_self_contained() {
		foreach ( $this->library->bundled() as $id => $template ) {
			$this->assertSame( 'block', $template->builder, $id . ' must not need a third-party builder.' );
			$this->assertFalse( isset( $template->requires['plugins'] ), $id . ' must not require a plugin.' );
			$this->assertSame( '6.4', $template->requires['wordpress'], $id . ' states the wrong WordPress requirement.' );
			$this->assertSame( '7.4', $template->requires['php'], $id . ' states the wrong PHP requirement.' );
			$this->assertTrue( $template->is_available(), $id . ' should be importable on a supported site.' );
			$this->assertSame( array(), $template->get_missing_requirements(), $id . ' has unmet requirements.' );
			$this->assertSame( 'Qevora Software', $template->author );
			$this->assertSame( 'GPL-2.0-or-later', $template->license );
			$this->assertNotEmpty( $template->tags, $id . ' has no tags for the library filter.' );

			$path = QEVORA_LAYOUTS_DIR . $template->preview;

			$this->assertTrue( is_readable( $path ), $id . ' points at a preview that does not exist.' );
			$this->assertStringContainsString( $template->preview, $template->get_preview_url() );
			$this->assertStringContainsString( 'qevora-layouts/', $template->file );
		}
	}

	/**
	 * The library screen data is complete for every starter.
	 *
	 * @return void
	 */
	public function test_the_library_screen_data_is_complete() {
		$rows = $this->library->for_display();

		$this->assertCount( 8, $rows );

		foreach ( $rows as $row ) {
			$this->assertNotEmpty( $row['id'] );
			$this->assertNotEmpty( $row['name'] );
			$this->assertNotEmpty( $row['description'] );
			$this->assertNotEmpty( $row['type_label'] );
			$this->assertNotEmpty( $row['preview_url'] );
			$this->assertTrue( $row['available'] );
			$this->assertTrue( $row['has_content'] );
			$this->assertSame( array(), $row['missing'] );
			$this->assertFalse( isset( $row['content'] ) );
		}
	}
}
