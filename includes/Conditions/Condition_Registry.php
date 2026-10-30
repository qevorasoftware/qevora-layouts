<?php
/**
 * Condition definitions.
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
 * The single source of truth for the available display conditions.
 *
 * Each definition declares:
 * - `label`         translated UI label,
 * - `description`   short help text,
 * - `value`         what the rule value contains: none|post_type|ids|taxonomy|term_ids|roles,
 * - `specificity`   weight used to break ties between matching layouts,
 * - `group`         UI grouping.
 *
 * @since 1.0.0
 */
class Condition_Registry {

	/**
	 * Returns every condition definition.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function all() {
		$conditions = array(
			'entire_site'       => array(
				'label'       => __( 'Entire site', 'qevora-layouts' ),
				'description' => __( 'Match every frontend request.', 'qevora-layouts' ),
				'value'       => 'none',
				'specificity' => 0,
				'group'       => __( 'Basic', 'qevora-layouts' ),
			),
			'front_page'        => array(
				'label'       => __( 'Front page', 'qevora-layouts' ),
				'description' => __( 'The page shown at the site root, whether it is a static page or the posts page.', 'qevora-layouts' ),
				'value'       => 'none',
				'specificity' => 2,
				'group'       => __( 'Basic', 'qevora-layouts' ),
			),
			'posts_page'        => array(
				'label'       => __( 'Posts page (blog)', 'qevora-layouts' ),
				'description' => __( 'The page assigned under Settings → Reading → Posts page.', 'qevora-layouts' ),
				'value'       => 'none',
				'specificity' => 2,
				'group'       => __( 'Basic', 'qevora-layouts' ),
			),
			'singular'          => array(
				'label'       => __( 'All singular content', 'qevora-layouts' ),
				'description' => __( 'Any single post, page or attachment.', 'qevora-layouts' ),
				'value'       => 'none',
				'specificity' => 2,
				'group'       => __( 'Basic', 'qevora-layouts' ),
			),
			'archive'           => array(
				'label'       => __( 'All archives', 'qevora-layouts' ),
				'description' => __( 'Post type, taxonomy, author and date archives.', 'qevora-layouts' ),
				'value'       => 'none',
				'specificity' => 2,
				'group'       => __( 'Basic', 'qevora-layouts' ),
			),
			'search'            => array(
				'label'       => __( 'Search results', 'qevora-layouts' ),
				'description' => __( 'The search results listing.', 'qevora-layouts' ),
				'value'       => 'none',
				'specificity' => 2,
				'group'       => __( 'Basic', 'qevora-layouts' ),
			),
			'404'               => array(
				'label'       => __( '404 page', 'qevora-layouts' ),
				'description' => __( 'Requests that do not match any content.', 'qevora-layouts' ),
				'value'       => 'none',
				'specificity' => 2,
				'group'       => __( 'Basic', 'qevora-layouts' ),
			),
			'post_type'         => array(
				'label'       => __( 'Singular post type', 'qevora-layouts' ),
				'description' => __( 'Single entries of one post type.', 'qevora-layouts' ),
				'value'       => 'post_type',
				'specificity' => 3,
				'group'       => __( 'Content', 'qevora-layouts' ),
			),
			'post_type_archive' => array(
				'label'       => __( 'Post type archive', 'qevora-layouts' ),
				'description' => __( 'The archive listing of one post type.', 'qevora-layouts' ),
				'value'       => 'post_type',
				'specificity' => 4,
				'group'       => __( 'Content', 'qevora-layouts' ),
			),
			'post'              => array(
				'label'       => __( 'Specific posts', 'qevora-layouts' ),
				'description' => __( 'One or more posts selected by ID.', 'qevora-layouts' ),
				'value'       => 'ids',
				'specificity' => 5,
				'group'       => __( 'Content', 'qevora-layouts' ),
			),
			'page'              => array(
				'label'       => __( 'Specific pages', 'qevora-layouts' ),
				'description' => __( 'One or more pages selected by ID.', 'qevora-layouts' ),
				'value'       => 'ids',
				'specificity' => 5,
				'group'       => __( 'Content', 'qevora-layouts' ),
			),
			'taxonomy'          => array(
				'label'       => __( 'Taxonomy archive', 'qevora-layouts' ),
				'description' => __( 'Archives of a taxonomy, optionally limited to specific terms.', 'qevora-layouts' ),
				'value'       => 'taxonomy',
				'specificity' => 4,
				'group'       => __( 'Taxonomy', 'qevora-layouts' ),
			),
			'term'              => array(
				'label'       => __( 'Terms (archive and content)', 'qevora-layouts' ),
				'description' => __( 'Term archives of the selected terms plus singular content assigned to them, including child terms.', 'qevora-layouts' ),
				'value'       => 'term_ids',
				'specificity' => 5,
				'group'       => __( 'Taxonomy', 'qevora-layouts' ),
			),
			'author'            => array(
				'label'       => __( 'Author', 'qevora-layouts' ),
				'description' => __( 'Author archives and singular content written by the selected users.', 'qevora-layouts' ),
				'value'       => 'ids',
				'specificity' => 3,
				'group'       => __( 'Users', 'qevora-layouts' ),
			),
			'logged_in'         => array(
				'label'       => __( 'Logged in users', 'qevora-layouts' ),
				'description' => __( 'Only shown to visitors who are signed in.', 'qevora-layouts' ),
				'value'       => 'none',
				'specificity' => 1,
				'group'       => __( 'Users', 'qevora-layouts' ),
			),
			'logged_out'        => array(
				'label'       => __( 'Logged out visitors', 'qevora-layouts' ),
				'description' => __( 'Only shown to visitors who are not signed in.', 'qevora-layouts' ),
				'value'       => 'none',
				'specificity' => 1,
				'group'       => __( 'Users', 'qevora-layouts' ),
			),
			'user_role'         => array(
				'label'       => __( 'User role', 'qevora-layouts' ),
				'description' => __( 'Only shown to logged in users holding one of the selected roles.', 'qevora-layouts' ),
				'value'       => 'roles',
				'specificity' => 3,
				'group'       => __( 'Users', 'qevora-layouts' ),
			),
		);

		/**
		 * Filters the available display conditions.
		 *
		 * A custom condition must also be handled by the
		 * `qevora_layouts_condition_matches` filter, see
		 * {@see Condition_Matcher::rule_matches()}.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,array<string,mixed>> $conditions Condition definitions.
		 */
		return apply_filters( 'qevora_layouts_conditions', $conditions );
	}

	/**
	 * Returns one condition definition.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Condition type.
	 * @return array<string,mixed>|null
	 */
	public static function get( $type ) {
		$conditions = self::all();

		return isset( $conditions[ $type ] ) ? $conditions[ $type ] : null;
	}

	/**
	 * Whether a condition type exists.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $type Candidate type.
	 * @return bool
	 */
	public static function exists( $type ) {
		return is_string( $type ) && array_key_exists( $type, self::all() );
	}

	/**
	 * Returns the translated label of a condition type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Condition type.
	 * @return string
	 */
	public static function label( $type ) {
		$condition = self::get( $type );

		return isset( $condition['label'] ) ? (string) $condition['label'] : (string) $type;
	}

	/**
	 * Returns the value type of a condition.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Condition type.
	 * @return string One of none|post_type|ids|taxonomy|term_ids|roles.
	 */
	public static function value_type( $type ) {
		$condition = self::get( $type );

		return isset( $condition['value'] ) ? (string) $condition['value'] : 'none';
	}

	/**
	 * Returns the specificity weight of a condition type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Condition type.
	 * @return int
	 */
	public static function specificity( $type ) {
		$condition = self::get( $type );

		return isset( $condition['specificity'] ) ? (int) $condition['specificity'] : 0;
	}

	/**
	 * Returns condition types grouped for the admin UI.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,array<string,string>> Group label => (type => label).
	 */
	public static function grouped_choices() {
		$groups = array();

		foreach ( self::all() as $type => $condition ) {
			$group = isset( $condition['group'] ) ? (string) $condition['group'] : __( 'Other', 'qevora-layouts' );

			if ( ! isset( $groups[ $group ] ) ) {
				$groups[ $group ] = array();
			}

			$groups[ $group ][ $type ] = isset( $condition['label'] ) ? (string) $condition['label'] : (string) $type;
		}

		return $groups;
	}

	/**
	 * Returns the condition catalogue in a JSON safe shape for the admin UI.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function for_js() {
		$out = array();

		foreach ( self::all() as $type => $condition ) {
			$out[ $type ] = array(
				'label'       => isset( $condition['label'] ) ? $condition['label'] : $type,
				'description' => isset( $condition['description'] ) ? $condition['description'] : '',
				'value'       => isset( $condition['value'] ) ? $condition['value'] : 'none',
				'group'       => isset( $condition['group'] ) ? $condition['group'] : '',
			);
		}

		return $out;
	}
}
