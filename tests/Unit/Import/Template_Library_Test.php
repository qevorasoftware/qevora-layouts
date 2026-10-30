<?php
/**
 * Tests for the template library.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Tests\Unit\Import;

use QevoraLayouts\Core\Settings;
use QevoraLayouts\Import\Template;
use QevoraLayouts\Import\Template_Library;
use QevoraLayouts\Tests\TestCase;
use Qevora_Test_Env;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The library is what an administrator sees before importing anything.
 *
 * @since 1.0.0
 */
class Template_Library_Test extends TestCase {

	/**
	 * Library under test.
	 *
	 * @var Template_Library
	 */
	private $library;

	/**
	 * Builds a fresh library.
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		Settings::flush_cache();

		$this->library = new Template_Library();
	}

	/**
	 * Switches the remote library on with a canned endpoint.
	 *
	 * @param string $url  Endpoint.
	 * @param string $body Response body.
	 * @return void
	 */
	private function enable_remote( $url, $body ) {
		Qevora_Test_Env::$options[ QEVORA_LAYOUTS_OPTION ] = array(
			'remote_library_enabled' => true,
			'remote_library_url'     => $url,
		);
		Qevora_Test_Env::$http[ $url ] = array(
			'body'     => $body,
			'response' => array( 'code' => 200 ),
			'headers'  => array( 'content-type' => 'application/json' ),
		);

		Settings::flush_cache();
	}

	/**
	 * Every bundled starter template is readable and valid.
	 *
	 * @return void
	 */
	public function test_bundled_templates_are_loaded() {
		$bundled = $this->library->bundled();

		$this->assertCount( 8, $bundled );
		$this->assertSame( array(), $this->library->get_problems(), 'The bundled library must not report problems.' );

		foreach ( $bundled as $id => $template ) {
			$this->assertInstanceOf( Template::class, $template );
			$this->assertSame( $id, $template->id, 'The library is keyed by template identifier.' );
			$this->assertSame( 'bundled', $template->origin );
			$this->assertNotEmpty( $template->name );
			$this->assertNotEmpty( $template->description );
			$this->assertContains( $template->type, array( 'header', 'footer', 'section' ) );
			$this->assertSame( 'block', $template->builder, 'Bundled starters never require a third-party builder.' );
			$this->assertNotEmpty( $template->content );
			$this->assertTrue( $template->is_available(), $id . ' should be importable on a supported site.' );
			$this->assertSame( array(), $template->get_missing_requirements(), $id . ' has unmet requirements.' );
			$this->assertSame( '6.4', $template->requires['wordpress'] );
			$this->assertSame( '7.4', $template->requires['php'] );
			$this->assertNotEmpty( $template->get_preview_url(), $id . ' is missing its preview image.' );
			$this->assertTrue( is_array( $template->conditions ) );
			$this->assertTrue( is_array( $template->conditions['include'] ) );
			$this->assertTrue( is_array( $template->conditions['exclude'] ) );
		}
	}

	/**
	 * The bundled starters cover headers, footers and sections.
	 *
	 * @return void
	 */
	public function test_bundled_library_covers_every_type() {
		$counts = $this->library->count_by_type();

		$this->assertSame( 3, $counts['header'] );
		$this->assertSame( 2, $counts['footer'] );
		$this->assertSame( 3, $counts['section'] );
	}

	/**
	 * Bundled templates are cached per instance but can be refreshed.
	 *
	 * @return void
	 */
	public function test_bundled_results_are_cached_and_clearable() {
		$first  = $this->library->bundled();
		$second = $this->library->bundled();

		$this->assertSame( $first, $second, 'A cached read returns the very same objects.' );

		$refreshed = $this->library->bundled( true );

		$this->assertSame( array_keys( $first ), array_keys( $refreshed ), 'A forced read returns the same templates.' );
		$this->assertNotSame( $first['qevora-header-simple'], $refreshed['qevora-header-simple'], 'A forced read rebuilds them.' );

		$this->library->clear_cache();

		$this->assertCount( 8, $this->library->bundled() );
	}

	/**
	 * A site can add its own starters without touching the plugin directory.
	 *
	 * @return void
	 */
	public function test_bundled_files_can_be_filtered() {
		$file = tempnam( sys_get_temp_dir(), 'qlt' );

		file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
			$file,
			wp_json_encode(
				array(
					'$schema' => Template::SCHEMA,
					'id'      => 'acme-custom-header',
					'name'    => 'Acme custom header',
					'type'    => 'header',
					'builder' => 'block',
					'content' => '<!-- wp:paragraph --><p>Custom</p><!-- /wp:paragraph -->',
				)
			)
		);

		add_filter(
			'qevora_layouts_bundled_template_files',
			function ( $files ) use ( $file ) {
				$files[] = $file;

				return $files;
			}
		);

		$bundled = $this->library->bundled( true );

		$this->assertCount( 9, $bundled );
		$this->assertSame( 'Acme custom header', $bundled['acme-custom-header']->name );

		unlink( $file );
	}

	/**
	 * A broken bundled file is reported instead of breaking the screen.
	 *
	 * @return void
	 */
	public function test_broken_files_are_reported() {
		$invalid = tempnam( sys_get_temp_dir(), 'qlt' );
		$php     = tempnam( sys_get_temp_dir(), 'qlt' );

		file_put_contents( $invalid, '{ "id": "broken" ' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		file_put_contents( $php, '<?php echo "never";' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.

		add_filter(
			'qevora_layouts_bundled_template_files',
			function ( $files ) use ( $invalid, $php ) {
				return array( $invalid, $php );
			}
		);

		$library   = new Template_Library();
		$bundled   = $library->bundled( true );
		$problems  = $library->get_problems();

		$this->assertCount( 0, $bundled );
		$this->assertNotEmpty( $problems );
		// Problems name the file, never the full server path.
		$this->assertStringContainsString( basename( $invalid ), implode( ' ', $problems ) );
		$this->assertStringContainsString( basename( $php ), implode( ' ', $problems ) );
		$this->assertStringNotContainsString( dirname( $php ), implode( ' ', $problems ), 'Server paths must not leak into admin messages.' );

		unlink( $invalid );
		unlink( $php );
	}

	/**
	 * Looking up a template works by identifier and origin.
	 *
	 * @return void
	 */
	public function test_find() {
		$found = $this->library->find( 'qevora-header-simple' );

		$this->assertInstanceOf( Template::class, $found );
		$this->assertSame( 'Simple Header', $found->name );

		$this->assertInstanceOf( Template::class, $this->library->find( 'qevora-header-simple', 'bundled' ) );
		$this->assertNull( $this->library->find( 'qevora-header-simple', 'remote' ), 'A bundled template is not a remote one.' );
		$this->assertNull( $this->library->find( 'does-not-exist' ) );
		$this->assertNull( $this->library->find( '' ) );
		$this->assertNull( $this->library->find( array( 'nope' ) ) );
	}

	/**
	 * Display rows never carry template content.
	 *
	 * @return void
	 */
	public function test_for_display_shapes_the_rows() {
		$rows = $this->library->for_display();

		$this->assertCount( 8, $rows );

		foreach ( $rows as $row ) {
			$this->assertArrayHasKey( 'id', $row );
			$this->assertArrayHasKey( 'name', $row );
			$this->assertArrayHasKey( 'type_label', $row );
			$this->assertArrayHasKey( 'preview_url', $row );
			$this->assertArrayHasKey( 'available', $row );
			$this->assertArrayHasKey( 'has_content', $row );
			$this->assertTrue( $row['has_content'] );
			$this->assertFalse( isset( $row['content'] ), 'Content is never sent to a list screen.' );
		}

		$this->assertCount( 3, $this->library->for_display( array( 'type' => 'header' ) ) );
		$this->assertCount( 2, $this->library->for_display( array( 'type' => 'footer' ) ) );
		$this->assertCount( 3, $this->library->for_display( array( 'type' => 'section' ) ) );
		$this->assertCount( 0, $this->library->for_display( array( 'type' => 'sidebar' ) ) );
		$this->assertCount( 1, $this->library->for_display( array( 'search' => 'faq' ) ) );
		$this->assertCount( 1, $this->library->for_display( array( 'search' => 'MINIMAL FOOTER' ) ), 'Search is case-insensitive.' );
		$this->assertCount( 0, $this->library->for_display( array( 'search' => 'nothing matches this' ) ) );
		$this->assertCount( 8, $this->library->for_display( array( 'origin' => 'bundled' ) ) );
		$this->assertCount( 0, $this->library->for_display( array( 'origin' => 'remote' ) ), 'Filtering by origin must not fall back to the other library.' );
	}

	/**
	 * Without a configured endpoint no remote request happens at all.
	 *
	 * @return void
	 */
	public function test_remote_is_off_by_default() {
		$this->assertSame( array(), $this->library->remote() );
		$this->assertCount( 8, $this->library->all() );
		$this->assertCount( 0, Qevora_Test_Env::$http_requests, 'No request may leave the site while the library is disabled.' );
	}

	/**
	 * A configured https endpoint is fetched and validated.
	 *
	 * @return void
	 */
	public function test_remote_templates_are_fetched_and_validated() {
		$url = 'https://library.example.org/templates/index.json';

		$this->enable_remote(
			$url,
			wp_json_encode(
				array(
					'templates' => array(
						array(
							'$schema' => Template::SCHEMA,
							'id'      => 'acme-remote-hero',
							'name'    => 'Remote hero',
							'type'    => 'section',
							'builder' => 'block',
							'content' => '<!-- wp:paragraph --><p>Remote</p><!-- /wp:paragraph -->',
						),
						array(
							'id'      => 'qevora-header-simple',
							'name'    => 'Shadowing attempt',
							'type'    => 'header',
							'builder' => 'block',
							'content' => '<!-- wp:paragraph --><p>Shadow</p><!-- /wp:paragraph -->',
						),
						array(
							'id'      => 'acme-broken',
							'name'    => 'Broken template',
							'type'    => 'header',
							'builder' => 'elementor',
							'content' => '<div>x</div>',
						),
					),
				)
			)
		);

		$library = new Template_Library();
		$all     = $library->all();

		$this->assertCount( 9, $all, 'The valid remote template is added.' );
		$this->assertSame( 'Remote hero', $all['acme-remote-hero']->name );
		$this->assertSame( 'remote', $all['acme-remote-hero']->origin );
		$this->assertSame( 'Simple Header', $all['qevora-header-simple']->name, 'A bundled template can never be shadowed.' );
		$this->assertFalse( isset( $all['acme-broken'] ), 'An Elementor template is not importable.' );

		$problems = implode( ' ', $library->get_problems() );

		$this->assertStringContainsString( 'qevora-header-simple', $problems );
		$this->assertCount( 1, Qevora_Test_Env::$http_requests );
		$this->assertSame( $url, Qevora_Test_Env::$http_requests[0][0] );
		$this->assertSame( 15, Qevora_Test_Env::$http_requests[0][1]['timeout'] );
		$this->assertSame( array(), Qevora_Test_Env::$http_requests[0][1]['cookies'], 'Cookies are never sent to a template endpoint.' );
	}

	/**
	 * An unreachable or malformed endpoint is reported, not fatal.
	 *
	 * @return void
	 */
	public function test_remote_failures_are_reported() {
		$this->enable_remote( 'https://library.example.org/templates/index.json', 'not json at all' );

		$library = new Template_Library();

		$this->assertSame( array(), $library->remote() );
		$this->assertCount( 8, $library->all(), 'The bundled library keeps working.' );
		$this->assertNotEmpty( $library->get_problems() );

		Qevora_Test_Env::$http = array();
		Qevora_Test_Env::$http_requests = array();

		$offline = new Template_Library();

		$this->assertSame( array(), $offline->remote() );
		$this->assertStringContainsString( 'could not be reached', implode( ' ', $offline->get_problems() ) );
	}
}
