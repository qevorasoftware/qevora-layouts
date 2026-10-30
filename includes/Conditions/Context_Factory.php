<?php
/**
 * Builds the request context from WordPress.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Conditions;

use QevoraLayouts\Core\Logger;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Translates WordPress query state into a {@see Context}.
 *
 * Only WordPress conditional tags and query APIs are used - never URL string
 * matching - so behaviour follows the main query exactly.
 *
 * @since 1.0.0
 */
class Context_Factory {

	/**
	 * Context resolved for the current request.
	 *
	 * @since 1.0.0
	 * @var   Context|null
	 */
	private static $current = null;

	/**
	 * Returns the context of the current request, building it once.
	 *
	 * @since 1.0.0
	 *
	 * @return Context
	 */
	public static function current() {
		if ( null === self::$current ) {
			self::$current = self::build();
		}

		/**
		 * Filters the request context used to match display conditions.
		 *
		 * Useful for previews, cache plugins and tests.
		 *
		 * @since 1.0.0
		 *
		 * @param Context $context Current context.
		 */
		return apply_filters( 'qevora_layouts_context', self::$current );
	}

	/**
	 * Drops the cached context so the next call rebuilds it.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function reset() {
		self::$current = null;
	}

	/**
	 * Overrides the context for the current request.
	 *
	 * @since 1.0.0
	 *
	 * @param Context|null $context Context or null to clear.
	 * @return void
	 */
	public static function set_current( ?Context $context ) {
		self::$current = $context;
	}

	/**
	 * Builds a fresh context from the WordPress environment.
	 *
	 * @since 1.0.0
	 *
	 * @return Context
	 */
	public static function build() {
		$context = new Context();

		$context->is_admin = is_admin();

		if ( $context->is_admin ) {
			$context->request_kind = 'admin';
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$context->request_kind = 'rest';
		}

		if ( wp_doing_cron() ) {
			$context->request_kind = 'cli';
		}

		$context->is_front_page = is_front_page();
		$context->is_posts_page = is_home() && ! is_front_page();
		$context->is_singular   = is_singular();
		$context->is_single     = is_single();
		$context->is_page       = is_page();
		$context->is_attachment = is_attachment();
		$context->is_archive    = is_archive();
		$context->is_tax        = is_tax();
		$context->is_category   = is_category();
		$context->is_tag        = is_tag();
		$context->is_search     = is_search();
		$context->is_404        = is_404();
		$context->is_author     = is_author();
		$context->is_preview    = is_preview();

		$context->is_post_type_archive = is_post_type_archive();

		if ( $context->is_post_type_archive ) {
			$archive_type                  = get_query_var( 'post_type' );
			$context->archive_post_type    = is_array( $archive_type ) ? (string) reset( $archive_type ) : (string) $archive_type;
		}

		$queried_object = get_queried_object();

		if ( $queried_object instanceof \WP_Post ) {
			$context->post_id     = (int) $queried_object->ID;
			$context->post_type   = (string) $queried_object->post_type;
			$context->post_author = (int) $queried_object->post_author;

			if ( empty( $context->post_type ) ) {
				$context->post_type = (string) get_post_type( $queried_object );
			}

			$context->post_term_ids = self::post_term_ids_with_ancestors( $queried_object );
		} elseif ( $queried_object instanceof \WP_Term ) {
			$context->taxonomy = (string) $queried_object->taxonomy;
			$context->term_ids = self::term_ids_with_descendants( $queried_object );
		} elseif ( $queried_object instanceof \WP_User ) {
			$context->author_id = (int) $queried_object->ID;
		} elseif ( $queried_object instanceof \WP_Post_Type ) {
			$context->archive_post_type = (string) $queried_object->name;

			if ( '' === $context->archive_post_type ) {
				$context->is_post_type_archive = true;
			}
		}

		if ( 0 === $context->post_id && $context->is_singular ) {
			$context->post_id = (int) get_queried_object_id();
		}

		if ( $context->is_author && 0 === $context->author_id ) {
			$context->author_id = (int) get_queried_object_id();
		}

		if ( $context->is_singular && 0 === $context->post_author ) {
			$context->post_author = (int) get_post_field( 'post_author', $context->post_id );
		}

		$context->is_user_logged_in = is_user_logged_in();

		if ( $context->is_user_logged_in ) {
			$user = wp_get_current_user();

			if ( $user instanceof \WP_User ) {
				$context->user_roles = array_values( array_map( 'strval', (array) $user->roles ) );
			}
		}

		Logger::debug( 'Built request context.', array( 'context' => $context->describe() ) );

		return $context;
	}

	/**
	 * Returns the term IDs of a post plus the ancestors of each term.
	 *
	 * Expanding to ancestors lets a condition that targets a parent term also
	 * match content filed under a child term.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post Queried post.
	 * @return int[]
	 */
	private static function post_term_ids_with_ancestors( \WP_Post $post ) {
		$taxonomies = get_object_taxonomies( $post->post_type, 'names' );

		if ( empty( $taxonomies ) ) {
			return array();
		}

		$terms = wp_get_object_terms( (int) $post->ID, array_values( $taxonomies ), array( 'fields' => 'ids' ) );

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$ids = array();

		foreach ( $terms as $term_id ) {
			$term_id = (int) $term_id;

			if ( $term_id > 0 && ! in_array( $term_id, $ids, true ) ) {
				$ids[] = $term_id;
			}

			$term = get_term( $term_id );

			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$ancestors = get_ancestors( $term_id, $term->taxonomy, 'taxonomy' );

			foreach ( (array) $ancestors as $ancestor_id ) {
				$ancestor_id = (int) $ancestor_id;

				if ( $ancestor_id > 0 && ! in_array( $ancestor_id, $ids, true ) ) {
					$ids[] = $ancestor_id;
				}
			}
		}

		return $ids;
	}

	/**
	 * Returns a term ID plus the IDs of all its descendants.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Term $term Queried term.
	 * @return int[]
	 */
	private static function term_ids_with_descendants( \WP_Term $term ) {
		$ids = array( (int) $term->term_id );

		$children = get_term_children( (int) $term->term_id, (string) $term->taxonomy );

		if ( is_wp_error( $children ) || ! is_array( $children ) ) {
			return $ids;
		}

		foreach ( $children as $child_id ) {
			$child_id = (int) $child_id;

			if ( $child_id > 0 && ! in_array( $child_id, $ids, true ) ) {
				$ids[] = $child_id;
			}
		}

		return $ids;
	}
}
