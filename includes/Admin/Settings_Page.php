<?php
/**
 * Settings screen.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Admin;

use QevoraLayouts\Core\Capabilities;
use QevoraLayouts\Core\Layout_Post_Type;
use QevoraLayouts\Core\Plugin;
use QevoraLayouts\Core\Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the plugin settings.
 *
 * Uses the WordPress Settings API: one option, one nonce handled by core, one
 * sanitize callback, and `manage_options` for every write.
 *
 * @since 1.0.0
 */
class Settings_Page {

	/**
	 * Settings group.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const GROUP = 'qevora_layouts_settings_group';

	/**
	 * Registers hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Registers the setting, sections and fields.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_settings() {
		if ( ! Capabilities::can_manage_settings() ) {
			return;
		}

		register_setting(
			self::GROUP,
			QEVORA_LAYOUTS_OPTION,
			array(
				'type'              => 'array',
				'description'       => __( 'Qevora Layouts settings', 'qevora-layouts' ),
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'show_in_rest'      => false,
				'default'           => Settings::get_defaults(),
			)
		);

		$sections = array(
			'rendering'   => array(
				'title' => __( 'Rendering', 'qevora-layouts' ),
				'text'  => __( 'Switch the layout types on or off and choose where they are inserted by default. Individual layouts can override the placement.', 'qevora-layouts' ),
			),
			'theme'       => array(
				'title' => __( 'Theme integration', 'qevora-layouts' ),
				'text'  => __( 'Optional helpers for themes that print their own header or footer. Nothing is removed without your input.', 'qevora-layouts' ),
			),
			'builders'    => array(
				'title' => __( 'Page builder integrations', 'qevora-layouts' ),
				'text'  => __( 'Integrations are optional. The block editor always works; Elementor is used only while it is active.', 'qevora-layouts' ),
			),
			'library'     => array(
				'title' => __( 'Template library and imports', 'qevora-layouts' ),
				'text'  => __( 'Bundled starter templates need no configuration. A remote library is optional and must be an https endpoint you control.', 'qevora-layouts' ),
			),
			'performance' => array(
				'title' => __( 'Performance and debugging', 'qevora-layouts' ),
				'text'  => __( 'Caching keeps condition matching cheap. Debug mode is off by default and only writes to the site debug log.', 'qevora-layouts' ),
			),
			'data'        => array(
				'title' => __( 'Data', 'qevora-layouts' ),
				'text'  => __( 'Controls what happens when the plugin is deleted. Layouts are ordinary posts and are never removed on deactivation.', 'qevora-layouts' ),
			),
		);

		foreach ( $sections as $id => $section ) {
			add_settings_section(
				'qevora_layouts_' . $id,
				isset( $section['title'] ) ? (string) $section['title'] : '',
				static function () use ( $section ) {
					if ( ! empty( $section['text'] ) ) {
						echo '<p class="description">' . esc_html( (string) $section['text'] ) . '</p>';
					}
				},
				Admin::SETTINGS_SLUG
			);
		}

		self::add_fields();
	}

	/**
	 * Adds every settings field.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function add_fields() {
		$page = Admin::SETTINGS_SLUG;

		$fields = array(
			// Rendering.
			array(
				'key'     => 'header_enabled',
				'section' => 'rendering',
				'label'   => __( 'Enable header layouts', 'qevora-layouts' ),
				'type'    => 'checkbox',
				'help'    => __( 'Turn off to stop rendering header layouts everywhere without deleting them.', 'qevora-layouts' ),
			),
			array(
				'key'     => 'footer_enabled',
				'section' => 'rendering',
				'label'   => __( 'Enable footer layouts', 'qevora-layouts' ),
				'type'    => 'checkbox',
				'help'    => __( 'Turn off to stop rendering footer layouts everywhere without deleting them.', 'qevora-layouts' ),
			),
			array(
				'key'     => 'section_enabled',
				'section' => 'rendering',
				'label'   => __( 'Enable reusable sections', 'qevora-layouts' ),
				'type'    => 'checkbox',
				'help'    => __( 'Sections render through the shortcode or the Qevora Layout block.', 'qevora-layouts' ),
			),
			array(
				'key'     => 'render_wrapper',
				'section' => 'rendering',
				'label'   => __( 'Wrap rendered layouts in a container element', 'qevora-layouts' ),
				'type'    => 'checkbox',
				'help'    => __( 'Adds a div with qevora-layouts classes. Themes can override the markup in qevora-layouts/layout-wrapper.php.', 'qevora-layouts' ),
			),
			array(
				'key'     => 'default_header_placement',
				'section' => 'rendering',
				'label'   => __( 'Default header placement', 'qevora-layouts' ),
				'type'    => 'select',
				'options' => Layout_Post_Type::get_placements( 'header' ),
				'help'    => __( 'wp_body_open works with classic and block themes. get_header only fires in classic themes that call get_header().', 'qevora-layouts' ),
			),
			array(
				'key'     => 'default_footer_placement',
				'section' => 'rendering',
				'label'   => __( 'Default footer placement', 'qevora-layouts' ),
				'type'    => 'select',
				'options' => Layout_Post_Type::get_placements( 'footer' ),
				'help'    => __( 'get_footer prints before the theme footer markup, wp_footer prints after it (just before the closing body tag).', 'qevora-layouts' ),
			),

			// Theme integration.
			array(
				'key'     => 'hide_theme_header_selectors',
				'section' => 'theme',
				'label'   => __( 'Hide the theme header with these CSS selectors', 'qevora-layouts' ),
				'type'    => 'text',
				'help'    => __( 'Optional and off by default. Comma separated selectors, for example: .site-header, header#masthead. Applied only on pages where a header layout renders.', 'qevora-layouts' ),
				'classes' => 'large-text code',
			),
			array(
				'key'     => 'hide_theme_footer_selectors',
				'section' => 'theme',
				'label'   => __( 'Hide the theme footer with these CSS selectors', 'qevora-layouts' ),
				'type'    => 'text',
				'help'    => __( 'Optional and off by default. Applied only on pages where a footer layout renders.', 'qevora-layouts' ),
				'classes' => 'large-text code',
			),
			array(
				'key'     => 'inline_hide_css',
				'section' => 'theme',
				'label'   => __( 'Print the hiding CSS', 'qevora-layouts' ),
				'type'    => 'checkbox',
				'help'    => __( 'Turn off if you prefer to add the rules to your theme or child theme stylesheet.', 'qevora-layouts' ),
			),

			// Builders.
			array(
				'key'     => 'builder_block',
				'section' => 'builders',
				'label'   => __( 'Block editor adapter', 'qevora-layouts' ),
				'type'    => 'checkbox',
				'help'    => __( 'Renders layout content with WordPress core. Disabling it stops layouts from rendering unless another adapter is available.', 'qevora-layouts' ),
			),
			array(
				'key'     => 'builder_elementor',
				'section' => 'builders',
				'label'   => __( 'Elementor adapter', 'qevora-layouts' ),
				'type'    => 'checkbox',
				'help'    => __( 'Renders layouts built with Elementor through the Elementor frontend API. Requires Elementor to be active.', 'qevora-layouts' ),
			),
			array(
				'key'     => 'elementor_editor_support',
				'section' => 'builders',
				'label'   => __( 'Allow building layouts with Elementor', 'qevora-layouts' ),
				'type'    => 'checkbox',
				'help'    => __( 'Adds the "Edit with Elementor" option to layouts by registering post type support, the same flag Elementor itself checks.', 'qevora-layouts' ),
			),

			// Library.
			array(
				'key'     => 'allow_uploaded_imports',
				'section' => 'library',
				'label'   => __( 'Allow importing .json template files', 'qevora-layouts' ),
				'type'    => 'checkbox',
				'help'    => __( 'Files are validated, never stored in wp-content/uploads and never executed.', 'qevora-layouts' ),
			),
			array(
				'key'     => 'max_import_size_kb',
				'section' => 'library',
				'label'   => __( 'Maximum template size (KB)', 'qevora-layouts' ),
				'type'    => 'number',
				'min'     => 16,
				'max'     => 20480,
				'help'    => __( 'Applies to imported template content. 16 to 20480 KB.', 'qevora-layouts' ),
				'classes' => 'small-text',
			),
			array(
				'key'     => 'duplicate_import_policy',
				'section' => 'library',
				'label'   => __( 'When a template was already imported', 'qevora-layouts' ),
				'type'    => 'select',
				'options' => array(
					'skip' => __( 'Skip it and keep the existing layout (default)', 'qevora-layouts' ),
					'copy' => __( 'Import a second copy', 'qevora-layouts' ),
				),
				'help'    => __( 'Existing layouts are never overwritten silently. You can still replace one layout explicitly from the library screen.', 'qevora-layouts' ),
			),
			array(
				'key'     => 'remote_library_enabled',
				'section' => 'library',
				'label'   => __( 'Enable a remote template library', 'qevora-layouts' ),
				'type'    => 'checkbox',
				'help'    => __( 'Off by default. There is no built-in remote service; the endpoint below is the only source used.', 'qevora-layouts' ),
			),
			array(
				'key'     => 'remote_library_url',
				'section' => 'library',
				'label'   => __( 'Remote library endpoint (https)', 'qevora-layouts' ),
				'type'    => 'url',
				'help'    => __( 'Must be an https URL returning the documented JSON index. Template download URLs must be on the same host.', 'qevora-layouts' ),
				'classes' => 'large-text code',
			),

			// Performance.
			array(
				'key'     => 'cache_candidates',
				'section' => 'performance',
				'label'   => __( 'Cache matching layouts', 'qevora-layouts' ),
				'type'    => 'checkbox',
				'help'    => __( 'Keeps the candidate list in the object cache. It is invalidated automatically whenever a layout or a setting changes.', 'qevora-layouts' ),
			),
			array(
				'key'     => 'cache_ttl_seconds',
				'section' => 'performance',
				'label'   => __( 'Cache lifetime (seconds)', 'qevora-layouts' ),
				'type'    => 'number',
				'min'     => 60,
				'max'     => 86400,
				'help'    => __( 'Between 60 and 86400 seconds.', 'qevora-layouts' ),
				'classes' => 'small-text',
			),
			array(
				'key'     => 'debug_mode',
				'section' => 'performance',
				'label'   => __( 'Debug mode', 'qevora-layouts' ),
				'type'    => 'checkbox',
				'help'    => __( 'Writes condition, rendering and import diagnostics to the PHP error log (and to debug.log when WP_DEBUG_LOG is on). No personal data is logged. Recent messages also appear on the System Status screen.', 'qevora-layouts' ),
			),

			// Data.
			array(
				'key'     => 'delete_data_on_uninstall',
				'section' => 'data',
				'label'   => __( 'Delete all plugin data when the plugin is deleted', 'qevora-layouts' ),
				'type'    => 'checkbox',
				'help'    => __( 'Off by default. When on, deleting the plugin removes every layout, layout meta and plugin option. Deactivating never deletes anything.', 'qevora-layouts' ),
			),
		);

		/**
		 * Filters the settings fields.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int,array<string,mixed>> $fields Field definitions.
		 */
		$fields = apply_filters( 'qevora_layouts_settings_fields', $fields );

		foreach ( (array) $fields as $field ) {
			if ( empty( $field['key'] ) || empty( $field['section'] ) ) {
				continue;
			}

			add_settings_field(
				'qevora_layouts_' . $field['key'],
				isset( $field['label'] ) ? (string) $field['label'] : '',
				array( __CLASS__, 'render_field' ),
				$page,
				'qevora_layouts_' . $field['section'],
				array(
					'label_for' => 'qevora-layouts-setting-' . $field['key'],
					'field'     => $field,
				)
			);
		}
	}

	/**
	 * Renders one settings field.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $args Field arguments.
	 * @return void
	 */
	public static function render_field( $args ) {
		$field = isset( $args['field'] ) && is_array( $args['field'] ) ? $args['field'] : array();

		if ( empty( $field['key'] ) ) {
			return;
		}

		$key   = (string) $field['key'];
		$type  = isset( $field['type'] ) ? (string) $field['type'] : 'text';
		$value = Settings::get( $key );
		$id    = 'qevora-layouts-setting-' . $key;
		$name  = QEVORA_LAYOUTS_OPTION . '[' . $key . ']';
		$class = isset( $field['classes'] ) ? (string) $field['classes'] : 'regular-text';

		switch ( $type ) {
			case 'checkbox':
				// The hidden input makes "off" submit a value.
				printf( '<input type="hidden" name="%1$s" value="0" />', esc_attr( $name ) );
				printf(
					'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s /> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( (bool) $value, true, false ),
					esc_html( isset( $field['label'] ) ? (string) $field['label'] : '' )
				);
				break;

			case 'select':
				printf( '<select id="%1$s" name="%2$s" class="%3$s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $class ) );

				foreach ( (array) ( isset( $field['options'] ) ? $field['options'] : array() ) as $option_value => $option_label ) {
					printf(
						'<option value="%1$s"%2$s>%3$s</option>',
						esc_attr( (string) $option_value ),
						selected( (string) $value, (string) $option_value, false ),
						esc_html( (string) $option_label )
					);
				}

				echo '</select>';
				break;

			case 'number':
				printf(
					'<input type="number" id="%1$s" name="%2$s" value="%3$s" class="%4$s" min="%5$d" max="%6$d" step="1" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					esc_attr( $class ),
					isset( $field['min'] ) ? (int) $field['min'] : 0,
					isset( $field['max'] ) ? (int) $field['max'] : 999999
				);
				break;

			case 'url':
				printf(
					'<input type="url" id="%1$s" name="%2$s" value="%3$s" class="%4$s" placeholder="%5$s" autocomplete="off" spellcheck="false" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					esc_attr( $class ),
					esc_attr__( 'https://example.com/qevora-layouts/index.json', 'qevora-layouts' )
				);
				break;

			default:
				printf(
					'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="%4$s" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					esc_attr( $class )
				);
				break;
		}

		if ( ! empty( $field['help'] ) ) {
			echo '<p class="description">' . esc_html( (string) $field['help'] ) . '</p>';
		}
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
		$view = QEVORA_LAYOUTS_DIR . 'includes/Admin/Views/settings.php';

		if ( ! is_readable( $view ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The settings screen could not be loaded.', 'qevora-layouts' ) . '</p></div>';

			return;
		}

		$context = array(
			'plugin'   => $plugin,
			'page'     => Admin::SETTINGS_SLUG,
			'settings' => Settings::all(),
		);

		/**
		 * Filters the data passed to the settings view.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,mixed> $context View context.
		 */
		$context = (array) apply_filters( 'qevora_layouts_settings_view_context', $context );

		require $view;
	}
}
