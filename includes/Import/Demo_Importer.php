<?php
/**
 * Imports the bundled PHP demos as layouts.
 *
 * A demo import creates a `qevora_layout` whose builder is `demo`, so the
 * markup, stylesheet and script all stay in the plugin - every demo keeps
 * its own data. Optionally a real WordPress nav menu is created for demos
 * that support one, filled with the home link and the site's published
 * pages (never invented content).
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Import;

use QevoraLayouts\Conditions\Condition_Sanitizer;
use QevoraLayouts\Core\Capabilities;
use QevoraLayouts\Core\Layout_Post_Type;
use QevoraLayouts\Core\Layout_Repository;
use QevoraLayouts\Core\Settings;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates layouts from the bundled PHP demos.
 *
 * @since 1.0.0
 */
class Demo_Importer {

	/**
	 * Layout repository.
	 *
	 * @since 1.0.0
	 * @var   Layout_Repository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Layout_Repository $repository Repository.
	 */
	public function __construct( Layout_Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Imports one demo.
	 *
	 * @since 1.0.0
	 *
	 * @param string              $slug    Demo slug.
	 * @param array<string,mixed> $options {
	 *     Optional import options.
	 *
	 *     @type string     $on_duplicate    skip|copy. Empty reads the setting.
	 *     @type string     $status          publish|draft.
	 *     @type array|null $conditions      Explicit conditions object.
	 *     @type string     $display_scope   Quick display scope key.
	 *     @type bool|null  $hide_theme_part Replace the theme part while rendering.
	 *     @type bool|null  $create_menu     Create a sample menu (null defaults to yes for menu demos).
	 * }
	 * @return array<string,mixed>|WP_Error Batch shaped result, WP_Error for an unknown demo.
	 */
	public function import( $slug, array $options = array() ) {
		$demo = Demo_Registry::get( $slug );

		if ( null === $demo ) {
			return new WP_Error( 'qevora_layouts_demo_not_found', __( 'That demo could not be found.', 'qevora-layouts' ), array( 'status' => 404 ) );
		}

		$result = array(
			'imported' => array(),
			'skipped'  => array(),
			'failed'   => array(),
			'warnings' => array(),
		);

		if ( ! Capabilities::can_publish_layouts() ) {
			$result['failed'][] = array(
				'name'   => (string) $demo['name'],
				'errors' => array( __( 'You are not allowed to import templates.', 'qevora-layouts' ) ),
			);

			return $result;
		}

		$options = wp_parse_args(
			$options,
			array(
				'on_duplicate'    => '',
				'status'          => 'publish',
				'conditions'      => null,
				'display_scope'   => '',
				'hide_theme_part' => null,
				'create_menu'     => null,
			)
		);

		$provenance = 'qevora-demo-' . $demo['id'];
		$existing   = $this->repository->find_by_template_id( $provenance );
		$policy     = (string) $options['on_duplicate'];

		if ( '' === $policy ) {
			$policy = (string) Settings::get( 'duplicate_import_policy', 'skip' );
		}

		if ( ! in_array( $policy, array( 'skip', 'copy' ), true ) ) {
			$policy = 'skip';
		}

		$base = array(
			'template_id' => $provenance,
			'name'        => (string) $demo['name'],
			'type'        => (string) $demo['type'],
			'action'      => 'failed',
			'errors'      => array(),
			'warnings'    => array(),
		);

		if ( ! empty( $existing ) && 'copy' !== $policy ) {
			$base['action']       = 'skipped';
			$base['existing_ids'] = array_map( 'intval', $existing );
			$base['existing_url'] = get_edit_post_link( (int) $existing[0], 'raw' );
			$base['reason']       = sprintf(
				/* translators: %s: demo name. */
				__( '"%s" was imported before and already exists. Nothing was changed.', 'qevora-layouts' ),
				(string) $demo['name']
			);

			$result['skipped'][] = $base;

			return $result;
		}

		// Where the demo displays: explicit conditions > quick scope > entire
		// site, so a freshly imported demo is visible right away.
		$conditions = null;

		if ( is_array( $options['conditions'] ) ) {
			$conditions = Condition_Sanitizer::normalize( $options['conditions'] );
		} elseif ( '' !== (string) $options['display_scope'] ) {
			$conditions = Importer::scope_conditions( (string) $options['display_scope'] );
		}

		if ( ! is_array( $conditions ) ) {
			$conditions = array(
				'include' => array(),
				'exclude' => array(),
			);
		}

		$menu_id     = 0;
		$create_menu = null === $options['create_menu'] ? true : (bool) $options['create_menu'];

		if ( $create_menu && ! empty( $demo['menu'] ) ) {
			$menu = $this->create_menu( $demo );

			if ( is_wp_error( $menu ) ) {
				$base['warnings'][]    = $menu->get_error_message();
				$result['warnings'][]  = $menu->get_error_message();
			} else {
				$menu_id = (int) $menu;
			}
		}

		$saved = $this->repository->save(
			array(
				'title'           => (string) $demo['name'],
				'content'         => '',
				'type'            => (string) $demo['type'],
				'status'          => $this->repository->sanitize_status( $options['status'] ),
				'priority'        => 10,
				'conditions'      => $conditions,
				'hide_theme_part' => null === $options['hide_theme_part'] ? false : Layout_Post_Type::sanitize_hide_theme_part( $options['hide_theme_part'] ),
				'placement'       => '',
				'builder'         => 'demo',
				'demo_slug'       => (string) $demo['id'],
				'menu_id'         => $menu_id,
				'template_id'     => $provenance,
				'import_source'   => 'bundled',
				'imported_at'     => gmdate( 'c' ),
			)
		);

		if ( is_wp_error( $saved ) ) {
			$base['errors'][]    = $saved->get_error_message();
			$result['failed'][]  = $base;

			return $result;
		}

		$base['action'] = ! empty( $existing ) ? 'copied' : 'created';
		$base['id']     = (int) $saved;
		$base['url']    = get_edit_post_link( (int) $saved, 'raw' );

		if ( ! empty( $existing ) ) {
			$base['warnings'][]   = sprintf(
				/* translators: %s: demo name. */
				__( '"%s" already existed, a second copy was created.', 'qevora-layouts' ),
				(string) $demo['name']
			);
			$result['warnings'][] = end( $base['warnings'] );
		}

		if ( $menu_id > 0 ) {
			$base['warnings'][] = sprintf(
				/* translators: %s: menu name. */
				__( 'A sample menu was created for this demo: %s', 'qevora-layouts' ),
				(string) $demo['name'] . ' menu'
			);
		}

		$result['imported'][] = $base;

		return $result;
	}

	/**
	 * Creates a real nav menu for a demo: the home link plus the site's
	 * published pages. No invented content is ever added.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $demo Demo manifest.
	 * @return int|WP_Error Menu ID or error.
	 */
	private function create_menu( array $demo ) {
		if ( ! function_exists( 'wp_update_nav_menu_object' ) || ! function_exists( 'wp_update_nav_menu_item' ) ) {
			return new WP_Error( 'qevora_layouts_menu_unavailable', __( 'Menus are not available in this environment, so the demo was imported without one.', 'qevora-layouts' ) );
		}

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error( 'qevora_layouts_menu_forbidden', __( 'You are not allowed to create menus, so the demo was imported without one.', 'qevora-layouts' ) );
		}

		$base_name = sprintf(
			/* translators: %s: demo name. */
			__( '%s menu', 'qevora-layouts' ),
			(string) $demo['name']
		);

		$menu_id = 0;

		for ( $attempt = 0; $attempt < 6; $attempt++ ) {
			$name    = 0 === $attempt ? $base_name : $base_name . ' ' . ( $attempt + 1 );
			$created = wp_update_nav_menu_object( 0, array( 'menu-name' => $name ) );

			if ( ! is_wp_error( $created ) ) {
				$menu_id = (int) $created;

				break;
			}

			if ( 'menu_exists' !== $created->get_error_code() ) {
				return $created;
			}
		}

		if ( $menu_id <= 0 ) {
			return new WP_Error( 'qevora_layouts_menu_exists', __( 'A menu for this demo already exists and could not be created again.', 'qevora-layouts' ) );
		}

		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'    => __( 'Home', 'qevora-layouts' ),
				'menu-item-url'      => home_url( '/' ),
				'menu-item-type'     => 'custom',
				'menu-item-status'   => 'publish',
				'menu-item-position' => 1,
			)
		);

		$pages    = function_exists( 'get_posts' )
			? get_posts(
				array(
					'post_type'   => 'page',
					'post_status' => 'publish',
					'numberposts' => 5,
					'orderby'     => 'menu_order',
					'order'       => 'ASC',
				)
			)
			: array();
		$position = 2;

		foreach ( (array) $pages as $page ) {
			if ( ! isset( $page->ID ) ) {
				continue;
			}

			wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'     => (string) $page->post_title,
					'menu-item-object-id' => (int) $page->ID,
					'menu-item-object'    => 'page',
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
					'menu-item-position'  => $position,
				)
			);

			++$position;
		}

		return $menu_id;
	}
}
