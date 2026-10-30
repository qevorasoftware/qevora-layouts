<?php
/**
 * Template library screen.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Admin;

use QevoraLayouts\Core\Capabilities;
use QevoraLayouts\Core\Layout_Post_Type;
use QevoraLayouts\Core\Plugin;
use QevoraLayouts\Core\Settings;
use QevoraLayouts\Import\Remote_Source;
use QevoraLayouts\Import\Template_Library;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prepares and renders the Template Library screen.
 *
 * @since 1.0.0
 */
class Template_Library_Page {

	/**
	 * Registers hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register() {
		// Rendered on demand by the menu callback.
	}

	/**
	 * Renders the screen.
	 *
	 * @since 1.0.0
	 *
	 * @param Plugin $plugin Plugin instance.
	 * @return void
	 */
	public static function render( Plugin $plugin ) {
		$view = QEVORA_LAYOUTS_DIR . 'includes/Admin/Views/template-library.php';

		if ( ! is_readable( $view ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The template library screen could not be loaded.', 'qevora-layouts' ) . '</p></div>';

			return;
		}

		$library = $plugin->library();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		$type   = isset( $_GET['ql_type'] ) ? sanitize_key( wp_unslash( $_GET['ql_type'] ) ) : '';
		$origin = isset( $_GET['ql_origin'] ) ? sanitize_key( wp_unslash( $_GET['ql_origin'] ) ) : '';
		$search = isset( $_GET['ql_search'] ) ? sanitize_text_field( wp_unslash( $_GET['ql_search'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( '' !== $type && ! Layout_Post_Type::is_valid_type( $type ) ) {
			$type = '';
		}

		if ( ! in_array( $origin, array( '', 'bundled', 'remote' ), true ) ) {
			$origin = '';
		}

		$templates = $library->for_display(
			array(
				'type'   => $type,
				'origin' => $origin,
				'search' => $search,
			)
		);

		$context = array(
			'plugin'        => $plugin,
			'library'       => $library,
			'templates'     => $templates,
			'problems'      => array_slice( (array) $library->get_problems(), 0, 10 ),
			'filters'       => array(
				'type'   => $type,
				'origin' => $origin,
				'search' => $search,
			),
			'types'         => Layout_Post_Type::get_types(),
			'imported'      => self::imported_map( $templates ),
			'settings'      => array(
				'remote_enabled'  => Remote_Source::is_enabled(),
				'remote_url'      => Remote_Source::get_url(),
				'uploads_allowed' => Settings::is_enabled( 'allow_uploaded_imports' ),
				'policy'          => (string) Settings::get( 'duplicate_import_policy', 'skip' ),
				'max_size_kb'     => (int) Settings::get( 'max_import_size_kb', 2048 ),
			),
			'import_url'    => admin_url( 'admin-post.php' ),
			'refresh_url'   => wp_nonce_url(
				admin_url( 'admin-post.php?action=' . Actions::REFRESH_LIBRARY_ACTION ),
				Actions::REFRESH_LIBRARY_ACTION
			),
			'settings_url'  => admin_url( 'edit.php?post_type=' . Layout_Post_Type::POST_TYPE . '&page=' . Admin::SETTINGS_SLUG ),
			'layouts_url'   => admin_url( 'edit.php?post_type=' . Layout_Post_Type::POST_TYPE ),
			'can_publish'   => Capabilities::can_publish_layouts(),
			'placeholder'   => QEVORA_LAYOUTS_URL . 'assets/images/preview-placeholder.svg',
		);

		/**
		 * Filters the template library view context.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,mixed>  $context Context.
		 * @param Template_Library     $library Library.
		 */
		$context = (array) apply_filters( 'qevora_layouts_library_view_context', $context, $library );

		require $view;
	}

	/**
	 * Maps template identifiers onto the layouts already imported from them.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int,array<string,mixed>> $templates Templates on screen.
	 * @return array<string,int[]>
	 */
	private static function imported_map( array $templates ) {
		$map = array();

		if ( empty( $templates ) ) {
			return $map;
		}

		$repository = Plugin::is_booted() ? Plugin::instance()->repository() : null;

		if ( null === $repository ) {
			return $map;
		}

		foreach ( $templates as $template ) {
			if ( empty( $template['id'] ) ) {
				continue;
			}

			$ids = $repository->find_by_template_id( (string) $template['id'] );

			if ( ! empty( $ids ) ) {
				$map[ (string) $template['id'] ] = $ids;
			}
		}

		return $map;
	}
}
