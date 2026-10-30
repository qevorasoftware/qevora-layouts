<?php
/**
 * Template importer.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Import;

use QevoraLayouts\Conditions\Condition_Sanitizer;
use QevoraLayouts\Core\Capabilities;
use QevoraLayouts\Core\Layout;
use QevoraLayouts\Core\Layout_Post_Type;
use QevoraLayouts\Core\Layout_Repository;
use QevoraLayouts\Core\Logger;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns validated templates into layouts.
 *
 * Imports are repeatable and explicit:
 *
 * - every payload passes {@see Template_Validator} first,
 * - the importer refuses to overwrite an existing layout unless the caller
 *   passes the ID of the layout to replace,
 * - a template that was already imported is skipped (default) or copied, based
 *   on the configured duplicate policy,
 * - uploaded files are read from PHP's temporary upload location and are never
 *   copied into `wp-content/uploads`, so no imported file can end up somewhere
 *   a web server might execute.
 *
 * @since 1.0.0
 */
class Importer {

	/**
	 * Repository.
	 *
	 * @since 1.0.0
	 * @var   Layout_Repository
	 */
	private $repository;

	/**
	 * Template library.
	 *
	 * @since 1.0.0
	 * @var   Template_Library|null
	 */
	private $library;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Layout_Repository      $repository Repository.
	 * @param Template_Library|null  $library    Library.
	 */
	public function __construct( Layout_Repository $repository, ?Template_Library $library = null ) {
		$this->repository = $repository;
		$this->library    = $library;
	}

	/**
	 * The quick display scopes offered when a template is imported.
	 *
	 * Each key maps to a condition set through {@see Importer::scope_conditions()}.
	 * `template` keeps the conditions shipped with the template.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,string> Scope key => label.
	 */
	public static function display_scopes() {
		$scopes = array(
			'template'    => __( 'Where the demo says - recommended', 'qevora-layouts' ),
			'entire_site' => __( 'Entire site', 'qevora-layouts' ),
			'front_page'  => __( 'Front page only', 'qevora-layouts' ),
			'singular'    => __( 'Single posts and pages', 'qevora-layouts' ),
			'archives'    => __( 'Blog and archive listings', 'qevora-layouts' ),
		);

		/**
		 * Filters the quick display scopes shown on import forms.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,string> $scopes Scope key => label.
		 */
		return (array) apply_filters( 'qevora_layouts_display_scopes', $scopes );
	}

	/**
	 * Translates a display scope into a condition set.
	 *
	 * @since 1.0.0
	 *
	 * @param string $scope Scope key.
	 * @return array<string,array<int,string>>|null Conditions, null for `template` or unknown scopes.
	 */
	public static function scope_conditions( $scope ) {
		$map = array(
			'entire_site' => array(
				'include' => array(),
				'exclude' => array(),
			),
			'front_page'  => array(
				'include' => array( 'front_page' ),
				'exclude' => array(),
			),
			'singular'    => array(
				'include' => array( 'singular' ),
				'exclude' => array(),
			),
			'archives'    => array(
				'include' => array( 'archive', 'posts_page' ),
				'exclude' => array(),
			),
		);

		/**
		 * Filters the condition sets behind the quick display scopes.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,array<string,array<int,string>>> $map Scope key => conditions.
		 */
		$map = (array) apply_filters( 'qevora_layouts_display_scope_conditions', $map );

		return isset( $map[ $scope ] ) && is_array( $map[ $scope ] ) ? $map[ $scope ] : null;
	}

	/**
	 * Curated multi-template demos that can be imported with one click.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,array<string,mixed>> Pack key => {label, description, templates}.
	 */
	public static function demo_packs() {
		$packs = array(
			'header-footer' => array(
				'label'       => __( 'Complete header and footer demo', 'qevora-layouts' ),
				'description' => __( 'Imports one header and one footer demo and displays both right away.', 'qevora-layouts' ),
				'templates'   => array( 'qevora-header-simple', 'qevora-footer-columns' ),
			),
			'full-site'     => array(
				'label'       => __( 'Full demo site', 'qevora-layouts' ),
				'description' => __( 'Header, footer and the three reusable sections, each with its own display rules.', 'qevora-layouts' ),
				'templates'   => array(
					'qevora-header-simple',
					'qevora-footer-columns',
					'qevora-section-hero',
					'qevora-section-cta',
					'qevora-section-faq',
				),
			),
		);

		/**
		 * Filters the one-click demo packs.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,array<string,mixed>> $packs Pack key => pack definition.
		 */
		return (array) apply_filters( 'qevora_layouts_demo_packs', $packs );
	}

	/**
	 * Loads the validated templates of one demo pack.
	 *
	 * @since 1.0.0
	 *
	 * @param string $pack_id Pack key.
	 * @return Template[]|WP_Error Templates, or an error for unknown packs.
	 */
	public function resolve_pack( $pack_id ) {
		$packs = self::demo_packs();

		if ( ! isset( $packs[ $pack_id ] ) || ! is_array( $packs[ $pack_id ] ) ) {
			return new WP_Error( 'qevora_layouts_unknown_pack', __( 'Unknown demo pack.', 'qevora-layouts' ) );
		}

		$library   = $this->library instanceof Template_Library ? $this->library : new Template_Library();
		$templates = array();

		foreach ( (array) $packs[ $pack_id ]['templates'] as $template_id ) {
			$template = $library->find( (string) $template_id, 'bundled' );

			if ( ! $template instanceof Template ) {
				return new WP_Error(
					'qevora_layouts_pack_template_missing',
					sprintf(
						/* translators: %s: template identifier. */
						__( 'The demo pack needs the bundled template "%s", which is missing.', 'qevora-layouts' ),
						(string) $template_id
					)
				);
			}

			$templates[] = $template;
		}

		return $templates;
	}

	/**
	 * Decodes a JSON payload.
	 *
	 * @since 1.0.0
	 *
	 * @param string $raw     Raw JSON.
	 * @param int    $max_depth Maximum nesting depth.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function decode_json( $raw, $max_depth = 32 ) {
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return new WP_Error( 'qevora_layouts_empty_payload', __( 'The template file is empty.', 'qevora-layouts' ) );
		}

		// Reject PHP before anything else touches the payload.
		if ( 1 === preg_match( '/<\?(?:php|\s|=)/i', $raw ) ) {
			return new WP_Error( 'qevora_layouts_php_in_payload', __( 'Template files must not contain PHP code.', 'qevora-layouts' ) );
		}

		$decoded = json_decode( $raw, true, max( 2, (int) $max_depth ) );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error(
				'qevora_layouts_invalid_json',
				sprintf(
					/* translators: %s: JSON error message. */
					__( 'The file is not valid JSON: %s', 'qevora-layouts' ),
					function_exists( 'json_last_error_msg' ) ? json_last_error_msg() : json_last_error()
				)
			);
		}

		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'qevora_layouts_invalid_payload', __( 'The JSON payload must be an object or an array of templates.', 'qevora-layouts' ) );
		}

		return $decoded;
	}

	/**
	 * Reads and validates an uploaded template file.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $file    One `$_FILES` entry.
	 * @param array<string,mixed> $options Import options.
	 * @return array{ok:bool,templates:Template[],errors:string[],warnings:string[]}|WP_Error
	 */
	public function read_uploaded_file( array $file, array $options = array() ) {
		if ( ! Capabilities::can_publish_layouts() ) {
			return new WP_Error( 'qevora_layouts_forbidden', __( 'You are not allowed to import templates.', 'qevora-layouts' ), array( 'status' => 403 ) );
		}

		$error = self::validate_upload( $file );

		if ( is_wp_error( $error ) ) {
			return $error;
		}

		$tmp = (string) $file['tmp_name'];

		// Only accept real uploads unless a caller (tests) opts out explicitly.
		if ( empty( $options['skip_upload_check'] ) && function_exists( 'is_uploaded_file' ) && ! is_uploaded_file( $tmp ) ) {
			return new WP_Error( 'qevora_layouts_invalid_upload', __( 'The uploaded file could not be verified.', 'qevora-layouts' ) );
		}

		$max_bytes = Template_Validator::max_content_bytes() * 4;
		$size      = (int) filesize( $tmp );

		if ( $size > $max_bytes ) {
			return new WP_Error(
				'qevora_layouts_file_too_large',
				sprintf(
					/* translators: %d: maximum file size in kilobytes. */
					__( 'The file is larger than the allowed %d KB.', 'qevora-layouts' ),
					(int) round( $max_bytes / 1024 )
				)
			);
		}

		$raw = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the uploaded temp file.

		if ( false === $raw ) {
			return new WP_Error( 'qevora_layouts_unreadable_file', __( 'The uploaded file could not be read.', 'qevora-layouts' ) );
		}

		return $this->validate_string( $raw, array_merge( $options, array( 'origin' => 'upload' ) ) );
	}

	/**
	 * Validates the shape of an uploaded file entry.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $file One `$_FILES` entry.
	 * @return true|WP_Error
	 */
	public static function validate_upload( array $file ) {
		if ( ! isset( $file['error'] ) || ! isset( $file['tmp_name'] ) ) {
			return new WP_Error( 'qevora_layouts_no_file', __( 'No file was uploaded.', 'qevora-layouts' ) );
		}

		$upload_error = (int) $file['error'];

		if ( UPLOAD_ERR_OK !== $upload_error ) {
			return new WP_Error( 'qevora_layouts_upload_error', self::upload_error_message( $upload_error ) );
		}

		$name = isset( $file['name'] ) ? (string) $file['name'] : '';
		$ext  = strtolower( (string) pathinfo( wp_basename( $name ), PATHINFO_EXTENSION ) );

		if ( 'json' !== $ext ) {
			return new WP_Error( 'qevora_layouts_invalid_file_type', __( 'Only .json template files are accepted.', 'qevora-layouts' ) );
		}

		if ( ! is_string( $file['tmp_name'] ) || '' === $file['tmp_name'] || ! is_readable( $file['tmp_name'] ) ) {
			return new WP_Error( 'qevora_layouts_unreadable_file', __( 'The uploaded file could not be read.', 'qevora-layouts' ) );
		}

		return true;
	}

	/**
	 * Translates a PHP upload error code.
	 *
	 * @since 1.0.0
	 *
	 * @param int $code Upload error code.
	 * @return string
	 */
	private static function upload_error_message( $code ) {
		switch ( (int) $code ) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return __( 'The file is larger than this server allows.', 'qevora-layouts' );
			case UPLOAD_ERR_PARTIAL:
				return __( 'The file was only partially uploaded. Please try again.', 'qevora-layouts' );
			case UPLOAD_ERR_NO_FILE:
				return __( 'No file was uploaded.', 'qevora-layouts' );
			case UPLOAD_ERR_NO_TMP_DIR:
			case UPLOAD_ERR_CANT_WRITE:
				return __( 'WordPress could not store the uploaded file. Please contact your host.', 'qevora-layouts' );
			case UPLOAD_ERR_EXTENSION:
				return __( 'A PHP extension stopped the upload.', 'qevora-layouts' );
			default:
				return __( 'The file could not be uploaded.', 'qevora-layouts' );
		}
	}

	/**
	 * Decodes and validates a raw JSON string.
	 *
	 * @since 1.0.0
	 *
	 * @param string              $raw     Raw JSON.
	 * @param array<string,mixed> $options Validation options.
	 * @return array{ok:bool,templates:Template[],errors:string[],warnings:string[]}
	 */
	public function validate_string( $raw, array $options = array() ) {
		$decoded = self::decode_json( $raw );

		if ( is_wp_error( $decoded ) ) {
			return array(
				'ok'        => false,
				'templates' => array(),
				'errors'    => array( $decoded->get_error_message() ),
				'warnings'  => array(),
			);
		}

		$options = wp_parse_args(
			$options,
			array(
				'origin' => 'upload',
			)
		);

		return Template_Validator::validate_payload( $decoded, $options );
	}

	/**
	 * Imports every template of a validated payload.
	 *
	 * @since 1.0.0
	 *
	 * @param Template[]          $templates Validated templates.
	 * @param array<string,mixed> $options   {
	 *     Import options.
	 *
	 *     @type string $origin       bundled|remote|upload.
	 *     @type string $on_duplicate skip|copy. Empty uses the configured policy.
	 *     @type string $status       publish|draft. Empty uses `publish`.
	 * }
	 * @return array{imported:array<int,array<string,mixed>>,skipped:array<int,array<string,mixed>>,failed:array<int,array<string,mixed>>,warnings:string[]}
	 */
	public function import_templates( array $templates, array $options = array() ) {
		$result = array(
			'imported' => array(),
			'skipped'  => array(),
			'failed'   => array(),
			'warnings' => array(),
		);

		if ( ! Capabilities::can_publish_layouts() ) {
			$result['failed'][] = array(
				'name'   => '',
				'errors' => array( __( 'You are not allowed to import templates.', 'qevora-layouts' ) ),
			);

			return $result;
		}

		$options = wp_parse_args(
			$options,
			array(
				'origin'        => 'upload',
				'on_duplicate'  => '',
				'status'        => 'publish',
				'conditions'    => null,
				'display_scope' => '',
			)
		);

		/*
		 * A display scope or an explicit conditions set chosen by the user
		 * replaces the conditions stored in the template, so an imported demo
		 * shows exactly where the user asked for it on the first render.
		 */
		$conditions = null;

		if ( is_array( $options['conditions'] ) ) {
			$conditions = Condition_Sanitizer::normalize( $options['conditions'] );
		} elseif ( '' !== (string) $options['display_scope'] ) {
			$conditions = self::scope_conditions( (string) $options['display_scope'] );
		}

		$options['conditions'] = $conditions;

		foreach ( $templates as $template ) {
			if ( ! $template instanceof Template ) {
				$result['failed'][] = array(
					'name'   => '',
					'errors' => array( __( 'Invalid template object.', 'qevora-layouts' ) ),
				);

				continue;
			}

			$single = $this->import_template( $template, $options );

			foreach ( (array) $single['warnings'] as $warning ) {
				$result['warnings'][] = $warning;
			}

			if ( 'created' === $single['action'] || 'updated' === $single['action'] || 'copied' === $single['action'] ) {
				$result['imported'][] = $single;
				continue;
			}

			if ( 'skipped' === $single['action'] ) {
				$result['skipped'][] = $single;
				continue;
			}

			$result['failed'][] = $single;
		}

		Logger::debug(
			'Import finished.',
			array(
				'imported' => count( $result['imported'] ),
				'skipped'  => count( $result['skipped'] ),
				'failed'   => count( $result['failed'] ),
			)
		);

		return $result;
	}

	/**
	 * Imports one template.
	 *
	 * @since 1.0.0
	 *
	 * @param Template            $template Validated template.
	 * @param array<string,mixed> $options  See {@see Importer::import_templates()}.
	 * @return array<string,mixed> Result with an `action` key: created|copied|updated|skipped|failed.
	 */
	public function import_template( Template $template, array $options = array() ) {
		$options = wp_parse_args(
			$options,
			array(
				'origin'       => $template->origin,
				'on_duplicate' => '',
				'status'       => 'publish',
				'replace_id'   => 0,
			)
		);

		$base = array(
			'template_id' => $template->id,
			'name'        => $template->name,
			'type'        => $template->type,
			'action'      => 'failed',
			'errors'      => array(),
			'warnings'    => array(),
		);

		if ( ! Capabilities::can_publish_layouts() ) {
			$base['errors'][] = __( 'You are not allowed to import templates.', 'qevora-layouts' );

			return $base;
		}

		$conditions = isset( $options['conditions'] ) && is_array( $options['conditions'] ) ? $options['conditions'] : null;

		/*
		 * A quick display scope chosen on an import form also applies when the
		 * caller reaches this method directly - single library imports, the
		 * REST route and the admin AJAX endpoint - not only through the batch
		 * importer. Without this the scope is silently ignored and the demo
		 * keeps the conditions it shipped with.
		 */
		if ( null === $conditions && ! empty( $options['display_scope'] ) ) {
			$scope_conditions = self::scope_conditions( (string) $options['display_scope'] );
			$conditions       = is_array( $scope_conditions ) ? $scope_conditions : null;
		}

		$policy = (string) $options['on_duplicate'];

		if ( '' === $policy ) {
			$policy = (string) \QevoraLayouts\Core\Settings::get( 'duplicate_import_policy', 'skip' );
		}

		if ( ! in_array( $policy, array( 'skip', 'copy' ), true ) ) {
			$policy = 'skip';
		}

		$existing = $this->repository->find_by_template_id( $template->id );
		$status   = $this->repository->sanitize_status( $options['status'] );
		$replace  = (int) $options['replace_id'];

		// Explicit replacement of a layout chosen by the user.
		if ( $replace > 0 ) {
			if ( ! Capabilities::can_edit_layout( $replace ) ) {
				$base['errors'][] = __( 'You are not allowed to overwrite that layout.', 'qevora-layouts' );

				return $base;
			}

			$saved = $this->repository->save( $this->to_layout_data( $template, $status, $replace, $conditions ) );

			if ( is_wp_error( $saved ) ) {
				$base['errors'][] = $saved->get_error_message();

				return $base;
			}

			$base['action'] = 'updated';
			$base['id']     = (int) $saved;
			$base['url']    = get_edit_post_link( (int) $saved, 'raw' );

			return $base;
		}

		if ( ! empty( $existing ) && 'copy' !== $policy ) {
			$base['action']        = 'skipped';
			$base['existing_ids']  = array_map( 'intval', $existing );
			$base['existing_url']  = get_edit_post_link( (int) $existing[0], 'raw' );
			$base['reason']        = sprintf(
				/* translators: %s: template name. */
				__( '"%s" was imported before and already exists. Nothing was changed.', 'qevora-layouts' ),
				$template->name
			);

			return $base;
		}

		$saved = $this->repository->save( $this->to_layout_data( $template, $status, 0, $conditions ) );

		if ( is_wp_error( $saved ) ) {
			$base['errors'][] = $saved->get_error_message();

			return $base;
		}

		$base['action'] = ! empty( $existing ) ? 'copied' : 'created';
		$base['id']     = (int) $saved;
		$base['url']    = get_edit_post_link( (int) $saved, 'raw' );

		if ( ! empty( $existing ) ) {
			$base['warnings'][] = sprintf(
				/* translators: %s: template name. */
				__( '"%s" already existed, a second copy was created.', 'qevora-layouts' ),
				$template->name
			);
		}

		return $base;
	}

	/**
	 * Maps a template onto repository save data.
	 *
	 * @since 1.0.0
	 *
	 * @param Template $template Template.
	 * @param string   $status   Post status.
	 * @param int      $id       Existing layout ID, 0 to create.
	 * @return array<string,mixed>
	 */
	private function to_layout_data( Template $template, $status, $id = 0, $conditions = null ) {
		$data = array(
			'title'         => $template->name,
			'content'       => $template->content,
			'type'          => $template->type,
			'status'        => $status,
			'priority'      => $template->priority,
			'conditions'    => is_array( $conditions ) ? $conditions : $template->conditions,
			'placement'     => $template->placement,
			'builder'       => $template->builder,
			'template_id'   => $template->id,
			'import_source' => in_array( $template->origin, array( 'bundled', 'remote', 'upload' ), true ) ? $template->origin : 'upload',
			'imported_at'   => gmdate( 'c' ),
		);

		if ( $id > 0 ) {
			$data['id'] = (int) $id;
		}

		/**
		 * Filters the layout data created from a template.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,mixed> $data     Layout data.
		 * @param Template            $template Template.
		 */
		return apply_filters( 'qevora_layouts_import_data', $data, $template );
	}

	/**
	 * Imports a template straight from the library by identifier.
	 *
	 * @since 1.0.0
	 *
	 * @param string              $template_id Template identifier.
	 * @param array<string,mixed> $options     Import options (`origin` selects the library).
	 * @return array<string,mixed>|WP_Error
	 */
	public function import_from_library( $template_id, array $options = array() ) {
		if ( null === $this->library ) {
			return new WP_Error( 'qevora_layouts_no_library', __( 'The template library is not available.', 'qevora-layouts' ) );
		}

		$template_id = Layout_Post_Type::sanitize_identifier( $template_id );

		if ( '' === $template_id ) {
			return new WP_Error( 'qevora_layouts_invalid_template', __( 'Unknown template.', 'qevora-layouts' ) );
		}

		$origin = isset( $options['origin'] ) ? (string) $options['origin'] : '';

		$template = $this->library->find( $template_id, $origin );

		if ( null === $template ) {
			return new WP_Error( 'qevora_layouts_template_not_found', __( 'That template could not be found.', 'qevora-layouts' ) );
		}

		if ( 'remote' === $template->origin && '' === $template->content ) {
			$fetched = Remote_Source::fetch_template( $template );

			if ( is_wp_error( $fetched ) ) {
				return $fetched;
			}

			$template = $fetched;
		}

		$options['origin'] = $template->origin;

		return $this->import_template( $template, $options );
	}

	/**
	 * Returns the layout created by an import result, when there is one.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $result Import result.
	 * @return Layout|null
	 */
	public static function layout_from_result( array $result ) {
		if ( empty( $result['id'] ) ) {
			return null;
		}

		$layout = Layout::from_post( (int) $result['id'] );

		return $layout instanceof Layout ? $layout : null;
	}
}
