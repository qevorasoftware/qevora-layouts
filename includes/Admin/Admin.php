<?php
/**
 * Admin subsystem.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Admin;

use QevoraLayouts\Core\Capabilities;
use QevoraLayouts\Core\Layout_Post_Type;
use QevoraLayouts\Core\Plugin;
use QevoraLayouts\Core\Settings;
use QevoraLayouts\Conditions\Condition_Registry;
use QevoraLayouts\Integrations\Builder_Manager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menus, screens, assets and notices for wp-admin.
 *
 * Layout editing reuses the native post editor (including the block editor), so
 * the plugin adds meta boxes and list columns instead of a bespoke editor. All
 * assets are loaded only on plugin screens.
 *
 * @since 1.0.0
 */
class Admin {

	/**
	 * Menu slug of the template library.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const LIBRARY_SLUG = 'qevora-layouts-library';

	/**
	 * Menu slug of the settings screen.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const SETTINGS_SLUG = 'qevora-layouts-settings';

	/**
	 * Menu slug of the system status screen.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const STATUS_SLUG = 'qevora-layouts-status';

	/**
	 * Plugin instance.
	 *
	 * @since 1.0.0
	 * @var   Plugin
	 */
	private $plugin;

	/**
	 * Hook suffixes of the screens owned by this plugin.
	 *
	 * @since 1.0.0
	 * @var   string[]
	 */
	private $screens = array();

	/**
	 * Sub pages, keyed by slug.
	 *
	 * @since 1.0.0
	 * @var   array<string,mixed>
	 */
	private $pages = array();

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
	 * Registers admin hooks. Runs on `init` priority 20.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_menus' ), 9 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
		add_action( 'admin_notices', array( Notices::class, 'render' ), 5 );
		add_action( 'admin_init', array( $this, 'register_integration_notices' ) );

		Layout_Columns::register();
		Meta_Boxes::register();
		Settings_Page::register();
		System_Status_Page::register();
		Template_Library_Page::register();
		Actions::register();
		Ajax::register();

		/**
		 * Fires after the admin subsystem registered its hooks.
		 *
		 * @since 1.0.0
		 *
		 * @param self $admin Admin subsystem.
		 */
		do_action( 'qevora_layouts_admin_registered', $this );
	}

	/**
	 * Adds the menu. Runs on `admin_menu`.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_menus() {
		$post_type = Layout_Post_Type::POST_TYPE;
		$list_url  = 'edit.php?post_type=' . $post_type;
		$new_url   = 'post-new.php?post_type=' . $post_type;

		$parent = add_menu_page(
			__( 'Qevora Layouts', 'qevora-layouts' ),
			__( 'Qevora Layouts', 'qevora-layouts' ),
			Capabilities::EDIT_LAYOUTS,
			$list_url,
			'',
			'dashicons-layout',
			59
		);

		$this->screens[] = (string) $parent;
		$this->screens[] = 'edit-' . $post_type;
		$this->screens[] = $post_type;

		$submenus = array(
			array(
				'page_title' => __( 'All Layouts', 'qevora-layouts' ),
				'menu_title' => __( 'All Layouts', 'qevora-layouts' ),
				'capability' => Capabilities::EDIT_LAYOUTS,
				'slug'       => $list_url,
				'callback'   => '',
			),
			array(
				'page_title' => __( 'Add New Layout', 'qevora-layouts' ),
				'menu_title' => __( 'Add New', 'qevora-layouts' ),
				'capability' => Capabilities::PUBLISH_LAYOUTS,
				'slug'       => $new_url,
				'callback'   => '',
			),
			array(
				'page_title' => __( 'Template Library', 'qevora-layouts' ),
				'menu_title' => __( 'Template Library', 'qevora-layouts' ),
				'capability' => Capabilities::PUBLISH_LAYOUTS,
				'slug'       => self::LIBRARY_SLUG,
				'callback'   => array( $this, 'render_library' ),
			),
			array(
				'page_title' => __( 'Qevora Layouts Settings', 'qevora-layouts' ),
				'menu_title' => __( 'Settings', 'qevora-layouts' ),
				'capability' => Capabilities::MANAGE_SETTINGS,
				'slug'       => self::SETTINGS_SLUG,
				'callback'   => array( $this, 'render_settings' ),
			),
			array(
				'page_title' => __( 'System Status', 'qevora-layouts' ),
				'menu_title' => __( 'System Status', 'qevora-layouts' ),
				'capability' => Capabilities::MANAGE_SETTINGS,
				'slug'       => self::STATUS_SLUG,
				'callback'   => array( $this, 'render_status' ),
			),
		);

		/**
		 * Filters the plugin admin submenus.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int,array<string,mixed>> $submenus Submenu definitions.
		 * @param string                         $parent   Parent menu slug.
		 */
		$submenus = apply_filters( 'qevora_layouts_admin_submenus', $submenus, $list_url );

		foreach ( (array) $submenus as $submenu ) {
			if ( empty( $submenu['slug'] ) || empty( $submenu['capability'] ) ) {
				continue;
			}

			$hook = add_submenu_page(
				$list_url,
				isset( $submenu['page_title'] ) ? (string) $submenu['page_title'] : '',
				isset( $submenu['menu_title'] ) ? (string) $submenu['menu_title'] : '',
				(string) $submenu['capability'],
				(string) $submenu['slug'],
				empty( $submenu['callback'] ) ? null : $submenu['callback']
			);

			if ( false !== $hook ) {
				$this->screens[]                 = (string) $hook;
				$this->pages[ $submenu['slug'] ] = (string) $hook;
			}
		}
	}

	/**
	 * Returns the hook suffix of one plugin screen.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Menu slug.
	 * @return string Empty when unknown.
	 */
	public function get_screen_hook( $slug ) {
		return isset( $this->pages[ $slug ] ) ? (string) $this->pages[ $slug ] : '';
	}

	/**
	 * Returns every screen hook owned by the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public function get_screens() {
		return array_values( array_unique( $this->screens ) );
	}

	/**
	 * Whether the current admin screen belongs to the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Hook suffix passed to `admin_enqueue_scripts`.
	 * @return bool
	 */
	public function is_plugin_screen( $hook ) {
		$hook = (string) $hook;

		if ( in_array( $hook, $this->get_screens(), true ) ) {
			return true;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( null === $screen ) {
			return false;
		}

		$own = array(
			'edit-' . Layout_Post_Type::POST_TYPE,
			Layout_Post_Type::POST_TYPE,
		);

		return in_array( (string) $screen->id, $own, true );
	}

	/**
	 * Loads assets on plugin screens only.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	private function asset_version( $relative ) {
		$file = QEVORA_LAYOUTS_DIR . $relative;
		$time = file_exists( $file ) ? (int) filemtime( $file ) : 0;

		return QEVORA_LAYOUTS_VERSION . ( $time ? '.' . $time : '' );
	}

	/**
	 * Queues admin screens' styles and scripts.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( ! $this->is_plugin_screen( $hook ) ) {
			return;
		}

		wp_enqueue_style(
			'qevora-layouts-admin',
			QEVORA_LAYOUTS_URL . 'assets/css/admin.css',
			array(),
			$this->asset_version( 'assets/css/admin.css' )
		);

		$screen   = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$screen_id = $screen ? (string) $screen->id : '';

		$is_editor = in_array( $screen_id, array( Layout_Post_Type::POST_TYPE ), true );
		$is_list   = 'edit-' . Layout_Post_Type::POST_TYPE === $screen_id;

		wp_enqueue_script(
			'qevora-layouts-admin',
			QEVORA_LAYOUTS_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			$this->asset_version( 'assets/js/admin.js' ),
			true
		);

		wp_localize_script(
			'qevora-layouts-admin',
			'qevoraLayoutsAdmin',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'restUrl'      => esc_url_raw( rest_url( 'qevora-layouts/v1/' ) ),
				'coreRestUrl'  => esc_url_raw( rest_url( 'wp/v2/' ) ),
				'nonce'        => wp_create_nonce( 'qevora_layouts_admin' ),
				'restNonce'    => wp_create_nonce( 'wp_rest' ),
				'postType'     => Layout_Post_Type::POST_TYPE,
				'isEditor'     => $is_editor,
				'isList'       => $is_list,
				'conditions'   => Condition_Registry::for_js(),
				'postTypes'    => $this->get_selectable_post_types(),
				'taxonomies'   => $this->get_selectable_taxonomies(),
				'roles'        => $this->get_selectable_roles(),
				'placements'   => Layout_Post_Type::get_placements( 'header' ),
				'footerPlaces' => Layout_Post_Type::get_placements( 'footer' ),
				'i18n'         => array(
					'selectCondition'  => __( 'Select a condition', 'qevora-layouts' ),
					'selectValue'      => __( 'Select a value', 'qevora-layouts' ),
					'idsPlaceholder'   => __( 'Comma separated IDs, e.g. 12, 48', 'qevora-layouts' ),
					'hookPlaceholder'  => __( 'theme_hook_name', 'qevora-layouts' ),
					'remove'           => __( 'Remove', 'qevora-layouts' ),
					'searching'        => __( 'Searching…', 'qevora-layouts' ),
					'noResults'        => __( 'No results.', 'qevora-layouts' ),
					'error'            => __( 'Something went wrong. Please try again.', 'qevora-layouts' ),
					'importing'        => __( 'Importing…', 'qevora-layouts' ),
					'imported'         => __( 'Imported.', 'qevora-layouts' ),
					'skipped'          => __( 'Skipped.', 'qevora-layouts' ),
					'networkError'     => __( 'The request did not reach the server. Check your connection and try again.', 'qevora-layouts' ),
					'forbidden'        => __( 'You are not allowed to do this, or your session expired.', 'qevora-layouts' ),
					'conditionAdded'   => __( 'Condition added. Choose a condition type.', 'qevora-layouts' ),
					'conditionRemoved' => __( 'Condition removed.', 'qevora-layouts' ),
					'searchPosts'      => __( 'Search posts and pages', 'qevora-layouts' ),
					'searchTerms'      => __( 'Search terms', 'qevora-layouts' ),
					'searchUsers'      => __( 'Search users', 'qevora-layouts' ),
					'searchPlaceholder' => __( 'Type at least two characters…', 'qevora-layouts' ),
					'search'           => __( 'Search', 'qevora-layouts' ),
					'close'            => __( 'Close', 'qevora-layouts' ),
					'searchHint'       => __( 'Type at least two characters to search.', 'qevora-layouts' ),
					'alreadyAdded'     => __( 'That ID is already in the list.', 'qevora-layouts' ),
					'added'            => __( 'Added.', 'qevora-layouts' ),
					'wrongFileType'    => __( 'Only .json template files are accepted.', 'qevora-layouts' ),
					'readyToImport'    => __( 'Ready to import.', 'qevora-layouts' ),
					'uploading'        => __( 'Uploading…', 'qevora-layouts' ),
					'fallingBack'      => __( 'The request could not be sent. Submitting the form directly…', 'qevora-layouts' ),
					'editLayout'       => __( 'Open the imported layout', 'qevora-layouts' ),
					'allLayouts'       => __( 'Open all layouts', 'qevora-layouts' ),
					'copied'           => __( 'Copied to the clipboard.', 'qevora-layouts' ),
					'copyReport'       => __( 'Copy status report', 'qevora-layouts' ),
					'nothingToCopy'    => __( 'There is nothing to copy.', 'qevora-layouts' ),
					'selectManually'   => __( 'Copy the text manually from the report box.', 'qevora-layouts' ),
				),
			)
		);

		wp_set_script_translations( 'qevora-layouts-admin', 'qevora-layouts', QEVORA_LAYOUTS_DIR . 'languages' );

		if ( $is_editor ) {
			wp_enqueue_script(
				'qevora-layouts-conditions',
				QEVORA_LAYOUTS_URL . 'assets/js/conditions.js',
				array( 'qevora-layouts-admin' ),
				$this->asset_version( 'assets/js/conditions.js' ),
				true
			);

			wp_set_script_translations( 'qevora-layouts-conditions', 'qevora-layouts', QEVORA_LAYOUTS_DIR . 'languages' );
		}

		if ( $this->get_screen_hook( self::LIBRARY_SLUG ) === $hook ) {
			wp_enqueue_script(
				'qevora-layouts-library',
				QEVORA_LAYOUTS_URL . 'assets/js/library.js',
				array( 'qevora-layouts-admin' ),
				$this->asset_version( 'assets/js/library.js' ),
				true
			);

			wp_set_script_translations( 'qevora-layouts-library', 'qevora-layouts', QEVORA_LAYOUTS_DIR . 'languages' );
		}

		/**
		 * Fires after plugin admin assets were enqueued.
		 *
		 * @since 1.0.0
		 *
		 * @param string $hook     Current screen hook.
		 * @param string $screenId Current screen ID.
		 */
		do_action( 'qevora_layouts_admin_assets', $hook, $screen_id );
	}

	/**
	 * Returns public post types that can be used in conditions.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,string>
	 */
	public function get_selectable_post_types() {
		$types = get_post_types( array( 'public' => true ), 'objects' );
		$out   = array();

		foreach ( (array) $types as $type ) {
			if ( ! isset( $type->name, $type->labels ) || Layout_Post_Type::POST_TYPE === $type->name ) {
				continue;
			}

			$out[ $type->name ] = isset( $type->labels->singular_name ) ? (string) $type->labels->singular_name : (string) $type->name;
		}

		/**
		 * Filters the post types offered in the condition UI.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,string> $out Post type slug => label.
		 */
		return apply_filters( 'qevora_layouts_condition_post_types', $out );
	}

	/**
	 * Returns public taxonomies that can be used in conditions.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,string>
	 */
	public function get_selectable_taxonomies() {
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		$out        = array();

		foreach ( (array) $taxonomies as $taxonomy ) {
			if ( ! isset( $taxonomy->name, $taxonomy->labels ) ) {
				continue;
			}

			$out[ $taxonomy->name ] = isset( $taxonomy->labels->name ) ? (string) $taxonomy->labels->name : (string) $taxonomy->name;
		}

		/**
		 * Filters the taxonomies offered in the condition UI.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,string> $out Taxonomy slug => label.
		 */
		return apply_filters( 'qevora_layouts_condition_taxonomies', $out );
	}

	/**
	 * Returns editable roles for the user role condition.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,string>
	 */
	public function get_selectable_roles() {
		$out = array();

		foreach ( (array) wp_roles()->roles as $slug => $role ) {
			$name = isset( $role['name'] ) ? translate_user_role( (string) $role['name'] ) : (string) $slug;

			$out[ (string) $slug ] = $name;
		}

		return $out;
	}

	/**
	 * Adds informational notices about builder availability.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_integration_notices() {
		if ( ! Capabilities::can_edit_layouts() ) {
			return;
		}

		add_action( 'admin_notices', array( $this, 'render_integration_notices' ), 6 );
	}

	/**
	 * Renders notices about missing or conflicting integrations.
	 *
	 * The plugin never blocks the site: it explains what is available and what
	 * the supported alternatives are.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_integration_notices() {
		if ( ! $this->is_plugin_screen( $this->current_hook() ) ) {
			return;
		}

		$builders = $this->plugin->builders()->get_status();

		// No third-party page builder active: point at the built-in block editor.
		$has_builder = false;

		foreach ( $builders as $builder ) {
			if ( ! empty( $builder['available'] ) && 'block' !== $builder['id'] ) {
				$has_builder = true;
			}
		}

		if ( ! $has_builder && Settings::is_enabled( 'builder_elementor' ) ) {
			echo '<div class="notice notice-info"><p>';
			echo esc_html__( 'Qevora Layouts: no third-party page builder was detected. Layouts are built with the WordPress block editor, which needs no extra plugin.', 'qevora-layouts' );

			if ( Builder_Manager::is_plugin_active_by_basename( 'elementor/elementor.php' ) ) {
				echo ' ';
				echo esc_html__( 'Elementor is installed; enable the integration in Settings → Integrations if you want to build layouts with it.', 'qevora-layouts' );
			}

			echo '</p></div>';
		}

		// A layout built with Elementor exists but Elementor is gone.
		if ( ! Builder_Manager::is_plugin_active_by_basename( 'elementor/elementor.php' ) && $this->has_orphaned_elementor_layouts() ) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'Qevora Layouts: one or more layouts were built with Elementor, but Elementor is not active. Those layouts fall back to their stored block content, which may be empty. Reactivate Elementor or edit the layouts.', 'qevora-layouts' );
			echo '</p></div>';
		}

		/**
		 * Fires after the plugin rendered its integration notices.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int,array<string,mixed>> $builders Builder status.
		 */
		do_action( 'qevora_layouts_admin_integration_notices', $builders );
	}

	/**
	 * Whether any layout was built with a builder that is no longer available.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	private function has_orphaned_elementor_layouts() {
		$cached = get_transient( 'qevora_layouts_orphaned_elementor' );

		if ( is_bool( $cached ) ) {
			return $cached;
		}

		$query = new \WP_Query(
			array(
				'post_type'              => Layout_Post_Type::POST_TYPE,
				'post_status'            => array( 'publish', 'draft' ),
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'fields'                 => 'ids',
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Cached admin check.
					array(
						'key'   => Layout_Post_Type::META_BUILDER,
						'value' => 'elementor',
					),
				),
			)
		);

		$found = ! empty( $query->posts );

		set_transient( 'qevora_layouts_orphaned_elementor', $found, 6 * HOUR_IN_SECONDS );

		return $found;
	}

	/**
	 * Returns the current admin page hook, best effort.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function current_hook() {
		global $pagenow, $plugin_page;

		if ( isset( $plugin_page ) && is_string( $plugin_page ) ) {
			foreach ( $this->pages as $slug => $hook ) {
				if ( $slug === $plugin_page ) {
					return (string) $hook;
				}
			}
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( null !== $screen && isset( $screen->base ) ) {
			return (string) $screen->base;
		}

		return isset( $pagenow ) ? (string) $pagenow : '';
	}

	/**
	 * Renders the template library screen.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_library() {
		if ( ! Capabilities::can_publish_layouts() ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'qevora-layouts' ), 403 );
		}

		Template_Library_Page::render( $this->plugin );
	}

	/**
	 * Renders the settings screen.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_settings() {
		if ( ! Capabilities::can_manage_settings() ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'qevora-layouts' ), 403 );
		}

		Settings_Page::render( $this->plugin );
	}

	/**
	 * Renders the system status screen.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_status() {
		if ( ! Capabilities::can_manage_settings() ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'qevora-layouts' ), 403 );
		}

		System_Status_Page::render( $this->plugin );
	}
}
