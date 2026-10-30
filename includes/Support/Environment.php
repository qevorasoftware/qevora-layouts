<?php
/**
 * Environment inspection.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Support;

use QevoraLayouts\Core\Layout_Post_Type;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only inspection of WordPress, PHP, the theme and other plugins.
 *
 * Used by the System Status screen and by integration notices. Nothing here is
 * exposed to visitors: every consumer checks `manage_options` first.
 *
 * @since 1.0.0
 */
class Environment {

	/**
	 * Plugins that also render headers or footers.
	 *
	 * Only used to warn an administrator about a possible duplicate header. The
	 * list is deliberately short and contains plugins whose purpose is known.
	 *
	 * @since 1.0.0
	 * @var   array<string,string>
	 */
	const HEADER_FOOTER_PLUGINS = array(
		'elementor-pro/elementor-pro.php'          => 'Elementor Pro (Theme Builder)',
		'bb-theme-builder/bb-theme-builder.php'    => 'Beaver Themer',
		'bricks/bricks.php'                        => 'Bricks Builder',
		'oxy-gen/oxy-gen.php'                      => 'Oxygen Builder',
		'header-footer-elementor/header-footer-elementor.php' => 'Elementor Header & Footer Builder',
		'genesis-simple-hooks/genesis-simple-hooks.php' => 'Genesis Simple Hooks',
	);

	/**
	 * Returns WordPress information.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,mixed>
	 */
	public static function wordpress() {
		global $wp_version, $wpdb;

		return array(
			'version'           => isset( $wp_version ) ? (string) $wp_version : '',
			'locale'            => get_locale(),
			'multisite'         => is_multisite(),
			'debug'             => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'debug_log'         => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
			'debug_display'     => defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY,
			'script_debug'      => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,
			'memory_limit'      => defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : '',
			'permalinks'        => (string) get_option( 'permalink_structure', '' ),
			'external_cache'    => wp_using_ext_object_cache(),
			'front_page'        => (int) get_option( 'page_on_front', 0 ),
			'posts_page'        => (int) get_option( 'page_for_posts', 0 ),
			'cron'              => (bool) ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ),
			'db_version'        => isset( $wpdb ) && is_object( $wpdb ) ? (string) $wpdb->db_version() : '',
			'charset_collate'   => isset( $wpdb ) && is_object( $wpdb ) ? (string) $wpdb->charset : '',
		);
	}

	/**
	 * Returns PHP information.
	 *
	 * No absolute server paths are included.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,mixed>
	 */
	public static function php() {
		return array(
			'version'          => PHP_VERSION,
			'sapi'             => PHP_SAPI,
			'memory_limit'     => (string) ini_get( 'memory_limit' ),
			'max_execution'    => (string) ini_get( 'max_execution_time' ),
			'upload_max'       => (string) ini_get( 'upload_max_filesize' ),
			'post_max'         => (string) ini_get( 'post_max_size' ),
			'display_errors'   => (bool) ini_get( 'display_errors' ),
			'json'             => extension_loaded( 'json' ),
			'mbstring'         => extension_loaded( 'mbstring' ),
			'curl'             => extension_loaded( 'curl' ),
			'opcache'          => function_exists( 'opcache_get_status' ),
			'max_input_vars'   => (string) ini_get( 'max_input_vars' ),
		);
	}

	/**
	 * Returns information about the active theme.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,mixed>
	 */
	public static function theme() {
		$theme  = wp_get_theme();
		$parent = $theme->parent();

		$is_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();

		return array(
			'name'             => (string) $theme->get( 'Name' ),
			'version'          => (string) $theme->get( 'Version' ),
			'stylesheet'       => (string) $theme->get_stylesheet(),
			'parent'           => $parent instanceof \WP_Theme ? (string) $parent->get( 'Name' ) : '',
			'is_block_theme'   => $is_block_theme,
			'wp_body_open'     => self::supports_wp_body_open(),
			'get_header_hooks' => ! $is_block_theme,
			'get_footer_hooks' => ! $is_block_theme,
			'template_count'   => $is_block_theme ? self::count_block_templates() : 0,
		);
	}

	/**
	 * Detects whether the active theme calls `wp_body_open()`.
	 *
	 * Block themes always fire it from the core template canvas. For classic
	 * themes the child and parent `header.php` files are searched, which is a
	 * read-only heuristic and is reported as such.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|null Null when it could not be determined.
	 */
	public static function supports_wp_body_open() {
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			return true;
		}

		$candidates = array();

		if ( function_exists( 'get_stylesheet_directory' ) ) {
			$candidates[] = get_stylesheet_directory() . '/header.php';
		}

		if ( function_exists( 'get_template_directory' ) ) {
			$candidates[] = get_template_directory() . '/header.php';
		}

		foreach ( $candidates as $candidate ) {
			if ( ! is_readable( $candidate ) ) {
				continue;
			}

			$content = file_get_contents( $candidate ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read-only theme inspection.

			if ( false !== $content && false !== strpos( $content, 'wp_body_open' ) ) {
				return true;
			}
		}

		return null;
	}

	/**
	 * Counts customized block templates and template parts.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	private static function count_block_templates() {
		if ( ! function_exists( 'get_block_templates' ) ) {
			return 0;
		}

		$templates = get_block_templates( array( 'post_types' => array( 'wp_template', 'wp_template_part' ) ) );

		return is_array( $templates ) ? count( $templates ) : 0;
	}

	/**
	 * Returns other active plugins that render headers or footers.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,string> Basename => label.
	 */
	public static function header_footer_plugins() {
		$active = array();

		foreach ( self::HEADER_FOOTER_PLUGINS as $basename => $label ) {
			if ( self::plugin_is_active( $basename ) ) {
				$active[ $basename ] = $label;
			}
		}

		return $active;
	}

	/**
	 * Whether a plugin is active, loading the required helper when needed.
	 *
	 * @since 1.0.0
	 *
	 * @param string $basename Plugin basename.
	 * @return bool
	 */
	public static function plugin_is_active( $basename ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			$file = ABSPATH . 'wp-admin/includes/plugin.php';

			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			return false;
		}

		return (bool) is_plugin_active( (string) $basename );
	}

	/**
	 * Returns layout counts for the status screen.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,int>
	 */
	public static function layout_counts() {
		$counts = array(
			'total'   => 0,
			'enabled' => 0,
		);

		foreach ( Layout_Post_Type::get_type_slugs() as $type ) {
			$counts[ $type ] = 0;
		}

		$posts = get_posts(
			array(
				'post_type'              => Layout_Post_Type::POST_TYPE,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'trash' ),
				'posts_per_page'         => 500,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
			)
		);

		$counts['total'] = count( $posts );

		foreach ( $posts as $post_id ) {
			$status = get_post_status( $post_id );

			if ( 'publish' === $status ) {
				++$counts['enabled'];
			}

			$type = (string) get_post_meta( $post_id, Layout_Post_Type::META_TYPE, true );

			if ( isset( $counts[ $type ] ) ) {
				++$counts[ $type ];
			}
		}

		return $counts;
	}

	/**
	 * Returns a plain text report suitable for support requests.
	 *
	 * Contains no secrets, no tokens and no user data.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $report Report from {@see System_Status_Page::get_report()}.
	 * @return string
	 */
	public static function report_to_text( array $report ) {
		$lines = array();

		$lines[] = '### Qevora Layouts system status ###';

		foreach ( $report as $section ) {
			if ( empty( $section['title'] ) || empty( $section['rows'] ) ) {
				continue;
			}

			$lines[] = '';
			$lines[] = '-- ' . $section['title'] . ' --';

			foreach ( (array) $section['rows'] as $row ) {
				$label = isset( $row['label'] ) ? (string) $row['label'] : '';
				$value = isset( $row['value'] ) ? $row['value'] : '';

				if ( is_bool( $value ) ) {
					$value = $value ? 'yes' : 'no';
				} elseif ( is_array( $value ) ) {
					$value = implode( ', ', array_map( 'strval', $value ) );
				}

				$lines[] = $label . ': ' . (string) $value;
			}
		}

		return implode( "\n", $lines );
	}
}
