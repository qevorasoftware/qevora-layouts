<?php
/**
 * Block editor (Gutenberg) adapter.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Integrations\Adapters;

use QevoraLayouts\Core\Layout;
use QevoraLayouts\Core\Layout_Post_Type;
use QevoraLayouts\Core\Settings;
use QevoraLayouts\Integrations\Abstract_Builder_Adapter;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders layout content with WordPress core APIs only.
 *
 * Content is passed through `the_content`, which runs the block parser
 * (`do_blocks`), shortcodes, embeds and paragraph formatting exactly like a
 * normal post. No third party plugin is required.
 *
 * @since 1.0.0
 */
class Block_Adapter extends Abstract_Builder_Adapter {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function get_id() {
		return 'block';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function get_name() {
		return __( 'Block editor (Gutenberg)', 'qevora-layouts' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function is_available() {
		return (bool) Settings::is_enabled( 'builder_block' ) && function_exists( 'do_blocks' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function supports( Layout $layout ) {
		unset( $layout );

		// The block adapter renders any post content, so it is always the
		// fallback adapter, even for layouts built with another builder that
		// has since been deactivated.
		return $this->is_available();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function supports_editing() {
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function get_version() {
		global $wp_version;

		return isset( $wp_version ) ? (string) $wp_version : '';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function render( $post_id ) {
		$post_id = (int) $post_id;

		return $this->guard(
			function () use ( $post_id ) {
				return $this->render_content( $post_id );
			},
			'render:' . $post_id
		);
	}

	/**
	 * Renders post content through `the_content` with a safe global state.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function render_content( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return '';
		}

		$content = (string) $post->post_content;

		if ( '' === trim( $content ) ) {
			return '';
		}

		/**
		 * Filters the raw layout content before WordPress renders it.
		 *
		 * @since 1.0.0
		 *
		 * @param string  $content Raw post content.
		 * @param int     $post_id Post ID.
		 * @param \WP_Post $post   Post object.
		 */
		$content = apply_filters( 'qevora_layouts_pre_render_content', $content, $post_id, $post );

		if ( '' === trim( (string) $content ) ) {
			return '';
		}

		$saved = $this->capture_globals();

		// Blocks such as post title or query loops read the global $post.
		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restored below.
		setup_postdata( $post );

		$rendered = apply_filters( 'the_content', $content );

		wp_reset_postdata();
		$this->restore_globals( $saved );

		return is_string( $rendered ) ? $rendered : '';
	}

	/**
	 * Captures the loop globals that `setup_postdata()` may overwrite.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,mixed>
	 */
	private function capture_globals() {
		$keys = array( 'post', 'more', 'page', 'pages', 'multipage' );
		$saved = array();

		foreach ( $keys as $key ) {
			$saved[ $key ] = array_key_exists( $key, $GLOBALS ) ? $GLOBALS[ $key ] : null;
		}

		return $saved;
	}

	/**
	 * Restores loop globals.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $saved Values captured by {@see capture_globals()}.
	 * @return void
	 */
	private function restore_globals( array $saved ) {
		foreach ( $saved as $key => $value ) {
			if ( null === $value ) {
				unset( $GLOBALS[ $key ] );
				continue;
			}

			$GLOBALS[ $key ] = $value; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring the previous value.
		}
	}

	/**
	 * Whether a post holds parseable block markup.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function has_block_content( $post_id ) {
		$post = get_post( (int) $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		return false !== strpos( (string) $post->post_content, '<!-- wp:' );
	}

	/**
	 * Registers the layout post type as a block editor post type.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function ensure_editor_support() {
		add_post_type_support( Layout_Post_Type::POST_TYPE, 'editor' );
	}
}
