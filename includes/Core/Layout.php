<?php
/**
 * Layout model.
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
 * A single header, footer or reusable section.
 *
 * The model is a lightweight value object. It can be built from a `WP_Post`
 * (reading registered meta) or from a plain array (hydrating cached data), and
 * it can be exported back to an array so it can be stored in the object cache.
 *
 * @since 1.0.0
 */
class Layout {

	/**
	 * Post ID.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	public $id = 0;

	/**
	 * Title.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $title = '';

	/**
	 * Layout type: header|footer|section.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $type = 'header';

	/**
	 * Post status. `publish` means the layout is enabled.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $status = 'draft';

	/**
	 * Rendering priority. Higher wins.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	public $priority = 10;

	/**
	 * Normalized display conditions.
	 *
	 * @since 1.0.0
	 * @var   array<string,array<int,array<string,string>>>
	 */
	public $conditions = array(
		'include' => array(),
		'exclude' => array(),
	);

	/**
	 * Placement override. Empty means "use the plugin default".
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $placement = '';

	/**
	 * Hide the theme's own header or footer while this layout is displayed.
	 *
	 * Only meaningful for header and footer layouts. Theme files are never
	 * modified: the suppression happens per request and is fully reversible.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	public $hide_theme_part = false;

	/**
	 * Custom hook name when placement is `custom_hook`.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $hook = '';

	/**
	 * Custom hook priority.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	public $hook_priority = 10;

	/**
	 * Builder hint: auto|block|elementor.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $builder = 'auto';

	/**
	 * Content source: self|reference.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $content_source = 'self';

	/**
	 * Referenced post ID when content_source is `reference`.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	public $reference_id = 0;

	/**
	 * Template identifier this layout was imported from.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $template_id = '';

	/**
	 * Import origin: bundled|upload|remote.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $import_source = '';

	/**
	 * Import timestamp (ISO 8601, UTC).
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $imported_at = '';

	/**
	 * Builds a layout from a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int|\WP_Post $post Post or post ID.
	 * @return self|null Null when the post is not a layout.
	 */
	public static function from_post( $post ) {
		$post = get_post( $post );

		if ( ! $post instanceof \WP_Post || Layout_Post_Type::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$layout = new self();

		$layout->id             = (int) $post->ID;
		$layout->title          = (string) get_the_title( $post );
		$layout->status         = (string) $post->post_status;
		$layout->type           = Layout_Post_Type::sanitize_type( get_post_meta( $layout->id, Layout_Post_Type::META_TYPE, true ) );
		$layout->priority       = Layout_Post_Type::sanitize_priority( get_post_meta( $layout->id, Layout_Post_Type::META_PRIORITY, true ) );
		$layout->conditions     = Condition_Sanitizer::normalize( get_post_meta( $layout->id, Layout_Post_Type::META_CONDITIONS, true ) );
		$layout->placement      = Layout_Post_Type::sanitize_placement( get_post_meta( $layout->id, Layout_Post_Type::META_PLACEMENT, true ) );
		$layout->hide_theme_part = Layout_Post_Type::sanitize_hide_theme_part( get_post_meta( $layout->id, Layout_Post_Type::META_HIDE_THEME_PART, true ) );
		$layout->hook           = Layout_Post_Type::sanitize_hook_name( get_post_meta( $layout->id, Layout_Post_Type::META_HOOK, true ) );
		$layout->hook_priority  = Layout_Post_Type::sanitize_hook_priority( get_post_meta( $layout->id, Layout_Post_Type::META_HOOK_PRIORITY, true ) );
		$layout->builder        = Layout_Post_Type::sanitize_builder( get_post_meta( $layout->id, Layout_Post_Type::META_BUILDER, true ) );
		$layout->content_source = Layout_Post_Type::sanitize_content_source( get_post_meta( $layout->id, Layout_Post_Type::META_CONTENT_SOURCE, true ) );
		$layout->reference_id   = Layout_Post_Type::sanitize_reference_id( get_post_meta( $layout->id, Layout_Post_Type::META_REFERENCE_ID, true ) );
		$layout->template_id    = Layout_Post_Type::sanitize_identifier( get_post_meta( $layout->id, Layout_Post_Type::META_TEMPLATE_ID, true ) );
		$layout->import_source  = Layout_Post_Type::sanitize_import_source( get_post_meta( $layout->id, Layout_Post_Type::META_IMPORT_SOURCE, true ) );
		$layout->imported_at    = sanitize_text_field( (string) get_post_meta( $layout->id, Layout_Post_Type::META_IMPORTED_AT, true ) );

		return $layout;
	}

	/**
	 * Builds a layout from an array (cached data or import payload).
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $data Layout data.
	 * @return self
	 */
	public static function from_array( array $data ) {
		$layout = new self();

		$layout->id             = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$layout->title          = isset( $data['title'] ) ? (string) $data['title'] : '';
		$layout->status         = isset( $data['status'] ) ? (string) $data['status'] : 'draft';
		$layout->type           = Layout_Post_Type::sanitize_type( isset( $data['type'] ) ? $data['type'] : 'header' );
		$layout->priority       = Layout_Post_Type::sanitize_priority( isset( $data['priority'] ) ? $data['priority'] : 10 );
		$layout->conditions     = Condition_Sanitizer::normalize( isset( $data['conditions'] ) ? $data['conditions'] : array() );
		$layout->placement      = Layout_Post_Type::sanitize_placement( isset( $data['placement'] ) ? $data['placement'] : '' );
		$layout->hide_theme_part = Layout_Post_Type::sanitize_hide_theme_part( isset( $data['hide_theme_part'] ) ? $data['hide_theme_part'] : false );
		$layout->hook           = Layout_Post_Type::sanitize_hook_name( isset( $data['hook'] ) ? $data['hook'] : '' );
		$layout->hook_priority  = Layout_Post_Type::sanitize_hook_priority( isset( $data['hook_priority'] ) ? $data['hook_priority'] : 10 );
		$layout->builder        = Layout_Post_Type::sanitize_builder( isset( $data['builder'] ) ? $data['builder'] : 'auto' );
		$layout->content_source = Layout_Post_Type::sanitize_content_source( isset( $data['content_source'] ) ? $data['content_source'] : 'self' );
		$layout->reference_id   = Layout_Post_Type::sanitize_reference_id( isset( $data['reference_id'] ) ? $data['reference_id'] : 0 );
		$layout->template_id    = Layout_Post_Type::sanitize_identifier( isset( $data['template_id'] ) ? $data['template_id'] : '' );
		$layout->import_source  = Layout_Post_Type::sanitize_import_source( isset( $data['import_source'] ) ? $data['import_source'] : '' );
		$layout->imported_at    = isset( $data['imported_at'] ) ? sanitize_text_field( (string) $data['imported_at'] ) : '';

		return $layout;
	}

	/**
	 * Exports the layout as a cache/JSON friendly array.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,mixed>
	 */
	public function to_array() {
		return array(
			'id'             => $this->id,
			'title'          => $this->title,
			'status'         => $this->status,
			'type'           => $this->type,
			'priority'       => $this->priority,
			'conditions'     => $this->conditions,
			'placement'      => $this->placement,
			'hide_theme_part' => $this->hide_theme_part,
			'hook'           => $this->hook,
			'hook_priority'  => $this->hook_priority,
			'builder'        => $this->builder,
			'content_source' => $this->content_source,
			'reference_id'   => $this->reference_id,
			'template_id'    => $this->template_id,
			'import_source'  => $this->import_source,
			'imported_at'    => $this->imported_at,
		);
	}

	/**
	 * Whether the layout is enabled (published).
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return 'publish' === $this->status;
	}

	/**
	 * Whether the layout was imported from a template.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_imported() {
		return '' !== $this->template_id;
	}

	/**
	 * Returns the effective placement for this layout.
	 *
	 * Falls back to the plugin default for the layout type when the layout does
	 * not override it.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_effective_placement() {
		if ( '' !== $this->placement ) {
			return $this->placement;
		}

		$default = 'footer' === $this->type
			? Settings::get( 'default_footer_placement', 'get_footer' )
			: Settings::get( 'default_header_placement', 'wp_body_open' );

		$allowed = array_merge( Settings::HEADER_PLACEMENTS, Settings::FOOTER_PLACEMENTS );

		if ( ! in_array( $default, $allowed, true ) ) {
			$default = 'footer' === $this->type ? 'get_footer' : 'wp_body_open';
		}

		/**
		 * Filters the effective placement of a layout.
		 *
		 * @since 1.0.0
		 *
		 * @param string $placement Placement slug.
		 * @param Layout $layout    The layout.
		 */
		return apply_filters( 'qevora_layouts_layout_placement', $default, $this );
	}

	/**
	 * Returns the post ID whose content should be rendered.
	 *
	 * A layout can either render its own content or reference another post
	 * (for example an existing page or an Elementor library template).
	 *
	 * @since 1.0.0
	 *
	 * @return int 0 when the reference cannot be resolved.
	 */
	public function get_content_post_id() {
		if ( 'reference' !== $this->content_source || $this->reference_id <= 0 ) {
			return $this->id;
		}

		$referenced = get_post( $this->reference_id );

		if ( ! $referenced instanceof \WP_Post ) {
			return 0;
		}

		if ( 'trash' === $referenced->post_status ) {
			return 0;
		}

		return (int) $referenced->ID;
	}
}
