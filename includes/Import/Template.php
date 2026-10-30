<?php
/**
 * Template model.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Import;

use QevoraLayouts\Core\Layout_Post_Type;
use QevoraLayouts\Integrations\Builder_Manager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One importable layout template.
 *
 * A template is plain data. It never contains PHP, never points at executable
 * files and is validated by {@see Template_Validator} before it is turned into
 * a {@see Template} instance.
 *
 * @since 1.0.0
 */
class Template {

	/**
	 * Current schema version.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const SCHEMA = 'qevora-layouts/template/v1';

	/**
	 * Unique template identifier.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $id = '';

	/**
	 * Display name.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $name = '';

	/**
	 * Short description.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $description = '';

	/**
	 * Layout type: header|footer|section.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $type = 'header';

	/**
	 * Builder hint: auto|block|elementor.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $builder = 'block';

	/**
	 * Layout content (block markup or plain HTML).
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $content = '';

	/**
	 * Display conditions.
	 *
	 * @since 1.0.0
	 * @var   array<string,array<int,array<string,string>>>
	 */
	public $conditions = array(
		'include' => array(),
		'exclude' => array(),
	);

	/**
	 * Rendering priority.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	public $priority = 10;

	/**
	 * Placement override, empty for the plugin default.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $placement = '';

	/**
	 * Preview image URL (bundled asset or https URL).
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $preview = '';

	/**
	 * Tags shown in the library.
	 *
	 * @since 1.0.0
	 * @var   string[]
	 */
	public $tags = array();

	/**
	 * Requirements: `array( 'wordpress' => '6.4', 'php' => '7.4', 'plugins' => array() )`.
	 *
	 * @since 1.0.0
	 * @var   array<string,mixed>
	 */
	public $requires = array();

	/**
	 * Origin: bundled|remote|upload.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $origin = 'bundled';

	/**
	 * Absolute path of the bundled file, empty for remote/uploaded templates.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $file = '';

	/**
	 * Download URL of a remote template summary, empty otherwise.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $url = '';

	/**
	 * Template version.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $version = '1.0.0';

	/**
	 * Template author.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $author = '';

	/**
	 * License identifier of the template content.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $license = '';

	/**
	 * Builds a template from validated data.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $data Validated data.
	 * @return self
	 */
	public static function from_array( array $data ) {
		$template = new self();

		$template->id          = isset( $data['id'] ) ? (string) $data['id'] : '';
		$template->name        = isset( $data['name'] ) ? (string) $data['name'] : '';
		$template->description = isset( $data['description'] ) ? (string) $data['description'] : '';
		$template->type        = Layout_Post_Type::sanitize_type( isset( $data['type'] ) ? $data['type'] : 'header' );
		$template->builder     = Layout_Post_Type::sanitize_builder( isset( $data['builder'] ) ? $data['builder'] : 'block' );
		$template->content     = isset( $data['content'] ) ? (string) $data['content'] : '';
		$template->conditions  = isset( $data['conditions'] ) && is_array( $data['conditions'] ) ? $data['conditions'] : array();
		$template->priority    = Layout_Post_Type::sanitize_priority( isset( $data['priority'] ) ? $data['priority'] : 10 );
		$template->placement   = Layout_Post_Type::sanitize_placement( isset( $data['placement'] ) ? $data['placement'] : '' );
		$template->preview     = isset( $data['preview'] ) ? (string) $data['preview'] : '';
		$template->tags        = isset( $data['tags'] ) && is_array( $data['tags'] ) ? array_values( $data['tags'] ) : array();
		$template->requires    = isset( $data['requires'] ) && is_array( $data['requires'] ) ? $data['requires'] : array();
		$template->origin      = isset( $data['origin'] ) ? (string) $data['origin'] : 'bundled';
		$template->file        = isset( $data['file'] ) ? (string) $data['file'] : '';
		$template->url         = isset( $data['url'] ) ? (string) $data['url'] : '';
		$template->version     = isset( $data['version'] ) ? (string) $data['version'] : '1.0.0';
		$template->author      = isset( $data['author'] ) ? (string) $data['author'] : '';
		$template->license     = isset( $data['license'] ) ? (string) $data['license'] : '';

		return $template;
	}

	/**
	 * Exports the template as an array.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $with_content Whether to include the (potentially large) content.
	 * @return array<string,mixed>
	 */
	public function to_array( $with_content = false ) {
		$data = array(
			'id'          => $this->id,
			'name'        => $this->name,
			'description' => $this->description,
			'type'        => $this->type,
			'type_label'  => Layout_Post_Type::get_type_label( $this->type ),
			'builder'     => $this->builder,
			'priority'    => $this->priority,
			'placement'   => $this->placement,
			'preview'     => $this->preview,
			'preview_url' => $this->get_preview_url(),
			'tags'        => $this->tags,
			'requires'    => $this->requires,
			'origin'      => $this->origin,
			'url'         => $this->url,
			'has_content' => '' !== trim( (string) $this->content ),
			'version'     => $this->version,
			'author'      => $this->author,
			'license'     => $this->license,
			'available'   => $this->is_available(),
			'missing'     => $this->get_missing_requirements(),
		);

		if ( $with_content ) {
			$data['content']    = $this->content;
			$data['conditions'] = $this->conditions;
		}

		return $data;
	}

	/**
	 * Returns the human readable builder requirement.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_builder_label() {
		if ( 'elementor' === $this->builder ) {
			return __( 'Requires Elementor', 'qevora-layouts' );
		}

		return __( 'Block editor', 'qevora-layouts' );
	}

	/**
	 * Returns plugins required by this template that are not active.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public function get_missing_requirements() {
		$missing = array();

		$plugins = isset( $this->requires['plugins'] ) && is_array( $this->requires['plugins'] ) ? $this->requires['plugins'] : array();

		foreach ( $plugins as $plugin ) {
			if ( ! is_array( $plugin ) ) {
				continue;
			}

			$basename = isset( $plugin['file'] ) ? (string) $plugin['file'] : '';
			$label    = isset( $plugin['name'] ) ? (string) $plugin['name'] : $basename;

			if ( '' === $basename ) {
				continue;
			}

			if ( ! Builder_Manager::is_plugin_active_by_basename( $basename ) ) {
				$missing[] = $label;
			}
		}

		return $missing;
	}

	/**
	 * Whether everything the template needs is available.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_available() {
		return empty( $this->get_missing_requirements() );
	}

	/**
	 * Returns the preview image URL, or an empty string when there is none.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_preview_url() {
		if ( '' === $this->preview ) {
			return '';
		}

		if ( preg_match( '#^https?://#i', $this->preview ) ) {
			return esc_url( $this->preview );
		}

		$relative = ltrim( str_replace( '\\', '/', (string) $this->preview ), '/' );
		$path     = QEVORA_LAYOUTS_DIR . $relative;

		if ( ! is_readable( $path ) ) {
			return '';
		}

		return esc_url( QEVORA_LAYOUTS_URL . $relative );
	}
}
