<?php
/**
 * Builder adapter contract.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Integrations;

use QevoraLayouts\Core\Layout;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract every page builder adapter must fulfil.
 *
 * An adapter is the only place allowed to talk to a third party builder. It
 * must use that builder's public API, must never write to the builder's
 * internal storage and must degrade gracefully when the builder is missing,
 * deactivated or older than the supported version.
 *
 * @since 1.0.0
 */
interface Builder_Adapter_Interface {

	/**
	 * Unique adapter identifier.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Human readable adapter name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Whether the builder is installed, active and usable right now.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_available();

	/**
	 * Registers the adapter's hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register();

	/**
	 * Whether this adapter can render the given layout.
	 *
	 * @since 1.0.0
	 *
	 * @param Layout $layout Layout.
	 * @return bool
	 */
	public function supports( Layout $layout );

	/**
	 * Renders a post's builder content and returns the markup.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID holding the content.
	 * @return string Markup, or an empty string when nothing can be rendered.
	 */
	public function render( $post_id );

	/**
	 * Whether layouts can be *edited* with this builder.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function supports_editing();

	/**
	 * Detected builder version, empty when unknown.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_version();

	/**
	 * Plugins this adapter needs, as `plugin-basename => label` pairs.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,string>
	 */
	public function get_requirements();
}
