<?php
/**
 * Admin AJAX endpoints.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Admin;

use QevoraLayouts\Core\Cache;
use QevoraLayouts\Core\Capabilities;
use QevoraLayouts\Core\Layout_Post_Type;
use QevoraLayouts\Core\Logger;
use QevoraLayouts\Core\Plugin;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX handlers used by the admin UI.
 *
 * Every handler verifies the shared admin nonce and a capability, and answers
 * with `wp_send_json_*` so error messages stay machine readable. The same work
 * can be done without JavaScript through {@see Actions}.
 *
 * @since 1.0.0
 */
class Ajax {

	/**
	 * Nonce action shared by the plugin AJAX endpoints.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const NONCE_ACTION = 'qevora_layouts_admin';

	/**
	 * Registers hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'wp_ajax_qevora_layouts_import', array( __CLASS__, 'import' ) );
		add_action( 'wp_ajax_qevora_layouts_search', array( __CLASS__, 'search' ) );
		add_action( 'wp_ajax_qevora_layouts_flush_cache', array( __CLASS__, 'flush_cache' ) );
	}

	/**
	 * Verifies the nonce and a capability.
	 *
	 * @since 1.0.0
	 *
	 * @param string $capability Required capability.
	 * @return true|WP_Error
	 */
	private static function authorize( $capability ) {
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by check_ajax_referer below.

		if ( '' === $nonce || ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			Logger::warning( 'AJAX request rejected: invalid nonce.' );

			return new WP_Error(
				'qevora_layouts_invalid_nonce',
				__( 'Your session has expired. Please reload the page and try again.', 'qevora-layouts' ),
				array( 'status' => 403 )
			);
		}

		if ( ! current_user_can( $capability ) ) {
			Logger::warning( 'AJAX request rejected: missing capability.', array( 'capability' => (string) $capability ) );

			return new WP_Error(
				'qevora_layouts_forbidden',
				__( 'You are not allowed to perform this action.', 'qevora-layouts' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Imports a template.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function import() {
		$auth = self::authorize( Capabilities::PUBLISH_LAYOUTS );

		if ( is_wp_error( $auth ) ) {
			self::send_error( $auth );

			return;
		}

		$params = isset( $_POST ) ? wp_unslash( $_POST ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Verified above; values are sanitized by the handler.
		$files  = isset( $_FILES ) ? $_FILES : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- Verified above; values are validated by the handler.

		$result = Import_Handler::handle( is_array( $params ) ? $params : array(), is_array( $files ) ? $files : array() );

		if ( is_wp_error( $result ) ) {
			$summary = Import_Handler::summarize( $result );

			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'summary' => $summary,
				),
				400
			);
		}

		$summary = Import_Handler::summarize( $result );

		wp_send_json_success(
			array(
				'result'  => $result,
				'summary' => $summary,
				'message' => $summary['message'],
			)
		);
	}

	/**
	 * Searches posts, terms or users for the condition UI.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function search() {
		$auth = self::authorize( Capabilities::EDIT_LAYOUTS );

		if ( is_wp_error( $auth ) ) {
			self::send_error( $auth );

			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified in authorize().
		$kind      = isset( $_REQUEST['kind'] ) ? sanitize_key( wp_unslash( $_REQUEST['kind'] ) ) : 'posts';
		$search    = isset( $_REQUEST['search'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['search'] ) ) : '';
		$post_type = isset( $_REQUEST['post_type'] ) ? sanitize_key( wp_unslash( $_REQUEST['post_type'] ) ) : '';
		$taxonomy  = isset( $_REQUEST['taxonomy'] ) ? sanitize_key( wp_unslash( $_REQUEST['taxonomy'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( '' === $search || self::length( $search ) < 2 ) {
			wp_send_json_error(
				array( 'message' => __( 'Enter at least two characters to search.', 'qevora-layouts' ) ),
				400
			);
		}

		$items = array();

		switch ( $kind ) {
			case 'terms':
				$items = self::search_terms( $search, $taxonomy );
				break;

			case 'users':
				$items = self::search_users( $search );
				break;

			case 'posts':
			default:
				$items = self::search_posts( $search, $post_type );
				break;
		}

		wp_send_json_success(
			array(
				'items' => $items,
				'kind'  => $kind,
			)
		);
	}

	/**
	 * Searches posts and pages.
	 *
	 * @since 1.0.0
	 *
	 * @param string $search    Search string.
	 * @param string $post_type Optional post type filter.
	 * @return array<int,array<string,mixed>>
	 */
	private static function search_posts( $search, $post_type = '' ) {
		$post_types = array( 'post', 'page' );

		if ( '' !== $post_type && post_type_exists( $post_type ) ) {
			$post_types = array( $post_type );
		} else {
			$public = get_post_types( array( 'public' => true ), 'names' );
			$public = array_values( array_diff( (array) $public, array( 'attachment', Layout_Post_Type::POST_TYPE ) ) );

			if ( ! empty( $public ) ) {
				$post_types = array_slice( $public, 0, 10 );
			}
		}

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

		$items = array();

		foreach ( (array) $query->posts as $post_id ) {
			$post_id = (int) $post_id;

			$items[] = array(
				'id'    => $post_id,
				'label' => sprintf(
					/* translators: 1: post title, 2: post type, 3: post ID. */
					__( '%1$s (%2$s #%3$d)', 'qevora-layouts' ),
					'' !== get_the_title( $post_id ) ? get_the_title( $post_id ) : __( '(no title)', 'qevora-layouts' ),
					(string) get_post_type( $post_id ),
					$post_id
				),
			);
		}

		return $items;
	}

	/**
	 * Searches terms.
	 *
	 * @since 1.0.0
	 *
	 * @param string $search   Search string.
	 * @param string $taxonomy Optional taxonomy filter.
	 * @return array<int,array<string,mixed>>
	 */
	private static function search_terms( $search, $taxonomy = '' ) {
		$taxonomies = array();

		if ( '' !== $taxonomy && taxonomy_exists( $taxonomy ) ) {
			$taxonomies = array( $taxonomy );
		} else {
			$taxonomies = array_values( (array) get_taxonomies( array( 'public' => true ), 'names' ) );
		}

		if ( empty( $taxonomies ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomies,
				'search'     => $search,
				'hide_empty' => false,
				'number'     => 20,
			)
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$items = array();

		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$items[] = array(
				'id'    => (int) $term->term_id,
				'label' => sprintf(
					/* translators: 1: term name, 2: taxonomy, 3: term ID. */
					__( '%1$s (%2$s #%3$d)', 'qevora-layouts' ),
					(string) $term->name,
					(string) $term->taxonomy,
					(int) $term->term_id
				),
			);
		}

		return $items;
	}

	/**
	 * Searches users that can author content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $search Search string.
	 * @return array<int,array<string,mixed>>
	 */
	private static function search_users( $search ) {
		$users = get_users(
			array(
				'search'  => '*' . $search . '*',
				'number'  => 20,
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'fields'  => array( 'ID', 'display_name' ),
			)
		);

		$items = array();

		foreach ( (array) $users as $user ) {
			if ( ! $user instanceof \WP_User ) {
				continue;
			}

			$items[] = array(
				'id'    => (int) $user->ID,
				'label' => sprintf(
					/* translators: 1: display name, 2: user ID. */
					__( '%1$s (#%2$d)', 'qevora-layouts' ),
					(string) $user->display_name,
					(int) $user->ID
				),
			);
		}

		return $items;
	}

	/**
	 * Clears plugin caches.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function flush_cache() {
		$auth = self::authorize( Capabilities::MANAGE_SETTINGS );

		if ( is_wp_error( $auth ) ) {
			self::send_error( $auth );

			return;
		}

		Cache::bump_version();
		Plugin::instance()->library()->clear_cache();

		wp_send_json_success(
			array( 'message' => __( 'Caches cleared.', 'qevora-layouts' ) )
		);
	}

	/**
	 * Returns the character length of a search string.
	 *
	 * Falls back to the byte length when mbstring is unavailable, which only
	 * makes the "at least two characters" threshold slightly lenient for
	 * multibyte input.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value Value.
	 * @return int
	 */
	private static function length( $value ) {
		$value = (string) $value;

		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( $value, 'UTF-8' );
		}

		return (int) strlen( $value );
	}

	/**
	 * Sends a WP_Error as a JSON error response.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Error $error Error.
	 * @return void
	 */
	private static function send_error( WP_Error $error ) {
		$data   = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;

		wp_send_json_error(
			array(
				'code'    => $error->get_error_code(),
				'message' => $error->get_error_message(),
			),
			$status
		);
	}
}
