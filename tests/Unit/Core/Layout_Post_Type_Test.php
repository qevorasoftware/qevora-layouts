<?php
/**
 * Tests for the layout post type helpers.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Tests\Unit\Core;

use QevoraLayouts\Core\Layout_Post_Type;
use QevoraLayouts\Tests\TestCase;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every stored value passes through one of these sanitizers.
 *
 * @since 1.0.0
 */
class Layout_Post_Type_Test extends TestCase {

	/**
	 * The data model is open for future template types.
	 *
	 * @return void
	 */
	public function test_types_are_extensible() {
		$types = Layout_Post_Type::get_types();

		$this->assertArrayHasKey( 'header', $types );
		$this->assertArrayHasKey( 'footer', $types );
		$this->assertArrayHasKey( 'section', $types );
		$this->assertSame( array( 'header', 'footer', 'section' ), Layout_Post_Type::get_type_slugs() );
		$this->assertSame( 'Header', Layout_Post_Type::get_type_label( 'header' ) );

		add_filter(
			'qevora_layouts_types',
			function ( $types ) {
				$types['archive'] = array(
					'label'       => 'Archive',
					'description' => 'An archive template.',
				);

				return $types;
			}
		);

		$this->assertTrue( Layout_Post_Type::is_valid_type( 'archive' ) );
		$this->assertSame( 'archive', Layout_Post_Type::sanitize_type( 'archive' ) );
		$this->assertContains( 'archive', Layout_Post_Type::get_type_slugs() );
	}

	/**
	 * An unknown type falls back to the first registered type.
	 *
	 * @return void
	 */
	public function test_type_sanitization() {
		$this->assertSame( 'header', Layout_Post_Type::sanitize_type( 'nonsense' ) );
		$this->assertSame( 'footer', Layout_Post_Type::sanitize_type( 'footer' ) );
		$this->assertSame( 'header', Layout_Post_Type::sanitize_type( array( 'footer' ) ) );
		$this->assertSame( 'header', Layout_Post_Type::sanitize_type( null ) );
		$this->assertFalse( Layout_Post_Type::is_valid_type( '' ) );
	}

	/**
	 * Priorities are clamped to 0-999.
	 *
	 * @return void
	 */
	public function test_priority_sanitization() {
		$this->assertSame( 10, Layout_Post_Type::sanitize_priority( 10 ) );
		$this->assertSame( 0, Layout_Post_Type::sanitize_priority( -50 ) );
		$this->assertSame( 999, Layout_Post_Type::sanitize_priority( 100000 ) );
		$this->assertSame( 0, Layout_Post_Type::sanitize_priority( 'abc' ) );
		$this->assertSame( 42, Layout_Post_Type::sanitize_priority( '42.9' ) );
	}

	/**
	 * Placements are limited to the documented injection points.
	 *
	 * @return void
	 */
	public function test_placement_sanitization() {
		$this->assertSame( 'wp_body_open', Layout_Post_Type::sanitize_placement( 'wp_body_open' ) );
		$this->assertSame( 'get_footer', Layout_Post_Type::sanitize_placement( 'get_footer' ) );
		$this->assertSame( 'manual', Layout_Post_Type::sanitize_placement( 'manual' ) );
		$this->assertSame( '', Layout_Post_Type::sanitize_placement( 'wp_head' ) );
		$this->assertSame( '', Layout_Post_Type::sanitize_placement( '<script>' ) );

		$header = Layout_Post_Type::get_placements( 'header' );
		$footer = Layout_Post_Type::get_placements( 'footer' );

		$this->assertArrayHasKey( 'wp_body_open', $header );
		$this->assertArrayHasKey( 'get_header', $header );
		$this->assertArrayHasKey( 'get_footer', $footer );
		$this->assertArrayHasKey( 'wp_footer', $footer );
		$this->assertArrayHasKey( 'custom_hook', $header );
		$this->assertArrayHasKey( 'manual', $footer );
		$this->assertFalse( isset( $header['get_footer'] ), 'A header must not offer a footer placement.' );
		$this->assertFalse( isset( $footer['get_header'] ), 'A footer must not offer a header placement.' );
	}

	/**
	 * Custom hook names are restricted and dangerous core hooks are refused.
	 *
	 * @return void
	 */
	public function test_hook_name_sanitization() {
		$this->assertSame( 'my_theme_before_header', Layout_Post_Type::sanitize_hook_name( 'my_theme_before_header' ) );
		$this->assertSame( 'astra_header', Layout_Post_Type::sanitize_hook_name( 'astra_header' ) );
		$this->assertSame( '', Layout_Post_Type::sanitize_hook_name( 'wp_head' ) );
		$this->assertSame( '', Layout_Post_Type::sanitize_hook_name( 'init' ) );
		$this->assertSame( '', Layout_Post_Type::sanitize_hook_name( 'plugins_loaded' ) );
		$this->assertSame( '', Layout_Post_Type::sanitize_hook_name( 'shutdown' ) );
		$this->assertSame( '', Layout_Post_Type::sanitize_hook_name( 'admin_init' ) );
		$this->assertSame( '', Layout_Post_Type::sanitize_hook_name( 'template_redirect' ) );
		$this->assertSame( '', Layout_Post_Type::sanitize_hook_name( 'ab' ), 'Too short to be a real hook name.' );
		$this->assertSame( '', Layout_Post_Type::sanitize_hook_name( 'hook name with spaces' ) );
		$this->assertSame( '', Layout_Post_Type::sanitize_hook_name( "hook\nname" ) );
		$this->assertSame( '', Layout_Post_Type::sanitize_hook_name( '<?php echo 1;' ) );
		$this->assertSame( '', Layout_Post_Type::sanitize_hook_name( array( 'init' ) ) );
	}

	/**
	 * Hook priorities are clamped like layout priorities.
	 *
	 * @return void
	 */
	public function test_hook_priority_sanitization() {
		$this->assertSame( 10, Layout_Post_Type::sanitize_hook_priority( 10 ) );
		$this->assertSame( 0, Layout_Post_Type::sanitize_hook_priority( -1 ) );
		$this->assertSame( 999, Layout_Post_Type::sanitize_hook_priority( 1e9 ) );
	}

	/**
	 * Builder hints are limited to known adapters.
	 *
	 * @return void
	 */
	public function test_builder_sanitization() {
		$this->assertSame( 'auto', Layout_Post_Type::sanitize_builder( 'auto' ) );
		$this->assertSame( 'block', Layout_Post_Type::sanitize_builder( 'block' ) );
		$this->assertSame( 'elementor', Layout_Post_Type::sanitize_builder( 'elementor' ) );
		$this->assertSame( 'auto', Layout_Post_Type::sanitize_builder( 'divi' ) );
		$this->assertSame( 'auto', Layout_Post_Type::sanitize_builder( null ) );

		add_filter(
			'qevora_layouts_allowed_builders',
			function ( $allowed ) {
				$allowed[] = 'bricks';

				return $allowed;
			}
		);

		$this->assertSame( 'bricks', Layout_Post_Type::sanitize_builder( 'bricks' ) );
	}

	/**
	 * Content sources and references are validated.
	 *
	 * @return void
	 */
	public function test_content_source_sanitization() {
		$this->assertSame( 'self', Layout_Post_Type::sanitize_content_source( 'self' ) );
		$this->assertSame( 'reference', Layout_Post_Type::sanitize_content_source( 'reference' ) );
		$this->assertSame( 'self', Layout_Post_Type::sanitize_content_source( 'template' ) );
		$this->assertSame( 0, Layout_Post_Type::sanitize_reference_id( -8 ) );
		$this->assertSame( 12, Layout_Post_Type::sanitize_reference_id( '12' ) );
	}

	/**
	 * Template identifiers cannot carry path or markup characters.
	 *
	 * @return void
	 */
	public function test_identifier_sanitization() {
		$this->assertSame( 'qevora-header-simple', Layout_Post_Type::sanitize_identifier( 'qevora-header-simple' ) );
		$this->assertSame( 'pack.v1', Layout_Post_Type::sanitize_identifier( 'pack.v1' ) );
		$this->assertSame( '', Layout_Post_Type::sanitize_identifier( '../../wp-config' ) );
		$this->assertSame( '', Layout_Post_Type::sanitize_identifier( 'has space' ) );
		$this->assertSame( '', Layout_Post_Type::sanitize_identifier( '<script>' ) );
		$this->assertSame( '', Layout_Post_Type::sanitize_identifier( array( 'ok' ) ) );
	}

	/**
	 * Import sources are limited to the three supported origins.
	 *
	 * @return void
	 */
	public function test_import_source_sanitization() {
		$this->assertSame( 'bundled', Layout_Post_Type::sanitize_import_source( 'bundled' ) );
		$this->assertSame( 'upload', Layout_Post_Type::sanitize_import_source( 'upload' ) );
		$this->assertSame( 'remote', Layout_Post_Type::sanitize_import_source( 'remote' ) );
		$this->assertSame( '', Layout_Post_Type::sanitize_import_source( 'database' ) );
	}

	/**
	 * Every meta key is prefixed, so uninstall and support can find them.
	 *
	 * @return void
	 */
	public function test_meta_keys_are_prefixed() {
		$keys = Layout_Post_Type::get_meta_keys();

		$this->assertNotEmpty( $keys );
		$this->assertContains( Layout_Post_Type::META_TYPE, $keys );
		$this->assertContains( Layout_Post_Type::META_CONDITIONS, $keys );

		foreach ( $keys as $key ) {
			$this->assertStringContainsString( '_qevora_layout_', $key );
		}
	}
}
