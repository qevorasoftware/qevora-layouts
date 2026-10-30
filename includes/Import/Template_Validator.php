<?php
/**
 * Template validation.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Import;

use QevoraLayouts\Conditions\Condition_Sanitizer;
use QevoraLayouts\Core\Layout_Post_Type;
use QevoraLayouts\Core\Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates and normalizes template payloads before anything is created.
 *
 * Imported data is treated as untrusted. The validator:
 *
 * - enforces the schema version,
 * - whitelists every key it knows and ignores the rest,
 * - rejects PHP tags, `<script>` elements and script URLs outright,
 * - runs content through `wp_kses_post()` unless the importing user holds the
 *   `unfiltered_html` capability (the same rule WordPress applies to posts),
 * - normalizes display conditions through the condition sanitizer,
 * - checks preview paths against path traversal and preview URLs against the
 *   https scheme,
 * - never executes, includes or evaluates anything from the payload.
 *
 * @since 1.0.0
 */
class Template_Validator {

	/**
	 * Hard upper limit for template content, in bytes.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	const ABSOLUTE_MAX_CONTENT_BYTES = 5242880; // 5 MB.

	/**
	 * Validates one template payload.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed            $data    Raw payload.
	 * @param array<string,mixed> $options {
	 *     Optional. Validation options.
	 *
	 *     @type string $origin      bundled|remote|upload. Default upload.
	 *     @type bool   $require_id  Whether `id` is mandatory. Default false.
	 *     @type int    $max_bytes   Content size limit in bytes.
	 * }
	 * @return array{ok:bool,template:?Template,errors:string[],warnings:string[]}
	 */
	public static function validate( $data, array $options = array() ) {
		$options = wp_parse_args(
			$options,
			array(
				'origin'     => 'upload',
				'require_id' => false,
				'max_bytes'  => 0,
			)
		);

		$errors   = array();
		$warnings = array();
		$origin   = in_array( $options['origin'], array( 'bundled', 'remote', 'upload' ), true ) ? $options['origin'] : 'upload';

		if ( ! is_array( $data ) ) {
			return self::result( null, array( __( 'The template file does not contain a valid object.', 'qevora-layouts' ) ), $warnings );
		}

		// 1. Schema -------------------------------------------------------.
		if ( isset( $data['$schema'] ) && ! self::schema_is_supported( $data['$schema'] ) ) {
			$errors[] = sprintf(
				/* translators: 1: schema found in the file, 2: schema supported by this version. */
				__( 'Unsupported template schema "%1$s". This version supports "%2$s".', 'qevora-layouts' ),
				is_scalar( $data['$schema'] ) ? (string) $data['$schema'] : gettype( $data['$schema'] ),
				Template::SCHEMA
			);
		}

		// 2. Name ---------------------------------------------------------.
		$name = isset( $data['name'] ) && is_scalar( $data['name'] ) ? trim( sanitize_text_field( (string) $data['name'] ) ) : '';

		if ( '' === $name ) {
			$errors[] = __( 'The template is missing a name.', 'qevora-layouts' );
		} elseif ( strlen( $name ) > 120 ) {
			$name = substr( $name, 0, 120 );
		}

		// 3. Type ---------------------------------------------------------.
		$type = isset( $data['type'] ) ? $data['type'] : '';

		if ( ! Layout_Post_Type::is_valid_type( $type ) ) {
			$errors[] = sprintf(
				/* translators: %s: list of supported layout types. */
				__( 'Unknown layout type. Supported types: %s.', 'qevora-layouts' ),
				implode( ', ', Layout_Post_Type::get_type_slugs() )
			);
			$type = 'header';
		}

		// 4. Identifier ---------------------------------------------------.
		$id = Layout_Post_Type::sanitize_identifier( isset( $data['id'] ) ? $data['id'] : '' );

		if ( '' === $id ) {
			if ( ! empty( $options['require_id'] ) ) {
				$errors[] = __( 'The template is missing an identifier.', 'qevora-layouts' );
			}

			$id = self::generate_id( $name );

			if ( '' !== $id ) {
				$warnings[] = __( 'The template had no identifier, one was generated from its name.', 'qevora-layouts' );
			}
		}

		// 5. Builder ------------------------------------------------------.
		$builder = Layout_Post_Type::sanitize_builder( isset( $data['builder'] ) ? $data['builder'] : 'block' );

		if ( 'elementor' === $builder ) {
			$errors[] = __( 'Elementor template files cannot be imported here. Import them in Elementor → Templates → Saved Templates and then reference the template from a layout (Layout → Content source → Reference existing content).', 'qevora-layouts' );
		}

		// 6. Content ------------------------------------------------------.
		$content  = isset( $data['content'] ) ? $data['content'] : '';
		$max_size = self::max_content_bytes( (int) $options['max_bytes'] );

		$reference_url = self::validate_reference_url( isset( $data['url'] ) ? $data['url'] : '', $origin, $warnings );

		if ( ! is_string( $content ) ) {
			$errors[] = __( 'The template content must be a string.', 'qevora-layouts' );
			$content = '';
		} elseif ( '' === trim( $content ) && '' !== $reference_url ) {
			// A remote index entry that points at the file holding the content.
			$content = '';
		} else {
			$content = str_replace( array( "\r\n", "\r" ), "\n", $content );

			if ( '' === trim( $content ) && '' === $reference_url ) {
				$errors[] = __( 'The template content is empty.', 'qevora-layouts' );
			}

			if ( strlen( $content ) > $max_size ) {
				$errors[] = sprintf(
					/* translators: %d: maximum size in kilobytes. */
					__( 'The template content is larger than the allowed %d KB.', 'qevora-layouts' ),
					(int) round( $max_size / 1024 )
				);
				$content = '';
			}

			$forbidden = self::find_forbidden_patterns( $content );

			if ( ! empty( $forbidden ) ) {
				$errors[] = sprintf(
					/* translators: %s: comma separated list of rejected patterns. */
					__( 'The template contains code that is not allowed in layouts: %s.', 'qevora-layouts' ),
					implode( ', ', $forbidden )
				);
				$content = '';
			}
		}

		if ( '' !== $content && ! current_user_can( 'unfiltered_html' ) ) {
			$filtered = wp_kses_post( $content );

			if ( $filtered !== $content ) {
				$warnings[] = __( 'Some markup was removed because your account is not allowed to publish unfiltered HTML.', 'qevora-layouts' );
			}

			$content = $filtered;
		}

		// 7. Conditions ---------------------------------------------------.
		$raw_conditions = isset( $data['conditions'] ) ? $data['conditions'] : array();
		$conditions     = Condition_Sanitizer::normalize( $raw_conditions );

		if ( self::count_rules( $raw_conditions ) !== self::count_rules( $conditions ) ) {
			$warnings[] = __( 'Some display conditions were removed because they are unknown or incomplete.', 'qevora-layouts' );
		}

		// 8. Placement / priority ----------------------------------------.
		$placement = Layout_Post_Type::sanitize_placement( isset( $data['placement'] ) ? $data['placement'] : '' );
		$priority  = Layout_Post_Type::sanitize_priority( isset( $data['priority'] ) ? $data['priority'] : 10 );

		// 9. Preview ------------------------------------------------------.
		$preview = self::validate_preview( isset( $data['preview'] ) ? $data['preview'] : '', $origin, $warnings );

		// 10. Misc --------------------------------------------------------.
		$tags     = self::validate_tags( isset( $data['tags'] ) ? $data['tags'] : array() );
		$requires = self::validate_requires( isset( $data['requires'] ) ? $data['requires'] : array(), $warnings );

		$known = array( '$schema', 'id', 'name', 'description', 'type', 'builder', 'content', 'conditions', 'priority', 'placement', 'preview', 'tags', 'requires', 'version', 'author', 'license', 'url' );
		$extra = array_diff( array_keys( $data ), $known );

		if ( ! empty( $extra ) ) {
			$warnings[] = sprintf(
				/* translators: %s: comma separated list of ignored keys. */
				__( 'Ignored unknown template fields: %s.', 'qevora-layouts' ),
				implode( ', ', array_slice( $extra, 0, 10 ) )
			);
		}

		if ( ! empty( $errors ) ) {
			return self::result( null, $errors, $warnings );
		}

		$template = Template::from_array(
			array(
				'id'          => $id,
				'name'        => $name,
				'description' => isset( $data['description'] ) ? sanitize_textarea_field( (string) $data['description'] ) : '',
				'type'        => $type,
				'builder'     => $builder,
				'content'     => $content,
				'conditions'  => $conditions,
				'priority'    => $priority,
				'placement'   => $placement,
				'preview'     => $preview,
				'tags'        => $tags,
				'requires'    => $requires,
				'origin'      => $origin,
				'url'         => $reference_url,
				'version'     => isset( $data['version'] ) ? sanitize_text_field( (string) $data['version'] ) : '1.0.0',
				'author'      => isset( $data['author'] ) ? sanitize_text_field( (string) $data['author'] ) : '',
				'license'     => isset( $data['license'] ) ? sanitize_text_field( (string) $data['license'] ) : '',
			)
		);

		return self::result( $template, $errors, $warnings );
	}

	/**
	 * Validates a payload that may hold one template or a list of templates.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed            $payload Decoded JSON payload.
	 * @param array<string,mixed> $options Validation options.
	 * @return array{ok:bool,templates:Template[],errors:string[],warnings:string[]}
	 */
	public static function validate_payload( $payload, array $options = array() ) {
		$items = array();

		if ( is_array( $payload ) && isset( $payload['templates'] ) && is_array( $payload['templates'] ) ) {
			$items = $payload['templates'];
		} elseif ( is_array( $payload ) ) {
			$items = array( $payload );
		}

		if ( empty( $items ) ) {
			return array(
				'ok'        => false,
				'templates' => array(),
				'errors'    => array( __( 'No templates found in this file.', 'qevora-layouts' ) ),
				'warnings'  => array(),
			);
		}

		$templates = array();
		$errors    = array();
		$warnings  = array();
		$seen      = array();

		foreach ( array_slice( $items, 0, 50 ) as $index => $item ) {
			$result = self::validate( $item, $options );

			foreach ( $result['warnings'] as $warning ) {
				$warnings[] = sprintf(
					/* translators: 1: template position in the file, 2: warning message. */
					__( 'Template #%1$d: %2$s', 'qevora-layouts' ),
					$index + 1,
					$warning
				);
			}

			if ( ! $result['ok'] || null === $result['template'] ) {
				foreach ( $result['errors'] as $error ) {
					$errors[] = sprintf(
						/* translators: 1: template position in the file, 2: error message. */
						__( 'Template #%1$d: %2$s', 'qevora-layouts' ),
						$index + 1,
						$error
					);
				}

				continue;
			}

			if ( in_array( $result['template']->id, $seen, true ) ) {
				$errors[] = sprintf(
					/* translators: %s: duplicated template identifier. */
					__( 'The file contains the template "%s" more than once.', 'qevora-layouts' ),
					$result['template']->id
				);

				continue;
			}

			$seen[]      = $result['template']->id;
			$templates[] = $result['template'];
		}

		return array(
			'ok'        => empty( $errors ) && ! empty( $templates ),
			'templates' => $templates,
			'errors'    => $errors,
			'warnings'  => $warnings,
		);
	}

	/**
	 * Whether a schema identifier is supported.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $schema Schema identifier.
	 * @return bool
	 */
	public static function schema_is_supported( $schema ) {
		if ( ! is_string( $schema ) ) {
			return false;
		}

		$supported = array( Template::SCHEMA );

		/**
		 * Filters the accepted template schema identifiers.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $supported Supported schema identifiers.
		 */
		$supported = (array) apply_filters( 'qevora_layouts_template_schemas', $supported );

		return in_array( $schema, $supported, true );
	}

	/**
	 * Returns the largest accepted content size in bytes.
	 *
	 * @since 1.0.0
	 *
	 * @param int $requested Requested limit, 0 for the configured default.
	 * @return int
	 */
	public static function max_content_bytes( $requested = 0 ) {
		$configured = max( 16, (int) Settings::get( 'max_import_size_kb', 2048 ) ) * 1024;
		$limit      = $requested > 0 ? (int) $requested : $configured;

		return min( $limit, self::ABSOLUTE_MAX_CONTENT_BYTES );
	}

	/**
	 * Finds forbidden patterns in template content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Candidate content.
	 * @return string[] Human readable names of the patterns found.
	 */
	public static function find_forbidden_patterns( $content ) {
		$content = (string) $content;
		$found   = array();

		$patterns = array(
			'php_open_tag'  => '/<\?(?:php|\s|=)/i',
			'script_tag'    => '/<\s*script/i',
			'script_url'    => '/javascript\s*:/i',
			'data_html_url' => '/data\s*:\s*text\/html/i',
			'iframe_tag'    => '/<\s*iframe/i',
			'object_tag'    => '/<\s*(?:object|embed)\b/i',
			'event_handler' => '/\son[a-z]{3,20}\s*=/i',
		);

		$labels = array(
			'php_open_tag'  => __( 'PHP tags', 'qevora-layouts' ),
			'script_tag'    => __( 'script elements', 'qevora-layouts' ),
			'script_url'    => __( 'javascript: URLs', 'qevora-layouts' ),
			'data_html_url' => __( 'data: HTML URLs', 'qevora-layouts' ),
			'iframe_tag'    => __( 'iframes', 'qevora-layouts' ),
			'object_tag'    => __( 'object/embed elements', 'qevora-layouts' ),
			'event_handler' => __( 'inline event handlers', 'qevora-layouts' ),
		);

		/**
		 * Filters the patterns that must never appear in imported content.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,string> $patterns Pattern name => regular expression.
		 */
		$patterns = (array) apply_filters( 'qevora_layouts_forbidden_content_patterns', $patterns );

		foreach ( $patterns as $name => $pattern ) {
			if ( ! is_string( $pattern ) || '' === $pattern ) {
				continue;
			}

			$matched = preg_match( $pattern, $content );

			if ( false === $matched ) {
				// An invalid filter value must not silently disable the check.
				$found[] = (string) $name;
				continue;
			}

			if ( 1 === $matched ) {
				$found[] = isset( $labels[ $name ] ) ? $labels[ $name ] : (string) $name;
			}
		}

		return array_values( array_unique( $found ) );
	}

	/**
	 * Validates the optional download URL of a remote index entry.
	 *
	 * Only remote templates may carry a URL, and only https is accepted. The
	 * host is verified against the configured endpoint by
	 * {@see Remote_Source::url_is_allowed()} before anything is fetched.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed    $url      Raw URL.
	 * @param string   $origin   Template origin.
	 * @param string[] $warnings Warnings are appended here.
	 * @return string Accepted https URL or empty string.
	 */
	private static function validate_reference_url( $url, $origin, array &$warnings ) {
		if ( ! is_string( $url ) || '' === trim( $url ) ) {
			return '';
		}

		if ( 'remote' !== $origin ) {
			$warnings[] = __( 'The template download URL was ignored because the template is not from a remote library.', 'qevora-layouts' );

			return '';
		}

		$clean = esc_url_raw( trim( $url ), array( 'https' ) );
		$parts = '' === $clean ? array() : (array) wp_parse_url( $clean );

		if ( '' === $clean || empty( $parts['scheme'] ) || 'https' !== strtolower( (string) $parts['scheme'] ) || empty( $parts['host'] ) ) {
			$warnings[] = __( 'The template download URL was rejected because it is not a valid https URL.', 'qevora-layouts' );

			return '';
		}

		return $clean;
	}

	/**
	 * Validates a preview value.
	 *
	 * Bundled templates may reference a file inside the plugin directory only.
	 * Remote and uploaded templates may reference an https image URL.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed    $preview  Raw preview value.
	 * @param string   $origin   Template origin.
	 * @param string[] $warnings Warnings are appended here.
	 * @return string Accepted preview value or empty string.
	 */
	private static function validate_preview( $preview, $origin, array &$warnings ) {
		if ( ! is_string( $preview ) || '' === trim( $preview ) ) {
			return '';
		}

		$preview = trim( $preview );

		if ( preg_match( '#^https://#i', $preview ) ) {
			$url = esc_url_raw( $preview, array( 'https' ) );

			if ( '' === $url ) {
				$warnings[] = __( 'The template preview URL was rejected and will not be shown.', 'qevora-layouts' );

				return '';
			}

			return $url;
		}

		if ( 'bundled' !== $origin ) {
			$warnings[] = __( 'The template preview was ignored because it is not an https URL.', 'qevora-layouts' );

			return '';
		}

		$relative = ltrim( str_replace( '\\', '/', $preview ), '/' );

		if ( false !== strpos( $relative, '..' ) || false !== strpos( $relative, '://' ) ) {
			$warnings[] = __( 'The template preview path was rejected.', 'qevora-layouts' );

			return '';
		}

		$path = realpath( QEVORA_LAYOUTS_DIR . $relative );
		$root = realpath( QEVORA_LAYOUTS_DIR );

		if ( false === $path || false === $root || 0 !== strncmp( $path, $root, strlen( $root ) ) || ! is_readable( $path ) ) {
			$warnings[] = __( 'The template preview file does not exist and will not be shown.', 'qevora-layouts' );

			return '';
		}

		return $relative;
	}

	/**
	 * Validates template tags.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $tags Raw tags.
	 * @return string[]
	 */
	private static function validate_tags( $tags ) {
		if ( ! is_array( $tags ) ) {
			return array();
		}

		$clean = array();

		foreach ( $tags as $tag ) {
			if ( ! is_scalar( $tag ) ) {
				continue;
			}

			$tag = sanitize_text_field( (string) $tag );

			if ( '' === $tag || strlen( $tag ) > 40 ) {
				continue;
			}

			if ( ! in_array( $tag, $clean, true ) ) {
				$clean[] = $tag;
			}

			if ( count( $clean ) >= 8 ) {
				break;
			}
		}

		return $clean;
	}

	/**
	 * Validates the requirements block.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed    $requires Raw requirements.
	 * @param string[] $warnings Warnings are appended here.
	 * @return array<string,mixed>
	 */
	private static function validate_requires( $requires, array &$warnings ) {
		$clean = array();

		if ( ! is_array( $requires ) ) {
			return $clean;
		}

		foreach ( array( 'wordpress', 'php' ) as $key ) {
			if ( ! isset( $requires[ $key ] ) ) {
				continue;
			}

			$value = is_scalar( $requires[ $key ] ) ? trim( (string) $requires[ $key ] ) : '';

			if ( 1 === preg_match( '/^[0-9][0-9a-z.\-]{0,20}$/i', $value ) ) {
				$clean[ $key ] = $value;
			} else {
				$warnings[] = sprintf(
					/* translators: %s: requirement key such as "php". */
					__( 'Ignored an invalid "%s" requirement.', 'qevora-layouts' ),
					$key
				);
			}
		}

		if ( isset( $requires['plugins'] ) && is_array( $requires['plugins'] ) ) {
			$plugins = array();

			foreach ( array_slice( $requires['plugins'], 0, 10 ) as $plugin ) {
				if ( is_string( $plugin ) ) {
					$plugin = array( 'file' => $plugin );
				}

				if ( ! is_array( $plugin ) || empty( $plugin['file'] ) || ! is_string( $plugin['file'] ) ) {
					continue;
				}

				$file = sanitize_text_field( $plugin['file'] );

				if ( 1 !== preg_match( '#^[a-z0-9_\-]+/[a-z0-9_\-.]+$#i', $file ) ) {
					$warnings[] = sprintf(
						/* translators: %s: plugin file reference. */
						__( 'Ignored an invalid plugin requirement "%s".', 'qevora-layouts' ),
						$file
					);

					continue;
				}

				$plugins[] = array(
					'file' => $file,
					'name' => isset( $plugin['name'] ) ? sanitize_text_field( (string) $plugin['name'] ) : $file,
				);
			}

			if ( ! empty( $plugins ) ) {
				$clean['plugins'] = $plugins;
			}
		}

		return $clean;
	}

	/**
	 * Generates a template identifier from a name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Template name.
	 * @return string
	 */
	public static function generate_id( $name ) {
		$name = (string) $name;

		if ( function_exists( 'remove_accents' ) ) {
			$name = remove_accents( $name );
		}

		$name = strtolower( $name );
		$name = preg_replace( '/[^a-z0-9]+/', '-', $name );
		$name = trim( (string) $name, '-' );

		if ( '' === $name ) {
			return '';
		}

		return substr( $name, 0, 100 );
	}

	/**
	 * Counts rules in a raw or normalized condition structure.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $conditions Conditions.
	 * @return int
	 */
	private static function count_rules( $conditions ) {
		if ( ! is_array( $conditions ) ) {
			return 0;
		}

		$count = 0;

		foreach ( array( 'include', 'exclude' ) as $list ) {
			if ( isset( $conditions[ $list ] ) && is_array( $conditions[ $list ] ) ) {
				$count += count( $conditions[ $list ] );
			}
		}

		return $count;
	}

	/**
	 * Builds a validation result.
	 *
	 * @since 1.0.0
	 *
	 * @param Template|null $template Validated template.
	 * @param string[]      $errors   Errors.
	 * @param string[]      $warnings Warnings.
	 * @return array{ok:bool,template:?Template,errors:string[],warnings:string[]}
	 */
	private static function result( ?Template $template, array $errors, array $warnings ) {
		return array(
			'ok'       => null !== $template && empty( $errors ),
			'template' => $template,
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}
}
