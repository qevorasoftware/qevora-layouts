<?php
/**
 * Layout post type and meta registration.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Core;

use QevoraLayouts\Conditions\Condition_Sanitizer;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the `qevora_layout` custom post type and all layout meta keys.
 *
 * Layouts are ordinary WordPress posts: they inherit revisions, the trash,
 * capabilities, the REST API and the block editor. Layout behaviour is stored
 * in registered post meta so it survives exports and is readable by other
 * plugins through supported APIs.
 *
 * @since 1.0.0
 */
class Layout_Post_Type {

	/**
	 * Post type slug.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const POST_TYPE = 'qevora_layout';

	/**
	 * Meta key: layout type.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const META_TYPE = '_qevora_layout_type';

	/**
	 * Meta key: rendering priority.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const META_PRIORITY = '_qevora_layout_priority';

	/**
	 * Meta key: display conditions.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const META_CONDITIONS = '_qevora_layout_conditions';

	/**
	 * Meta key: injection point.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const META_PLACEMENT = '_qevora_layout_placement';

	/**
	 * Meta key: hide the theme's own header or footer while this layout is
	 * displayed. The consent lives on the layout itself.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const META_HIDE_THEME_PART = '_qevora_layout_hide_theme_part';

	/**
	 * Meta key: custom hook name.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const META_HOOK = '_qevora_layout_hook';

	/**
	 * Meta key: custom hook priority.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const META_HOOK_PRIORITY = '_qevora_layout_hook_priority';

	/**
	 * Meta key: builder hint.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const META_BUILDER = '_qevora_layout_builder';

	/**
	 * Meta key: content source mode.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const META_CONTENT_SOURCE = '_qevora_layout_content_source';

	/**
	 * Meta key: referenced post ID.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const META_REFERENCE_ID = '_qevora_layout_reference_id';

	/**
	 * Meta key: imported template identifier.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const META_TEMPLATE_ID = '_qevora_layout_template_id';

	/**
	 * Meta key: import origin (bundled|upload|remote).
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const META_IMPORT_SOURCE = '_qevora_layout_import_source';

	/**
	 * Meta key: import timestamp.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const META_IMPORTED_AT = '_qevora_layout_imported_at';

	/**
	 * Meta key: slug of the bundled PHP demo a layout renders.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const META_DEMO = '_qevora_layout_demo';

	/**
	 * Meta key: nav menu created for a demo layout.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const META_MENU_ID = '_qevora_layout_menu_id';

	/**
	 * Registers post type, meta and rewrite rules.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register() {
		$labels = array(
			'name'               => __( 'Layouts', 'qevora-layouts' ),
			'singular_name'      => __( 'Layout', 'qevora-layouts' ),
			'menu_name'          => __( 'All Layouts', 'qevora-layouts' ),
			'add_new'            => __( 'Add New', 'qevora-layouts' ),
			'add_new_item'       => __( 'Add New Layout', 'qevora-layouts' ),
			'edit_item'          => __( 'Edit Layout', 'qevora-layouts' ),
			'new_item'           => __( 'New Layout', 'qevora-layouts' ),
			'view_item'          => __( 'View Layout', 'qevora-layouts' ),
			'view_items'         => __( 'View Layouts', 'qevora-layouts' ),
			'search_items'       => __( 'Search Layouts', 'qevora-layouts' ),
			'not_found'          => __( 'No layouts found.', 'qevora-layouts' ),
			'not_found_in_trash' => __( 'No layouts found in Trash.', 'qevora-layouts' ),
			'all_items'          => __( 'All Layouts', 'qevora-layouts' ),
			'archives'           => __( 'Layout Archives', 'qevora-layouts' ),
			'attributes'         => __( 'Layout Attributes', 'qevora-layouts' ),
			'insert_into_item'   => __( 'Insert into layout', 'qevora-layouts' ),
			'uploaded_to_this_item' => __( 'Uploaded to this layout', 'qevora-layouts' ),
			'filter_items_list'  => __( 'Filter layouts list', 'qevora-layouts' ),
			'items_list_navigation' => __( 'Layouts list navigation', 'qevora-layouts' ),
			'items_list'         => __( 'Layouts list', 'qevora-layouts' ),
			'item_published'     => __( 'Layout published.', 'qevora-layouts' ),
			'item_reverted_to_draft' => __( 'Layout reverted to draft.', 'qevora-layouts' ),
		);

		$args = array(
			'labels'              => $labels,
			'description'         => __( 'Headers, footers and reusable layout templates managed by Qevora Layouts.', 'qevora-layouts' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => false, // Added under the Qevora Layouts menu.
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => true,
			'rest_base'           => 'qevora-layouts',
			'rest_controller_class' => 'WP_REST_Posts_Controller',
			'map_meta_cap'        => true,
			'capability_type'     => 'post',
			'supports'            => array( 'title', 'editor', 'revisions', 'custom-fields' ),
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'menu_icon'           => 'dashicons-layout',
			'template'            => array(),
			'delete_with_user'    => false,
		);

		/**
		 * Filters the arguments used to register the layout post type.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,mixed> $args register_post_type() arguments.
		 */
		$args = apply_filters( 'qevora_layouts_post_type_args', $args );

		register_post_type( self::POST_TYPE, $args );

		self::register_meta();
	}

	/**
	 * Registers all layout meta keys with sanitize callbacks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_meta() {
		$definitions = array(
			self::META_TYPE            => array(
				'type'     => 'string',
				'default'  => 'header',
				'sanitize' => array( __CLASS__, 'sanitize_type' ),
				'rest'     => true,
			),
			self::META_PRIORITY        => array(
				'type'     => 'integer',
				'default'  => 10,
				'sanitize' => array( __CLASS__, 'sanitize_priority' ),
				'rest'     => true,
			),
			self::META_CONDITIONS      => array(
				'type'     => 'array',
				'default'  => array(),
				'sanitize' => array( Condition_Sanitizer::class, 'sanitize' ),
				'rest'     => false,
			),
			self::META_PLACEMENT       => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => array( __CLASS__, 'sanitize_placement' ),
				'rest'     => true,
			),
			self::META_HIDE_THEME_PART => array(
				'type'     => 'boolean',
				'default'  => false,
				'sanitize' => array( __CLASS__, 'sanitize_hide_theme_part' ),
				'rest'     => true,
			),
			self::META_HOOK            => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => array( __CLASS__, 'sanitize_hook_name' ),
				'rest'     => false,
			),
			self::META_HOOK_PRIORITY   => array(
				'type'     => 'integer',
				'default'  => 10,
				'sanitize' => array( __CLASS__, 'sanitize_hook_priority' ),
				'rest'     => false,
			),
			self::META_BUILDER         => array(
				'type'     => 'string',
				'default'  => 'auto',
				'sanitize' => array( __CLASS__, 'sanitize_builder' ),
				'rest'     => true,
			),
			self::META_CONTENT_SOURCE  => array(
				'type'     => 'string',
				'default'  => 'self',
				'sanitize' => array( __CLASS__, 'sanitize_content_source' ),
				'rest'     => true,
			),
			self::META_REFERENCE_ID    => array(
				'type'     => 'integer',
				'default'  => 0,
				'sanitize' => array( __CLASS__, 'sanitize_reference_id' ),
				'rest'     => true,
			),
			self::META_TEMPLATE_ID     => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => array( __CLASS__, 'sanitize_identifier' ),
				'rest'     => false,
			),
			self::META_IMPORT_SOURCE   => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => array( __CLASS__, 'sanitize_import_source' ),
				'rest'     => false,
			),
			self::META_IMPORTED_AT     => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
				'rest'     => false,
			),
			self::META_DEMO            => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => array( __CLASS__, 'sanitize_identifier' ),
				'rest'     => true,
			),
			self::META_MENU_ID         => array(
				'type'     => 'integer',
				'default'  => 0,
				'sanitize' => 'absint',
				'rest'     => true,
			),
		);

		/**
		 * Filters the registered layout meta definitions.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,array<string,mixed>> $definitions Meta definitions.
		 */
		$definitions = apply_filters( 'qevora_layouts_meta_definitions', $definitions );

		foreach ( $definitions as $key => $definition ) {
			$args = array(
				'type'              => isset( $definition['type'] ) ? $definition['type'] : 'string',
				'description'       => __( 'Qevora Layouts metadata.', 'qevora-layouts' ),
				'single'            => true,
				'show_in_rest'      => ! empty( $definition['rest'] ),
				'sanitize_callback' => isset( $definition['sanitize'] ) ? $definition['sanitize'] : null,
				'auth_callback'     => array( __CLASS__, 'meta_auth_callback' ),
				'default'           => isset( $definition['default'] ) ? $definition['default'] : '',
			);

			register_post_meta( self::POST_TYPE, $key, $args );
		}
	}

	/**
	 * Meta auth callback: only users who can edit the layout may change meta.
	 *
	 * @since 1.0.0
	 *
	 * @param bool   $allowed   Whether the user can add the meta.
	 * @param string $meta_key  Meta key.
	 * @param int    $object_id Object ID.
	 * @return bool
	 */
	public static function meta_auth_callback( $allowed, $meta_key, $object_id ) {
		unset( $meta_key );

		return Capabilities::can_edit_layout( $object_id );
	}

	/**
	 * Returns the supported layout types.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function get_types() {
		$types = array(
			'header'  => array(
				'label'       => __( 'Header', 'qevora-layouts' ),
				'description' => __( 'A header layout rendered near the top of the page.', 'qevora-layouts' ),
			),
			'footer'  => array(
				'label'       => __( 'Footer', 'qevora-layouts' ),
				'description' => __( 'A footer layout rendered near the bottom of the page.', 'qevora-layouts' ),
			),
			'section' => array(
				'label'       => __( 'Reusable Section', 'qevora-layouts' ),
				'description' => __( 'A reusable block of content inserted with a shortcode or a block.', 'qevora-layouts' ),
			),
		);

		/**
		 * Filters the available layout types.
		 *
		 * The data model is intentionally open so future releases can add
		 * archive, single, search or 404 template types without a schema
		 * change.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,array<string,string>> $types Layout types.
		 */
		return apply_filters( 'qevora_layouts_types', $types );
	}

	/**
	 * Returns the slugs of the supported layout types.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public static function get_type_slugs() {
		return array_keys( self::get_types() );
	}

	/**
	 * Whether a layout type slug is valid.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $type Candidate type.
	 * @return bool
	 */
	public static function is_valid_type( $type ) {
		return is_string( $type ) && in_array( $type, self::get_type_slugs(), true );
	}

	/**
	 * Returns the translated label for a layout type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Layout type.
	 * @return string
	 */
	public static function get_type_label( $type ) {
		$types = self::get_types();

		if ( isset( $types[ $type ]['label'] ) ) {
			return $types[ $type ]['label'];
		}

		return ucfirst( (string) $type );
	}

	/**
	 * Sanitizes the hide-the-theme-part flag.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public static function sanitize_hide_theme_part( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		return in_array( (string) $value, array( '1', 'on', 'true' ), true );
	}

	/**
	 * Returns placement choices for a layout type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Layout type.
	 * @return array<string,string> Placement slug => label.
	 */
	public static function get_placements( $type ) {
		if ( 'footer' === $type ) {
			$placements = array(
				'get_footer'   => __( 'Above the site footer - best for most themes', 'qevora-layouts' ),
				'wp_footer'    => __( 'At the very bottom of the page', 'qevora-layouts' ),
				'wp_body_open' => __( 'At the top of the page - rarely useful for a footer', 'qevora-layouts' ),
				'custom_hook'  => __( 'A custom position - advanced, choose the hook below', 'qevora-layouts' ),
				'manual'       => __( 'Do not show automatically - place it with the shortcode, block or template tag', 'qevora-layouts' ),
			);
		} else {
			$placements = array(
				'wp_body_open' => __( 'At the top of the page, before the theme header - best for most themes', 'qevora-layouts' ),
				'get_header'   => __( 'Inside the theme header - classic themes only', 'qevora-layouts' ),
				'custom_hook'  => __( 'A custom position - advanced, choose the hook below', 'qevora-layouts' ),
				'manual'       => __( 'Do not show automatically - place it with the shortcode, block or template tag', 'qevora-layouts' ),
			);
		}

		/**
		 * Filters the placement choices offered for a layout type.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,string> $placements Placement choices.
		 * @param string               $type       Layout type.
		 */
		return apply_filters( 'qevora_layouts_placements', $placements, $type );
	}

	/**
	 * Sanitizes a layout type value.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_type( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';

		if ( self::is_valid_type( $value ) ) {
			return $value;
		}

		$slugs = self::get_type_slugs();

		return isset( $slugs[0] ) ? $slugs[0] : 'header';
	}

	/**
	 * Sanitizes a priority value.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function sanitize_priority( $value ) {
		return max( 0, min( 999, (int) $value ) );
	}

	/**
	 * Sanitizes a placement value.
	 *
	 * Empty means "use the plugin default for this layout type".
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_placement( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';

		$allowed = array_merge( Settings::HEADER_PLACEMENTS, Settings::FOOTER_PLACEMENTS );

		return in_array( $value, $allowed, true ) ? $value : '';
	}

	/**
	 * Sanitizes a custom hook name.
	 *
	 * Only a conservative hook name character set is accepted; core hook names
	 * that would let a layout render outside of the intended area are refused.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_hook_name( $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';

		if ( '' === $value ) {
			return '';
		}

		if ( 1 !== preg_match( '/^[a-zA-Z0-9_\-\/.]{3,80}$/', $value ) ) {
			return '';
		}

		$blocked = array( 'init', 'plugins_loaded', 'shutdown', 'wp_loaded', 'admin_init', 'wp_head', 'wp_footer', 'template_redirect', 'parse_query', 'pre_get_posts' );

		if ( in_array( strtolower( $value ), $blocked, true ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * Sanitizes a custom hook priority.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function sanitize_hook_priority( $value ) {
		return max( 0, min( 999, (int) $value ) );
	}

	/**
	 * Sanitizes the builder hint.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_builder( $value ) {
		$value = is_scalar( $value ) ? (string) $value : 'auto';
		$value = sanitize_key( $value );

		$allowed = array( 'auto', 'block', 'elementor', 'demo' );

		/**
		 * Filters the builder identifiers a layout may request.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $allowed Allowed builder identifiers.
		 */
		$allowed = apply_filters( 'qevora_layouts_allowed_builders', $allowed );

		return in_array( $value, $allowed, true ) ? $value : 'auto';
	}

	/**
	 * Sanitizes the content source mode.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_content_source( $value ) {
		$value = is_scalar( $value ) ? (string) $value : 'self';

		return in_array( $value, array( 'self', 'reference' ), true ) ? $value : 'self';
	}

	/**
	 * Sanitizes a referenced post ID.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function sanitize_reference_id( $value ) {
		return max( 0, (int) $value );
	}

	/**
	 * Sanitizes a template identifier.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_identifier( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';

		if ( 1 !== preg_match( '/^[a-z0-9._\-]{1,120}$/i', $value ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * Sanitizes an import source value.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_import_source( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';

		return in_array( $value, array( 'bundled', 'upload', 'remote' ), true ) ? $value : '';
	}

	/**
	 * Returns every meta key used by the plugin.
	 *
	 * Used by uninstall and by the system status screen.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public static function get_meta_keys() {
		return array(
			self::META_TYPE,
			self::META_PRIORITY,
			self::META_CONDITIONS,
			self::META_PLACEMENT,
			self::META_HOOK,
			self::META_HOOK_PRIORITY,
			self::META_BUILDER,
			self::META_CONTENT_SOURCE,
			self::META_REFERENCE_ID,
			self::META_TEMPLATE_ID,
			self::META_IMPORT_SOURCE,
			self::META_IMPORTED_AT,
			self::META_HIDE_THEME_PART,
			self::META_DEMO,
			self::META_MENU_ID,
		);
	}
}
