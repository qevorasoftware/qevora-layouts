<?php
/**
 * Condition matching engine.
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
 * Pure matching logic for layout display conditions.
 *
 * Rules of the engine (documented in README.md):
 *
 * 1. Conditions are stored as `{ include: [rule…], exclude: [rule…] }`.
 * 2. An empty `include` list means "entire site".
 * 3. A layout matches when at least one `include` rule matches **and** no
 *    `exclude` rule matches. Exclusions always win over inclusions.
 * 4. When several layouts of the same type match, they are ordered by
 *    priority (higher first), then by specificity of the matched include rule
 *    (more specific first), then by post ID (lower first) so the outcome is
 *    deterministic.
 *
 * @since 1.0.0
 */
class Condition_Matcher {

	/**
	 * Whether a stored condition set matches a context.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed   $conditions Stored conditions.
	 * @param Context $context    Current request context.
	 * @return bool
	 */
	public static function matches( $conditions, Context $context ) {
		$conditions = Condition_Sanitizer::normalize( $conditions );

		foreach ( $conditions['exclude'] as $rule ) {
			if ( self::rule_matches( $rule, $context ) ) {
				return false;
			}
		}

		if ( empty( $conditions['include'] ) ) {
			return true;
		}

		foreach ( $conditions['include'] as $rule ) {
			if ( self::rule_matches( $rule, $context ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the specificity of the most specific matching include rule.
	 *
	 * Used as the second ordering criterion when several layouts match.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed   $conditions Stored conditions.
	 * @param Context $context    Current request context.
	 * @return int 0 when nothing matched (or the whole site matched).
	 */
	public static function specificity( $conditions, Context $context ) {
		$conditions = Condition_Sanitizer::normalize( $conditions );
		$best       = 0;

		foreach ( $conditions['include'] as $rule ) {
			if ( ! self::rule_matches( $rule, $context ) ) {
				continue;
			}

			$score = Condition_Registry::specificity( isset( $rule['type'] ) ? $rule['type'] : '' );

			if ( $score > $best ) {
				$best = $score;
			}
		}

		return $best;
	}

	/**
	 * Evaluates a single rule against a context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $rule    Normalized rule.
	 * @param Context             $context Current request context.
	 * @return bool
	 */
	public static function rule_matches( array $rule, Context $context ) {
		$type = isset( $rule['type'] ) ? (string) $rule['type'] : '';

		if ( '' === $type ) {
			return false;
		}

		/**
		 * Allows third parties to implement custom condition types.
		 *
		 * Return a boolean to take over the decision, or null to let the
		 * built-in matcher run.
		 *
		 * @since 1.0.0
		 *
		 * @param bool|null            $matched Null by default.
		 * @param string               $type    Condition type.
		 * @param array<string,mixed>  $rule    Normalized rule.
		 * @param Context              $context Current request context.
		 */
		$custom = apply_filters( 'qevora_layouts_condition_matches', null, $type, $rule, $context );

		if ( is_bool( $custom ) ) {
			return $custom;
		}

		switch ( $type ) {
			case 'entire_site':
				return true;

			case 'front_page':
				return (bool) $context->is_front_page;

			case 'posts_page':
				return (bool) $context->is_posts_page;

			case 'singular':
				return (bool) $context->is_singular;

			case 'archive':
				return (bool) $context->is_archive;

			case 'search':
				return (bool) $context->is_search;

			case '404':
				return (bool) $context->is_404;

			case 'logged_in':
				return (bool) $context->is_user_logged_in;

			case 'logged_out':
				return ! $context->is_user_logged_in;

			case 'post_type':
				$value = self::string_value( $rule );

				return $context->is_singular && '' !== $value && $context->post_type === $value;

			case 'post_type_archive':
				$value = self::string_value( $rule );

				if ( ! $context->is_post_type_archive || '' === $value ) {
					return false;
				}

				return $context->archive_post_type === $value;

			case 'post':
				return $context->is_singular && self::in_ids( $context->post_id, $rule );

			case 'page':
				return $context->is_page && self::in_ids( $context->post_id, $rule );

			case 'taxonomy':
				return self::matches_taxonomy( $rule, $context );

			case 'term':
				return self::matches_term( $rule, $context );

			case 'author':
				return self::matches_author( $rule, $context );

			case 'user_role':
				return self::matches_role( $rule, $context );

			default:
				return false;
		}
	}

	/**
	 * Matches a taxonomy archive rule, optionally limited to specific terms.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $rule    Normalized rule.
	 * @param Context             $context Current request context.
	 * @return bool
	 */
	private static function matches_taxonomy( array $rule, Context $context ) {
		if ( ! $context->is_tax ) {
			return false;
		}

		$taxonomy = self::string_value( $rule );

		if ( '' !== $taxonomy && $context->taxonomy !== $taxonomy ) {
			return false;
		}

		$terms = self::int_list( isset( $rule['terms'] ) ? $rule['terms'] : '' );

		if ( empty( $terms ) ) {
			return true;
		}

		return ! empty( array_intersect( $terms, $context->term_ids ) );
	}

	/**
	 * Matches a term rule against archives and singular content.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $rule    Normalized rule.
	 * @param Context             $context Current request context.
	 * @return bool
	 */
	private static function matches_term( array $rule, Context $context ) {
		$terms = self::int_list( self::value_of( $rule ) );

		if ( empty( $terms ) ) {
			return false;
		}

		if ( $context->is_tax && ! empty( array_intersect( $terms, $context->term_ids ) ) ) {
			return true;
		}

		if ( $context->is_singular && ! empty( array_intersect( $terms, $context->post_term_ids ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Matches an author rule against author archives and authored content.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $rule    Normalized rule.
	 * @param Context             $context Current request context.
	 * @return bool
	 */
	private static function matches_author( array $rule, Context $context ) {
		$ids = self::int_list( self::value_of( $rule ) );

		if ( empty( $ids ) ) {
			return false;
		}

		if ( $context->is_author && in_array( $context->author_id, $ids, true ) ) {
			return true;
		}

		return $context->is_singular && in_array( $context->post_author, $ids, true );
	}

	/**
	 * Matches a user role rule.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $rule    Normalized rule.
	 * @param Context             $context Current request context.
	 * @return bool
	 */
	private static function matches_role( array $rule, Context $context ) {
		if ( ! $context->is_user_logged_in ) {
			return false;
		}

		$roles = self::string_list( self::value_of( $rule ) );

		if ( empty( $roles ) || empty( $context->user_roles ) ) {
			return false;
		}

		return ! empty( array_intersect( $roles, $context->user_roles ) );
	}

	/**
	 * Whether an ID is listed in a rule value.
	 *
	 * @since 1.0.0
	 *
	 * @param int                 $id   Candidate ID.
	 * @param array<string,mixed> $rule Normalized rule.
	 * @return bool
	 */
	private static function in_ids( $id, array $rule ) {
		$ids = self::int_list( self::value_of( $rule ) );

		return ! empty( $ids ) && in_array( (int) $id, $ids, true );
	}

	/**
	 * Returns the raw `value` of a rule.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $rule Normalized rule.
	 * @return mixed
	 */
	private static function value_of( array $rule ) {
		return isset( $rule['value'] ) ? $rule['value'] : '';
	}

	/**
	 * Returns the rule value as a trimmed string.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $rule Normalized rule.
	 * @return string
	 */
	private static function string_value( array $rule ) {
		$value = self::value_of( $rule );

		if ( is_array( $value ) ) {
			$value = isset( $value[0] ) ? $value[0] : '';
		}

		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * Converts a rule value into a list of positive integers.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value (csv string or array).
	 * @return int[]
	 */
	public static function int_list( $value ) {
		$parts = is_array( $value ) ? $value : preg_split( '/[\s,]+/', (string) $value );
		$parts = is_array( $parts ) ? $parts : array();
		$out   = array();

		foreach ( $parts as $part ) {
			if ( is_array( $part ) || is_object( $part ) ) {
				continue;
			}

			$int = (int) $part;

			if ( $int > 0 && ! in_array( $int, $out, true ) ) {
				$out[] = $int;
			}
		}

		return $out;
	}

	/**
	 * Converts a rule value into a list of lowercase slugs.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value (csv string or array).
	 * @return string[]
	 */
	public static function string_list( $value ) {
		$parts = is_array( $value ) ? $value : preg_split( '/[\s,]+/', (string) $value );
		$parts = is_array( $parts ) ? $parts : array();
		$out   = array();

		foreach ( $parts as $part ) {
			if ( ! is_scalar( $part ) ) {
				continue;
			}

			$part = trim( strtolower( (string) $part ) );

			if ( '' !== $part && ! in_array( $part, $out, true ) ) {
				$out[] = $part;
			}
		}

		return $out;
	}
}
