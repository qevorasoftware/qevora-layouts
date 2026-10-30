<?php
/**
 * Template library.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Import;

use QevoraLayouts\Core\Cache;
use QevoraLayouts\Core\Logger;
use QevoraLayouts\Integrations\Builder_Manager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects the templates a user can import.
 *
 * Two sources are supported:
 *
 * 1. **Bundled** starter templates in `templates/starter/*.json`. They ship
 *    with the plugin, need no network access and always work.
 * 2. **Remote** templates from the optional https endpoint configured in
 *    Settings → Template Library. Disabled by default; there is no built-in
 *    remote service.
 *
 * @since 1.0.0
 */
class Template_Library {

	/**
	 * Directory holding bundled templates, relative to the plugin root.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const BUNDLED_DIR = 'templates/starter';

	/**
	 * Builder manager.
	 *
	 * @since 1.0.0
	 * @var   Builder_Manager|null
	 */
	private $builders;

	/**
	 * Bundled templates resolved for this request.
	 *
	 * @since 1.0.0
	 * @var   Template[]|null
	 */
	private $bundled = null;

	/**
	 * Problems found while reading the library.
	 *
	 * @since 1.0.0
	 * @var   string[]
	 */
	private $problems = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Builder_Manager|null $builders Builder manager.
	 */
	public function __construct( ?Builder_Manager $builders = null ) {
		$this->builders = $builders;
	}

	/**
	 * Reads the bundled starter templates.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $force Whether to re-read the files.
	 * @return Template[]
	 */
	public function bundled( $force = false ) {
		if ( ! $force && null !== $this->bundled ) {
			return $this->bundled;
		}

		$templates = array();
		$pattern   = QEVORA_LAYOUTS_DIR . self::BUNDLED_DIR . '/*.json';

		/**
		 * Filters the glob pattern used to find bundled templates.
		 *
		 * @since 1.0.0
		 *
		 * @param string $pattern Absolute glob pattern.
		 */
		$pattern = (string) apply_filters( 'qevora_layouts_bundled_template_pattern', $pattern );

		$files = glob( $pattern );
		$files = is_array( $files ) ? $files : array();

		/**
		 * Filters the list of bundled template files.
		 *
		 * Allows a site to add its own starter templates without touching the
		 * plugin directory. Files must be absolute paths to readable JSON files
		 * inside a directory the plugin may read.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $files Absolute file paths.
		 */
		$files = (array) apply_filters( 'qevora_layouts_bundled_template_files', $files );

		sort( $files );

		foreach ( array_slice( $files, 0, 100 ) as $file ) {
			foreach ( $this->read_file( $file ) as $template ) {
				if ( isset( $templates[ $template->id ] ) ) {
					$this->problems[] = sprintf(
						/* translators: %s: template identifier. */
						__( 'Duplicate bundled template "%s" was skipped.', 'qevora-layouts' ),
						$template->id
					);

					continue;
				}

				$templates[ $template->id ] = $template;
			}
		}

		$this->bundled = $templates;

		return $templates;
	}

	/**
	 * Reads and validates one bundled template file.
	 *
	 * A file may contain a single template object or a `{ "templates": [...] }`
	 * collection.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file Absolute path.
	 * @return Template[] Validated templates found in the file.
	 */
	private function read_file( $file ) {
		if ( ! is_string( $file ) || '' === $file ) {
			return array();
		}

		$real = realpath( $file );

		if ( false === $real || ! is_readable( $real ) || is_dir( $real ) ) {
			$this->note_problem( $file, __( 'The bundled template file could not be read.', 'qevora-layouts' ) );

			return array();
		}

		if ( filesize( $real ) > Template_Validator::max_content_bytes() * 4 ) {
			$this->note_problem( $real, __( 'The bundled template file is too large and was skipped.', 'qevora-layouts' ) );

			return array();
		}

		$raw = file_get_contents( $real ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a bundled plugin file.

		if ( false === $raw ) {
			return array();
		}

		$decoded = Importer::decode_json( $raw );

		if ( is_wp_error( $decoded ) ) {
			$this->note_problem( $real, $decoded->get_error_message() );

			return array();
		}

		$payload = isset( $decoded['templates'] ) && is_array( $decoded['templates'] )
			? $decoded
			: array( 'templates' => array( $decoded ) );

		$result = Template_Validator::validate_payload(
			$payload,
			array(
				'origin'     => 'bundled',
				'require_id' => true,
			)
		);

		foreach ( $result['errors'] as $error ) {
			$this->note_problem( $real, $error );
		}

		foreach ( (array) $result['warnings'] as $warning ) {
			Logger::debug( 'Bundled template warning.', array( 'file' => basename( $real ), 'warning' => $warning ) );
		}

		$templates = array();

		foreach ( $result['templates'] as $template ) {
			$template->file   = $real;
			$template->origin = 'bundled';
			$templates[]      = $template;
		}

		return $templates;
	}

	/**
	 * Records a library problem for the admin UI.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file    File the problem came from.
	 * @param string $message Message.
	 * @return void
	 */
	private function note_problem( $file, $message ) {
		$this->problems[] = sprintf(
			/* translators: 1: template file name, 2: problem description. */
			__( 'Bundled template "%1$s": %2$s', 'qevora-layouts' ),
			basename( (string) $file ),
			(string) $message
		);
	}

	/**
	 * Reads the remote template index.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $force Whether to bypass the cache.
	 * @return Template[]
	 */
	public function remote( $force = false ) {
		if ( ! Remote_Source::is_enabled() ) {
			return array();
		}

		$index = Remote_Source::fetch_index( $force );

		if ( is_wp_error( $index ) ) {
			$this->problems[] = $index->get_error_message();

			return array();
		}

		$result = Template_Validator::validate_payload(
			array( 'templates' => $index ),
			array(
				'origin'     => 'remote',
				'require_id' => true,
			)
		);

		foreach ( $result['errors'] as $error ) {
			$this->problems[] = $error;
		}

		$templates = array();

		foreach ( $result['templates'] as $template ) {
			$templates[ $template->id ] = $template;
		}

		return $templates;
	}

	/**
	 * Returns every template, bundled first.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $include_remote Whether to query the remote endpoint.
	 * @return Template[]
	 */
	public function all( $include_remote = true ) {
		$templates = $this->bundled();

		if ( $include_remote ) {
			foreach ( $this->remote() as $id => $template ) {
				if ( isset( $templates[ $id ] ) ) {
					// Bundled templates win so a remote source cannot shadow them.
					$this->problems[] = sprintf(
						/* translators: %s: template identifier. */
						__( 'Remote template "%s" was ignored because a bundled template uses the same identifier.', 'qevora-layouts' ),
						$id
					);

					continue;
				}

				$templates[ $id ] = $template;
			}
		}

		return $templates;
	}

	/**
	 * Finds one template.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id     Template identifier.
	 * @param string $origin Optional origin filter (bundled|remote).
	 * @return Template|null
	 */
	public function find( $id, $origin = '' ) {
		$id = is_string( $id ) ? $id : '';

		if ( '' === $id ) {
			return null;
		}

		if ( 'remote' === $origin ) {
			$templates = $this->remote();

			return isset( $templates[ $id ] ) ? $templates[ $id ] : null;
		}

		$templates = $this->all( '' === $origin );

		return isset( $templates[ $id ] ) ? $templates[ $id ] : null;
	}

	/**
	 * Returns problems found while reading the library.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public function get_problems() {
		return $this->problems;
	}

	/**
	 * Returns library data shaped for the admin UI or the REST API.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $args {
	 *     Optional filters.
	 *
	 *     @type string $type   Layout type.
	 *     @type string $origin bundled|remote.
	 *     @type string $search Free text search.
	 * }
	 * @return array<int,array<string,mixed>>
	 */
	public function for_display( array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'type'   => '',
				'origin' => '',
				'search' => '',
			)
		);

		$include_remote = '' !== $args['origin'] ? 'remote' === $args['origin'] : true;
		$templates      = $this->all( $include_remote );
		$search         = '' !== $args['search'] ? strtolower( (string) $args['search'] ) : '';
		$out            = array();

		foreach ( $templates as $template ) {
			if ( '' !== $args['origin'] && $args['origin'] !== $template->origin ) {
				continue;
			}

			if ( '' !== $args['type'] && $args['type'] !== $template->type ) {
				continue;
			}

			if ( '' !== $search ) {
				$haystack = strtolower( $template->name . ' ' . $template->description . ' ' . implode( ' ', $template->tags ) );

				if ( false === strpos( $haystack, $search ) ) {
					continue;
				}
			}

			$out[] = $template->to_array();
		}

		return $out;
	}

	/**
	 * Returns template counts per layout type.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,int>
	 */
	public function count_by_type() {
		$counts = array();

		foreach ( $this->all() as $template ) {
			$counts[ $template->type ] = isset( $counts[ $template->type ] ) ? $counts[ $template->type ] + 1 : 1;
		}

		return $counts;
	}

	/**
	 * Drops cached library data.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function clear_cache() {
		$this->bundled  = null;
		$this->problems = array();

		Remote_Source::clear_cache();
		Cache::bump_version();
	}
}
