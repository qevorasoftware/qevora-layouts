<?php
/**
 * Request context value object.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Conditions;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * An immutable snapshot of "where are we right now".
 *
 * The condition engine never touches WordPress globals directly. Everything it
 * needs is described by a Context, which makes the matching logic unit testable
 * and prevents fragile URL substring checks.
 *
 * @since 1.0.0
 */
class Context {

	/**
	 * Request kind: frontend|admin|rest|cli.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $request_kind = 'frontend';

	/**
	 * Whether this is the site front page.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	public $is_front_page = false;

	/**
	 * Whether this is the posts page ("blog" page).
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	public $is_posts_page = false;

	/**
	 * Whether this is any singular view.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	public $is_singular = false;

	/**
	 * Whether this is a single post of any post type.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	public $is_single = false;

	/**
	 * Whether this is a page.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	public $is_page = false;

	/**
	 * Whether this is an attachment page.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	public $is_attachment = false;

	/**
	 * Current (queried) post ID, 0 when there is none.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	public $post_id = 0;

	/**
	 * Post type of the current post.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $post_type = '';

	/**
	 * Author ID of the current post, or the queried author on author archives.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	public $post_author = 0;

	/**
	 * Whether this is any archive.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	public $is_archive = false;

	/**
	 * Whether this is a post type archive.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	public $is_post_type_archive = false;

	/**
	 * Post type of the current post type archive.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $archive_post_type = '';

	/**
	 * Whether this is a taxonomy archive.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	public $is_tax = false;

	/**
	 * Whether this is a category archive.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	public $is_category = false;

	/**
	 * Whether this is a tag archive.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	public $is_tag = false;

	/**
	 * Taxonomy of the current taxonomy archive.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $taxonomy = '';

	/**
	 * Term IDs matching the current archive, including descendants of the
	 * queried term for hierarchical taxonomies.
	 *
	 * @since 1.0.0
	 * @var   int[]
	 */
	public $term_ids = array();

	/**
	 * Term IDs assigned to the current singular post (all taxonomies).
	 *
	 * @since 1.0.0
	 * @var   int[]
	 */
	public $post_term_ids = array();

	/**
	 * Whether this is a search results page.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	public $is_search = false;

	/**
	 * Whether this is a 404 page.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	public $is_404 = false;

	/**
	 * Whether this is an author archive.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	public $is_author = false;

	/**
	 * Author ID on author archives.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	public $author_id = 0;

	/**
	 * Whether a user is logged in.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	public $is_user_logged_in = false;

	/**
	 * Roles of the current user.
	 *
	 * @since 1.0.0
	 * @var   string[]
	 */
	public $user_roles = array();

	/**
	 * Whether the current request is a preview of a draft.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	public $is_preview = false;

	/**
	 * Whether this is an admin request.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	public $is_admin = false;

	/**
	 * Creates a context from an array.
	 *
	 * Unknown keys are ignored so stored contexts stay forward compatible.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $data Context data.
	 * @return self
	 */
	public static function from_array( array $data ) {
		$context = new self();

		foreach ( $data as $key => $value ) {
			if ( ! property_exists( $context, $key ) ) {
				continue;
			}

			$current = $context->{$key};

			if ( is_bool( $current ) ) {
				$context->{$key} = (bool) $value;
				continue;
			}

			if ( is_int( $current ) ) {
				$context->{$key} = (int) $value;
				continue;
			}

			if ( is_array( $current ) ) {
				if ( ! is_array( $value ) ) {
					continue;
				}

				if ( 'user_roles' === $key ) {
					$context->{$key} = array_values( array_filter( array_map( 'strval', $value ) ) );
					continue;
				}

				$context->{$key} = array_values( array_filter( array_map( 'intval', $value ) ) );
				continue;
			}

			$context->{$key} = (string) $value;
		}

		return $context;
	}

	/**
	 * Serializes the context.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,mixed>
	 */
	public function to_array() {
		return get_object_vars( $this );
	}

	/**
	 * Returns a short, human readable description. Used by the debug panel.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function describe() {
		$flags = array();

		foreach ( $this->to_array() as $key => $value ) {
			if ( 0 === strpos( $key, 'is_' ) && true === $value ) {
				$flags[] = substr( $key, 3 );
			}
		}

		$description = empty( $flags ) ? 'unknown' : implode( ', ', $flags );

		if ( '' !== $this->post_type ) {
			$description .= ' [' . $this->post_type . ':' . $this->post_id . ']';
		} elseif ( '' !== $this->taxonomy ) {
			$description .= ' [' . $this->taxonomy . ':' . implode( ',', $this->term_ids ) . ']';
		}

		return $description;
	}
}
