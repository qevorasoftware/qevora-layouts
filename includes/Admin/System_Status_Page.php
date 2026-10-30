<?php
/**
 * System status screen.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Admin;

use QevoraLayouts\Core\Cache;
use QevoraLayouts\Core\Layout_Post_Type;
use QevoraLayouts\Core\Logger;
use QevoraLayouts\Core\Plugin;
use QevoraLayouts\Core\Settings;
use QevoraLayouts\Import\Remote_Source;
use QevoraLayouts\Support\Environment;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and renders the System Status screen.
 *
 * The screen is restricted to `manage_options`. It never prints secrets,
 * tokens or anything a visitor could reach.
 *
 * @since 1.0.0
 */
class System_Status_Page {

	/**
	 * Registers hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register() {
		// No hooks: the screen is rendered on demand.
	}

	/**
	 * Builds the status report.
	 *
	 * @since 1.0.0
	 *
	 * @param Plugin|null $plugin Plugin instance.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_report( ?Plugin $plugin = null ) {
		$wp      = Environment::wordpress();
		$php     = Environment::php();
		$theme   = Environment::theme();
		$counts  = Environment::layout_counts();
		$plugins = Environment::header_footer_plugins();

		$sections = array();

		$sections[] = array(
			'id'    => 'plugin',
			'title' => __( 'Qevora Layouts', 'qevora-layouts' ),
			'rows'  => array(
				array(
					'label' => __( 'Plugin version', 'qevora-layouts' ),
					'value' => QEVORA_LAYOUTS_VERSION,
				),
				array(
					'label' => __( 'Layouts (total)', 'qevora-layouts' ),
					'value' => (int) $counts['total'],
				),
				array(
					'label' => __( 'Layouts enabled', 'qevora-layouts' ),
					'value' => (int) $counts['enabled'],
				),
				array(
					'label' => __( 'Headers / footers / sections', 'qevora-layouts' ),
					'value' => sprintf(
						'%1$d / %2$d / %3$d',
						isset( $counts['header'] ) ? (int) $counts['header'] : 0,
						isset( $counts['footer'] ) ? (int) $counts['footer'] : 0,
						isset( $counts['section'] ) ? (int) $counts['section'] : 0
					),
				),
				array(
					'label'  => __( 'Header rendering', 'qevora-layouts' ),
					'value'  => Settings::is_enabled( 'header_enabled' ),
					'status' => Settings::is_enabled( 'header_enabled' ) ? 'ok' : 'info',
				),
				array(
					'label'  => __( 'Footer rendering', 'qevora-layouts' ),
					'value'  => Settings::is_enabled( 'footer_enabled' ),
					'status' => Settings::is_enabled( 'footer_enabled' ) ? 'ok' : 'info',
				),
				array(
					'label' => __( 'Default header placement', 'qevora-layouts' ),
					'value' => (string) Settings::get( 'default_header_placement', '' ),
				),
				array(
					'label' => __( 'Default footer placement', 'qevora-layouts' ),
					'value' => (string) Settings::get( 'default_footer_placement', '' ),
				),
				array(
					'label'  => __( 'Cache version', 'qevora-layouts' ),
					'value'  => Cache::get_version(),
					'note'   => __( 'Changes whenever a layout or setting changes. Old cached values are ignored.', 'qevora-layouts' ),
				),
				array(
					'label'  => __( 'Debug mode', 'qevora-layouts' ),
					'value'  => Settings::is_enabled( 'debug_mode' ),
					'status' => Settings::is_enabled( 'debug_mode' ) ? 'warn' : 'ok',
					'note'   => Settings::is_enabled( 'debug_mode' ) ? __( 'Diagnostics are written to the PHP error log. Turn this off on a production site when you are done.', 'qevora-layouts' ) : '',
				),
			),
		);

		$sections[] = array(
			'id'    => 'wordpress',
			'title' => __( 'WordPress', 'qevora-layouts' ),
			'rows'  => array(
				array(
					'label'  => __( 'Version', 'qevora-layouts' ),
					'value'  => (string) $wp['version'],
					'status' => version_compare( (string) $wp['version'], QEVORA_LAYOUTS_MIN_WP, '>=' ) ? 'ok' : 'warn',
				),
				array(
					'label' => __( 'Locale', 'qevora-layouts' ),
					'value' => (string) $wp['locale'],
				),
				array(
					'label' => __( 'Multisite', 'qevora-layouts' ),
					'value' => (bool) $wp['multisite'],
				),
				array(
					'label'  => __( 'WP_DEBUG', 'qevora-layouts' ),
					'value'  => (bool) $wp['debug'],
					'status' => $wp['debug'] ? 'warn' : 'ok',
				),
				array(
					'label' => __( 'WP_DEBUG_LOG', 'qevora-layouts' ),
					'value' => (bool) $wp['debug_log'],
				),
				array(
					'label'  => __( 'Permalinks', 'qevora-layouts' ),
					'value'  => '' !== $wp['permalinks'] ? (string) $wp['permalinks'] : __( 'Plain (default)', 'qevora-layouts' ),
				),
				array(
					'label' => __( 'External object cache', 'qevora-layouts' ),
					'value' => (bool) $wp['external_cache'],
				),
				array(
					'label' => __( 'WP_MEMORY_LIMIT', 'qevora-layouts' ),
					'value' => (string) $wp['memory_limit'],
				),
			),
		);

		$sections[] = array(
			'id'    => 'php',
			'title' => __( 'Server (PHP)', 'qevora-layouts' ),
			'rows'  => array(
				array(
					'label'  => __( 'PHP version', 'qevora-layouts' ),
					'value'  => (string) $php['version'],
					'status' => version_compare( (string) $php['version'], QEVORA_LAYOUTS_MIN_PHP, '>=' ) ? 'ok' : 'warn',
				),
				array(
					'label' => __( 'SAPI', 'qevora-layouts' ),
					'value' => (string) $php['sapi'],
				),
				array(
					'label' => __( 'Memory limit', 'qevora-layouts' ),
					'value' => (string) $php['memory_limit'],
				),
				array(
					'label'  => __( 'Max upload size', 'qevora-layouts' ),
					'value'  => (string) $php['upload_max'],
					'note'   => __( 'Applies to uploaded template files.', 'qevora-layouts' ),
				),
				array(
					'label' => __( 'Max post size', 'qevora-layouts' ),
					'value' => (string) $php['post_max'],
				),
				array(
					'label' => __( 'Max execution time', 'qevora-layouts' ),
					'value' => (string) $php['max_execution'],
				),
				array(
					'label'  => __( 'JSON extension', 'qevora-layouts' ),
					'value'  => (bool) $php['json'],
					'status' => $php['json'] ? 'ok' : 'warn',
				),
				array(
					'label' => __( 'mbstring extension', 'qevora-layouts' ),
					'value' => (bool) $php['mbstring'],
				),
				array(
					'label' => __( 'OPcache available', 'qevora-layouts' ),
					'value' => (bool) $php['opcache'],
				),
			),
		);

		$sections[] = array(
			'id'    => 'theme',
			'title' => __( 'Active theme', 'qevora-layouts' ),
			'rows'  => array(
				array(
					'label' => __( 'Theme', 'qevora-layouts' ),
					'value' => trim( (string) $theme['name'] . ' ' . (string) $theme['version'] ),
				),
				array(
					'label' => __( 'Parent theme', 'qevora-layouts' ),
					'value' => '' !== $theme['parent'] ? (string) $theme['parent'] : __( 'None (not a child theme)', 'qevora-layouts' ),
				),
				array(
					'label' => __( 'Block theme', 'qevora-layouts' ),
					'value' => (bool) $theme['is_block_theme'],
				),
				array(
					'label'  => __( 'wp_body_open() available', 'qevora-layouts' ),
					'value'  => null === $theme['wp_body_open'] ? __( 'Could not be detected', 'qevora-layouts' ) : (bool) $theme['wp_body_open'],
					'status' => false === $theme['wp_body_open'] ? 'warn' : 'ok',
					'note'   => null === $theme['wp_body_open']
						? __( 'The theme header.php was not readable. If the header placement does nothing, use the "Custom theme hook" or "Manual only" placement.', 'qevora-layouts' )
						: ( false === $theme['wp_body_open'] ? __( 'This theme does not call wp_body_open(). Use a custom theme hook or the manual placement for headers.', 'qevora-layouts' ) : '' ),
				),
				array(
					'label'  => __( 'get_header() / get_footer() hooks', 'qevora-layouts' ),
					'value'  => (bool) $theme['get_header_hooks'],
					'status' => $theme['get_header_hooks'] ? 'ok' : 'info',
					'note'   => $theme['get_header_hooks'] ? '' : __( 'Block themes do not fire get_header/get_footer. Use wp_body_open, wp_footer, a custom hook or the manual placement.', 'qevora-layouts' ),
				),
				array(
					'label'  => __( 'Theme header selectors to hide', 'qevora-layouts' ),
					'value'  => '' !== (string) Settings::get( 'hide_theme_header_selectors', '' ) ? (string) Settings::get( 'hide_theme_header_selectors', '' ) : __( 'None', 'qevora-layouts' ),
				),
				array(
					'label'  => __( 'Theme footer selectors to hide', 'qevora-layouts' ),
					'value'  => '' !== (string) Settings::get( 'hide_theme_footer_selectors', '' ) ? (string) Settings::get( 'hide_theme_footer_selectors', '' ) : __( 'None', 'qevora-layouts' ),
				),
			),
		);

		$builder_rows = array();

		if ( null !== $plugin ) {
			foreach ( $plugin->builders()->get_status() as $builder ) {
				$note = '';

				if ( ! empty( $builder['requirements'] ) ) {
					$labels = array();

					foreach ( (array) $builder['requirements'] as $requirement ) {
						$labels[] = ( isset( $requirement['label'] ) ? (string) $requirement['label'] : '' ) . ( empty( $requirement['active'] ) ? ' (' . __( 'not active', 'qevora-layouts' ) . ')' : ' (' . __( 'active', 'qevora-layouts' ) . ')' );
					}

					$note = implode( ', ', $labels );
				}

				$builder_rows[] = array(
					'label'  => (string) $builder['name'],
					'value'  => ! empty( $builder['available'] )
						? ( '' !== $builder['version'] ? sprintf( /* translators: %s: builder version. */ __( 'Available (%s)', 'qevora-layouts' ), $builder['version'] ) : __( 'Available', 'qevora-layouts' ) )
						: __( 'Not available', 'qevora-layouts' ),
					'status' => ! empty( $builder['available'] ) ? 'ok' : 'info',
					'note'   => trim( $note . ( ! empty( $builder['can_edit'] ) ? ' · ' . __( 'layouts can be edited with this builder', 'qevora-layouts' ) : '' ) ),
				);
			}
		}

		if ( empty( $builder_rows ) ) {
			$builder_rows[] = array(
				'label' => __( 'Builders', 'qevora-layouts' ),
				'value' => __( 'Not resolved yet', 'qevora-layouts' ),
			);
		}

		$sections[] = array(
			'id'    => 'builders',
			'title' => __( 'Builder adapters', 'qevora-layouts' ),
			'rows'  => $builder_rows,
		);

		$library_rows = array(
			array(
				'label' => __( 'Uploaded imports allowed', 'qevora-layouts' ),
				'value' => (bool) Settings::is_enabled( 'allow_uploaded_imports' ),
			),
			array(
				'label' => __( 'Maximum template size', 'qevora-layouts' ),
				'value' => (int) Settings::get( 'max_import_size_kb', 2048 ) . ' KB',
			),
			array(
				'label' => __( 'Duplicate import policy', 'qevora-layouts' ),
				'value' => 'copy' === Settings::get( 'duplicate_import_policy', 'skip' )
					? __( 'Import a second copy', 'qevora-layouts' )
					: __( 'Skip already imported templates', 'qevora-layouts' ),
			),
			array(
				'label'  => __( 'Remote library', 'qevora-layouts' ),
				'value'  => Remote_Source::is_enabled() ? __( 'Enabled', 'qevora-layouts' ) : __( 'Disabled', 'qevora-layouts' ),
				'status' => Remote_Source::is_enabled() ? 'info' : 'ok',
				'note'   => Remote_Source::is_enabled() ? __( 'Requests are limited to the configured https host.', 'qevora-layouts' ) : __( 'Bundled starter templates are always available.', 'qevora-layouts' ),
			),
		);

		if ( Remote_Source::is_enabled() ) {
			$endpoint_host = wp_parse_url( Remote_Source::get_url() );

			$library_rows[] = array(
				'label' => __( 'Remote host', 'qevora-layouts' ),
				'value' => isset( $endpoint_host['host'] ) ? (string) $endpoint_host['host'] : '',
			);
		}

		$sections[] = array(
			'id'    => 'library',
			'title' => __( 'Template library', 'qevora-layouts' ),
			'rows'  => $library_rows,
		);

		$conflict_rows = array();

		foreach ( $plugins as $basename => $label ) {
			$conflict_rows[] = array(
				'label'  => (string) $label,
				'value'  => __( 'Active', 'qevora-layouts' ),
				'status' => 'warn',
				'note'   => __( 'Another plugin can render a header or footer. If you see duplicates, disable one of them or hide the theme markup.', 'qevora-layouts' ),
			);
		}

		if ( empty( $conflict_rows ) ) {
			$conflict_rows[] = array(
				'label'  => __( 'Known header/footer plugins', 'qevora-layouts' ),
				'value'  => __( 'None detected', 'qevora-layouts' ),
				'status' => 'ok',
			);
		}

		$sections[] = array(
			'id'    => 'conflicts',
			'title' => __( 'Possible conflicts', 'qevora-layouts' ),
			'rows'  => $conflict_rows,
		);

		/**
		 * Filters the system status report.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int,array<string,mixed>> $sections Report sections.
		 */
		return apply_filters( 'qevora_layouts_status_report', $sections );
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
		$view = QEVORA_LAYOUTS_DIR . 'includes/Admin/Views/system-status.php';

		if ( ! is_readable( $view ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The system status screen could not be loaded.', 'qevora-layouts' ) . '</p></div>';

			return;
		}

		$report = self::get_report( $plugin );

		$context = array(
			'plugin'      => $plugin,
			'report'      => $report,
			'report_text' => Environment::report_to_text( $report ),
			'log'         => Logger::get_buffer(),
			'flush_url'   => wp_nonce_url(
				add_query_arg(
					array( 'action' => 'qevora_layouts_flush_cache' ),
					admin_url( 'admin-post.php' )
				),
				Actions::FLUSH_CACHE_ACTION
			),
		);

		require $view;
	}

	/**
	 * Returns the layout post type slug. Convenience for the view.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function post_type() {
		return Layout_Post_Type::POST_TYPE;
	}
}
