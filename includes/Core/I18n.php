<?php
/**
 * Translations.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the plugin text domain.
 *
 * WordPress loads translations from `wp-content/languages/plugins/` first, so
 * the bundled `languages/` directory is only the fallback for translations
 * shipped with the plugin itself.
 *
 * @since 1.0.0
 */
class I18n {

	/**
	 * Text domain.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const TEXT_DOMAIN = 'qevora-layouts';

	/**
	 * Loads the text domain.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function load() {
		/**
		 * Filters the directory that holds the plugin translations.
		 *
		 * @since 1.0.0
		 *
		 * @param string $relative_dir Directory relative to the plugin root.
		 */
		$relative_dir = apply_filters( 'qevora_layouts_languages_dir', 'languages' );

		load_plugin_textdomain(
			self::TEXT_DOMAIN,
			false,
			trailingslashit( dirname( QEVORA_LAYOUTS_BASENAME ) ) . $relative_dir
		);
	}
}
