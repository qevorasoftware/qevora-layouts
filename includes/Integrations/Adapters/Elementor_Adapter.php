<?php
/**
 * Elementor adapter.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Integrations\Adapters;

use QevoraLayouts\Core\Layout;
use QevoraLayouts\Core\Layout_Post_Type;
use QevoraLayouts\Core\Logger;
use QevoraLayouts\Core\Settings;
use QevoraLayouts\Integrations\Abstract_Builder_Adapter;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders layouts built with Elementor and lets Elementor edit them.
 *
 * Everything this adapter does goes through Elementor's public API:
 *
 * - `add_post_type_support( 'qevora_layout', 'elementor' )` — the same flag
 *   Elementor itself reads (`Elementor\Utils::is_post_type_support()`) to
 *   decide whether a post type can be edited with Elementor.
 * - `Elementor\Plugin::$instance->documents->get( $post_id )` and
 *   `Document::is_built_with_elementor()` — the supported way to detect
 *   Elementor content (`Elementor\DB::is_built_with_elementor()` is deprecated
 *   since Elementor 3.2.0).
 * - `Elementor\Plugin::$instance->frontend->get_builder_content_for_display()`
 *   — the documented method for printing builder content outside of the
 *   Elementor document itself. It enqueues the document CSS and flags the page
 *   so Elementor's own `wp_footer()` handler loads the frontend assets.
 * - `Elementor\Plugin::$instance->frontend->enqueue_styles()` — called early
 *   when a layout is known to render, so Elementor styles are not deferred to
 *   the footer. The method is idempotent (Elementor guards it internally).
 *
 * The adapter never writes `_elementor_data` or any other Elementor meta
 * directly. If Elementor is missing, deactivated or older than the supported
 * version, the adapter reports itself as unavailable and the block adapter
 * renders the stored post content instead.
 *
 * @since 1.0.0
 */
class Elementor_Adapter extends Abstract_Builder_Adapter {

	/**
	 * Minimum Elementor version this adapter was written against.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const MIN_VERSION = '3.0.0';

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function get_id() {
		return 'elementor';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function get_name() {
		return __( 'Elementor', 'qevora-layouts' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function is_available() {
		if ( ! Settings::is_enabled( 'builder_elementor' ) ) {
			return false;
		}

		if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}

		$version = $this->get_version();

		if ( '' !== $version && version_compare( $version, self::MIN_VERSION, '<' ) ) {
			return false;
		}

		return $this->has_plugin_instance();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function get_version() {
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			return (string) ELEMENTOR_VERSION;
		}

		return '';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function get_requirements() {
		return array(
			'elementor/elementor.php' => sprintf(
				/* translators: %s: minimum supported Elementor version. */
				__( 'Elementor %s or newer', 'qevora-layouts' ),
				self::MIN_VERSION
			),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function supports_editing() {
		return $this->is_available() && Settings::is_enabled( 'elementor_editor_support' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function supports( Layout $layout ) {
		if ( ! $this->is_available() ) {
			return false;
		}

		if ( 'block' === $layout->builder ) {
			return false;
		}

		$post_id = $layout->get_content_post_id();

		if ( $post_id <= 0 ) {
			return false;
		}

		if ( 'elementor' === $layout->builder ) {
			return true;
		}

		return $this->is_built_with_elementor( $post_id );
	}

	/**
	 * Registers hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'maybe_add_editor_support' ), 30 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ), 20 );
	}

	/**
	 * Makes the layout post type editable with Elementor.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function maybe_add_editor_support() {
		if ( ! $this->supports_editing() ) {
			return;
		}

		if ( function_exists( 'add_post_type_support' ) ) {
			add_post_type_support( Layout_Post_Type::POST_TYPE, 'elementor' );
		}
	}

	/**
	 * Loads Elementor frontend assets before `wp_head` when a layout needs them.
	 *
	 * Elementor only enqueues its frontend stylesheet when the current document
	 * is built with Elementor. A header layout rendered on a page that is not
	 * built with Elementor would otherwise get its styles printed in the footer
	 * by Elementor's own `wp_footer()` fallback.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function maybe_enqueue_assets() {
		if ( ! $this->is_available() ) {
			return;
		}

		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}

		$needed = false;

		foreach ( array( 'header', 'footer' ) as $type ) {
			$layout = qevora_layouts()->repository()->get_active_layout( $type );

			if ( $layout instanceof Layout && $this->supports( $layout ) ) {
				$needed = true;
				break;
			}
		}

		if ( ! $needed ) {
			return;
		}

		$this->guard(
			static function () {
				$frontend = self::get_frontend();

				if ( null !== $frontend && method_exists( $frontend, 'enqueue_styles' ) ) {
					$frontend->enqueue_styles();
				}

				return '';
			},
			'early-assets'
		);

		Logger::debug( 'Loaded Elementor frontend assets early for a Qevora layout.' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	public function render( $post_id ) {
		$post_id = (int) $post_id;

		if ( ! $this->is_available() ) {
			return '';
		}

		return $this->guard(
			function () use ( $post_id ) {
				$frontend = self::get_frontend();

				if ( null === $frontend || ! method_exists( $frontend, 'get_builder_content_for_display' ) ) {
					return '';
				}

				if ( ! $this->is_built_with_elementor( $post_id ) ) {
					return '';
				}

				$content = $frontend->get_builder_content_for_display( $post_id );

				return is_string( $content ) ? $content : '';
			},
			'render:' . $post_id
		);
	}

	/**
	 * Whether a post was built with Elementor.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function is_built_with_elementor( $post_id ) {
		$post_id = (int) $post_id;

		if ( $post_id <= 0 || ! $this->is_available() ) {
			return false;
		}

		$document = $this->get_document( $post_id );

		if ( null !== $document && method_exists( $document, 'is_built_with_elementor' ) ) {
			return (bool) $this->guard_value(
				static function () use ( $document ) {
					return $document->is_built_with_elementor();
				},
				'is_built_with_elementor'
			);
		}

		// Read-only fallback for unusual Elementor states. No Elementor meta is
		// ever written by this plugin.
		$data = get_post_meta( $post_id, '_elementor_data', true );

		return is_string( $data ) && '' !== trim( $data );
	}

	/**
	 * Returns the Elementor document for a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return object|null
	 */
	private function get_document( $post_id ) {
		$plugin = self::get_plugin_instance();

		if ( null === $plugin || ! isset( $plugin->documents ) || ! is_object( $plugin->documents ) ) {
			return null;
		}

		if ( ! method_exists( $plugin->documents, 'get' ) ) {
			return null;
		}

		$document = $this->guard_value(
			static function () use ( $plugin, $post_id ) {
				return $plugin->documents->get( $post_id );
			},
			'documents->get:' . $post_id
		);

		return is_object( $document ) ? $document : null;
	}

	/**
	 * Returns the Elementor frontend instance.
	 *
	 * @since 1.0.0
	 *
	 * @return object|null
	 */
	private static function get_frontend() {
		$plugin = self::get_plugin_instance();

		if ( null === $plugin || ! isset( $plugin->frontend ) ) {
			return null;
		}

		return is_object( $plugin->frontend ) ? $plugin->frontend : null;
	}

	/**
	 * Returns the Elementor plugin singleton.
	 *
	 * @since 1.0.0
	 *
	 * @return object|null
	 */
	private static function get_plugin_instance() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return null;
		}

		$class    = '\Elementor\Plugin';
		$instance = isset( $class::$instance ) ? $class::$instance : null;

		return is_object( $instance ) ? $instance : null;
	}

	/**
	 * Whether the Elementor singleton is usable.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	private function has_plugin_instance() {
		return null !== self::get_plugin_instance();
	}
}
