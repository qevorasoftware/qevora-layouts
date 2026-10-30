<?php
/**
 * Builder adapter for the bundled PHP demos.
 *
 * Layouts with the `demo` builder are rendered straight from the plugin's
 * own template files - no post content, no page builder and no editor
 * involved. Each demo brings its own stylesheet and optional script, which
 * are enqueued only while that demo actually renders.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Integrations\Adapters;

use QevoraLayouts\Core\Layout;
use QevoraLayouts\Core\Logger;
use QevoraLayouts\Import\Demo_Registry;
use QevoraLayouts\Integrations\Abstract_Builder_Adapter;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders layouts whose content is a bundled PHP demo template.
 *
 * @since 1.0.0
 */
class Demo_Adapter extends Abstract_Builder_Adapter {

	/**
	 * Builder identifier.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_id() {
		return 'demo';
	}

	/**
	 * Human readable name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'Bundled demo (PHP)', 'qevora-layouts' );
	}

	/**
	 * The demos ship with the plugin, so the adapter is always available.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_available() {
		return true;
	}

	/**
	 * Whether this adapter renders the given layout.
	 *
	 * @since 1.0.0
	 *
	 * @param Layout $layout Layout.
	 * @return bool
	 */
	public function supports( Layout $layout ) {
		return 'demo' === $layout->builder && Demo_Registry::exists( $layout->demo_slug );
	}

	/**
	 * Renders the demo template of a layout.
	 *
	 * The template file is resolved through the registry allow list, so only
	 * files shipped inside the plugin can ever be included.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Layout post ID.
	 * @return string Markup, or an empty string when nothing can be rendered.
	 */
	public function render( $post_id ) {
		$post = get_post( (int) $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return '';
		}

		$layout = Layout::from_post( $post );

		if ( ! $layout instanceof Layout ) {
			return '';
		}

		$demo = Demo_Registry::get( $layout->demo_slug );

		if ( null === $demo ) {
			Logger::warning( 'Layout asks for an unknown demo.', array( 'layout_id' => $layout->id, 'demo' => $layout->demo_slug ) );

			return '';
		}

		$file = Demo_Registry::path( (string) $demo['id'], 'template.php' );

		if ( '' === $file ) {
			Logger::error( 'Demo template file is missing.', array( 'demo' => (string) $demo['id'] ) );

			return '';
		}

		Demo_Registry::enqueue_assets( (string) $demo['id'] );

		$qevora_layout  = $layout;
		$qevora_demo    = $demo;
		$qevora_menu_id = (int) $layout->menu_id;

		ob_start();

		include $file; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.NotAbsolutePath -- Resolved through the registry allow list above.

		$html = (string) ob_get_clean();

		/**
		 * Filters the markup rendered by a bundled PHP demo.
		 *
		 * @since 1.0.0
		 *
		 * @param string               $html   Rendered demo markup.
		 * @param Layout               $layout Layout being rendered.
		 * @param array<string,mixed>  $demo   Demo manifest.
		 */
		return (string) apply_filters( 'qevora_layouts_demo_markup', $html, $layout, $demo );
	}
}
