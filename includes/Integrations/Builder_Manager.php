<?php
/**
 * Builder adapter registry.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Integrations;

use QevoraLayouts\Core\Layout;
use QevoraLayouts\Core\Logger;
use QevoraLayouts\Integrations\Adapters\Block_Adapter;
use QevoraLayouts\Integrations\Adapters\Elementor_Adapter;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects installed builders and resolves the adapter that renders a layout.
 *
 * The manager is the only place that knows which builders exist. Adding support
 * for another builder means registering one adapter class - no other part of
 * the plugin has to change.
 *
 * @since 1.0.0
 */
class Builder_Manager {

	/**
	 * Registered adapters.
	 *
	 * @since 1.0.0
	 * @var   array<string,Builder_Adapter_Interface>
	 */
	private $adapters = array();

	/**
	 * Whether {@see register()} has run.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	private $registered = false;

	/**
	 * Creates the manager with the built-in adapters.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$adapters = array(
			new Block_Adapter(),
			new Elementor_Adapter(),
		);

		/**
		 * Filters the registered builder adapters.
		 *
		 * An adapter must implement {@see Builder_Adapter_Interface}. Adapters
		 * are consulted in order, so a more specific adapter should be added
		 * before the generic block adapter.
		 *
		 * @since 1.0.0
		 *
		 * @param Builder_Adapter_Interface[] $adapters Adapters.
		 * @param self                        $manager  Builder manager.
		 */
		$adapters = apply_filters( 'qevora_layouts_builder_adapters', $adapters, $this );

		foreach ( (array) $adapters as $adapter ) {
			$this->add( $adapter );
		}
	}

	/**
	 * Registers an adapter.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $adapter Adapter instance.
	 * @return bool True when accepted.
	 */
	public function add( $adapter ) {
		if ( ! $adapter instanceof Builder_Adapter_Interface ) {
			Logger::warning( 'Ignored an invalid builder adapter.' );

			return false;
		}

		$this->adapters[ $adapter->get_id() ] = $adapter;

		return true;
	}

	/**
	 * Registers adapter hooks. Runs on `init`.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register() {
		if ( $this->registered ) {
			return;
		}

		$this->registered = true;

		foreach ( $this->adapters as $adapter ) {
			$adapter->register();
		}

		/**
		 * Fires after builder adapters have registered their hooks.
		 *
		 * @since 1.0.0
		 *
		 * @param self $manager Builder manager.
		 */
		do_action( 'qevora_layouts_builders_registered', $this );
	}

	/**
	 * Returns every registered adapter.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,Builder_Adapter_Interface>
	 */
	public function all() {
		return $this->adapters;
	}

	/**
	 * Returns one adapter.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id Adapter identifier.
	 * @return Builder_Adapter_Interface|null
	 */
	public function get( $id ) {
		$id = is_string( $id ) ? $id : '';

		return isset( $this->adapters[ $id ] ) ? $this->adapters[ $id ] : null;
	}

	/**
	 * Returns the identifiers of the adapters that are currently usable.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public function available() {
		$available = array();

		foreach ( $this->adapters as $id => $adapter ) {
			if ( $adapter->is_available() ) {
				$available[] = $id;
			}
		}

		return $available;
	}

	/**
	 * Picks the adapter that renders a layout.
	 *
	 * Order of preference:
	 * 1. an adapter matching the layout's explicit builder hint,
	 * 2. any available adapter that reports support for the layout
	 *    (Elementor content is detected through Elementor's own API),
	 * 3. the block adapter as the universal fallback.
	 *
	 * @since 1.0.0
	 *
	 * @param Layout $layout Layout.
	 * @return Builder_Adapter_Interface|null
	 */
	public function resolve( Layout $layout ) {
		$hint = $layout->builder;

		if ( 'auto' !== $hint && isset( $this->adapters[ $hint ] ) ) {
			$adapter = $this->adapters[ $hint ];

			if ( $adapter->is_available() ) {
				return $adapter;
			}
		}

		foreach ( $this->adapters as $adapter ) {
			if ( ! $adapter->is_available() ) {
				continue;
			}

			if ( $adapter->supports( $layout ) ) {
				return $adapter;
			}
		}

		$fallback = $this->get( 'block' );

		if ( $fallback instanceof Builder_Adapter_Interface && $fallback->is_available() ) {
			return $fallback;
		}

		return null;
	}

	/**
	 * Renders a layout with the resolved adapter.
	 *
	 * When the preferred builder is unavailable the block adapter renders the
	 * stored post content so the site keeps showing something sane.
	 *
	 * @since 1.0.0
	 *
	 * @param Layout $layout Layout.
	 * @return string Markup, possibly empty.
	 */
	public function render( Layout $layout ) {
		$post_id = $layout->get_content_post_id();

		if ( $post_id <= 0 ) {
			Logger::warning( 'Layout has no renderable content.', array( 'layout_id' => $layout->id ) );

			return '';
		}

		$adapter = $this->resolve( $layout );

		if ( null === $adapter ) {
			Logger::error( 'No builder adapter available.', array( 'layout_id' => $layout->id ) );

			return '';
		}

		$content = $adapter->render( $post_id );

		// Elementor was requested but could not render (deactivated, content
		// deleted, old version): fall back to core rendering.
		if ( '' === trim( (string) $content ) && 'block' !== $adapter->get_id() ) {
			$fallback = $this->get( 'block' );

			if ( $fallback instanceof Builder_Adapter_Interface && $fallback->is_available() ) {
				$content = $fallback->render( $post_id );

				Logger::debug(
					'Fell back to the block adapter.',
					array(
						'layout_id' => $layout->id,
						'from'      => $adapter->get_id(),
					)
				);
			}
		}

		/**
		 * Filters the rendered markup of a layout.
		 *
		 * @since 1.0.0
		 *
		 * @param string                       $content Rendered markup.
		 * @param Layout                       $layout  Layout.
		 * @param Builder_Adapter_Interface    $adapter Adapter that rendered it.
		 */
		return apply_filters( 'qevora_layouts_rendered_content', (string) $content, $layout, $adapter );
	}

	/**
	 * Returns a compatibility report for the System Status screen.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_status() {
		$report = array();

		foreach ( $this->adapters as $adapter ) {
			$requirements = array();

			foreach ( $adapter->get_requirements() as $basename => $label ) {
				$requirements[] = array(
					'label'  => $label,
					'active' => self::is_plugin_active_by_basename( $basename ),
				);
			}

			$report[] = array(
				'id'           => $adapter->get_id(),
				'name'         => $adapter->get_name(),
				'available'    => $adapter->is_available(),
				'can_edit'     => $adapter->supports_editing(),
				'version'      => $adapter->get_version(),
				'requirements' => $requirements,
			);
		}

		return $report;
	}

	/**
	 * Whether a plugin is active, without requiring the admin context.
	 *
	 * @since 1.0.0
	 *
	 * @param string $basename Plugin basename, e.g. `elementor/elementor.php`.
	 * @return bool
	 */
	public static function is_plugin_active_by_basename( $basename ) {
		if ( ! is_string( $basename ) || '' === $basename ) {
			return false;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			$file = ABSPATH . 'wp-admin/includes/plugin.php';

			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			return false;
		}

		return (bool) is_plugin_active( $basename );
	}
}
