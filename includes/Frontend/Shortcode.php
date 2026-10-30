<?php
/**
 * Layout shortcode.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Frontend;

use QevoraLayouts\Core\Layout;
use QevoraLayouts\Core\Plugin;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `[qevora_layout]` renders any layout anywhere shortcodes run.
 *
 * Attributes:
 * - `id`      layout ID (required unless `type` is given),
 * - `type`    renders the best matching layout of that type for the current
 *             request, ignoring its placement setting,
 * - `wrapper` `false` to print the content without the wrapper element.
 *
 * @since 1.0.0
 */
class Shortcode {

	/**
	 * Shortcode tag.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const TAG = 'qevora_layout';

	/**
	 * Registers the shortcode.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
	}

	/**
	 * Shortcode callback.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $atts    Shortcode attributes.
	 * @param string $content Enclosed content (unused).
	 * @param string $tag     Shortcode tag.
	 * @return string
	 */
	public static function render( $atts, $content = '', $tag = '' ) {
		unset( $content, $tag );

		$atts = shortcode_atts(
			array(
				'id'      => 0,
				'type'    => '',
				'wrapper' => 'true',
			),
			is_array( $atts ) ? $atts : array(),
			self::TAG
		);

		$use_wrapper = ! in_array( strtolower( (string) $atts['wrapper'] ), array( 'false', '0', 'no', 'off' ), true );
		$id          = (int) $atts['id'];

		if ( $id <= 0 && '' !== trim( (string) $atts['type'] ) ) {
			$layout = Plugin::instance()->repository()->get_active_layout( sanitize_key( (string) $atts['type'] ) );

			if ( $layout instanceof Layout ) {
				$id = $layout->id;
			}
		}

		if ( $id <= 0 ) {
			return self::message( __( 'No layout found for this shortcode.', 'qevora-layouts' ) );
		}

		$result = Plugin::instance()->frontend()->renderer()->render_by_id(
			$id,
			array(
				'wrapper' => $use_wrapper,
				'slot'    => 'shortcode:' . $id,
			)
		);

		if ( is_wp_error( $result ) ) {
			return self::message( $result );
		}

		return (string) $result;
	}

	/**
	 * Builds the message shown when a layout cannot be rendered.
	 *
	 * Editors see the reason, visitors see nothing.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Error|string $error Error or message.
	 * @return string
	 */
	private static function message( $error ) {
		if ( ! current_user_can( 'edit_qevora_layouts' ) ) {
			return '';
		}

		$text = $error instanceof WP_Error ? $error->get_error_message() : (string) $error;

		return '<p class="qevora-layouts-notice"><strong>' . esc_html__( 'Qevora Layouts:', 'qevora-layouts' ) . '</strong> ' . esc_html( $text ) . '</p>';
	}
}
