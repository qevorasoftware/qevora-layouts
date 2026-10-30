<?php
/**
 * Template tags.
 *
 * Loaded by {@see QevoraLayouts\Core\Plugin::boot()} so theme authors can call
 * these functions from any template file.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use QevoraLayouts\Core\Layout;
use QevoraLayouts\Core\Plugin;

if ( ! function_exists( 'qevora_layouts_get_layout' ) ) {
	/**
	 * Returns the markup of a layout.
	 *
	 * @since 1.0.0
	 *
	 * @param int                 $layout_id Layout ID.
	 * @param array<string,mixed> $args      Optional. See Renderer::render_by_id().
	 * @return string Markup, or an empty string when the layout cannot render.
	 */
	function qevora_layouts_get_layout( $layout_id, array $args = array() ) {
		if ( ! Plugin::is_booted() ) {
			return '';
		}

		$result = Plugin::instance()->frontend()->renderer()->render_by_id( (int) $layout_id, $args );

		return is_wp_error( $result ) ? '' : (string) $result;
	}
}

if ( ! function_exists( 'qevora_layouts_the_layout' ) ) {
	/**
	 * Prints the markup of a layout.
	 *
	 * @since 1.0.0
	 *
	 * @param int                 $layout_id Layout ID.
	 * @param array<string,mixed> $args      Optional. See Renderer::render_by_id().
	 * @return void
	 */
	function qevora_layouts_the_layout( $layout_id, array $args = array() ) {
		// Markup produced by the builder adapters; layout content is sanitized on save.
		echo qevora_layouts_get_layout( $layout_id, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

if ( ! function_exists( 'qevora_layouts_get_header' ) ) {
	/**
	 * Returns the header layout that matches the current request.
	 *
	 * Useful for themes that want full control: set the layout placement to
	 * "Manual only" and call this function where the header belongs.
	 *
	 * @since 1.0.0
	 *
	 * @return string Markup, or an empty string.
	 */
	function qevora_layouts_get_header() {
		if ( ! Plugin::is_booted() ) {
			return '';
		}

		$layout = Plugin::instance()->repository()->get_active_layout( 'header' );

		if ( ! $layout instanceof Layout ) {
			return '';
		}

		return Plugin::instance()->frontend()->renderer()->render_layout( $layout, 'header' );
	}
}

if ( ! function_exists( 'qevora_layouts_get_footer' ) ) {
	/**
	 * Returns the footer layout that matches the current request.
	 *
	 * @since 1.0.0
	 *
	 * @return string Markup, or an empty string.
	 */
	function qevora_layouts_get_footer() {
		if ( ! Plugin::is_booted() ) {
			return '';
		}

		$layout = Plugin::instance()->repository()->get_active_layout( 'footer' );

		if ( ! $layout instanceof Layout ) {
			return '';
		}

		return Plugin::instance()->frontend()->renderer()->render_layout( $layout, 'footer' );
	}
}

if ( ! function_exists( 'qevora_layouts_the_header' ) ) {
	/**
	 * Prints the matching header layout.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	function qevora_layouts_the_header() {
		// Markup produced by the builder adapters; layout content is sanitized on save.
		echo qevora_layouts_get_header(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

if ( ! function_exists( 'qevora_layouts_the_footer' ) ) {
	/**
	 * Prints the matching footer layout.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	function qevora_layouts_the_footer() {
		// Markup produced by the builder adapters; layout content is sanitized on save.
		echo qevora_layouts_get_footer(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

if ( ! function_exists( 'qevora_layouts_has_layout' ) ) {
	/**
	 * Whether a layout of a type matches the current request.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Layout type (header|footer|section).
	 * @return bool
	 */
	function qevora_layouts_has_layout( $type ) {
		if ( ! Plugin::is_booted() ) {
			return false;
		}

		return Plugin::instance()->repository()->get_active_layout( (string) $type ) instanceof Layout;
	}
}
