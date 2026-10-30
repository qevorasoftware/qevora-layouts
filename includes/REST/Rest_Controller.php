<?php
/**
 * REST API endpoints.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\REST;

use QevoraLayouts\Admin\Import_Handler;
use QevoraLayouts\Admin\System_Status_Page;
use QevoraLayouts\Core\Capabilities;
use QevoraLayouts\Core\Layout;
use QevoraLayouts\Core\Layout_Post_Type;
use QevoraLayouts\Core\Plugin;
use QevoraLayouts\Support\Environment;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only and import endpoints used by the admin UI.
 *
 * Nothing here is public: every route has a permission callback and only
 * returns data the current user may already see in wp-admin.
 *
 * @since 1.0.0
 */
class Rest_Controller {

	/**
	 * REST namespace.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const REST_NAMESPACE = 'qevora-layouts/v1';

	/**
	 * Registers the routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/layouts',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_layouts' ),
					'permission_callback' => array( __CLASS__, 'can_read_layouts' ),
					'args'                => self::list_args(),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/layouts/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_layout' ),
					'permission_callback' => array( __CLASS__, 'can_read_layouts' ),
					'args'                => array(
						'id' => array(
							'description'       => __( 'Layout ID.', 'qevora-layouts' ),
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/templates',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_templates' ),
					'permission_callback' => array( __CLASS__, 'can_import' ),
					'args'                => array(
						'type'   => array(
							'description'       => __( 'Filter by layout type.', 'qevora-layouts' ),
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_key',
						),
						'origin' => array(
							'description'       => __( 'Filter by source: bundled or remote.', 'qevora-layouts' ),
							'type'              => 'string',
							'default'           => '',
							'enum'              => array( '', 'bundled', 'remote' ),
							'sanitize_callback' => 'sanitize_key',
						),
						'search' => array(
							'description'       => __( 'Search term.', 'qevora-layouts' ),
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/templates/import',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'import_template' ),
					'permission_callback' => array( __CLASS__, 'can_import' ),
					'args'                => array(
						'template_id'  => array(
							'description'       => __( 'Template identifier from the library. Optional when a bundled demo or a demo pack is imported.', 'qevora-layouts' ),
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => array( Layout_Post_Type::class, 'sanitize_identifier' ),
						),
						'origin'       => array(
							'description'       => __( 'Template source.', 'qevora-layouts' ),
							'type'              => 'string',
							'default'           => 'bundled',
							'enum'              => array( 'bundled', 'remote' ),
							'sanitize_callback' => 'sanitize_key',
						),
						'on_duplicate' => array(
							'description'       => __( 'What to do when the template was imported before.', 'qevora-layouts' ),
							'type'              => 'string',
							'default'           => 'skip',
							'enum'              => array( 'skip', 'copy' ),
							'sanitize_callback' => 'sanitize_key',
						),
						'display_scope' => array(
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_key',
						),
						'demo'          => array(
							'description'       => __( 'Slug of a bundled PHP demo to import.', 'qevora-layouts' ),
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_key',
						),
						'create_menu'   => array(
							'description' => __( 'Create a sample nav menu for demos that support one.', 'qevora-layouts' ),
							'type'        => 'boolean',
							'required'    => false,
							'default'     => null,
						),
						'hide_theme_part' => array(
							'description'       => __( 'Hide the theme header or footer while the imported layout displays.', 'qevora-layouts' ),
							'type'              => 'boolean',
							'required'          => false,
							'default'           => null,
						),
						'conditions'    => array(
							'required'          => false,
							'default'           => null,
							'validate_callback' => array( __CLASS__, 'is_conditions_array' ),
						),
						'status'       => array(
							'description'       => __( 'Post status of the imported layout.', 'qevora-layouts' ),
							'type'              => 'string',
							'default'           => 'publish',
							'enum'              => array( 'publish', 'draft' ),
							'sanitize_callback' => 'sanitize_key',
						),
						'replace_id'   => array(
							'description'       => __( 'Layout ID to replace. Requires edit permission on that layout.', 'qevora-layouts' ),
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/search',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'search' ),
					'permission_callback' => array( __CLASS__, 'can_read_layouts' ),
					'args'                => array(
						'kind'      => array(
							'description'       => __( 'What to search for.', 'qevora-layouts' ),
							'type'              => 'string',
							'default'           => 'posts',
							'enum'              => array( 'posts', 'terms', 'users' ),
							'sanitize_callback' => 'sanitize_key',
						),
						'search'    => array(
							'description'       => __( 'Search term (at least two characters).', 'qevora-layouts' ),
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'post_type' => array(
							'description'       => __( 'Optional post type filter.', 'qevora-layouts' ),
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_key',
						),
						'taxonomy'  => array(
							'description'       => __( 'Optional taxonomy filter.', 'qevora-layouts' ),
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/system-status',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_system_status' ),
					'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
					'args'                => array(
						'format' => array(
							'description'       => __( 'Report format.', 'qevora-layouts' ),
							'type'              => 'string',
							'default'           => 'structured',
							'enum'              => array( 'structured', 'text' ),
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
			)
		);
	}

	/**
	 * Permission callback: reading layouts.
	 *
	 * @since 1.0.0
	 *
	 * @return true|WP_Error
	 */
	public static function can_read_layouts() {
		return Capabilities::can_edit_layouts()
			? true
			: new WP_Error( 'qevora_layouts_rest_forbidden', __( 'Sorry, you are not allowed to read layouts.', 'qevora-layouts' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Permission callback: importing.
	 *
	 * @since 1.0.0
	 *
	 * @return true|WP_Error
	 */
	public static function can_import() {
		return Capabilities::can_publish_layouts()
			? true
			: new WP_Error( 'qevora_layouts_rest_forbidden', __( 'Sorry, you are not allowed to import templates.', 'qevora-layouts' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Permission callback: managing settings.
	 *
	 * @since 1.0.0
	 *
	 * @return true|WP_Error
	 */
	public static function can_manage_settings() {
		return Capabilities::can_manage_settings()
			? true
			: new WP_Error( 'qevora_layouts_rest_forbidden', __( 'Sorry, you are not allowed to view the system status.', 'qevora-layouts' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Returns the list arguments.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function list_args() {
		return array(
			'type'     => array(
				'description'       => __( 'Filter by layout type.', 'qevora-layouts' ),
				'type'              => 'string',
				'default'           => '',
				'enum'              => array_merge( array( '' ), Layout_Post_Type::get_type_slugs() ),
				'sanitize_callback' => 'sanitize_key',
			),
			'status'   => array(
				'description'       => __( 'Filter by post status.', 'qevora-layouts' ),
				'type'              => 'string',
				'default'           => 'any',
				'enum'              => array( 'any', 'publish', 'draft', 'pending', 'trash' ),
				'sanitize_callback' => 'sanitize_key',
			),
			'search'   => array(
				'description'       => __( 'Search in layout titles.', 'qevora-layouts' ),
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'per_page' => array(
				'description'       => __( 'Maximum number of items to return.', 'qevora-layouts' ),
				'type'              => 'integer',
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
			),
			'page'     => array(
				'description'       => __( 'Page of results.', 'qevora-layouts' ),
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Lists layouts.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_layouts( WP_REST_Request $request ) {
		$per_page = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) );
		$page     = max( 1, (int) $request->get_param( 'page' ) );

		$query = new \WP_Query(
			array(
				'post_type'              => Layout_Post_Type::POST_TYPE,
				'post_status'            => self::sanitize_status_param( (string) $request->get_param( 'status' ) ),
				'posts_per_page'         => $per_page,
				'paged'                  => $page,
				's'                      => (string) $request->get_param( 'search' ),
				'orderby'                => array(
					'meta_value_num' => 'DESC',
					'title'          => 'ASC',
				),
				'meta_key'               => Layout_Post_Type::META_PRIORITY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'update_post_term_cache' => false,
			)
		);

		$type_filter = (string) $request->get_param( 'type' );
		$items       = array();

		foreach ( (array) $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}

			$layout = Layout::from_post( $post );

			if ( null === $layout ) {
				continue;
			}

			if ( '' !== $type_filter && $layout->type !== $type_filter ) {
				continue;
			}

			$items[] = self::prepare_layout( $layout );
		}

		$response = rest_ensure_response( $items );

		if ( $response instanceof WP_REST_Response ) {
			$response->header( 'X-WP-Total', (int) $query->found_posts );
			$response->header( 'X-WP-TotalPages', (int) $query->max_num_pages );
		}

		return $response;
	}

	/**
	 * Returns one layout.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_layout( WP_REST_Request $request ) {
		$layout = Layout::from_post( (int) $request->get_param( 'id' ) );

		if ( null === $layout ) {
			return new WP_Error( 'qevora_layouts_not_found', __( 'Layout not found.', 'qevora-layouts' ), array( 'status' => 404 ) );
		}

		if ( ! Capabilities::can_edit_layout( $layout->id ) ) {
			return new WP_Error( 'qevora_layouts_rest_forbidden', __( 'Sorry, you are not allowed to read this layout.', 'qevora-layouts' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return rest_ensure_response( self::prepare_layout( $layout, true ) );
	}

	/**
	 * Prepares a layout for a REST response.
	 *
	 * @since 1.0.0
	 *
	 * @param Layout $layout  Layout.
	 * @param bool   $verbose Include content.
	 * @return array<string,mixed>
	 */
	private static function prepare_layout( Layout $layout, $verbose = false ) {
		$conditions = (array) $layout->conditions;

		$data = array(
			'id'              => (int) $layout->id,
			'title'           => (string) $layout->title,
			'status'          => (string) $layout->status,
			'enabled'         => $layout->is_enabled(),
			'type'            => (string) $layout->type,
			'type_label'      => Layout_Post_Type::get_type_label( $layout->type ),
			'priority'        => (int) $layout->priority,
			'placement'       => (string) $layout->placement,
			'effective_placement' => $layout->get_effective_placement(),
			'hook'            => (string) $layout->hook,
			'hook_priority'   => (int) $layout->hook_priority,
			'builder'         => (string) $layout->builder,
			'content_source'  => (string) $layout->content_source,
			'reference_id'    => (int) $layout->reference_id,
			'template_id'     => (string) $layout->template_id,
			'conditions'      => $conditions,
			'condition_count' => count( isset( $conditions['include'] ) ? (array) $conditions['include'] : array() ) + count( isset( $conditions['exclude'] ) ? (array) $conditions['exclude'] : array() ),
			'edit_link'       => (string) get_edit_post_link( $layout->id, 'raw' ),
		);

		if ( $verbose ) {
			$post = get_post( $layout->id );

			$data['content']       = $post instanceof \WP_Post ? (string) $post->post_content : '';
			$data['import_source'] = (string) $layout->import_source;
			$data['imported_at']   = (string) $layout->imported_at;
		}

		/**
		 * Filters a layout prepared for the REST API.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,mixed> $data    Data.
		 * @param Layout              $layout  Layout.
		 * @param bool                $verbose Verbose output.
		 */
		return (array) apply_filters( 'qevora_layouts_rest_layout', $data, $layout, $verbose );
	}

	/**
	 * Validates the optional conditions parameter of the import route.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Request value.
	 * @return bool
	 */
	public static function is_conditions_array( $value ) {
		if ( null === $value ) {
			return true;
		}

		if ( ! is_array( $value ) ) {
			return false;
		}

		foreach ( $value as $key => $rules ) {
			if ( ! in_array( $key, array( 'include', 'exclude' ), true ) || ! is_array( $rules ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Lists library templates.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_templates( WP_REST_Request $request ) {
		$templates = Plugin::instance()->library()->for_display(
			array(
				'type'   => (string) $request->get_param( 'type' ),
				'origin' => (string) $request->get_param( 'origin' ),
				'search' => (string) $request->get_param( 'search' ),
			)
		);

		$items = array();

		foreach ( $templates as $template ) {
			unset( $template['content'], $template['conditions'] );

			$items[] = $template;
		}

		return rest_ensure_response(
			array(
				'items'    => $items,
				'problems' => array_slice( (array) Plugin::instance()->library()->get_problems(), 0, 10 ),
			)
		);
	}

	/**
	 * Imports a template.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function import_template( WP_REST_Request $request ) {
		$result = Import_Handler::handle(
			array(
				'template_id'   => (string) $request->get_param( 'template_id' ),
				'origin'        => (string) $request->get_param( 'origin' ),
				'on_duplicate'  => (string) $request->get_param( 'on_duplicate' ),
				'status'        => (string) $request->get_param( 'status' ),
				'replace_id'    => (int) $request->get_param( 'replace_id' ),
				'display_scope' => (string) $request->get_param( 'display_scope' ),
				'hide_theme_part' => $request->get_param( 'hide_theme_part' ),
				'demo'          => (string) $request->get_param( 'demo' ),
				'create_menu'   => $request->get_param( 'create_menu' ),
				'conditions'    => $request->get_param( 'conditions' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$summary = Import_Handler::summarize( $result );

		return rest_ensure_response(
			array(
				'result'  => $result,
				'summary' => $summary,
			)
		);
	}

	/**
	 * Searches posts, terms or users for the condition UI.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function search( WP_REST_Request $request ) {
		$search = trim( (string) $request->get_param( 'search' ) );

		if ( strlen( $search ) < 2 ) {
			return new WP_Error( 'qevora_layouts_search_too_short', __( 'Enter at least two characters.', 'qevora-layouts' ), array( 'status' => 400 ) );
		}

		$kind      = (string) $request->get_param( 'kind' );
		$post_type = (string) $request->get_param( 'post_type' );
		$taxonomy  = (string) $request->get_param( 'taxonomy' );

		$items = array();

		if ( 'terms' === $kind ) {
			$taxonomies = '' !== $taxonomy && taxonomy_exists( $taxonomy ) ? array( $taxonomy ) : array_values( (array) get_taxonomies( array( 'public' => true ), 'names' ) );

			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomies,
					'search'     => $search,
					'hide_empty' => false,
					'number'     => 20,
				)
			);

			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					if ( $term instanceof \WP_Term ) {
						$items[] = array(
							'id'    => (int) $term->term_id,
							'label' => $term->name . ' (' . $term->taxonomy . ' #' . $term->term_id . ')',
						);
					}
				}
			}
		} elseif ( 'users' === $kind ) {
			$users = get_users(
				array(
					'search' => '*' . $search . '*',
					'number' => 20,
					'fields' => array( 'ID', 'display_name' ),
				)
			);

			foreach ( (array) $users as $user ) {
				if ( $user instanceof \WP_User ) {
					$items[] = array(
						'id'    => (int) $user->ID,
						'label' => $user->display_name . ' (#' . (int) $user->ID . ')',
					);
				}
			}
		} else {
			$post_types = '' !== $post_type && post_type_exists( $post_type )
				? array( $post_type )
				: array( 'post', 'page' );

			$query = new \WP_Query(
				array(
					'post_type'              => $post_types,
					'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
					's'                      => $search,
					'posts_per_page'         => 20,
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
					'update_post_meta_cache' => false,
					'fields'                 => 'ids',
				)
			);

			foreach ( (array) $query->posts as $post_id ) {
				$post_id = (int) $post_id;

				$items[] = array(
					'id'    => $post_id,
					'label' => ( '' !== get_the_title( $post_id ) ? get_the_title( $post_id ) : __( '(no title)', 'qevora-layouts' ) ) . ' (' . get_post_type( $post_id ) . ' #' . $post_id . ')',
				);
			}
		}

		return rest_ensure_response(
			array(
				'kind'  => $kind,
				'items' => array_slice( $items, 0, 20 ),
			)
		);
	}

	/**
	 * Returns the system status report.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_system_status( WP_REST_Request $request ) {
		$report = System_Status_Page::get_report( Plugin::is_booted() ? Plugin::instance() : null );

		if ( 'text' === (string) $request->get_param( 'format' ) ) {
			return rest_ensure_response( array( 'text' => Environment::report_to_text( $report ) ) );
		}

		return rest_ensure_response( array( 'sections' => $report ) );
	}

	/**
	 * Sanitizes a post status filter.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status Status.
	 * @return string|string[]
	 */
	private static function sanitize_status_param( $status ) {
		$allowed = array( 'any', 'publish', 'draft', 'pending', 'trash' );

		if ( ! in_array( $status, $allowed, true ) ) {
			$status = 'any';
		}

		if ( 'any' === $status ) {
			return array( 'publish', 'draft', 'pending' );
		}

		return $status;
	}
}
