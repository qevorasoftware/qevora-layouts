<?php
/**
 * Condition storage sanitizer.
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
 * Normalizes and sanitizes stored display conditions.
 *
 * The sanitizer is intentionally free of database access: it validates the
 * shape and the character set of every rule so the same code can run for admin
 * form input, REST input, imported JSON and unit tests.
 *
 * @since 1.0.0
 */
class Condition_Sanitizer {

	/**
	 * Maximum number of rules per include/exclude list.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	const MAX_RULES = 100;

	/**
	 * Maximum size of a JSON encoded condition set.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	const MAX_JSON_BYTES = 65536;

	/**
	 * Registered sanitize callback for the conditions meta key.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $value          Raw value.
	 * @param string $meta_key       Meta key (unused).
	 * @param string $meta_type      Meta type (unused).
	 * @param string $object_subtype Object subtype (unused).
	 * @return array<string,array<int,array<string,string>>>
	 */
	public static function sanitize( $value, $meta_key = '', $meta_type = '', $object_subtype = '' ) {
		unset( $meta_key, $meta_type, $object_subtype );

		return self::normalize( $value );
	}

	/**
	 * Normalizes any input into the canonical conditions structure.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw conditions.
	 * @return array<string,array<int,array<string,string>>>
	 */
	public static function normalize( $value ) {
		$normalized = array(
			'include' => array(),
			'exclude' => array(),
		);

		if ( is_string( $value ) ) {
			// Meta normally holds an array; a JSON string can only come from a
			// manual database edit or another plugin. Decoding it keeps such a
			// layout from silently widening to the entire site.
			$decoded = self::decode_string( $value );

			if ( ! is_array( $decoded ) ) {
				Logger::warning(
					'Ignored display conditions that are not an array.',
					array( 'type' => gettype( $value ) )
				);

				return $normalized;
			}

			$value = $decoded;
		}

		if ( ! is_array( $value ) ) {
			if ( ! empty( $value ) ) {
				Logger::warning(
					'Ignored display conditions of an unexpected type.',
					array( 'type' => gettype( $value ) )
				);
			}

			return $normalized;
		}

		// Tolerate a flat list of rules (no include/exclude keys).
		if ( ! isset( $value['include'] ) && ! isset( $value['exclude'] ) && ! empty( $value ) ) {
			$value = array( 'include' => $value );
		}

		foreach ( array( 'include', 'exclude' ) as $list ) {
			if ( empty( $value[ $list ] ) || ! is_array( $value[ $list ] ) ) {
				continue;
			}

			$rules = array();

			foreach ( $value[ $list ] as $raw_rule ) {
				$rule = self::normalize_rule( $raw_rule );

				if ( null === $rule ) {
					continue;
				}

				if ( ! in_array( $rule, $rules, true ) ) {
					$rules[] = $rule;
				}

				if ( count( $rules ) >= self::MAX_RULES ) {
					break;
				}
			}

			$normalized[ $list ] = $rules;
		}

		return $normalized;
	}

	/**
	 * Decodes a stored JSON condition set.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value Raw value.
	 * @return array<string,mixed>|null Null when the value is not usable JSON.
	 */
	private static function decode_string( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value || strlen( $value ) > self::MAX_JSON_BYTES ) {
			return null;
		}

		$first = substr( $value, 0, 1 );

		if ( '{' !== $first && '[' !== $first ) {
			return null;
		}

		$decoded = json_decode( $value, true, 32 );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Normalizes one rule.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $raw_rule Raw rule.
	 * @return array<string,string>|null Null when the rule is unusable.
	 */
	public static function normalize_rule( $raw_rule ) {
		if ( is_string( $raw_rule ) ) {
			$raw_rule = array( 'type' => $raw_rule );
		}

		if ( ! is_array( $raw_rule ) ) {
			return null;
		}

		$type = isset( $raw_rule['type'] ) ? $raw_rule['type'] : '';

		if ( ! is_string( $type ) || ! Condition_Registry::exists( $type ) ) {
			Logger::debug( 'Dropped unknown display condition.', array( 'type' => is_scalar( $type ) ? (string) $type : gettype( $type ) ) );

			return null;
		}

		$rule       = array( 'type' => $type );
		$value_type = Condition_Registry::value_type( $type );

		if ( 'none' === $value_type ) {
			return $rule;
		}

		$raw_value = isset( $raw_rule['value'] ) ? $raw_rule['value'] : '';
		$value     = self::sanitize_value( $raw_value, $value_type );

		if ( '' !== $value ) {
			$rule['value'] = $value;
		}

		if ( 'taxonomy' === $type && isset( $raw_rule['terms'] ) ) {
			$terms = self::sanitize_value( $raw_rule['terms'], 'ids' );

			if ( '' !== $terms ) {
				$rule['terms'] = $terms;
			}
		}

		// A rule that needs a value but does not have one cannot match.
		if ( ! isset( $rule['value'] ) && ! isset( $rule['terms'] ) ) {
			return null;
		}

		return $rule;
	}

	/**
	 * Sanitizes a rule value according to its declared value type.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $value      Raw value.
	 * @param string $value_type One of post_type|ids|taxonomy|term_ids|roles.
	 * @return string Normalized value (csv for lists) or empty string.
	 */
	public static function sanitize_value( $value, $value_type ) {
		switch ( $value_type ) {
			case 'ids':
			case 'term_ids':
				return implode( ',', Condition_Matcher::int_list( $value ) );

			case 'roles':
				$roles = Condition_Matcher::string_list( $value );
				$known = self::known_roles();

				$roles = array_filter(
					$roles,
					static function ( $role ) use ( $known ) {
						if ( 1 !== preg_match( '/^[a-z0-9_\-]{1,60}$/', $role ) ) {
							return false;
						}

						// Without a role list (very early bootstrap) the pattern
						// check is the only guard available.
						return empty( $known ) || in_array( $role, $known, true );
					}
				);

				return implode( ',', array_slice( $roles, 0, 20 ) );

			case 'post_type':
			case 'taxonomy':
				return self::sanitize_slug( $value );

			default:
				return '';
		}
	}

	/**
	 * Returns the role slugs registered on this site.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Empty when roles are not available yet.
	 */
	private static function known_roles() {
		if ( ! function_exists( 'wp_roles' ) ) {
			return array();
		}

		$roles = wp_roles();

		if ( ! isset( $roles->roles ) || ! is_array( $roles->roles ) ) {
			return array();
		}

		return array_map( 'strval', array_keys( $roles->roles ) );
	}

	/**
	 * Sanitizes a post type or taxonomy slug.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw slug.
	 * @return string
	 */
	public static function sanitize_slug( $value ) {
		if ( is_array( $value ) ) {
			$value = isset( $value[0] ) ? $value[0] : '';
		}

		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$value = trim( (string) $value );

		if ( 1 !== preg_match( '/^[a-z0-9_\-]{1,60}$/i', $value ) ) {
			return '';
		}

		return strtolower( $value );
	}

	/**
	 * Whether a stored condition set is empty (matches the entire site).
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $conditions Stored conditions.
	 * @return bool
	 */
	public static function is_entire_site( $conditions ) {
		$conditions = self::normalize( $conditions );

		return empty( $conditions['include'] ) && empty( $conditions['exclude'] );
	}

	/**
	 * Builds a short human readable summary for admin list columns.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $conditions Stored conditions.
	 * @param int   $limit      Maximum number of rules to name.
	 * @return string
	 */
	public static function summarize( $conditions, $limit = 3 ) {
		$conditions = self::normalize( $conditions );
		$parts      = array();

		if ( empty( $conditions['include'] ) ) {
			$parts[] = __( 'Entire site', 'qevora-layouts' );
		} else {
			$labels = array();

			foreach ( $conditions['include'] as $rule ) {
				$labels[] = Condition_Registry::label( $rule['type'] );

				if ( count( $labels ) >= $limit ) {
					break;
				}
			}

			$parts[] = implode( ', ', $labels );

			if ( count( $conditions['include'] ) > $limit ) {
				/* translators: %d: number of additional conditions. */
				$parts[] = sprintf( _n( '+%d more', '+%d more', count( $conditions['include'] ) - $limit, 'qevora-layouts' ), count( $conditions['include'] ) - $limit );
			}
		}

		if ( ! empty( $conditions['exclude'] ) ) {
			/* translators: %d: number of excluded conditions. */
			$parts[] = sprintf( _n( '%d exclusion', '%d exclusions', count( $conditions['exclude'] ), 'qevora-layouts' ), count( $conditions['exclude'] ) );
		}

		return implode( ' · ', $parts );
	}
}
