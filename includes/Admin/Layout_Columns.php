<?php
/**
 * Layout list table columns.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Admin;

use QevoraLayouts\Conditions\Condition_Sanitizer;
use QevoraLayouts\Core\Capabilities;
use QevoraLayouts\Core\Layout;
use QevoraLayouts\Core\Layout_Post_Type;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extends the native layout list table.
 *
 * The plugin reuses `WP_Posts_List_Table` instead of shipping a custom table so
 * bulk actions, search, pagination, views and accessibility stay exactly like
 * the rest of WordPress.
 *
 * @since 1.0.0
 */
class Layout_Columns {

	/**
	 * Query var used by the type filter.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const TYPE_FILTER = 'qevora_layout_type';

	/**
	 * Registers hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register() {
		$post_type = Layout_Post_Type::POST_TYPE;

		add_filter( 'manage_edit-' . $post_type . '_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . $post_type . '_posts_custom_column', array( __CLASS__, 'render_column' ), 10, 2 );
		add_filter( 'manage_edit-' . $post_type . '_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'render_filters' ), 10, 2 );
		add_action( 'pre_get_posts', array( __CLASS__, 'apply_filters' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_filter( 'display_post_states', array( __CLASS__, 'post_states' ), 10, 2 );
		add_filter( 'bulk_actions-edit-' . $post_type, array( __CLASS__, 'bulk_actions' ) );
	}

	/**
	 * Defines the list table columns.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,string> $columns Columns.
	 * @return array<string,string>
	 */
	public static function columns( $columns ) {
		$columns = is_array( $columns ) ? $columns : array();

		$custom = array(
			'cb'            => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
			'title'         => __( 'Layout', 'qevora-layouts' ),
			'ql_type'       => __( 'Type', 'qevora-layouts' ),
			'ql_conditions' => __( 'Display conditions', 'qevora-layouts' ),
			'ql_priority'   => __( 'Priority', 'qevora-layouts' ),
			'ql_placement'  => __( 'Placement', 'qevora-layouts' ),
			'ql_builder'    => __( 'Built with', 'qevora-layouts' ),
			'ql_source'     => __( 'Source', 'qevora-layouts' ),
			'date'          => __( 'Date', 'qevora-layouts' ),
		);

		/**
		 * Filters the layout list table columns.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,string> $custom Columns.
		 */
		return apply_filters( 'qevora_layouts_list_columns', $custom );
	}

	/**
	 * Prints a custom column.
	 *
	 * @since 1.0.0
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function render_column( $column, $post_id ) {
		$layout = Layout::from_post( (int) $post_id );

		if ( null === $layout ) {
			echo '&mdash;';

			return;
		}

		switch ( $column ) {
			case 'ql_type':
				printf(
					'<span class="qevora-layouts-badge qevora-layouts-badge--%1$s">%2$s</span>',
					esc_attr( $layout->type ),
					esc_html( Layout_Post_Type::get_type_label( $layout->type ) )
				);
				break;

			case 'ql_conditions':
				echo esc_html( Condition_Sanitizer::summarize( $layout->conditions ) );
				break;

			case 'ql_priority':
				echo esc_html( number_format_i18n( $layout->priority ) );
				break;

			case 'ql_placement':
				echo esc_html( self::placement_label( $layout ) );
				break;

			case 'ql_builder':
				echo esc_html( self::builder_label( $layout ) );
				break;

			case 'ql_source':
				echo esc_html( self::source_label( $layout ) );
				break;

			default:
				/**
				 * Fires for unknown layout list columns.
				 *
				 * @since 1.0.0
				 *
				 * @param string $column  Column key.
				 * @param Layout $layout  Layout.
				 */
				do_action( 'qevora_layouts_list_column', (string) $column, $layout );
				break;
		}
	}

	/**
	 * Returns the placement label of a layout.
	 *
	 * @since 1.0.0
	 *
	 * @param Layout $layout Layout.
	 * @return string
	 */
	private static function placement_label( Layout $layout ) {
		$placement  = $layout->get_effective_placement();
		$placements = Layout_Post_Type::get_placements( $layout->type );

		if ( 'custom_hook' === $placement && '' !== $layout->hook ) {
			return sprintf(
				/* translators: %s: theme hook name. */
				__( 'Hook: %s', 'qevora-layouts' ),
				$layout->hook
			);
		}

		return isset( $placements[ $placement ] ) ? $placements[ $placement ] : $placement;
	}

	/**
	 * Returns the builder label of a layout.
	 *
	 * @since 1.0.0
	 *
	 * @param Layout $layout Layout.
	 * @return string
	 */
	private static function builder_label( Layout $layout ) {
		if ( 'reference' === $layout->content_source && $layout->reference_id > 0 ) {
			$title = get_the_title( $layout->reference_id );

			return sprintf(
				/* translators: %s: referenced content title. */
				__( 'Reference: %s', 'qevora-layouts' ),
				'' !== $title ? $title : '#' . $layout->reference_id
			);
		}

		$labels = array(
			'auto'      => __( 'Auto detect', 'qevora-layouts' ),
			'block'     => __( 'Block editor', 'qevora-layouts' ),
			'elementor' => __( 'Elementor', 'qevora-layouts' ),
		);

		return isset( $labels[ $layout->builder ] ) ? $labels[ $layout->builder ] : $layout->builder;
	}

	/**
	 * Returns the provenance label of a layout.
	 *
	 * @since 1.0.0
	 *
	 * @param Layout $layout Layout.
	 * @return string
	 */
	private static function source_label( Layout $layout ) {
		if ( '' === $layout->template_id ) {
			return __( 'Created here', 'qevora-layouts' );
		}

		$origins = array(
			'bundled' => __( 'Starter template', 'qevora-layouts' ),
			'remote'  => __( 'Remote library', 'qevora-layouts' ),
			'upload'  => __( 'Uploaded file', 'qevora-layouts' ),
		);

		$origin = isset( $origins[ $layout->import_source ] ) ? $origins[ $layout->import_source ] : __( 'Imported', 'qevora-layouts' );

		return $origin;
	}

	/**
	 * Declares sortable columns.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,string> $columns Sortable columns.
	 * @return array<string,string>
	 */
	public static function sortable_columns( $columns ) {
		$columns = is_array( $columns ) ? $columns : array();

		$columns['ql_priority'] = 'ql_priority';
		$columns['ql_type']     = 'ql_type';

		return $columns;
	}

	/**
	 * Renders the type filter dropdown above the list table.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type Current post type.
	 * @param string $which     Position (top|bottom).
	 * @return void
	 */
	public static function render_filters( $post_type, $which = '' ) {
		if ( Layout_Post_Type::POST_TYPE !== $post_type || 'top' !== $which ) {
			return;
		}

		if ( ! Capabilities::can_edit_layouts() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter, no state change.
		$current = isset( $_GET[ self::TYPE_FILTER ] ) ? sanitize_key( wp_unslash( $_GET[ self::TYPE_FILTER ] ) ) : '';

		printf(
			'<label class="screen-reader-text" for="%1$s">%2$s</label>',
			esc_attr( self::TYPE_FILTER ),
			esc_html__( 'Filter by layout type', 'qevora-layouts' )
		);

		printf(
			'<select name="%1$s" id="%1$s"><option value="">%2$s</option>',
			esc_attr( self::TYPE_FILTER ),
			esc_html__( 'All layout types', 'qevora-layouts' )
		);

		foreach ( Layout_Post_Type::get_types() as $slug => $type ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( (string) $slug ),
				selected( $current, (string) $slug, false ),
				esc_html( isset( $type['label'] ) ? (string) $type['label'] : (string) $slug )
			);
		}

		echo '</select>';
	}

	/**
	 * Applies the list table filters and sorting.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Query $query Query.
	 * @return void
	 */
	public static function apply_filters( $query ) {
		if ( ! is_admin() || ! $query instanceof \WP_Query || ! $query->is_main_query() ) {
			return;
		}

		if ( Layout_Post_Type::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list table state.
		$type = isset( $_GET[ self::TYPE_FILTER ] ) ? sanitize_key( wp_unslash( $_GET[ self::TYPE_FILTER ] ) ) : '';

		if ( '' !== $type && Layout_Post_Type::is_valid_type( $type ) ) {
			$meta_query   = (array) $query->get( 'meta_query' );
			$meta_query[] = array(
				'key'   => Layout_Post_Type::META_TYPE,
				'value' => $type,
			);

			$query->set( 'meta_query', $meta_query );
		}

		$orderby = $query->get( 'orderby' );

		if ( 'ql_priority' === $orderby ) {
			$query->set( 'meta_key', Layout_Post_Type::META_PRIORITY );
			$query->set( 'orderby', 'meta_value_num' );
		}

		if ( 'ql_type' === $orderby ) {
			$query->set( 'meta_key', Layout_Post_Type::META_TYPE );
			$query->set( 'orderby', 'meta_value' );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Adds the clone and enable/disable row actions.
	 *
	 * Both are plain links with a nonce, so they work without JavaScript.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,string> $actions Row actions.
	 * @param \WP_Post             $post    Post.
	 * @return array<string,string>
	 */
	public static function row_actions( $actions, $post ) {
		$actions = is_array( $actions ) ? $actions : array();

		if ( ! $post instanceof \WP_Post || Layout_Post_Type::POST_TYPE !== $post->post_type ) {
			return $actions;
		}

		$layout = Layout::from_post( $post );

		if ( null === $layout || ! Capabilities::can_edit_layout( $post->ID ) ) {
			return $actions;
		}

		// "View" is meaningless: layouts have no public single view.
		unset( $actions['view'], $actions['inline hide-if-no-js'] );

		$toggle_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'   => 'publish' === $post->post_status ? 'qevora_layouts_disable' : 'qevora_layouts_enable',
					'layout'   => $post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			'qevora_layouts_toggle_' . $post->ID
		);

		$toggle_label = 'publish' === $post->post_status
			? __( 'Disable', 'qevora-layouts' )
			: __( 'Enable', 'qevora-layouts' );

		$actions['qevora_toggle'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $toggle_url ),
			esc_html( $toggle_label )
		);

		if ( Capabilities::can_publish_layouts() ) {
			$clone_url = wp_nonce_url(
				add_query_arg(
					array(
						'action' => 'qevora_layouts_clone',
						'layout' => $post->ID,
					),
					admin_url( 'admin-post.php' )
				),
				'qevora_layouts_clone_' . $post->ID
			);

			$actions['qevora_clone'] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $clone_url ),
				esc_html__( 'Clone', 'qevora-layouts' )
			);
		}

		return $actions;
	}

	/**
	 * Adds a post state label so disabled layouts are obvious.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int,string> $states Post states.
	 * @param \WP_Post          $post   Post.
	 * @return array<int,string>
	 */
	public static function post_states( $states, $post ) {
		$states = is_array( $states ) ? $states : array();

		if ( ! $post instanceof \WP_Post || Layout_Post_Type::POST_TYPE !== $post->post_type ) {
			return $states;
		}

		$type = get_post_meta( $post->ID, Layout_Post_Type::META_TYPE, true );

		if ( Layout_Post_Type::is_valid_type( $type ) ) {
			$states[] = Layout_Post_Type::get_type_label( $type );
		}

		return $states;
	}

	/**
	 * Keeps the default bulk actions and documents them.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,string> $actions Bulk actions.
	 * @return array<string,string>
	 */
	public static function bulk_actions( $actions ) {
		if ( ! is_array( $actions ) ) {
			return array();
		}

		if ( isset( $actions['edit'] ) ) {
			$actions['edit'] = __( 'Change type and priority', 'qevora-layouts' );
		}

		return $actions;
	}
}
