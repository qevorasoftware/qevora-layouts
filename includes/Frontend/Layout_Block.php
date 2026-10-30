<?php
/**
 * Layout block.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Frontend;

use QevoraLayouts\Core\Capabilities;
use QevoraLayouts\Core\Plugin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the `qevora/layout` dynamic block.
 *
 * The block is a thin picker around the same rendering path the shortcode uses,
 * so layouts inserted with the block behave identically everywhere. The editor
 * script is plain JavaScript built on `wp.blocks`, `wp.element` and
 * `wp.data` - no build step and no third party package.
 *
 * @since 1.0.0
 */
class Layout_Block {

	/**
	 * Block name.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const NAME = 'qevora/layout';

	/**
	 * Editor script handle.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const SCRIPT_HANDLE = 'qevora-layouts-block';

	/**
	 * Whether registration already happened.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	private static $registered = false;

	/**
	 * Registers the block and its editor assets.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register() {
		if ( self::$registered ) {
			return;
		}

		self::$registered = true;

		add_action( 'init', array( __CLASS__, 'register_block' ), 25 );
	}

	/**
	 * Registers the block type. Runs on `init`.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			self::SCRIPT_HANDLE,
			QEVORA_LAYOUTS_URL . 'assets/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-server-side-render', 'wp-api-fetch' ),
			QEVORA_LAYOUTS_VERSION,
			true
		);

		wp_set_script_translations( self::SCRIPT_HANDLE, 'qevora-layouts', QEVORA_LAYOUTS_DIR . 'languages' );

		register_block_type(
			self::NAME,
			array(
				'api_version'     => 3,
				'editor_script'   => self::SCRIPT_HANDLE,
				'render_callback' => array( __CLASS__, 'render' ),
				'attributes'      => array(
					'layoutId' => array(
						'type'    => 'number',
						'default' => 0,
					),
					'wrapper'  => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'align'    => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'supports'        => array(
					'html'   => false,
					'anchor' => true,
					'align'  => array( 'wide', 'full' ),
				),
			)
		);
	}

	/**
	 * Block render callback.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $attributes Block attributes.
	 * @return string
	 */
	public static function render( $attributes ) {
		$attributes = is_array( $attributes ) ? $attributes : array();

		$layout_id = isset( $attributes['layoutId'] ) ? (int) $attributes['layoutId'] : 0;

		if ( $layout_id <= 0 ) {
			return Capabilities::can_edit_layouts()
				? '<p class="qevora-layouts-notice">' . esc_html__( 'Qevora Layouts: select a layout in the block settings.', 'qevora-layouts' ) . '</p>'
				: '';
		}

		$result = Plugin::instance()->frontend()->renderer()->render_by_id(
			$layout_id,
			array(
				'wrapper' => empty( $attributes['wrapper'] ) ? false : true,
				'slot'    => 'block:' . $layout_id,
			)
		);

		if ( is_wp_error( $result ) ) {
			return Capabilities::can_edit_layouts()
				? '<p class="qevora-layouts-notice">' . esc_html( $result->get_error_message() ) . '</p>'
				: '';
		}

		return (string) $result;
	}
}
