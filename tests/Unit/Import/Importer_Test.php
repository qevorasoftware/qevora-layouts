<?php
/**
 * Tests for the importer.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Tests\Unit\Import;

use QevoraLayouts\Core\Layout;
use QevoraLayouts\Core\Layout_Repository;
use QevoraLayouts\Core\Settings;
use QevoraLayouts\Import\Importer;
use QevoraLayouts\Import\Template;
use QevoraLayouts\Import\Template_Library;
use QevoraLayouts\Import\Template_Validator;
use QevoraLayouts\Tests\TestCase;
use Qevora_Test_Env;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Importing is the only way untrusted content enters the plugin, so every
 * step has to fail closed.
 *
 * @since 1.0.0
 */
class Importer_Test extends TestCase {

	/**
	 * Importer under test.
	 *
	 * @var Importer
	 */
	private $importer;

	/**
	 * Repository used by the importer.
	 *
	 * @var Layout_Repository
	 */
	private $repository;

	/**
	 * Builds the importer and its collaborators.
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		Settings::flush_cache();

		$this->repository = new Layout_Repository();
		$this->importer   = new Importer( $this->repository, new Template_Library() );
	}

	/**
	 * Returns a validated template.
	 *
	 * @param array<string,mixed> $overrides Payload overrides.
	 * @param array<string,mixed> $options   Validation options.
	 * @return Template
	 */
	private function template( array $overrides = array(), array $options = array() ) {
		$payload = array_merge(
			array(
				'$schema'    => Template::SCHEMA,
				'id'         => 'acme-header',
				'name'       => 'Acme header',
				'type'       => 'header',
				'builder'    => 'block',
				'content'    => '<!-- wp:paragraph --><p>Imported</p><!-- /wp:paragraph -->',
				'priority'   => 30,
				'placement'  => 'wp_body_open',
				'conditions' => array(
					'include' => array( array( 'type' => 'front_page' ) ),
					'exclude' => array(),
				),
			),
			$overrides
		);

		$result = Template_Validator::validate( $payload, $options );

		$this->assertTrue( $result['ok'], 'The fixture itself must validate: ' . implode( ' | ', $result['errors'] ) );

		return $result['template'];
	}

	/**
	 * Writes a temporary file and returns its path.
	 *
	 * @param string $contents File contents.
	 * @return string
	 */
	private function temp_file( $contents ) {
		$file = tempnam( sys_get_temp_dir(), 'qlt' );

		file_put_contents( $file, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.

		return $file;
	}

	/**
	 * Builds a `$_FILES` entry for a temporary file.
	 *
	 * @param string $contents File contents.
	 * @param string $name     File name.
	 * @return array<string,mixed>
	 */
	private function upload( $contents, $name = 'templates.json' ) {
		$file = $this->temp_file( $contents );

		return array(
			'name'     => $name,
			'type'     => 'application/json',
			'tmp_name' => $file,
			'error'    => UPLOAD_ERR_OK,
			'size'     => strlen( $contents ),
		);
	}

	/**
	 * Valid JSON payloads are decoded into arrays.
	 *
	 * @return void
	 */
	public function test_decode_json_accepts_valid_payloads() {
		$this->assertSame( array( 'id' => 'a' ), Importer::decode_json( '{"id":"a"}' ) );
		$this->assertCount( 2, Importer::decode_json( '{"templates":[{"id":"a"},{"id":"b"}]}' )['templates'] );
		$this->assertCount( 2, Importer::decode_json( '[{"id":"a"},{"id":"b"}]' ) );
	}

	/**
	 * Anything that is not a JSON object or list is refused.
	 *
	 * @return void
	 */
	public function test_decode_json_rejects_bad_payloads() {
		// A list rather than a map: PHP would turn the "5" key into an integer.
		$cases = array(
			array( '', 'qevora_layouts_empty_payload' ),
			array( '   ', 'qevora_layouts_empty_payload' ),
			array( 'not json', 'qevora_layouts_invalid_json' ),
			array( '{"id":', 'qevora_layouts_invalid_json' ),
			array( 'null', 'qevora_layouts_invalid_payload' ),
			array( '"a string"', 'qevora_layouts_invalid_payload' ),
			array( '5', 'qevora_layouts_invalid_payload' ),
			array( 'true', 'qevora_layouts_invalid_payload' ),
		);

		foreach ( $cases as $case ) {
			$result = Importer::decode_json( $case[0] );

			$this->assertIsError( $result );
			$this->assertSame( $case[1], $result->get_error_code(), 'Payload: ' . $case[0] );
		}

		$this->assertIsError( Importer::decode_json( array( 'not', 'a', 'string' ) ) );
	}

	/**
	 * PHP is rejected before the payload is even parsed.
	 *
	 * @return void
	 */
	public function test_decode_json_rejects_php() {
		$result = Importer::decode_json( '{"content":"<?php echo get_option( \'blogname\' ); ?>"}' );

		$this->assertIsError( $result );
		$this->assertSame( 'qevora_layouts_php_in_payload', $result->get_error_code() );
		$this->assertIsError( Importer::decode_json( '<?= "x" ?>' ) );
	}

	/**
	 * Deeply nested payloads are refused instead of exhausting the parser.
	 *
	 * @return void
	 */
	public function test_decode_json_limits_nesting() {
		$deep  = str_repeat( '{"a":', 40 ) . '1' . str_repeat( '}', 40 );
		$shallow = '{"a":' . str_repeat( '{"b":', 5 ) . '1' . str_repeat( '}', 5 ) . '}';

		$this->assertIsError( Importer::decode_json( $deep ) );
		$this->assertTrue( is_array( Importer::decode_json( $shallow ) ) );
	}

	/**
	 * The upload shape is checked before anything is read.
	 *
	 * @return void
	 */
	public function test_validate_upload_checks_the_shape() {
		$this->assertIsError( Importer::validate_upload( array() ) );
		$this->assertIsError( Importer::validate_upload( array( 'tmp_name' => '/tmp/x.json' ) ) );

		$file = $this->upload( '{}' );

		$this->assertTrue( Importer::validate_upload( $file ) );

		$failed              = $file;
		$failed['error']     = UPLOAD_ERR_INI_SIZE;
		$result              = Importer::validate_upload( $failed );

		$this->assertIsError( $result );
		$this->assertStringContainsString( 'larger than this server allows', $result->get_error_message() );

		$partial             = $file;
		$partial['error']    = UPLOAD_ERR_PARTIAL;
		$result              = Importer::validate_upload( $partial );

		$this->assertIsError( $result );
		$this->assertStringContainsString( 'partially uploaded', $result->get_error_message() );

		foreach ( array( 'shell.php', 'templates.txt', 'templates.json.php', 'no-extension', '' ) as $name ) {
			$renamed         = $file;
			$renamed['name'] = $name;
			$result          = Importer::validate_upload( $renamed );

			$this->assertIsError( $result, 'File name "' . $name . '" must be refused.' );
			$this->assertSame( 'qevora_layouts_invalid_file_type', $result->get_error_code() );
		}

		$unreadable            = $file;
		$unreadable['tmp_name'] = sys_get_temp_dir() . '/does-not-exist.json';
		$result                = Importer::validate_upload( $unreadable );

		$this->assertIsError( $result );
		$this->assertSame( 'qevora_layouts_unreadable_file', $result->get_error_code() );

		unlink( $file['tmp_name'] );
	}

	/**
	 * A file that did not come through an upload is refused.
	 *
	 * @return void
	 */
	public function test_read_uploaded_file_requires_a_real_upload() {
		$upload = $this->upload( wp_json_encode( array( 'templates' => array() ) ) );

		$result = $this->importer->read_uploaded_file( $upload );

		$this->assertIsError( $result );
		$this->assertSame( 'qevora_layouts_invalid_upload', $result->get_error_code() );

		unlink( $upload['tmp_name'] );
	}

	/**
	 * A verified upload is decoded and validated.
	 *
	 * @return void
	 */
	public function test_read_uploaded_file_returns_validated_templates() {
		$upload = $this->upload(
			wp_json_encode(
				array(
					'templates' => array(
						array(
							'$schema' => Template::SCHEMA,
							'id'      => 'acme-header',
							'name'    => 'Acme header',
							'type'    => 'header',
							'content' => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->',
						),
					),
				)
			)
		);

		$result = $this->importer->read_uploaded_file( $upload, array( 'skip_upload_check' => true ) );

		$this->assertTrue( is_array( $result ) );
		$this->assertTrue( $result['ok'] );
		$this->assertCount( 1, $result['templates'] );
		$this->assertSame( 'acme-header', $result['templates'][0]->id );
		$this->assertSame( 'upload', $result['templates'][0]->origin );

		unlink( $upload['tmp_name'] );
	}

	/**
	 * Oversized files are refused before they are read.
	 *
	 * @return void
	 */
	public function test_read_uploaded_file_caps_the_size() {
		Qevora_Test_Env::$options[ QEVORA_LAYOUTS_OPTION ] = array( 'max_import_size_kb' => 16 );
		Settings::flush_cache();

		$upload = $this->upload( str_repeat( 'a', 70000 ) );
		$result = $this->importer->read_uploaded_file( $upload, array( 'skip_upload_check' => true ) );

		$this->assertIsError( $result );
		$this->assertSame( 'qevora_layouts_file_too_large', $result->get_error_code() );

		unlink( $upload['tmp_name'] );
	}

	/**
	 * Importing needs the publish capability.
	 *
	 * @return void
	 */
	public function test_import_requires_capabilities() {
		Qevora_Test_Env::$caps['publish_qevora_layouts'] = false;

		$upload = $this->upload( '{}' );
		$result = $this->importer->read_uploaded_file( $upload, array( 'skip_upload_check' => true ) );

		$this->assertIsError( $result );
		$this->assertSame( 'qevora_layouts_forbidden', $result->get_error_code() );

		$single = $this->importer->import_template( $this->template() );

		$this->assertSame( 'failed', $single['action'] );
		$this->assertStringContainsString( 'not allowed to import', implode( ' ', $single['errors'] ) );

		$batch = $this->importer->import_templates( array( $this->template() ) );

		$this->assertCount( 0, $batch['imported'] );
		$this->assertCount( 1, $batch['failed'] );
		$this->assertSame( 0, $this->repository->count_layouts() );

		unlink( $upload['tmp_name'] );
	}

	/**
	 * A bad JSON string produces an error array, never an exception.
	 *
	 * @return void
	 */
	public function test_validate_string_reports_errors() {
		$result = $this->importer->validate_string( 'this is not json' );

		$this->assertFalse( $result['ok'] );
		$this->assertCount( 0, $result['templates'] );
		$this->assertStringContainsString( 'not valid JSON', implode( ' ', $result['errors'] ) );

		$result = $this->importer->validate_string( '<?php echo 1;' );

		$this->assertFalse( $result['ok'] );
		$this->assertStringContainsString( 'must not contain PHP code', implode( ' ', $result['errors'] ) );

		$valid = $this->importer->validate_string( wp_json_encode( $this->template()->to_array( true ) ) );

		$this->assertTrue( $valid['ok'] );
		$this->assertCount( 1, $valid['templates'] );
	}

	/**
	 * Importing creates a published layout with full provenance.
	 *
	 * @return void
	 */
	public function test_import_template_creates_a_layout() {
		$result = $this->importer->import_template( $this->template() );

		$this->assertSame( 'created', $result['action'] );
		$this->assertTrue( $result['id'] > 0 );
		$this->assertNotEmpty( $result['url'] );
		$this->assertSame( array(), $result['errors'] );

		$layout = Importer::layout_from_result( $result );

		$this->assertInstanceOf( Layout::class, $layout );
		$this->assertSame( 'Acme header', $layout->title );
		$this->assertSame( 'header', $layout->type );
		$this->assertSame( 30, $layout->priority );
		$this->assertSame( 'wp_body_open', $layout->placement );
		$this->assertTrue( $layout->is_enabled() );
		$this->assertSame( 'acme-header', $layout->template_id );
		$this->assertSame( 'upload', $layout->import_source );
		$this->assertNotEmpty( $layout->imported_at );
		$this->assertCount( 1, $layout->conditions['include'] );
		$this->assertStringContainsString( 'wp:paragraph', get_post( $layout->id )->post_content );
	}

	/**
	 * Importing the same template again changes nothing.
	 *
	 * @return void
	 */
	public function test_importing_twice_skips_by_default() {
		$first  = $this->importer->import_template( $this->template() );
		$second = $this->importer->import_template( $this->template() );

		$this->assertSame( 'created', $first['action'] );
		$this->assertSame( 'skipped', $second['action'] );
		$this->assertSame( array( $first['id'] ), $second['existing_ids'] );
		$this->assertNotEmpty( $second['existing_url'] );
		$this->assertStringContainsString( 'already exists', $second['reason'] );
		$this->assertSame( 1, $this->repository->count_layouts(), 'Nothing was created or changed.' );
	}

	/**
	 * The copy policy is honoured, and it says so.
	 *
	 * @return void
	 */
	public function test_the_copy_policy_creates_a_second_layout() {
		$this->importer->import_template( $this->template() );

		$copy = $this->importer->import_template( $this->template(), array( 'on_duplicate' => 'copy' ) );

		$this->assertSame( 'copied', $copy['action'] );
		$this->assertSame( 2, $this->repository->count_layouts() );
		$this->assertStringContainsString( 'a second copy was created', implode( ' ', $copy['warnings'] ) );

		Qevora_Test_Env::$options[ QEVORA_LAYOUTS_OPTION ] = array( 'duplicate_import_policy' => 'copy' );
		Settings::flush_cache();

		$third = $this->importer->import_template( $this->template() );

		$this->assertSame( 'copied', $third['action'], 'The configured policy is used when the caller stays silent.' );
		$this->assertSame( 3, $this->repository->count_layouts() );
	}

	/**
	 * An unknown duplicate policy falls back to the safe one.
	 *
	 * @return void
	 */
	public function test_an_unknown_policy_falls_back_to_skip() {
		$this->importer->import_template( $this->template() );

		$result = $this->importer->import_template( $this->template(), array( 'on_duplicate' => 'overwrite-everything' ) );

		$this->assertSame( 'skipped', $result['action'] );
		$this->assertSame( 1, $this->repository->count_layouts() );
	}

	/**
	 * Overwriting only happens when the user names the layout to replace.
	 *
	 * @return void
	 */
	public function test_explicit_replacement_updates_one_layout() {
		$first = $this->importer->import_template( $this->template() );

		$replaced = $this->importer->import_template(
			$this->template( array( 'name' => 'Acme header v2' ) ),
			array( 'replace_id' => $first['id'] )
		);

		$this->assertSame( 'updated', $replaced['action'] );
		$this->assertSame( $first['id'], $replaced['id'] );
		$this->assertSame( 1, $this->repository->count_layouts() );
		$this->assertSame( 'Acme header v2', get_post( $first['id'] )->post_title );

		Qevora_Test_Env::$caps['edit_post'] = false;

		$denied = $this->importer->import_template( $this->template(), array( 'replace_id' => $first['id'] ) );

		$this->assertSame( 'failed', $denied['action'] );
		$this->assertStringContainsString( 'not allowed to overwrite', implode( ' ', $denied['errors'] ) );
	}

	/**
	 * The requested status is sanitized like anywhere else.
	 *
	 * @return void
	 */
	public function test_the_import_status_is_sanitized() {
		$draft = $this->importer->import_template( $this->template(), array( 'status' => 'draft' ) );

		$this->assertSame( 'draft', get_post( $draft['id'] )->post_status );

		$odd = $this->importer->import_template(
			$this->template( array( 'id' => 'acme-footer', 'type' => 'footer' ) ),
			array( 'status' => 'trash' )
		);

		$this->assertSame( 'draft', get_post( $odd['id'] )->post_status );
	}

	/**
	 * A batch import reports every template separately.
	 *
	 * @return void
	 */
	public function test_import_templates_sorts_results() {
		$templates = array(
			$this->template(),
			$this->template(
				array(
					'id'   => 'acme-footer',
					'name' => 'Acme footer',
					'type' => 'footer',
				)
			),
			'not a template',
			new \stdClass(),
		);

		$result = $this->importer->import_templates( $templates );

		$this->assertCount( 2, $result['imported'] );
		$this->assertCount( 0, $result['skipped'] );
		$this->assertCount( 2, $result['failed'] );
		$this->assertStringContainsString( 'Invalid template object', implode( ' ', $result['failed'][0]['errors'] ) );
		$this->assertSame( 2, $this->repository->count_layouts() );

		$again = $this->importer->import_templates( $templates );

		$this->assertCount( 0, $again['imported'] );
		$this->assertCount( 2, $again['skipped'] );
		$this->assertCount( 2, $again['failed'] );
	}

	/**
	 * A display scope chosen at import time replaces the template's own
	 * conditions, so the demo shows where the user asked for it.
	 *
	 * @return void
	 */
	public function test_display_scope_applies_conditions_to_imported_layouts() {
		$result = $this->importer->import_templates(
			array( $this->template() ),
			array( 'display_scope' => 'front_page' )
		);

		$this->assertCount( 1, $result['imported'] );

		$layout = Importer::layout_from_result( $result['imported'][0] );

		$this->assertNotNull( $layout );
		$this->assertCount( 1, $layout->conditions['include'] );
		$this->assertSame( 'front_page', $layout->conditions['include'][0]['type'] );
		$this->assertSame( array(), $layout->conditions['exclude'] );
	}

	/**
	 * An explicit conditions set (REST API) wins over both the template and
	 * the scope, and is sanitized on the way in.
	 *
	 * @return void
	 */
	public function test_conditions_option_overrides_template_conditions() {
		$result = $this->importer->import_templates(
			array( $this->template() ),
			array(
				'conditions'    => array(
					'include' => array( 'search', 'not-a-condition' ),
					'exclude' => array( 'front_page' ),
				),
				'display_scope' => 'entire_site',
			)
		);

		$layout = Importer::layout_from_result( $result['imported'][0] );

		$this->assertNotNull( $layout );
		$this->assertCount( 1, $layout->conditions['include'] );
		$this->assertSame( 'search', $layout->conditions['include'][0]['type'] );
		$this->assertCount( 1, $layout->conditions['exclude'] );
		$this->assertSame( 'front_page', $layout->conditions['exclude'][0]['type'] );
	}

	/**
	 * The scope map is explicit: unknown scopes and `template` keep the
	 * conditions shipped with the template.
	 *
	 * @return void
	 */
	public function test_scope_conditions_map() {
		$this->assertSame( array(), Importer::scope_conditions( 'entire_site' )['include'] );
		$this->assertSame( array( 'singular' ), Importer::scope_conditions( 'singular' )['include'] );
		$this->assertNull( Importer::scope_conditions( 'template' ) );
		$this->assertNull( Importer::scope_conditions( 'not-a-scope' ) );
	}

	/**
	 * One-click demo packs resolve to bundled templates and reject unknown
	 * pack ids; the pack list is filterable.
	 *
	 * @return void
	 */
	public function test_demo_packs_resolve_and_are_filterable() {
		$templates = $this->importer->resolve_pack( 'header-footer' );

		$this->assertTrue( is_array( $templates ) );
		$this->assertCount( 2, $templates );
		$this->assertSame( 'qevora-header-simple', $templates[0]->id );
		$this->assertSame( 'qevora-footer-columns', $templates[1]->id );

		$this->assertIsError( $this->importer->resolve_pack( 'not-a-pack' ) );

		$add_solo = function ( $packs ) {
			$packs['solo'] = array(
				'label'     => 'Solo',
				'templates' => array( 'qevora-section-hero' ),
			);

			return $packs;
		};

		add_filter( 'qevora_layouts_demo_packs', $add_solo );
		$solo = $this->importer->resolve_pack( 'solo' );
		remove_filter( 'qevora_layouts_demo_packs', $add_solo );

		$this->assertTrue( is_array( $solo ) );
		$this->assertCount( 1, $solo );
	}

	/**
	 * A pack import creates every layout of the pack in one call.
	 *
	 * @return void
	 */
	public function test_pack_import_creates_every_layout() {
		$templates = $this->importer->resolve_pack( 'header-footer' );

		$result = $this->importer->import_templates(
			$templates,
			array(
				'origin'        => 'bundled',
				'display_scope' => 'entire_site',
			)
		);

		$this->assertCount( 2, $result['imported'] );
		$this->assertSame( 2, $this->repository->count_layouts() );
	}

	/**
	 * The hide-theme-part flag reaches the created layout through both the
	 * batch importer and single library imports, and stays off by default.
	 *
	 * @return void
	 */
	public function test_hide_theme_part_option_is_stored_on_the_layout() {
		$result = $this->importer->import_templates(
			array( $this->template() ),
			array( 'hide_theme_part' => true )
		);

		$layout = Importer::layout_from_result( $result['imported'][0] );

		$this->assertNotNull( $layout );
		$this->assertTrue( $layout->hide_theme_part );

		$single = $this->importer->import_from_library(
			'qevora-footer-minimal',
			array( 'hide_theme_part' => '1' )
		);

		$footer = Importer::layout_from_result( $single );

		$this->assertNotNull( $footer );
		$this->assertTrue( $footer->hide_theme_part, 'Checkbox strings count as consent.' );

		$plain = $this->importer->import_from_library( 'qevora-header-simple' );

		$header = Importer::layout_from_result( $plain );

		$this->assertNotNull( $header );
		$this->assertFalse( $header->hide_theme_part, 'Nothing is hidden without an explicit choice.' );
	}

	/**
	 * The import summary tells the user where the new layout will display,
	 * in plain words, straight after the import.
	 *
	 * @return void
	 */
	public function test_import_summary_states_where_the_layout_displays() {
		$result = $this->importer->import_templates(
			array( $this->template() ),
			array( 'display_scope' => 'front_page' )
		);

		$summary = \QevoraLayouts\Admin\Import_Handler::summarize( $result );

		$this->assertStringContainsString( 'Displays on:', implode( ' ', (array) $summary['details'] ) );
		$this->assertStringContainsString( 'Front page', implode( ' ', (array) $summary['details'] ) );
	}

	/**
	 * A batch import can pull its policy from the settings.
	 *
	 * @return void
	 */
	public function test_import_templates_uses_the_configured_policy() {
		Qevora_Test_Env::$options[ QEVORA_LAYOUTS_OPTION ] = array( 'duplicate_import_policy' => 'copy' );
		Settings::flush_cache();

		$this->importer->import_templates( array( $this->template() ) );
		$result = $this->importer->import_templates( array( $this->template() ) );

		$this->assertCount( 1, $result['imported'] );
		$this->assertSame( 'copied', $result['imported'][0]['action'] );
		$this->assertStringContainsString( 'a second copy was created', implode( ' ', $result['warnings'] ) );
	}

	/**
	 * Bundled starters can be imported straight from the library.
	 *
	 * @return void
	 */
	public function test_import_from_library_uses_bundled_templates() {
		$result = $this->importer->import_from_library( 'qevora-section-hero' );

		$this->assertTrue( is_array( $result ) );
		$this->assertSame( 'created', $result['action'] );

		$layout = Importer::layout_from_result( $result );

		$this->assertSame( 'section', $layout->type );
		$this->assertSame( 'qevora-section-hero', $layout->template_id );
		$this->assertSame( 'bundled', $layout->import_source );
		$this->assertSame( 'manual', $layout->placement, 'Sections are placed by hand.' );

		$again = $this->importer->import_from_library( 'qevora-section-hero' );

		$this->assertSame( 'skipped', $again['action'], 'A repeat import never overwrites.' );
	}

	/**
	 * A single library import (admin card, AJAX and REST all take this path)
	 * honours the quick display scope too, otherwise the demo silently keeps
	 * its bundled conditions and never shows where the user asked for it.
	 *
	 * @return void
	 */
	public function test_import_from_library_applies_display_scope() {
		$result = $this->importer->import_from_library(
			'qevora-footer-minimal',
			array( 'display_scope' => 'entire_site' )
		);

		$this->assertSame( 'created', $result['action'] );

		$layout = Importer::layout_from_result( $result );

		$this->assertSame( array(), $layout->conditions['include'], 'Entire site means no include conditions.' );
		$this->assertSame( array(), $layout->conditions['exclude'] );

		$result = $this->importer->import_from_library(
			'qevora-header-simple',
			array( 'display_scope' => 'front_page' )
		);

		$layout = Importer::layout_from_result( $result );

		$this->assertCount( 1, $layout->conditions['include'] );
		$this->assertSame( 'front_page', $layout->conditions['include'][0]['type'] );
	}

	/**
	 * Unknown or malformed identifiers are refused.
	 *
	 * @return void
	 */
	public function test_import_from_library_validates_the_identifier() {
		$missing = $this->importer->import_from_library( 'does-not-exist' );

		$this->assertIsError( $missing );
		$this->assertSame( 'qevora_layouts_template_not_found', $missing->get_error_code() );

		$this->assertIsError( $this->importer->import_from_library( '' ) );
		$this->assertIsError( $this->importer->import_from_library( array() ) );
		$this->assertIsError( $this->importer->import_from_library( '../../wp-config' ) );

		$without_library = new Importer( $this->repository );
		$result          = $without_library->import_from_library( 'qevora-section-hero' );

		$this->assertIsError( $result );
		$this->assertSame( 'qevora_layouts_no_library', $result->get_error_code() );
	}

	/**
	 * A remote summary entry is fetched lazily from the same host.
	 *
	 * @return void
	 */
	public function test_import_from_library_fetches_remote_content() {
		$index_url     = 'https://library.example.org/templates/index.json';
		$template_url  = 'https://library.example.org/templates/acme-remote-hero.json';
		$evil_url      = 'https://attacker.example.org/templates/acme-remote-hero.json';

		Qevora_Test_Env::$options[ QEVORA_LAYOUTS_OPTION ] = array(
			'remote_library_enabled' => true,
			'remote_library_url'     => $index_url,
		);
		Qevora_Test_Env::$http[ $index_url ] = array(
			'body'     => wp_json_encode(
				array(
					'templates' => array(
						array(
							'$schema' => Template::SCHEMA,
							'id'      => 'acme-remote-hero',
							'name'    => 'Remote hero',
							'type'    => 'section',
							'url'     => $template_url,
						),
						array(
							'$schema' => Template::SCHEMA,
							'id'      => 'acme-evil-hero',
							'name'    => 'Evil hero',
							'type'    => 'section',
							'url'     => $evil_url,
						),
					),
				)
			),
			'response' => array( 'code' => 200 ),
			'headers'  => array( 'content-type' => 'application/json' ),
		);
		Qevora_Test_Env::$http[ $template_url ] = array(
			'body'     => wp_json_encode(
				array(
					'$schema' => Template::SCHEMA,
					'id'      => 'acme-remote-hero',
					'name'    => 'Remote hero',
					'type'    => 'section',
					'builder' => 'block',
					'content' => '<!-- wp:paragraph --><p>Fetched</p><!-- /wp:paragraph -->',
				)
			),
			'response' => array( 'code' => 200 ),
			'headers'  => array( 'content-type' => 'application/json' ),
		);

		Settings::flush_cache();

		$importer = new Importer( $this->repository, new Template_Library() );

		$result = $importer->import_from_library( 'acme-remote-hero' );

		$this->assertTrue( is_array( $result ), 'A remote template with a download URL is fetched on import.' );
		$this->assertSame( 'created', $result['action'] );
		$this->assertSame( 'remote', Importer::layout_from_result( $result )->import_source );
		$this->assertStringContainsString( 'Fetched', get_post( $result['id'] )->post_content );

		$evil = $importer->import_from_library( 'acme-evil-hero' );

		$this->assertIsError( $evil );
		$this->assertSame( 'qevora_layouts_remote_host_mismatch', $evil->get_error_code() );

		$requested = array();

		foreach ( Qevora_Test_Env::$http_requests as $request ) {
			$requested[] = $request[0];
		}

		$this->assertNotContains( $evil_url, $requested, 'A different host is never requested.' );
	}

	/**
	 * A failed remote fetch produces an actionable message.
	 *
	 * @return void
	 */
	public function test_remote_fetch_failures_are_reported() {
		$index_url = 'https://library.example.org/templates/index.json';

		Qevora_Test_Env::$options[ QEVORA_LAYOUTS_OPTION ] = array(
			'remote_library_enabled' => true,
			'remote_library_url'     => $index_url,
		);
		Qevora_Test_Env::$http[ $index_url ] = new WP_Error( 'http_request_failed', 'cURL error 6: Could not resolve host.' );

		Settings::flush_cache();

		$importer = new Importer( $this->repository, new Template_Library() );
		$result   = $importer->import_from_library( 'acme-remote-hero' );

		$this->assertIsError( $result );
		$this->assertSame( 'qevora_layouts_template_not_found', $result->get_error_code() );
		$this->assertSame( 0, $this->repository->count_layouts() );
	}

	/**
	 * A result without a layout yields null instead of a fatal.
	 *
	 * @return void
	 */
	public function test_layout_from_result() {
		$this->assertNull( Importer::layout_from_result( array() ) );
		$this->assertNull( Importer::layout_from_result( array( 'id' => 0 ) ) );
		$this->assertNull( Importer::layout_from_result( array( 'id' => 999999 ) ) );

		$result = $this->importer->import_template( $this->template() );

		$this->assertInstanceOf( Layout::class, Importer::layout_from_result( $result ) );
	}
}
