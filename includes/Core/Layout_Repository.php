<?php
/**
 * Layout storage.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Core;

use QevoraLayouts\Conditions\Condition_Matcher;
use QevoraLayouts\Conditions\Context;
use WP_Error;
use WP_Query;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes layouts and resolves which layout applies to a request.
 *
 * Candidate lists (IDs plus the meta needed for matching) are cached per layout
 * type in the object cache and in a versioned transient, so a normal frontend
 * request costs at most one small cached lookup.
 *
 * @since 1.0.0
 */
class Layout_Repository {

	/**
	 * Maximum number of candidates fetched per type.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	const MAX_CANDIDATES = 300;

	/**
	 * Creates or updates a layout.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $data {
	 *     Layout data.
	 *
	 *     @type int    $id             Optional. Existing layout ID.
	 *     @type string $title          Layout title.
	 *     @type string $content        Layout content (block markup or HTML).
	 *     @type string $type           header|footer|section.
	 *     @type string $status         publish|draft|pending|private.
	 *     @type int    $priority       0-999.
	 *     @type array  $conditions     Display conditions.
	 *     @type string $placement      Placement override.
	 *     @type bool   $hide_theme_part Hide the theme part while rendering.
	 *     @type string $demo_slug      Bundled PHP demo slug (builder `demo`).
	 *     @type int    $menu_id        Nav menu created for a demo.
	 *     @type string $hook           Custom hook name.
	 *     @type int    $hook_priority  Custom hook priority.
	 *     @type string $builder        Builder hint.
	 *     @type string $content_source self|reference.
	 *     @type int    $reference_id   Referenced post ID.
	 *     @type string $template_id    Import origin identifier.
	 *     @type string $import_source  bundled|upload|remote.
	 * }
	 * @return int|WP_Error Layout ID on success.
	 */
	public function save( array $data ) {
		$type   = Layout_Post_Type::sanitize_type( isset( $data['type'] ) ? $data['type'] : 'header' );
		$status = $this->sanitize_status( isset( $data['status'] ) ? $data['status'] : 'publish' );
		$id     = isset( $data['id'] ) ? (int) $data['id'] : 0;

		if ( $id > 0 && ! Capabilities::can_edit_layout( $id ) ) {
			return new WP_Error(
				'qevora_layouts_forbidden',
				__( 'You are not allowed to edit this layout.', 'qevora-layouts' ),
				array( 'status' => 403 )
			);
		}

		if ( 0 === $id && ! Capabilities::can_publish_layouts() ) {
			return new WP_Error(
				'qevora_layouts_forbidden',
				__( 'You are not allowed to create layouts.', 'qevora-layouts' ),
				array( 'status' => 403 )
			);
		}

		$postarr = array(
			'post_type'    => Layout_Post_Type::POST_TYPE,
			'post_status'  => $status,
			'post_title'   => isset( $data['title'] ) ? sanitize_text_field( (string) $data['title'] ) : '',
			'post_content' => $this->sanitize_content( isset( $data['content'] ) ? $data['content'] : '' ),
		);

		if ( '' === $postarr['post_title'] ) {
			$postarr['post_title'] = __( 'Untitled layout', 'qevora-layouts' );
		}

		if ( $id > 0 ) {
			$postarr['ID'] = $id;
		}

		$result = wp_insert_post( wp_slash( $postarr ), true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$post_id = (int) $result;

		$meta_map = array(
			Layout_Post_Type::META_TYPE           => array( $type, null ),
			Layout_Post_Type::META_PRIORITY       => array( isset( $data['priority'] ) ? $data['priority'] : 10, array( Layout_Post_Type::class, 'sanitize_priority' ) ),
			Layout_Post_Type::META_CONDITIONS     => array( isset( $data['conditions'] ) ? $data['conditions'] : array(), array( \QevoraLayouts\Conditions\Condition_Sanitizer::class, 'sanitize' ) ),
			Layout_Post_Type::META_PLACEMENT      => array( isset( $data['placement'] ) ? $data['placement'] : '', array( Layout_Post_Type::class, 'sanitize_placement' ) ),
			Layout_Post_Type::META_HIDE_THEME_PART => array( isset( $data['hide_theme_part'] ) ? $data['hide_theme_part'] : false, array( Layout_Post_Type::class, 'sanitize_hide_theme_part' ) ),
			Layout_Post_Type::META_DEMO            => array( isset( $data['demo_slug'] ) ? $data['demo_slug'] : '', array( Layout_Post_Type::class, 'sanitize_identifier' ) ),
			Layout_Post_Type::META_MENU_ID         => array( isset( $data['menu_id'] ) ? $data['menu_id'] : 0, 'absint' ),
			Layout_Post_Type::META_HOOK           => array( isset( $data['hook'] ) ? $data['hook'] : '', array( Layout_Post_Type::class, 'sanitize_hook_name' ) ),
			Layout_Post_Type::META_HOOK_PRIORITY  => array( isset( $data['hook_priority'] ) ? $data['hook_priority'] : 10, array( Layout_Post_Type::class, 'sanitize_hook_priority' ) ),
			Layout_Post_Type::META_BUILDER        => array( isset( $data['builder'] ) ? $data['builder'] : 'auto', array( Layout_Post_Type::class, 'sanitize_builder' ) ),
			Layout_Post_Type::META_CONTENT_SOURCE => array( isset( $data['content_source'] ) ? $data['content_source'] : 'self', array( Layout_Post_Type::class, 'sanitize_content_source' ) ),
			Layout_Post_Type::META_REFERENCE_ID   => array( isset( $data['reference_id'] ) ? $data['reference_id'] : 0, array( Layout_Post_Type::class, 'sanitize_reference_id' ) ),
		);

		foreach ( $meta_map as $key => $pair ) {
			$value    = $pair[0];
			$sanitizer = $pair[1];

			if ( null !== $sanitizer && is_callable( $sanitizer ) ) {
				$value = call_user_func( $sanitizer, $value );
			}

			update_post_meta( $post_id, $key, $value );
		}

		// Import provenance is only written when explicitly provided.
		if ( isset( $data['template_id'] ) ) {
			update_post_meta( $post_id, Layout_Post_Type::META_TEMPLATE_ID, Layout_Post_Type::sanitize_identifier( $data['template_id'] ) );
		}

		if ( isset( $data['import_source'] ) ) {
			update_post_meta( $post_id, Layout_Post_Type::META_IMPORT_SOURCE, Layout_Post_Type::sanitize_import_source( $data['import_source'] ) );
		}

		if ( ! empty( $data['template_id'] ) && empty( $data['imported_at'] ) ) {
			$data['imported_at'] = gmdate( 'c' );
		}

		if ( isset( $data['imported_at'] ) ) {
			update_post_meta( $post_id, Layout_Post_Type::META_IMPORTED_AT, sanitize_text_field( (string) $data['imported_at'] ) );
		}

		Cache::bump_version();

		/**
		 * Fires after a layout has been created or updated.
		 *
		 * @since 1.0.0
		 *
		 * @param int                 $post_id Layout ID.
		 * @param array<string,mixed> $data    Normalized input data.
		 */
		do_action( 'qevora_layouts_layout_saved', $post_id, $data );

		return $post_id;
	}

	/**
	 * Sanitizes layout content.
	 *
	 * Users without the `unfiltered_html` capability get the same KSES filtering
	 * WordPress applies to regular post content. PHP tags are always rejected:
	 * layout content is never evaluated, only rendered as markup.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $content Raw content.
	 * @return string
	 */
	public function sanitize_content( $content ) {
		if ( ! is_string( $content ) ) {
			return '';
		}

		$content = str_replace( array( "\r\n", "\r" ), "\n", $content );

		/*
		 * Layout markup is rendered, never evaluated, so a PHP tag has no
		 * legitimate use here. Drop them even for users who are allowed to
		 * save unfiltered HTML: this keeps copied markup and imported files
		 * from smuggling code into the database.
		 */
		$content = preg_replace( '/<\?(?:php\b|=)?[\s\S]*?\?>/i', '', $content );
		$content = preg_replace( '/<\?(?:php\b|=)?/i', '', $content );
		$content = str_replace( '?>', '', $content );

		$max_bytes = max( 1024, (int) Settings::get( 'max_import_size_kb', 2048 ) * 1024 );

		if ( strlen( $content ) > $max_bytes ) {
			$content = substr( $content, 0, $max_bytes );
		}

		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$content = wp_kses_post( $content );
		}

		/**
		 * Filters sanitized layout content before it is stored.
		 *
		 * @since 1.0.0
		 *
		 * @param string $content Sanitized content.
		 */
		return apply_filters( 'qevora_layouts_sanitize_content', $content );
	}

	/**
	 * Sanitizes a post status.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $status Raw status.
	 * @return string
	 */
	public function sanitize_status( $status ) {
		$status = is_scalar( $status ) ? (string) $status : 'draft';

		$allowed = array( 'publish', 'draft', 'pending', 'private' );

		return in_array( $status, $allowed, true ) ? $status : 'draft';
	}

	/**
	 * Returns a layout.
	 *
	 * @since 1.0.0
	 *
	 * @param int|\WP_Post $post_id Layout or post.
	 * @return Layout|null
	 */
	public function get( $post_id ) {
		return Layout::from_post( $post_id );
	}

	/**
	 * Enables or disables a layout by changing its post status.
	 *
	 * @since 1.0.0
	 *
	 * @param int  $post_id Layout ID.
	 * @param bool $enabled True to publish, false to set to draft.
	 * @return bool|WP_Error
	 */
	public function set_enabled( $post_id, $enabled ) {
		$post_id = (int) $post_id;
		$layout  = Layout::from_post( $post_id );

		if ( null === $layout ) {
			return new WP_Error( 'qevora_layouts_not_found', __( 'Layout not found.', 'qevora-layouts' ), array( 'status' => 404 ) );
		}

		if ( ! Capabilities::can_edit_layout( $post_id ) ) {
			return new WP_Error( 'qevora_layouts_forbidden', __( 'You are not allowed to change this layout.', 'qevora-layouts' ), array( 'status' => 403 ) );
		}

		$status = $enabled ? 'publish' : 'draft';

		if ( $status === $layout->status ) {
			return true;
		}

		$result = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $status,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		Cache::bump_version();

		return true;
	}

	/**
	 * Duplicates a layout, including its content and meta.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Layout ID.
	 * @return int|WP_Error New layout ID.
	 */
	public function duplicate( $post_id ) {
		$post_id = (int) $post_id;
		$source  = get_post( $post_id );

		if ( ! $source instanceof \WP_Post || Layout_Post_Type::POST_TYPE !== $source->post_type ) {
			return new WP_Error( 'qevora_layouts_not_found', __( 'Layout not found.', 'qevora-layouts' ), array( 'status' => 404 ) );
		}

		if ( ! Capabilities::can_edit_layout( $post_id ) || ! Capabilities::can_publish_layouts() ) {
			return new WP_Error( 'qevora_layouts_forbidden', __( 'You are not allowed to duplicate this layout.', 'qevora-layouts' ), array( 'status' => 403 ) );
		}

		$new = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => Layout_Post_Type::POST_TYPE,
					'post_status'  => 'draft',
					'post_title'   => $this->duplicate_title( $source->post_title ),
					'post_content' => $source->post_content,
					'post_excerpt' => $source->post_excerpt,
				)
			),
			true
		);

		if ( is_wp_error( $new ) ) {
			return $new;
		}

		$new = (int) $new;

		foreach ( Layout_Post_Type::get_meta_keys() as $key ) {
			// Provenance is intentionally not copied: the clone is a new layout.
			if ( in_array( $key, array( Layout_Post_Type::META_TEMPLATE_ID, Layout_Post_Type::META_IMPORT_SOURCE, Layout_Post_Type::META_IMPORTED_AT ), true ) ) {
				continue;
			}

			$value = get_post_meta( $post_id, $key, true );

			if ( '' === $value || array() === $value || null === $value ) {
				continue;
			}

			update_post_meta( $new, $key, wp_slash( $value ) );
		}

		Cache::bump_version();

		/**
		 * Fires after a layout has been duplicated.
		 *
		 * @since 1.0.0
		 *
		 * @param int $new_layout_id  New layout ID.
		 * @param int $source_layout_id Source layout ID.
		 */
		do_action( 'qevora_layouts_layout_duplicated', $new, $post_id );

		return $new;
	}

	/**
	 * Builds a title for a duplicated layout.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title Original title.
	 * @return string
	 */
	private function duplicate_title( $title ) {
		$title = '' === trim( (string) $title ) ? __( 'Untitled layout', 'qevora-layouts' ) : (string) $title;

		/* translators: %s: original layout title. */
		$candidate = sprintf( __( '%s (copy)', 'qevora-layouts' ), $title );

		if ( strlen( $candidate ) > 200 ) {
			$candidate = substr( $candidate, 0, 200 );
		}

		return $candidate;
	}

	/**
	 * Deletes a layout (moves it to the trash unless forced).
	 *
	 * @since 1.0.0
	 *
	 * @param int  $post_id    Layout ID.
	 * @param bool $force_delete Whether to bypass the trash.
	 * @return bool|WP_Error
	 */
	public function delete( $post_id, $force_delete = false ) {
		$post_id = (int) $post_id;

		if ( ! Capabilities::can_delete_layout( $post_id ) ) {
			return new WP_Error( 'qevora_layouts_forbidden', __( 'You are not allowed to delete this layout.', 'qevora-layouts' ), array( 'status' => 403 ) );
		}

		$result = $force_delete ? wp_delete_post( $post_id, true ) : wp_trash_post( $post_id );

		if ( ! $result instanceof \WP_Post && ( false === $result || null === $result ) ) {
			return new WP_Error( 'qevora_layouts_delete_failed', __( 'The layout could not be deleted.', 'qevora-layouts' ), array( 'status' => 500 ) );
		}

		Cache::bump_version();

		return true;
	}

	/**
	 * Returns every enabled layout of a type, ordered by priority.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Layout type.
	 * @return array<string,mixed>[] Cached, serializable layout data.
	 */
	public function get_candidate_data( $type ) {
		$type = Layout_Post_Type::sanitize_type( $type );
		$key  = 'candidates_' . $type;

		$use_cache = (bool) Settings::is_enabled( 'cache_candidates' );

		if ( $use_cache ) {
			$cached = Cache::get( $key );

			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$query = new WP_Query(
			array(
				'post_type'              => Layout_Post_Type::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => self::MAX_CANDIDATES,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'ignore_sticky_posts'    => true,
				'fields'                 => 'ids',
				'orderby'                => array(
					'meta_value_num' => 'DESC',
					'ID'             => 'ASC',
				),
				'meta_key'               => Layout_Post_Type::META_PRIORITY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Indexed by the meta query below.
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Narrow, cached query.
					'relation' => 'AND',
					array(
						'key'   => Layout_Post_Type::META_TYPE,
						'value' => $type,
					),
				),
			)
		);

		$ids  = array_map( 'intval', (array) $query->posts );
		$data = array();

		foreach ( $ids as $id ) {
			$layout = Layout::from_post( $id );

			if ( null === $layout ) {
				continue;
			}

			$data[] = $layout->to_array();
		}

		if ( $use_cache ) {
			Cache::set( $key, $data, (int) Settings::get( 'cache_ttl_seconds', 43200 ) );
		}

		return $data;
	}

	/**
	 * Returns the enabled layouts of a type that match a context, best first.
	 *
	 * @since 1.0.0
	 *
	 * @param string  $type    Layout type.
	 * @param Context $context Request context.
	 * @return Layout[]
	 */
	public function find_matching( $type, Context $context ) {
		$candidates = $this->get_candidate_data( $type );
		$matching   = array();

		foreach ( $candidates as $data ) {
			$layout = Layout::from_array( $data );

			if ( ! $layout->is_enabled() ) {
				continue;
			}

			if ( ! Condition_Matcher::matches( $layout->conditions, $context ) ) {
				continue;
			}

			$matching[] = array(
				'layout'      => $layout,
				'priority'    => $layout->priority,
				'specificity' => Condition_Matcher::specificity( $layout->conditions, $context ),
			);
		}

		usort(
			$matching,
			static function ( $a, $b ) {
				if ( $a['priority'] !== $b['priority'] ) {
					return $b['priority'] <=> $a['priority'];
				}

				if ( $a['specificity'] !== $b['specificity'] ) {
					return $b['specificity'] <=> $a['specificity'];
				}

				return $a['layout']->id <=> $b['layout']->id;
			}
		);

		$ordered = array();

		foreach ( $matching as $entry ) {
			$ordered[] = $entry['layout'];
		}

		/**
		 * Filters the matching layouts for a type, best match first.
		 *
		 * @since 1.0.0
		 *
		 * @param Layout[] $ordered Matching layouts.
		 * @param string   $type    Layout type.
		 * @param Context  $context Request context.
		 */
		return apply_filters( 'qevora_layouts_matching_layouts', $ordered, $type, $context );
	}

	/**
	 * Returns the single layout that should render for a type and context.
	 *
	 * @since 1.0.0
	 *
	 * @param string       $type    Layout type.
	 * @param Context|null $context Optional context. Defaults to the current request.
	 * @return Layout|null
	 */
	public function get_active_layout( $type, ?Context $context = null ) {
		$switch = 'footer' === $type ? 'footer_enabled' : ( 'section' === $type ? 'section_enabled' : 'header_enabled' );

		if ( ! Settings::is_enabled( $switch ) ) {
			return null;
		}

		if ( null === $context ) {
			$context = \QevoraLayouts\Conditions\Context_Factory::current();
		}

		$matching = $this->find_matching( $type, $context );

		return empty( $matching ) ? null : $matching[0];
	}

	/**
	 * Finds layouts imported from a template.
	 *
	 * Used to detect duplicates before importing.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template_id Template identifier.
	 * @return int[] Post IDs.
	 */
	public function find_by_template_id( $template_id ) {
		$template_id = Layout_Post_Type::sanitize_identifier( $template_id );

		if ( '' === $template_id ) {
			return array();
		}

		$query = new WP_Query(
			array(
				'post_type'              => Layout_Post_Type::POST_TYPE,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'         => 100,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'fields'                 => 'ids',
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Runs on import only.
					array(
						'key'   => Layout_Post_Type::META_TEMPLATE_ID,
						'value' => $template_id,
					),
				),
			)
		);

		return array_map( 'intval', (array) $query->posts );
	}

	/**
	 * Counts layouts, optionally by type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Optional layout type.
	 * @return int
	 */
	public function count_layouts( $type = '' ) {
		$args = array(
			'post_type'      => Layout_Post_Type::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'no_found_rows'  => false,
			'fields'         => 'ids',
		);

		if ( '' !== $type ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin only.
				array(
					'key'   => Layout_Post_Type::META_TYPE,
					'value' => Layout_Post_Type::sanitize_type( $type ),
				),
			);
		}

		$query = new WP_Query( $args );

		return (int) $query->found_posts;
	}

	/**
	 * Hooks that invalidate cached candidate lists.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_cache_invalidation() {
		add_action( 'save_post_' . Layout_Post_Type::POST_TYPE, array( __CLASS__, 'invalidate' ), 20, 0 );
		add_action( 'delete_post', array( __CLASS__, 'invalidate' ), 20, 0 );
		add_action( 'wp_trash_post', array( __CLASS__, 'invalidate' ), 20, 0 );
		add_action( 'untrash_post', array( __CLASS__, 'invalidate' ), 20, 0 );
		add_action( 'transition_post_status', array( __CLASS__, 'invalidate_on_layout_transition' ), 20, 3 );
		add_action( 'added_post_meta', array( __CLASS__, 'invalidate_on_meta_change' ), 20, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'invalidate_on_meta_change' ), 20, 4 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'invalidate_on_meta_change' ), 20, 4 );
	}

	/**
	 * Drops cached candidate lists.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function invalidate() {
		Cache::bump_version();
	}

	/**
	 * Invalidates the cache when a layout changes status.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 * @return void
	 */
	public static function invalidate_on_layout_transition( $new_status, $old_status, $post ) {
		unset( $new_status, $old_status );

		if ( $post instanceof \WP_Post && Layout_Post_Type::POST_TYPE === $post->post_type ) {
			Cache::bump_version();
		}
	}

	/**
	 * Invalidates the cache when layout meta changes.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $object_id  Object ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public static function invalidate_on_meta_change( $meta_id, $object_id, $meta_key, $meta_value ) {
		unset( $meta_id, $meta_value );

		if ( 0 !== strpos( (string) $meta_key, '_qevora_layout_' ) ) {
			return;
		}

		$post = get_post( $object_id );

		if ( $post instanceof \WP_Post && Layout_Post_Type::POST_TYPE === $post->post_type ) {
			Cache::bump_version();
		}
	}
}
