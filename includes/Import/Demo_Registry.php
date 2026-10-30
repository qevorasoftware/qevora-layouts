<?php
/**
 * Registry of the bundled PHP demos.
 *
 * One demo is exactly one PHP file: `templates/demos/<slug>.php` returns its
 * manifest, its customisable settings schema, its scoped stylesheet, its
 * vanilla JavaScript and the closure that renders its HTML. Nothing here
 * executes user-supplied code: only files shipped inside the plugin are
 * read, and file access is restricted to that one allow-listed path per
 * demo. The block editor is never involved - the admin screen exposes the
 * settings schema as a metabox and the front end renders the same file.
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
	 * Folder (relative to the plugin root) that holds the demo files.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const BASE_DIR = 'templates/demos';

	/**
	 * Field types a demo settings schema may declare.
	 *
	 * @since 1.0.0
	 * @var   string[]
	 */
	const FIELD_TYPES = array( 'text', 'textarea', 'url', 'image', 'checkbox', 'select', 'color', 'number' );

	/**
	 * Validated demo definitions, cached per request. Null until first scan.
	 *
	 * @since 1.0.0
	 * @var   array<string,array<string,mixed>>|null
	 */
	private static $defs = null;

	/**
	 * Loaded settings schemas, cached per request. Slug => field definitions.
	 *
	 * @since 1.0.0
	 * @var   array<string,array<string,array<string,mixed>>>
	 */
	private static $schemas = array();

	/**
	 * Returns every valid bundled demo manifest, keyed by slug.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function demos() {
		$defs = self::defs();
		$demos = array();

		foreach ( $defs as $slug => $def ) {
			$demos[ $slug ] = $def['manifest'];
		}

		return $demos;
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
		$def = self::def( $slug );

		return null === $def ? null : $def['manifest'];
	}

	/**
	 * Returns one complete demo definition (manifest, settings, css, js,
	 * render closure).
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Demo slug.
	 * @return array<string,mixed>|null Null when unknown.
	 */
	public static function def( $slug ) {
		$slug  = Layout_Post_Type::sanitize_identifier( $slug );
		$defs  = self::defs();

		return isset( $defs[ $slug ] ) ? $defs[ $slug ] : null;
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
		return null !== self::def( $slug );
	}

	/**
	 * Resolves the absolute path of one demo's PHP file.
	 *
	 * Only the demo's own file inside the demos folder resolves; anything
	 * else (traversal, unknown slugs) returns an empty string.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Demo slug.
	 * @return string Absolute path or an empty string.
	 */
	public static function path( $slug ) {
		$slug = Layout_Post_Type::sanitize_identifier( $slug );

		if ( '' === $slug || ! self::exists( $slug ) ) {
			return '';
		}

		$full = self::base_path() . $slug . '.php';
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
	 * Registers and enqueues the demo's own stylesheet and JavaScript.
	 *
	 * Both live inside the demo's single PHP file and are printed inline
	 * under per-demo handles, so assets never mix between demos and only
	 * load while that demo is planned or rendered.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Demo slug.
	 * @return void
	 */
	public static function enqueue_assets( $slug ) {
		$def = self::def( $slug );

		if ( null === $def || ! function_exists( 'wp_enqueue_style' ) ) {
			return;
		}

		$css = isset( $def['css'] ) ? (string) $def['css'] : '';

		if ( '' !== trim( $css ) ) {
			wp_register_style( 'qevora-demo-' . $def['manifest']['id'], false, array(), QEVORA_LAYOUTS_VERSION );
			wp_enqueue_style( 'qevora-demo-' . $def['manifest']['id'] );
			wp_add_inline_style( 'qevora-demo-' . $def['manifest']['id'], $css );
		}

		$js = isset( $def['js'] ) ? (string) $def['js'] : '';

		if ( '' !== trim( $js ) && function_exists( 'wp_enqueue_script' ) ) {
			wp_register_script( 'qevora-demo-' . $def['manifest']['id'] . '-js', false, array(), QEVORA_LAYOUTS_VERSION, true );
			wp_enqueue_script( 'qevora-demo-' . $def['manifest']['id'] . '-js' );
			wp_add_inline_script( 'qevora-demo-' . $def['manifest']['id'] . '-js', $js );
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
			);
		}

		return $cards;
	}

	/**
	 * Renders a demo exactly as the front end will, for admin previews.
	 *
	 * The library shows this live markup inside each card (and in the
	 * preview dialog), so the header or footer a user selects is literally
	 * the markup the site will render - no screenshot can drift away from
	 * the template. The preview runs with default settings and without a
	 * menu, wraps the demo's own CSS in a scoped style tag and is never
	 * interactive.
	 *
	 * @since 1.0.0
	 *
	 * @param string              $slug        Demo slug.
	 * @param int                 $instance_id Unique id suffix so several
	 *                                         previews of the same demo never
	 *                                         share DOM ids.
	 * @param array<string,mixed>|null $settings Settings to preview with;
	 *                                         null means the demo defaults.
	 * @return string HTML, empty for unknown demos.
	 */
	public static function preview_html( $slug, $instance_id = 0, $settings = null ) {
		$def = self::def( $slug );

		if ( null === $def || ! is_callable( $def['render'] ) ) {
			return '';
		}

		$layout   = (object) array(
			'id'      => (int) $instance_id,
			'preview' => true,
		);
		$settings = is_array( $settings ) ? self::get_settings( (string) $def['manifest']['id'], $settings ) : self::get_settings( (string) $def['manifest']['id'] );
		$html     = (string) call_user_func( $def['render'], $layout, $def['manifest'], 0, $settings );

		if ( '' === trim( $html ) ) {
			return '';
		}

		$css = isset( $def['css'] ) ? (string) $def['css'] : '';

		return '<style id="qevora-demo-preview-css-' . (string) $def['manifest']['id'] . '-' . (int) $instance_id . '">' . $css . '</style>' . $html;
	}

	/**
	 * Settings schema of one demo, from its own definition.
	 *
	 * Every demo keeps its data in its own PHP file: this returns the
	 * field definitions (label, type, default, options, help) that both
	 * the edit-screen metabox and the render closure use. Unknown or
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
		$def    = self::def( $slug );
		$raw    = ( null !== $def && isset( $def['settings']['fields'] ) && is_array( $def['settings']['fields'] ) ) ? $def['settings']['fields'] : array();

		foreach ( $raw as $key => $field ) {
			$key = sanitize_key( (string) $key );

			if ( '' === $key || ! is_array( $field ) ) {
				continue;
			}

			$type    = isset( $field['type'] ) && in_array( (string) $field['type'], self::FIELD_TYPES, true ) ? (string) $field['type'] : 'text';
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
	 * @param string $slug   Demo slug.
	 * @param mixed  $values Raw values (usually $_POST data).
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
				case 'image':
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
	 * @param string              $slug  Demo slug.
	 * @param array<string,mixed> $saved Stored settings (may be partial).
	 * @return array<string,mixed> Every schema key, sanitised.
	 */
	public static function get_settings( $slug, $saved = array() ) {
		$defaults = self::settings_defaults( $slug );
		$stored   = self::sanitize_settings( $slug, is_array( $saved ) ? $saved : array() );

		return array_merge( $defaults, $stored );
	}

	/**
	 * Resets the request cache. Used by tests.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function reset() {
		self::$defs    = null;
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
	 * Loads and validates every bundled demo file.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,array<string,mixed>> Slug => definition.
	 */
	private static function defs() {
		if ( null !== self::$defs ) {
			return self::$defs;
		}

		$defs = array();
		$base = self::base_path();

		if ( '' !== $base && is_dir( $base ) ) {
			$files = glob( $base . '*.php' );
			$files = is_array( $files ) ? $files : array();

			foreach ( $files as $file ) {
				$slug = basename( $file, '.php' );

				if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9\-]{1,58}[a-z0-9]$/', $slug ) ) {
					continue;
				}

				$def = include $file; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.NotAbsolutePath -- Resolved from the plugin's own demos folder only.

				$manifest = self::validate( $slug, is_array( $def ) && isset( $def['manifest'] ) ? $def['manifest'] : null );

				if ( null === $manifest || ! is_array( $def ) || ! is_callable( $def['render'] ) ) {
					Logger::warning( 'Bundled demo failed validation.', array( 'demo' => $slug ) );

					continue;
				}

				$defs[ $slug ] = array(
					'manifest' => $manifest,
					'settings' => isset( $def['settings'] ) && is_array( $def['settings'] ) ? $def['settings'] : array(),
					'css'      => isset( $def['css'] ) && is_string( $def['css'] ) ? $def['css'] : '',
					'js'       => isset( $def['js'] ) && is_string( $def['js'] ) ? $def['js'] : '',
					'render'   => $def['render'],
				);
			}
		}

		/**
		 * Filters the bundled PHP demos offered in the template library.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,array<string,mixed>> $defs Validated demo definitions keyed by slug.
		 */
		$defs = (array) apply_filters( 'qevora_layouts_demos', $defs );

		// Re-key manifests after filtering, keeping slug => definition.
		$clean = array();

		foreach ( $defs as $slug => $def ) {
			if ( is_array( $def ) && isset( $def['manifest']['id'] ) ) {
				$clean[ (string) $def['manifest']['id'] ] = $def;
			}
		}

		self::$defs = $clean;

		return self::$defs;
	}

	/**
	 * Validates one demo manifest.
	 *
	 * @since 1.0.0
	 *
	 * @param string     $slug     File slug (folder-less file name).
	 * @param mixed      $manifest Raw manifest array.
	 * @return array<string,mixed>|null Validated manifest, null when invalid.
	 */
	private static function validate( $slug, $manifest ) {
		if ( ! is_array( $manifest ) ) {
			return null;
		}

		$id   = isset( $manifest['id'] ) ? Layout_Post_Type::sanitize_identifier( $manifest['id'] ) : '';
		$type = isset( $manifest['type'] ) ? Layout_Post_Type::sanitize_type( $manifest['type'] ) : '';
		$name = isset( $manifest['name'] ) ? sanitize_text_field( (string) $manifest['name'] ) : '';

		if ( $id !== $slug || '' === $name || ! in_array( $type, array( 'header', 'footer', 'section' ), true ) ) {
			return null;
		}

		return array(
			'id'          => $id,
			'name'        => $name,
			'type'        => $type,
			'description' => isset( $manifest['description'] ) ? sanitize_text_field( (string) $manifest['description'] ) : '',
			'menu'        => ! empty( $manifest['menu'] ),
			'menu_label'  => isset( $manifest['menu_label'] ) ? sanitize_text_field( (string) $manifest['menu_label'] ) : '',
			'tags'        => isset( $manifest['tags'] ) && is_array( $manifest['tags'] ) ? array_map( 'sanitize_key', $manifest['tags'] ) : array(),
		);
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
}
