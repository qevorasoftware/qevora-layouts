<?php
/**
 * Plugin settings.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Typed access to the single `qevora_layouts_settings` option.
 *
 * Every value written to the option passes through {@see Settings::sanitize()},
 * which is also the `sanitize_callback` used by `register_setting()`.
 *
 * @since 1.0.0
 */
class Settings {

	/**
	 * In-memory copy of the settings for the current request.
	 *
	 * @since 1.0.0
	 * @var   array<string,mixed>|null
	 */
	private static $cache = null;

	/**
	 * Header injection points.
	 *
	 * @since 1.0.0
	 * @var   string[]
	 */
	const HEADER_PLACEMENTS = array(
		'wp_body_open',
		'get_header',
		'custom_hook',
		'manual',
	);

	/**
	 * Footer injection points.
	 *
	 * @since 1.0.0
	 * @var   string[]
	 */
	const FOOTER_PLACEMENTS = array(
		'get_footer',
		'wp_footer',
		'wp_body_open',
		'custom_hook',
		'manual',
	);

	/**
	 * Returns the default settings.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,mixed>
	 */
	public static function get_defaults() {
		$defaults = array(
			// Rendering.
			'header_enabled'                => true,
			'footer_enabled'                => true,
			'section_enabled'               => true,
			'render_wrapper'                => true,
			'default_header_placement'      => 'wp_body_open',
			'default_footer_placement'      => 'get_footer',
			'hide_theme_header_selectors'   => '',
			'hide_theme_footer_selectors'   => '',
			'inline_hide_css'               => true,

			// Integrations.
			'builder_block'                 => true,
			'builder_elementor'             => true,
			'elementor_editor_support'      => true,

			// Template library / imports.
			'remote_library_enabled'        => false,
			'remote_library_url'            => '',
			'allow_uploaded_imports'        => true,
			'max_import_size_kb'            => 2048,
			'duplicate_import_policy'       => 'skip',

			// Performance / debugging.
			'cache_candidates'              => true,
			'cache_ttl_seconds'             => 43200,
			'debug_mode'                    => false,

			// Housekeeping.
			'delete_data_on_uninstall'      => false,
		);

		/**
		 * Filters the default plugin settings.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,mixed> $defaults Default settings.
		 */
		return apply_filters( 'qevora_layouts_default_settings', $defaults );
	}

	/**
	 * Returns all settings merged with the defaults.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,mixed>
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored = get_option( QEVORA_LAYOUTS_OPTION, array() );

			if ( ! is_array( $stored ) ) {
				$stored = array();
			}

			self::$cache = array_merge( self::get_defaults(), $stored );
		}

		return self::$cache;
	}

	/**
	 * Returns a single setting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Value used when the key is unknown.
	 * @return mixed
	 */
	public static function get( $key, $fallback = null ) {
		$settings = self::all();

		if ( array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}

		return $fallback;
	}

	/**
	 * Returns a boolean setting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Setting key.
	 * @return bool
	 */
	public static function is_enabled( $key ) {
		return (bool) self::get( $key, false );
	}

	/**
	 * Writes settings after sanitizing them.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $values Values to merge into the stored option.
	 * @return bool
	 */
	public static function update( array $values ) {
		$merged  = array_merge( self::all(), $values );
		$clean   = self::sanitize( $merged );
		$updated = update_option( QEVORA_LAYOUTS_OPTION, $clean, false );

		self::$cache = $clean;
		Cache::bump_version();

		return (bool) $updated;
	}

	/**
	 * Replaces the whole option.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $values New settings.
	 * @return bool
	 */
	public static function replace( array $values ) {
		return self::update( array_merge( self::get_defaults(), $values ) );
	}

	/**
	 * Drops the in-memory cache. Used after direct option writes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$cache = null;
	}

	/**
	 * Sanitizes a raw settings array.
	 *
	 * Registered as the `sanitize_callback` for the settings option, therefore
	 * it must accept and return a full settings array.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $input Raw input.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::get_defaults();
		$output   = array();

		foreach ( $defaults as $key => $default ) {
			$value = array_key_exists( $key, $input ) ? $input[ $key ] : $default;

			switch ( $key ) {
				case 'header_enabled':
				case 'footer_enabled':
				case 'section_enabled':
				case 'render_wrapper':
				case 'inline_hide_css':
				case 'builder_block':
				case 'builder_elementor':
				case 'elementor_editor_support':
				case 'remote_library_enabled':
				case 'allow_uploaded_imports':
				case 'cache_candidates':
				case 'debug_mode':
				case 'delete_data_on_uninstall':
					$output[ $key ] = rest_sanitize_boolean( $value );
					break;

				case 'default_header_placement':
					$value          = is_string( $value ) ? $value : '';
					$output[ $key ] = in_array( $value, self::HEADER_PLACEMENTS, true ) ? $value : $defaults[ $key ];
					break;

				case 'default_footer_placement':
					$value          = is_string( $value ) ? $value : '';
					$output[ $key ] = in_array( $value, self::FOOTER_PLACEMENTS, true ) ? $value : $defaults[ $key ];
					break;

				case 'duplicate_import_policy':
					$value          = is_string( $value ) ? $value : '';
					$output[ $key ] = in_array( $value, array( 'skip', 'copy' ), true ) ? $value : 'skip';
					break;

				case 'hide_theme_header_selectors':
				case 'hide_theme_footer_selectors':
					$output[ $key ] = self::sanitize_css_selectors( $value );
					break;

				case 'remote_library_url':
					$output[ $key ] = self::sanitize_library_url( $value );
					break;

				case 'max_import_size_kb':
					$output[ $key ] = max( 16, min( 20480, (int) $value ) );
					break;

				case 'cache_ttl_seconds':
					$output[ $key ] = max( 60, min( 86400, (int) $value ) );
					break;

				default:
					$output[ $key ] = is_scalar( $value ) ? $value : $default;
					break;
			}
		}

		return $output;
	}

	/**
	 * Sanitizes a comma separated list of CSS selectors.
	 *
	 * The plugin only ever outputs `{ display: none !important; }` for the
	 * accepted selectors. Characters that could terminate the declaration or
	 * open a new one (`{`, `}`, `;`, `<`, `/`, `\`, `&`, `@`) are removed,
	 * which also makes it impossible to smuggle `url()`, `@import` or a
	 * closing `</style>` tag.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw selector list.
	 * @return string Comma separated selectors, or an empty string.
	 */
	public static function sanitize_css_selectors( $value ) {
		if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
			return '';
		}

		$value = (string) $value;

		if ( '' === trim( $value ) ) {
			return '';
		}

		$parts    = preg_split( '/[\r\n,]+/', $value );
		$parts    = is_array( $parts ) ? $parts : array();
		$accepted = array();

		foreach ( $parts as $part ) {
			$part = trim( $part );

			if ( '' === $part ) {
				continue;
			}

			// Strip anything that could escape the declaration block.
			if ( false !== strpbrk( $part, '{};<\\/&@' ) ) {
				continue;
			}

			$lower = strtolower( $part );

			if ( false !== strpos( $lower, 'expression' ) || false !== strpos( $lower, 'javascript:' ) || false !== strpos( $lower, 'behavior' ) ) {
				continue;
			}

			// A selector must start with something CSS-like.
			if ( 1 !== preg_match( '/^[.#\[a-zA-Z*\x{0080}-\x{FFFF}]/u', $part ) ) {
				continue;
			}

			if ( strlen( $part ) > 200 ) {
				continue;
			}

			$accepted[] = $part;
		}

		$accepted = array_slice( array_unique( $accepted ), 0, 20 );

		return implode( ', ', $accepted );
	}

	/**
	 * Sanitizes the remote template library endpoint.
	 *
	 * Only absolute HTTPS URLs on a valid host are accepted. The value is
	 * optional: when empty the plugin uses the bundled starter templates only.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw URL.
	 * @return string Sanitized URL or empty string.
	 */
	public static function sanitize_library_url( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = trim( $value );

		if ( '' === $value ) {
			return '';
		}

		$escaped = esc_url_raw( $value, array( 'https' ) );

		if ( '' === $escaped ) {
			return '';
		}

		$parts = wp_parse_url( $escaped );

		if ( empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		return $escaped;
	}
}
