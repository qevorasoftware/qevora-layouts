<?php
/**
 * Tests for the template validator.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Tests\Unit\Import;

use QevoraLayouts\Core\Settings;
use QevoraLayouts\Import\Template;
use QevoraLayouts\Import\Template_Validator;
use QevoraLayouts\Tests\TestCase;
use Qevora_Test_Env;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Everything an import brings in is untrusted, so it has to pass this gate.
 *
 * @since 1.0.0
 */
class Template_Validator_Test extends TestCase {

	/**
	 * Prepares a clean settings cache.
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		Settings::flush_cache();
	}

	/**
	 * Returns a minimal, valid template payload.
	 *
	 * @param array<string,mixed> $overrides Values merged over the payload.
	 * @return array<string,mixed>
	 */
	private function payload( array $overrides = array() ) {
		return array_merge(
			array(
				'$schema'   => Template::SCHEMA,
				'id'        => 'acme-header',
				'name'      => 'Acme header',
				'type'      => 'header',
				'builder'   => 'block',
				'content'   => '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->',
				'priority'  => 10,
				'placement' => 'wp_body_open',
			),
			$overrides
		);
	}

	/**
	 * Asserts that a payload was accepted.
	 *
	 * @param array<string,mixed> $data    Payload.
	 * @param array<string,mixed> $options Validation options.
	 * @return Template
	 */
	private function assert_accepts( array $data, array $options = array() ) {
		$result = Template_Validator::validate( $data, $options );

		if ( ! $result['ok'] ) {
			$this->fail( 'Expected the template to validate, got: ' . implode( ' | ', $result['errors'] ) );
		}

		$this->assertInstanceOf( Template::class, $result['template'] );

		return $result['template'];
	}

	/**
	 * Asserts that a payload was rejected.
	 *
	 * @param array<string,mixed> $data    Payload.
	 * @param array<string,mixed> $options Validation options.
	 * @return string[] Errors.
	 */
	private function assert_rejects( array $data, array $options = array() ) {
		$result = Template_Validator::validate( $data, $options );

		$this->assertFalse( $result['ok'] );
		$this->assertNull( $result['template'] );
		$this->assertNotEmpty( $result['errors'] );

		return $result['errors'];
	}

	/**
	 * A well-formed payload produces a usable template.
	 *
	 * @return void
	 */
	public function test_a_valid_payload_is_accepted() {
		$template = $this->assert_accepts( $this->payload() );

		$this->assertSame( 'acme-header', $template->id );
		$this->assertSame( 'Acme header', $template->name );
		$this->assertSame( 'header', $template->type );
		$this->assertSame( 'block', $template->builder );
		$this->assertSame( 10, $template->priority );
		$this->assertSame( 'wp_body_open', $template->placement );
		$this->assertStringContainsString( 'wp:paragraph', $template->content );
		$this->assertSame( 'upload', $template->origin, 'The default origin is the least trusted one.' );
		$this->assertSame( '1.0.0', $template->version );
	}

	/**
	 * The origin is normalized and never taken from the file.
	 *
	 * @return void
	 */
	public function test_origin_comes_from_the_options() {
		$this->assertSame( 'bundled', $this->assert_accepts( $this->payload(), array( 'origin' => 'bundled' ) )->origin );
		$this->assertSame( 'remote', $this->assert_accepts( $this->payload(), array( 'origin' => 'remote' ) )->origin );
		$this->assertSame( 'upload', $this->assert_accepts( $this->payload(), array( 'origin' => 'carrier-pigeon' ) )->origin );
	}

	/**
	 * Non-array payloads are rejected outright.
	 *
	 * @return void
	 */
	public function test_a_non_array_payload_is_rejected() {
		foreach ( array( 'text', 42, null, true ) as $candidate ) {
			$result = Template_Validator::validate( $candidate );

			$this->assertFalse( $result['ok'] );
			$this->assertNull( $result['template'] );
			$this->assertNotEmpty( $result['errors'] );
		}
	}

	/**
	 * A missing or unknown schema is handled explicitly.
	 *
	 * @return void
	 */
	public function test_schema_support() {
		$this->assertTrue( Template_Validator::schema_is_supported( Template::SCHEMA ) );
		$this->assertFalse( Template_Validator::schema_is_supported( 'qevora-layouts/template/v2' ) );
		$this->assertFalse( Template_Validator::schema_is_supported( array() ) );

		$errors = $this->assert_rejects( $this->payload( array( '$schema' => 'someone-elses/template/v9' ) ) );

		$this->assertStringContainsString( 'Unsupported template schema', implode( ' ', $errors ) );
	}

	/**
	 * A third party may accept its own schema through the filter.
	 *
	 * @return void
	 */
	public function test_schema_support_is_filterable() {
		add_filter(
			'qevora_layouts_template_schemas',
			function ( $schemas ) {
				$schemas[] = 'acme/template/v1';

				return $schemas;
			}
		);

		$this->assertTrue( Template_Validator::schema_is_supported( 'acme/template/v1' ) );
		$this->assert_accepts( $this->payload( array( '$schema' => 'acme/template/v1' ) ) );
	}

	/**
	 * Name and type are mandatory and normalized.
	 *
	 * @return void
	 */
	public function test_name_and_type_are_required() {
		$errors = $this->assert_rejects( $this->payload( array( 'name' => '   ' ) ) );

		$this->assertStringContainsString( 'missing a name', implode( ' ', $errors ) );

		$errors = $this->assert_rejects( $this->payload( array( 'type' => 'sidebar' ) ) );

		$this->assertStringContainsString( 'Unknown layout type', implode( ' ', $errors ) );
		$this->assertStringContainsString( 'header, footer, section', implode( ' ', $errors ) );

		$long = $this->assert_accepts( $this->payload( array( 'name' => str_repeat( 'n', 300 ) ) ) );

		$this->assertSame( 120, strlen( $long->name ), 'Long names are trimmed to a usable length.' );
	}

	/**
	 * An identifier is required for remote templates, generated otherwise.
	 *
	 * @return void
	 */
	public function test_identifiers() {
		$result = Template_Validator::validate( $this->payload( array( 'id' => '' ) ) );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'acme-header', $result['template']->id, 'An identifier is derived from the name.' );
		$this->assertStringContainsString( 'no identifier', implode( ' ', $result['warnings'] ) );

		$errors = $this->assert_rejects( $this->payload( array( 'id' => '' ) ), array( 'require_id' => true ) );

		$this->assertStringContainsString( 'missing an identifier', implode( ' ', $errors ) );

		$dirty = $this->assert_accepts( $this->payload( array( 'id' => '"><script>alert(1)</script>' ) ) );

		$this->assertStringNotContainsString( '<', $dirty->id );
		$this->assertStringNotContainsString( '"', $dirty->id );
	}

	/**
	 * Identifier generation is slug-safe.
	 *
	 * @return void
	 */
	public function test_generate_id() {
		$this->assertSame( 'acme-header', Template_Validator::generate_id( 'Acme Header' ) );
		$this->assertSame( 'a-b-c', Template_Validator::generate_id( '  A -- B // C  ' ) );
		$this->assertSame( 'cafe', Template_Validator::generate_id( 'Café' ) );
		$this->assertSame( '', Template_Validator::generate_id( '!!!' ) );
		$this->assertSame( 100, strlen( Template_Validator::generate_id( str_repeat( 'x', 400 ) ) ) );
	}

	/**
	 * Elementor content cannot be imported, and the message says what to do.
	 *
	 * @return void
	 */
	public function test_elementor_templates_are_rejected_with_guidance() {
		$errors = $this->assert_rejects( $this->payload( array( 'builder' => 'elementor' ) ) );
		$message = implode( ' ', $errors );

		$this->assertStringContainsString( 'Elementor', $message );
		$this->assertStringContainsString( 'Saved Templates', $message, 'The error explains the supported workaround.' );
		$this->assertStringContainsString( 'Reference existing content', $message );
	}

	/**
	 * An unknown builder falls back to the block editor.
	 *
	 * @return void
	 */
	public function test_unknown_builder_falls_back() {
		$this->assertSame( 'auto', $this->assert_accepts( $this->payload( array( 'builder' => 'beaver' ) ) )->builder );
	}

	/**
	 * Empty content is rejected unless the entry is a remote pointer.
	 *
	 * @return void
	 */
	public function test_content_is_required() {
		$errors = $this->assert_rejects( $this->payload( array( 'content' => '   ' ) ) );

		$this->assertStringContainsString( 'content is empty', implode( ' ', $errors ) );

		$errors = $this->assert_rejects( $this->payload( array( 'content' => array( 'not', 'a', 'string' ) ) ) );

		$this->assertStringContainsString( 'must be a string', implode( ' ', $errors ) );

		$pointer = $this->assert_accepts(
			$this->payload(
				array(
					'content' => '',
					'url'     => 'https://library.example.org/templates/acme-header.json',
				)
			),
			array( 'origin' => 'remote' )
		);

		$this->assertSame( '', $pointer->content );
		$this->assertSame( 'https://library.example.org/templates/acme-header.json', $pointer->url );
	}

	/**
	 * Code that could execute or load remote content never gets in.
	 *
	 * @return void
	 */
	public function test_forbidden_content_is_rejected() {
		$cases = array(
			'php_open_tag'  => '<!-- wp:html --><div><?php echo get_option( "blogname" ); ?></div><!-- /wp:html -->',
			'php_short_tag' => '<?= $secret ?>',
			'script_tag'    => '<div>ok</div><script>alert(1)</script>',
			'script_url'    => '<a href="javascript:alert(1)">click</a>',
			'data_html_url' => '<a href="data:text/html;base64,PHNjcmlwdD4=">click</a>',
			'iframe_tag'    => '<iframe src="https://evil.example.org"></iframe>',
			'object_tag'    => '<object data="evil.swf"></object>',
			'embed_tag'     => '<embed src="evil.swf">',
			'event_handler' => '<div onclick="steal()">click</div>',
			'onerror'       => '<img src="x" onerror="steal()">',
		);

		foreach ( $cases as $label => $content ) {
			$errors = $this->assert_rejects( $this->payload( array( 'content' => $content ) ) );

			$this->assertStringContainsString( 'not allowed in layouts', implode( ' ', $errors ), $label . ' must be rejected.' );
		}
	}

	/**
	 * The pattern list names what it found.
	 *
	 * @return void
	 */
	public function test_find_forbidden_patterns_names_every_hit() {
		$found = Template_Validator::find_forbidden_patterns( '<script>x</script><a href="javascript:x">y</a>' );

		$this->assertContains( 'script elements', $found );
		$this->assertContains( 'javascript: URLs', $found );
		$this->assertSame( array(), Template_Validator::find_forbidden_patterns( '<p>perfectly fine</p>' ) );
		$this->assertSame( array(), Template_Validator::find_forbidden_patterns( '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->' ) );
	}

	/**
	 * A site may extend the block list.
	 *
	 * @return void
	 */
	public function test_forbidden_patterns_are_filterable() {
		add_filter(
			'qevora_layouts_forbidden_content_patterns',
			function ( $patterns ) {
				$patterns['acme_word'] = '/acme-secret/i';

				return $patterns;
			}
		);

		// A pattern without a label is reported under its own name.
		$this->assertContains( 'acme_word', Template_Validator::find_forbidden_patterns( 'the acme-secret recipe' ) );
		$this->assert_rejects( $this->payload( array( 'content' => '<p>the acme-secret recipe</p>' ) ) );
	}

	/**
	 * Oversized content is refused, not truncated.
	 *
	 * @return void
	 */
	public function test_oversized_content_is_rejected() {
		$errors = $this->assert_rejects(
			$this->payload( array( 'content' => str_repeat( 'a', 500 ) ) ),
			array( 'max_bytes' => 100 )
		);

		$this->assertStringContainsString( 'larger than the allowed', implode( ' ', $errors ) );
	}

	/**
	 * The size limit follows the setting and has an absolute ceiling.
	 *
	 * @return void
	 */
	public function test_max_content_bytes() {
		$this->assertSame( 2048 * 1024, Template_Validator::max_content_bytes() );
		$this->assertSame( 512, Template_Validator::max_content_bytes( 512 ) );

		Qevora_Test_Env::$options[ QEVORA_LAYOUTS_OPTION ] = array( 'max_import_size_kb' => 64 );
		Settings::flush_cache();

		$this->assertSame( 64 * 1024, Template_Validator::max_content_bytes() );

		Qevora_Test_Env::$options[ QEVORA_LAYOUTS_OPTION ] = array( 'max_import_size_kb' => 20480 );
		Settings::flush_cache();

		$this->assertSame(
			Template_Validator::ABSOLUTE_MAX_CONTENT_BYTES,
			Template_Validator::max_content_bytes(),
			'The setting can never lift the hard ceiling.'
		);
	}

	/**
	 * Conditions are normalized and losses are reported.
	 *
	 * @return void
	 */
	public function test_conditions_are_normalized_with_a_warning() {
		$template = $this->assert_accepts(
			$this->payload(
				array(
					'conditions' => array(
						'include' => array(
							array( 'type' => 'front_page' ),
							array( 'type' => 'not_a_condition' ),
						),
						'exclude' => array( array( 'type' => 'logged_in' ) ),
					),
				)
			)
		);

		$this->assertCount( 1, $template->conditions['include'] );
		$this->assertCount( 1, $template->conditions['exclude'] );
		$this->assertSame( 'front_page', $template->conditions['include'][0]['type'] );
	}

	/**
	 * Preview handling differs per origin and blocks path traversal.
	 *
	 * @return void
	 */
	public function test_preview_validation() {
		$bundled = $this->assert_accepts(
			$this->payload( array( 'preview' => 'assets/images/previews/header-dark-bar.svg' ) ),
			array( 'origin' => 'bundled' )
		);

		$this->assertSame( 'assets/images/previews/header-dark-bar.svg', $bundled->preview );
		$this->assertSame(
			QEVORA_LAYOUTS_URL . 'assets/images/previews/header-dark-bar.svg',
			$bundled->get_preview_url()
		);

		$traversal = Template_Validator::validate(
			$this->payload( array( 'preview' => '../../wp-config.php' ) ),
			array( 'origin' => 'bundled' )
		);

		$this->assertSame( '', $traversal['template']->preview );
		$this->assertStringContainsString( 'preview path was rejected', implode( ' ', $traversal['warnings'] ) );

		$missing = Template_Validator::validate(
			$this->payload( array( 'preview' => 'assets/images/previews/nope.svg' ) ),
			array( 'origin' => 'bundled' )
		);

		$this->assertSame( '', $missing['template']->preview );
		$this->assertStringContainsString( 'does not exist', implode( ' ', $missing['warnings'] ) );

		$upload = Template_Validator::validate(
			$this->payload( array( 'preview' => 'assets/images/previews/header-dark-bar.svg' ) ),
			array( 'origin' => 'upload' )
		);

		$this->assertSame( '', $upload['template']->preview, 'A local path means nothing outside the plugin.' );
		$this->assertStringContainsString( 'not an https URL', implode( ' ', $upload['warnings'] ) );

		$remote = $this->assert_accepts(
			$this->payload( array( 'preview' => 'https://library.example.org/previews/acme.png' ) ),
			array( 'origin' => 'remote' )
		);

		$this->assertSame( 'https://library.example.org/previews/acme.png', $remote->get_preview_url() );

		$insecure = Template_Validator::validate(
			$this->payload( array( 'preview' => 'http://library.example.org/previews/acme.png' ) ),
			array( 'origin' => 'remote' )
		);

		$this->assertSame( '', $insecure['template']->preview );
	}

	/**
	 * Download URLs only exist for remote entries and must be https.
	 *
	 * @return void
	 */
	public function test_reference_url_validation() {
		$upload = Template_Validator::validate(
			$this->payload( array( 'url' => 'https://library.example.org/acme.json' ) ),
			array( 'origin' => 'upload' )
		);

		$this->assertSame( '', $upload['template']->url );
		$this->assertStringContainsString( 'not from a remote library', implode( ' ', $upload['warnings'] ) );

		$insecure = Template_Validator::validate(
			$this->payload( array( 'url' => 'http://library.example.org/acme.json' ) ),
			array( 'origin' => 'remote' )
		);

		$this->assertSame( '', $insecure['template']->url );
		$this->assertStringContainsString( 'not a valid https URL', implode( ' ', $insecure['warnings'] ) );
	}

	/**
	 * Tags and requirements are cleaned up rather than rejected.
	 *
	 * @return void
	 */
	public function test_tags_and_requires() {
		$template = $this->assert_accepts(
			$this->payload(
				array(
					'tags'     => array( 'minimal', 'minimal', str_repeat( 't', 60 ), array( 'nested' ), 'hero', 'dark' ),
					'requires' => array(
						'wordpress' => '6.4',
						'php'       => '7.4',
						'plugins'   => array(
							'elementor/elementor.php',
							array(
								'file' => 'qevora-layouts/qevora-layouts.php',
								'name' => 'Qevora Layouts',
							),
							'../../etc/passwd',
							array( 'file' => array() ),
						),
					),
				)
			)
		);

		$this->assertSame( array( 'minimal', 'hero', 'dark' ), $template->tags );
		$this->assertSame( '6.4', $template->requires['wordpress'] );
		$this->assertSame( '7.4', $template->requires['php'] );
		$this->assertCount( 2, $template->requires['plugins'] );
		$this->assertSame( 'elementor/elementor.php', $template->requires['plugins'][0]['file'] );
		$this->assertSame( 'Qevora Layouts', $template->requires['plugins'][1]['name'] );
	}

	/**
	 * Invalid requirement values are dropped with a warning.
	 *
	 * @return void
	 */
	public function test_invalid_requires_are_dropped() {
		$result = Template_Validator::validate(
			$this->payload(
				array(
					'requires' => array(
						'wordpress' => '"><script>',
						'php'       => array( '7.4' ),
					),
				)
			)
		);

		$this->assertTrue( $result['ok'] );
		$this->assertSame( array(), $result['template']->requires );
		$this->assertStringContainsString( 'Ignored an invalid', implode( ' ', $result['warnings'] ) );
	}

	/**
	 * Unknown fields are ignored but reported.
	 *
	 * @return void
	 */
	public function test_unknown_fields_are_reported() {
		$result = Template_Validator::validate(
			$this->payload(
				array(
					'php_code'    => '<?php echo 1;',
					'executable'  => true,
					'description' => 'A tidy header.',
				)
			)
		);

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'A tidy header.', $result['template']->description );
		$this->assertStringContainsString( 'Ignored unknown template fields', implode( ' ', $result['warnings'] ) );
		$this->assertStringContainsString( 'php_code', implode( ' ', $result['warnings'] ) );
	}

	/**
	 * Users without `unfiltered_html` get KSES-filtered content plus a warning.
	 *
	 * @return void
	 */
	public function test_content_is_filtered_for_limited_users() {
		Qevora_Test_Env::$caps['unfiltered_html'] = false;

		$result = Template_Validator::validate(
			$this->payload( array( 'content' => '<!-- wp:html --><p>fine</p><!-- /wp:html -->' ) )
		);

		$this->assertTrue( $result['ok'] );
		$this->assertStringContainsString( 'wp:html', $result['template']->content );
	}

	/**
	 * A payload may hold one template, a list, or a wrapped list.
	 *
	 * @return void
	 */
	public function test_validate_payload_shapes() {
		$single = Template_Validator::validate_payload( $this->payload() );

		$this->assertTrue( $single['ok'] );
		$this->assertCount( 1, $single['templates'] );

		$wrapped = Template_Validator::validate_payload(
			array(
				'templates' => array(
					$this->payload(),
					$this->payload(
						array(
							'id'   => 'acme-footer',
							'name' => 'Acme footer',
							'type' => 'footer',
						)
					),
				),
			)
		);

		$this->assertTrue( $wrapped['ok'] );
		$this->assertCount( 2, $wrapped['templates'] );
		$this->assertSame( 'acme-header', $wrapped['templates'][0]->id );
		$this->assertSame( 'acme-footer', $wrapped['templates'][1]->id );

		$empty = Template_Validator::validate_payload( array( 'templates' => array() ) );

		$this->assertFalse( $empty['ok'] );
		$this->assertStringContainsString( 'No templates found', implode( ' ', $empty['errors'] ) );

		$scalar = Template_Validator::validate_payload( 'not an array' );

		$this->assertFalse( $scalar['ok'] );
	}

	/**
	 * A payload cannot smuggle in an unlimited number of templates.
	 *
	 * @return void
	 */
	public function test_validate_payload_caps_the_number_of_templates() {
		$items = array();

		for ( $i = 1; $i <= 60; $i++ ) {
			$items[] = $this->payload(
				array(
					'id'   => 'acme-' . $i,
					'name' => 'Acme ' . $i,
				)
			);
		}

		$result = Template_Validator::validate_payload( array( 'templates' => $items ) );

		$this->assertTrue( $result['ok'] );
		$this->assertCount( 50, $result['templates'], 'Only the first 50 templates of a file are imported.' );
	}

	/**
	 * Duplicate identifiers inside one file are refused.
	 *
	 * @return void
	 */
	public function test_validate_payload_rejects_duplicates() {
		$result = Template_Validator::validate_payload(
			array(
				'templates' => array( $this->payload(), $this->payload() ),
			)
		);

		$this->assertFalse( $result['ok'] );
		$this->assertCount( 1, $result['templates'] );
		$this->assertStringContainsString( 'more than once', implode( ' ', $result['errors'] ) );
	}

	/**
	 * Errors point at the template that caused them.
	 *
	 * @return void
	 */
	public function test_validate_payload_numbers_its_errors() {
		$result = Template_Validator::validate_payload(
			array(
				'templates' => array(
					$this->payload(),
					$this->payload( array( 'name' => '' ) ),
				),
			)
		);

		$this->assertFalse( $result['ok'] );
		$this->assertCount( 1, $result['templates'] );
		$this->assertStringContainsString( 'Template #2: The template is missing a name.', implode( ' ', $result['errors'] ) );
	}
}
