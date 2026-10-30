<?php
/**
 * Duplicate output guard.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Frontend;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Makes sure one slot renders at most once per request.
 *
 * A "slot" is `header`, `footer` or `section:{id}`. The guard is the reason a
 * theme that calls `wp_body_open()` twice, or a layout that matches several
 * placement hooks, can never produce two headers.
 *
 * @since 1.0.0
 */
class Duplicate_Guard {

	/**
	 * Slots that have already been rendered.
	 *
	 * @since 1.0.0
	 * @var   array<string,int>
	 */
	private static $rendered = array();

	/**
	 * Whether a slot may still render.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slot Slot name.
	 * @return bool
	 */
	public static function should_render( $slot ) {
		$slot = self::normalize( $slot );

		if ( isset( self::$rendered[ $slot ] ) ) {
			return false;
		}

		/**
		 * Filters whether a slot may render.
		 *
		 * Themes and other plugins can return false to keep their own markup,
		 * for example on pages where they already print a header.
		 *
		 * @since 1.0.0
		 *
		 * @param bool   $allowed Whether rendering is allowed.
		 * @param string $slot    Slot name.
		 */
		return (bool) apply_filters( 'qevora_layouts_should_render', true, $slot );
	}

	/**
	 * Marks a slot as rendered.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slot     Slot name.
	 * @param int    $layout_id Layout that rendered.
	 * @return void
	 */
	public static function mark_rendered( $slot, $layout_id = 0 ) {
		self::$rendered[ self::normalize( $slot ) ] = (int) $layout_id;

		/**
		 * Fires after a layout slot has been rendered.
		 *
		 * @since 1.0.0
		 *
		 * @param string $slot      Slot name.
		 * @param int    $layout_id Layout ID.
		 */
		do_action( 'qevora_layouts_slot_rendered', self::normalize( $slot ), (int) $layout_id );
	}

	/**
	 * Whether a slot already rendered.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slot Slot name.
	 * @return bool
	 */
	public static function has_rendered( $slot ) {
		return isset( self::$rendered[ self::normalize( $slot ) ] );
	}

	/**
	 * Returns the layout ID that rendered a slot, 0 when none did.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slot Slot name.
	 * @return int
	 */
	public static function rendered_layout_id( $slot ) {
		$slot = self::normalize( $slot );

		return isset( self::$rendered[ $slot ] ) ? (int) self::$rendered[ $slot ] : 0;
	}

	/**
	 * Resets the guard. Used by tests and previews.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function reset() {
		self::$rendered = array();
	}

	/**
	 * Normalizes a slot name.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $slot Raw slot.
	 * @return string
	 */
	private static function normalize( $slot ) {
		$slot = is_scalar( $slot ) ? strtolower( (string) $slot ) : '';
		$slot = preg_replace( '/[^a-z0-9_:\-]/', '', $slot );

		return '' === $slot ? 'unknown' : $slot;
	}
}
