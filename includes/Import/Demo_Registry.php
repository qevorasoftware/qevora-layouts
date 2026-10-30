<?php
/**
 * Registry of the bundled PHP demos.
 *
 * Demos live in `templates/demos/<slug>/` and are rendered by the plugin
 * itself - plain HTML in `template.php`, a scoped stylesheet in `style.css`
 * and optional vanilla JavaScript in `script.js`. Every demo keeps its own
 * files, so data and assets never mix between demos. Nothing here executes
 * user-supplied code: only folders shipped inside the plugin are read and
 * file access is restricted to a fixed allow list.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Import;

use QevoraLayouts\Core\Layout_Post_Type;
use QevoraLayouts\Core\Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Discovers, validates and serves the bundled PHP demos.
 *
 * @since 1.0.0
 */
class Demo_Registry {

	/**
	 * Folder (relative to the plugin root) that holds the demos.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const BASE_DIR = 'templates/demos';

	/**
	 * Files a demo folder may contain and that may be resolved or loaded.
	 *
	 * @since 1.0.0
	 * @var   string[]
	 */
	const ALLOWED_FILES = array( 'demo.json', 'template.php', 'style.css', 'script.js', 'settings.php', 'preview.png' );

	/**
	 * Field types a demo settings schema may declare.
	 *
	 * @since 1.0.0
	 * @var   string[]
	 */
	const FIELD_TYPES = array( 'text', 'textarea', 'url', 'checkbox', 'select', 'color', 'number' );

	/**
	 * Validated demos, cached per request. Null until the first scan.
	 *
	 * @since 1.0.0
	 * @var   array<string,array<string,mixed>>|null
	 */
	private static $demos = null;

	/**
	 * Loaded settings schemas, cached per request. Slug => field definitions.
	 *
	 * @since 1.0.0
	 * @var   array<string,array<string,array<string,mixed>>>
	 */
	private static $schemas = array();

	/**
	 * Returns every valid bundled demo, keyed by slug.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function demos() {
		if ( null !== self::$demos ) {
			return self::$demos;
		}

		$demos = array();
		$base  = self::base_path();

		if ( '' !== $base && is_dir( $base ) ) {
			$folders = scandir( $base );

			foreach ( (array) $folders as $folder ) {
				if ( ! is_string( $folder ) || '.' === $folder || '..' === $folder ) {
					continue;
				}

				$demo = self::validate( $folder, $base . $folder );

				if ( null !== $demo ) {
					$demos[ $demo['id'] ] = $demo;
				}
			}
		}

		/**
		 * Filters the bundled PHP demos offered in the template library.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,array<string,mixed>> $demos Validated demos keyed by slug.
		 */
		self::$demos = (array) apply_filters( 'qevora_layouts_demos', $demos );

		return self::$demos;
	}

	/**
	 * Returns one demo manifest.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Demo slug.
	 * @return array<string,mixed>|null Null when unknown.
	 */
	public static function get( $slug ) {
		$slug = Layout_Post_Type::sanitize_identifier( $slug );
		$demos = self::demos();

		return isset( $demos[ $slug ] ) ? $demos[ $slug ] : null;
	}

	/**
	 * Whether a demo slug is known.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Demo slug.
	 * @return bool
	 */
	public static function exists( $slug ) {
		return null !== self::get( $slug );
	}

	/**
	 * Resolves the absolute path of one demo file.
	 *
	 * Only allow-listed file names inside the demo's own folder resolve;
	 * anything else (traversal, unknown names) returns an empty string.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Demo slug.
	 * @param string $file Allow-listed file name.
	 * @return string Absolute path or an empty string.
	 */
	public static function path( $slug, $file ) {
		$demo = self::get( $slug );

		if ( null === $demo || ! in_array( $file, self::ALLOWED_FILES, true ) ) {
			return '';
		}

		$full = self::base_path() . $demo['id'] . '/' . $file;
		$real = realpath( $full );
		$base = realpath( self::base_path() );

		if ( ! is_string( $real ) || ! is_string( $base ) || ! is_file( $real ) ) {
			return '';
		}

		if ( 0 !== strpos( $real, $base . DIRECTORY_SEPARATOR ) ) {
			return '';
		}

		return $real;
	}

	/**
	 * Public URL of one demo file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Demo slug.
	 * @param string $file Allow-listed file name.
	 * @return string URL, empty when the file does not exist.
	 */
	public static function url( $slug, $file ) {
		if ( '' === self::path( $slug, $file ) ) {
			return '';
		}

		return QEVORA_LAYOUTS_URL . self::BASE_DIR . '/' . $slug . '/' . $file;
	}

	/**
	 * Registers and enqueues the demo's own stylesheet and script.
	 *
	 * Called when a demo layout is planned (so assets land in `wp_head`)
	 * and again at render time as a safety net for manual renders.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Demo slug.
	 * @return void
	 */
	public static function enqueue_assets( $slug ) {
		$demo = self::get( $slug );

		if ( null === $demo || ! function_exists( 'wp_enqueue_style' ) ) {
			return;
		}

		$style = self::url( $demo['id'], 'style.css' );

		if ( '' !== $style ) {
			wp_register_style( 'qevora-demo-' . $demo['id'], $style, array(), QEVORA_LAYOUTS_VERSION );
			wp_enqueue_style( 'qevora-demo-' . $demo['id'] );
		}

		$script = self::url( $demo['id'], 'script.js' );

		if ( '' !== $script && function_exists( 'wp_enqueue_script' ) ) {
			wp_register_script( 'qevora-demo-' . $demo['id'] . '-js', $script, array(), QEVORA_LAYOUTS_VERSION, true );
			wp_enqueue_script( 'qevora-demo-' . $demo['id'] . '-js' );
		}
	}

	/**
	 * Demo cards for the template library screen.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function for_display() {
		$cards = array();

		foreach ( self::demos() as $demo ) {
			$cards[] = array(
				'id'          => (string) $demo['id'],
				'name'        => (string) $demo['name'],
				'type'        => (string) $demo['type'],
				'type_label'  => Layout_Post_Type::get_type_label( (string) $demo['type'] ),
				'description' => (string) $demo['description'],
				'menu'        => ! empty( $demo['menu'] ),
				'menu_label'  => isset( $demo['menu_label'] ) ? (string) $demo['menu_label'] : '',
				'preview_url' => self::url( (string) $demo['id'], 'preview.png' ),
			);
		}

		return $cards;
	}

	/**
	 * Settings schema of one demo, loaded from its own `settings.php`.
	 *
	 * Every demo keeps its data in its own PHP file: this returns the
	 * field definitions (label, type, default, options, help) that both
	 * the edit-screen metabox and the template render from. Unknown or
	 * malformed entries are dropped, so a bad schema can never reach
	 * the screen or the front end.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Demo slug.
	 * @return array<string,array<string,mixed>> Field key => definition.
	 */
	public static function settings_schema( $slug ) {
		$slug = Layout_Post_Type::sanitize_identifier( $slug );

		if ( array_key_exists( $slug, self::$schemas ) ) {
			return self::$schemas[ $slug ];
		}

		$fields = array();
		$path   = self::path( $slug, 'settings.php' );

		if ( '' !== $path ) {
			$raw = include $path; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.NotAbsolutePath -- Resolved through the registry allow list.

			if ( is_array( $raw ) && isset( $raw['fields'] ) && is_array( $raw['fields'] ) ) {
				foreach ( $raw['fields'] as $key => $field ) {
					$key = sanitize_key( (string) $key );

					if ( '' === $key || ! is_array( $field ) ) {
						continue;
					}

					$type = isset( $field['type'] ) && in_array( (string) $field['type'], self::FIELD_TYPES, true ) ? (string) $field['type'] : 'text';
					$options = array();

					if ( 'select' === $type && isset( $field['options'] ) && is_array( $field['options'] ) ) {
						foreach ( $field['options'] as $option_value => $option_label ) {
							$options[ sanitize_key( (string) $option_value ) ] = sanitize_text_field( (string) $option_label );
						}
					}

					$fields[ $key ] = array(
						'label'   => isset( $field['label'] ) ? sanitize_text_field( (string) $field['label'] ) : $key,
						'type'    => $type,
						'default' => self::setting_default( $type, isset( $field['default'] ) ? $field['default'] : null, $options ),
						'help'    => isset( $field['help'] ) ? sanitize_text_field( (string) $field['help'] ) : '',
						'options' => $options,
					);
				}
			}
		}

		self::$schemas[ $slug ] = $fields;

		return $fields;
	}

	/**
	 * Default values of every field in a demo's settings schema.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Demo slug.
	 * @return array<string,mixed>
	 */
	public static function settings_defaults( $slug ) {
		$defaults = array();

		foreach ( self::settings_schema( $slug ) as $key => $field ) {
			$defaults[ $key ] = $field['default'];
		}

		return $defaults;
	}

	/**
	 * Sanitises submitted settings against a demo's schema.
	 *
	 * Only keys the demo declares survive, each cleaned for its field
	 * type. Anything else is dropped, so stored settings can never
	 * carry markup, scripts or unexpected shapes.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $slug   Demo slug.
	 * @param mixed                $values Raw values (usually $_POST data).
	 * @return array<string,mixed> Clean values, missing keys omitted.
	 */
	public static function sanitize_settings( $slug, $values ) {
		$clean = array();

		if ( ! is_array( $values ) ) {
			return $clean;
		}

		foreach ( self::settings_schema( $slug ) as $key => $field ) {
			if ( ! array_key_exists( $key, $values ) ) {
				continue;
			}

			$value = $values[ $key ];

			switch ( $field['type'] ) {
				case 'checkbox':
					$clean[ $key ] = (bool) $value;
					break;
				case 'number':
					$clean[ $key ] = is_numeric( $value ) ? (int) $value : (int) $field['default'];
					break;
				case 'url':
					$clean[ $key ] = esc_url_raw( (string) $value );
					break;
				case 'color':
					$hex = strtolower( sanitize_text_field( (string) $value ) );
					$clean[ $key ] = ( 1 === preg_match( '/^#[0-9a-f]{6}$/', $hex ) ) ? $hex : (string) $field['default'];
					break;
				case 'select':
					$selected = sanitize_key( (string) $value );
					$clean[ $key ] = array_key_exists( $selected, $field['options'] ) ? $selected : $field['default'];
					break;
				case 'textarea':
					$clean[ $key ] = sanitize_textarea_field( (string) $value );
					break;
				default:
					$clean[ $key ] = sanitize_text_field( (string) $value );
			}
		}

		return $clean;
	}

	/**
	 * Merges stored settings over a demo's defaults.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $slug  Demo slug.
	 * @param array<string,mixed>  $saved Stored settings (may be partial).
	 * @return array<string,mixed> Every schema key, sanitised.
	 */
	public static function get_settings( $slug, $saved = array() ) {
		$defaults = self::settings_defaults( $slug );
		$stored   = self::sanitize_settings( $slug, is_array( $saved ) ? $saved : array() );

		return array_merge( $defaults, $stored );
	}

	/**
	 * Coerces one schema default to its field type.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $type    Field type.
	 * @param mixed                $default Declared default.
	 * @param array<string,string> $options Select options.
	 * @return mixed
	 */
	private static function setting_default( $type, $default, $options ) {
		switch ( $type ) {
			case 'checkbox':
				return (bool) $default;
			case 'number':
				return is_numeric( $default ) ? (int) $default : 0;
			case 'color':
				$hex = strtolower( (string) $default );
				return ( 1 === preg_match( '/^#[0-9a-f]{6}$/', $hex ) ) ? $hex : '#2271b1';
			case 'select':
				$selected = sanitize_key( (string) $default );
				if ( array_key_exists( $selected, $options ) ) {
					return $selected;
				}
				$keys = array_keys( $options );
				return empty( $keys ) ? '' : (string) $keys[0];
			default:
				return sanitize_textarea_field( (string) $default );
		}
	}

	/**
	 * Resets the request cache. Used by tests.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function reset() {
		self::$demos   = null;
		self::$schemas = array();
	}

	/**
	 * Absolute path of the demos folder, with a trailing slash.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private static function base_path() {
		return QEVORA_LAYOUTS_DIR . self::BASE_DIR . '/';
	}

	/**
	 * Validates one demo folder and its manifest.
	 *
	 * @since 1.0.0
	 *
	 * @param string $folder Folder name.
	 * @param string $dir    Absolute folder path.
	 * @return array<string,mixed>|null Validated manifest, null when invalid.
	 */
	private static function validate( $folder, $dir ) {
		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9\-]{1,58}[a-z0-9]$/', (string) $folder ) || ! is_dir( $dir ) ) {
			return null;
		}

		$file = $dir . '/demo.json';

		if ( ! is_file( $file ) || ! is_file( $dir . '/template.php' ) ) {
			return null;
		}

		$raw = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local, bundled file.

		if ( ! is_array( $raw ) ) {
			Logger::warning( 'Demo manifest is not valid JSON.', array( 'demo' => (string) $folder ) );

			return null;
		}

		$id   = isset( $raw['id'] ) ? Layout_Post_Type::sanitize_identifier( $raw['id'] ) : '';
		$type = isset( $raw['type'] ) ? Layout_Post_Type::sanitize_type( $raw['type'] ) : '';
		$name = isset( $raw['name'] ) ? sanitize_text_field( (string) $raw['name'] ) : '';

		if ( $id !== $folder || '' === $name || ! in_array( $type, array( 'header', 'footer', 'section' ), true ) ) {
			Logger::warning( 'Demo manifest failed validation.', array( 'demo' => (string) $folder ) );

			return null;
		}

		return array(
			'id'          => $id,
			'name'        => $name,
			'type'        => $type,
			'description' => isset( $raw['description'] ) ? sanitize_text_field( (string) $raw['description'] ) : '',
			'menu'        => ! empty( $raw['menu'] ),
			'menu_label'  => isset( $raw['menu_label'] ) ? sanitize_text_field( (string) $raw['menu_label'] ) : '',
			'tags'        => isset( $raw['tags'] ) && is_array( $raw['tags'] ) ? array_map( 'sanitize_key', $raw['tags'] ) : array(),
		);
	}
}
