<?php
/**
 * Frontend renderer.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Frontend;

use QevoraLayouts\Core\Layout;
use QevoraLayouts\Core\Layout_Post_Type;
use QevoraLayouts\Core\Logger;
use QevoraLayouts\Core\Plugin;
use QevoraLayouts\Core\Settings;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a layout into markup.
 *
 * The renderer never evaluates layout content: markup is produced by a builder
 * adapter and wrapped in a small, filterable container that themes can override
 * by copying `templates/frontend/layout-wrapper.php` into
 * `<theme>/qevora-layouts/`.
 *
 * @since 1.0.0
 */
class Renderer {

	/**
	 * Plugin instance.
	 *
	 * @since 1.0.0
	 * @var   Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Renders a layout object.
	 *
	 * @since 1.0.0
	 *
	 * @param Layout $layout Layout.
	 * @param string $slot   Slot name used by the duplicate guard.
	 * @return string Markup, empty when the layout produces nothing.
	 */
	public function render_layout( Layout $layout, $slot = '' ) {
		if ( '' === $slot ) {
			$slot = $layout->type . ':' . $layout->id;
		}

		$content = $this->plugin->builders()->render( $layout );

		if ( '' === trim( (string) $content ) ) {
			Logger::debug( 'Layout produced no markup.', array( 'layout_id' => $layout->id, 'slot' => $slot ) );

			return '';
		}

		$html = $this->wrap( $layout, $content );

		/**
		 * Filters the final markup of a layout.
		 *
		 * @since 1.0.0
		 *
		 * @param string $html   Markup including the wrapper.
		 * @param Layout $layout Layout.
		 * @param string $slot   Slot name.
		 */
		$html = apply_filters( 'qevora_layouts_render_layout', $html, $layout, $slot );

		Duplicate_Guard::mark_rendered( $slot, $layout->id );

		return is_string( $html ) ? $html : '';
	}

	/**
	 * Renders a layout by ID.
	 *
	 * Used by the shortcode, the block and the template tags. Any layout type
	 * can be rendered manually, including layouts whose placement is `manual`.
	 *
	 * @since 1.0.0
	 *
	 * @param int              $layout_id Layout ID.
	 * @param array<string,mixed> $args    {
	 *     Optional arguments.
	 *
	 *     @type bool   $wrapper Whether to print the wrapper element. Default true.
	 *     @type string $slot    Duplicate guard slot. Defaults to `section:{id}`.
	 * }
	 * @return string|WP_Error Markup, empty string when the layout is empty, or WP_Error.
	 */
	public function render_by_id( $layout_id, array $args = array() ) {
		$layout_id = (int) $layout_id;

		if ( $layout_id <= 0 ) {
			return new WP_Error( 'qevora_layouts_invalid_id', __( 'A valid layout ID is required.', 'qevora-layouts' ) );
		}

		$layout = $this->plugin->repository()->get( $layout_id );

		if ( null === $layout ) {
			return new WP_Error( 'qevora_layouts_not_found', __( 'Layout not found.', 'qevora-layouts' ) );
		}

		$is_preview = current_user_can( 'edit_post', $layout_id ) && ( is_preview() || is_customize_preview() );

		if ( ! $layout->is_enabled() && ! $is_preview ) {
			/**
			 * Filters whether a disabled layout may still render.
			 *
			 * @since 1.0.0
			 *
			 * @param bool   $allowed Whether rendering is allowed.
			 * @param Layout $layout  Layout.
			 */
			if ( ! apply_filters( 'qevora_layouts_allow_disabled_layout', false, $layout ) ) {
				return new WP_Error( 'qevora_layouts_disabled', __( 'This layout is not published.', 'qevora-layouts' ) );
			}
		}

		$defaults = array(
			'wrapper' => (bool) Settings::is_enabled( 'render_wrapper' ),
			'slot'    => 'section:' . $layout_id,
		);

		$args = wp_parse_args( $args, $defaults );

		$content = $this->plugin->builders()->render( $layout );

		if ( '' === trim( (string) $content ) ) {
			return '';
		}

		if ( empty( $args['wrapper'] ) ) {
			return (string) $content;
		}

		$html = $this->wrap( $layout, $content );

		Duplicate_Guard::mark_rendered( (string) $args['slot'], $layout_id );

		return is_string( $html ) ? $html : '';
	}

	/**
	 * Wraps rendered content in the layout container.
	 *
	 * @since 1.0.0
	 *
	 * @param Layout $layout  Layout.
	 * @param string $content Inner markup (already escaped/rendered by the adapter).
	 * @return string
	 */
	private function wrap( Layout $layout, $content ) {
		if ( ! Settings::is_enabled( 'render_wrapper' ) ) {
			return (string) $content;
		}

		$classes = array(
			'qevora-layouts',
			'qevora-layouts--' . sanitize_html_class( $layout->type ),
			'qevora-layouts--id-' . (int) $layout->id,
		);

		if ( 'self' !== $layout->content_source ) {
			$classes[] = 'qevora-layouts--referenced';
		}

		/**
		 * Filters the wrapper CSS classes of a layout.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $classes CSS classes.
		 * @param Layout   $layout  Layout.
		 */
		$classes = (array) apply_filters( 'qevora_layouts_wrapper_classes', $classes, $layout );
		$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );

		$attributes = array(
			'class'              => implode( ' ', $classes ),
			'data-qevora-layout' => (int) $layout->id,
			'data-qevora-type'   => sanitize_html_class( $layout->type ),
		);

		/**
		 * Filters the wrapper attributes of a layout.
		 *
		 * Values are escaped when printed.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,string|int> $attributes Attributes.
		 * @param Layout                   $layout     Layout.
		 */
		$attributes = (array) apply_filters( 'qevora_layouts_wrapper_attributes', $attributes, $layout );

		$template = self::locate_template( 'layout-wrapper.php' );

		if ( '' !== $template && is_readable( $template ) ) {
			ob_start();

			// The template receives the values it needs and echoes them escaped.
			$qevora_layout     = $layout; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local to the template.
			$qevora_content    = $content;
			$qevora_attributes = $attributes;

			include $template;

			return (string) ob_get_clean();
		}

		$attr_string = '';

		foreach ( $attributes as $name => $value ) {
			$attr_string .= ' ' . esc_attr( (string) $name ) . '="' . esc_attr( (string) $value ) . '"';
		}

		return '<div' . $attr_string . '>' . $content . '</div>';
	}

	/**
	 * Locates a template file, allowing theme overrides.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Template file name.
	 * @return string Absolute path or empty string.
	 */
	public static function locate_template( $name ) {
		$name = basename( (string) $name );

		if ( '' === $name ) {
			return '';
		}

		$candidates = array();

		if ( defined( 'STYLESHEETPATH' ) && STYLESHEETPATH ) {
			$candidates[] = trailingslashit( STYLESHEETPATH ) . 'qevora-layouts/' . $name;
		}

		if ( defined( 'TEMPLATEPATH' ) && TEMPLATEPATH ) {
			$candidates[] = trailingslashit( TEMPLATEPATH ) . 'qevora-layouts/' . $name;
		}

		$candidates[] = QEVORA_LAYOUTS_DIR . 'templates/frontend/' . $name;

		/**
		 * Filters the candidate paths used to locate a plugin template.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $candidates Candidate absolute paths, first match wins.
		 * @param string   $name       Template file name.
		 */
		$candidates = (array) apply_filters( 'qevora_layouts_template_candidates', $candidates, $name );

		foreach ( $candidates as $candidate ) {
			if ( ! is_string( $candidate ) || '' === $candidate ) {
				continue;
			}

			$real = realpath( $candidate );

			if ( false === $real || ! is_readable( $real ) ) {
				continue;
			}

			return $real;
		}

		return '';
	}

	/**
	 * Returns the layout types that can render automatically.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public static function auto_render_types() {
		$types = array( 'header', 'footer' );

		/**
		 * Filters the layout types that render automatically on the frontend.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $types Layout types.
		 */
		return apply_filters( 'qevora_layouts_auto_render_types', $types );
	}

	/**
	 * Whether a layout type is a known auto-rendered type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Layout type.
	 * @return bool
	 */
	public static function is_auto_render_type( $type ) {
		return in_array( (string) $type, self::auto_render_types(), true );
	}

	/**
	 * Returns the post type slug. Convenience for templates.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function post_type() {
		return Layout_Post_Type::POST_TYPE;
	}
}
