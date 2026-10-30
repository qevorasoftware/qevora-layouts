<?php
/**
 * Main plugin container.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Core;

use QevoraLayouts\Admin\Admin;
use QevoraLayouts\Frontend\Frontend;
use QevoraLayouts\Import\Importer;
use QevoraLayouts\Import\Template_Library;
use QevoraLayouts\Integrations\Builder_Manager;
use QevoraLayouts\REST\Rest_Controller;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstraps every subsystem and exposes the shared services.
 *
 * @since 1.0.0
 */
final class Plugin {

	/**
	 * Single instance.
	 *
	 * @since 1.0.0
	 * @var   self|null
	 */
	private static $instance = null;

	/**
	 * Hook collector.
	 *
	 * @since 1.0.0
	 * @var   Loader
	 */
	private $loader;

	/**
	 * Layout storage.
	 *
	 * @since 1.0.0
	 * @var   Layout_Repository|null
	 */
	private $repository = null;

	/**
	 * Builder adapters.
	 *
	 * @since 1.0.0
	 * @var   Builder_Manager|null
	 */
	private $builders = null;

	/**
	 * Template library.
	 *
	 * @since 1.0.0
	 * @var   Template_Library|null
	 */
	private $library = null;

	/**
	 * Importer.
	 *
	 * @since 1.0.0
	 * @var   Importer|null
	 */
	private $importer = null;

	/**
	 * Frontend subsystem.
	 *
	 * @since 1.0.0
	 * @var   Frontend|null
	 */
	private $frontend = null;

	/**
	 * Admin subsystem.
	 *
	 * @since 1.0.0
	 * @var   Admin|null
	 */
	private $admin = null;

	/**
	 * Whether boot() has run.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	private $booted = false;

	/**
	 * Private constructor. Use {@see Plugin::instance()}.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {}

	/**
	 * Returns (and boots) the single plugin instance.
	 *
	 * @since 1.0.0
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}

		return self::$instance;
	}

	/**
	 * Whether the plugin has booted.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function is_booted() {
		return null !== self::$instance && self::$instance->booted;
	}

	/**
	 * Wires the plugin into WordPress.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;
		$this->loader = new Loader();

		require_once QEVORA_LAYOUTS_DIR . 'includes/Frontend/Template_Tags.php';

		$this->loader->add_action( 'init', array( I18n::class, 'load' ), 1 );
		$this->loader->add_action( 'init', array( Layout_Post_Type::class, 'register' ), 5 );
		$this->loader->add_action( 'init', array( $this, 'register_subsystems' ), 20 );
		$this->loader->add_action( 'rest_api_init', array( Rest_Controller::class, 'register_routes' ), 20 );

		$this->repository()->register_cache_invalidation();

		$this->loader->add_action( 'update_option_' . QEVORA_LAYOUTS_OPTION, array( Cache::class, 'bump_version' ), 20, 0 );

		if ( is_admin() ) {
			$this->loader->add_action( 'admin_init', array( $this, 'maybe_migrate' ), 1 );
		}

		/**
		 * Fires after the plugin has registered its core hooks.
		 *
		 * @since 1.0.0
		 *
		 * @param self   $plugin Plugin instance.
		 * @param Loader $loader Hook collector (not yet run).
		 */
		do_action( 'qevora_layouts_booted', $this, $this->loader );

		$this->loader->run();
	}

	/**
	 * Brings an existing installation up to the current plugin version.
	 *
	 * Grants the plugin capabilities when a site arrives from an older
	 * release (or from a build whose activation never completed) so an
	 * upgrade can never leave administrators without access.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function maybe_migrate() {
		$installed     = (string) get_option( Activator::VERSION_OPTION, '' );
		$administrator = get_role( 'administrator' );
		$caps_missing  = ! $administrator || ! $administrator->has_cap( Capabilities::EDIT_LAYOUTS );

		if ( ! $caps_missing && QEVORA_LAYOUTS_VERSION === $installed ) {
			return;
		}

		/*
		 * Self-healing: a site whose activation never completed (or whose
		 * roles were rebuilt) would otherwise lose access to every screen.
		 */
		if ( $caps_missing ) {
			Capabilities::grant();
		}

		if ( QEVORA_LAYOUTS_VERSION !== $installed ) {
			update_option( Activator::VERSION_OPTION, QEVORA_LAYOUTS_VERSION );

			/**
			 * Fires after the plugin has migrated an older installation.
			 *
			 * @since 1.0.0
			 *
			 * @param string $previous_version Version marker found, empty when missing.
			 */
			do_action( 'qevora_layouts_upgraded', $installed );
		}
	}

	/**
	 * Registers subsystems that depend on the post type existing.
	 *
	 * Runs on `init` at priority 20.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_subsystems() {
		$this->frontend()->register();
		$this->builders()->register();

		if ( is_admin() ) {
			$this->admin()->register();
		}

		/**
		 * Fires after all subsystems have been registered.
		 *
		 * @since 1.0.0
		 *
		 * @param self $plugin Plugin instance.
		 */
		do_action( 'qevora_layouts_loaded', $this );
	}

	/**
	 * Returns the hook collector.
	 *
	 * @since 1.0.0
	 *
	 * @return Loader
	 */
	public function loader() {
		return $this->loader;
	}

	/**
	 * Returns the layout repository.
	 *
	 * @since 1.0.0
	 *
	 * @return Layout_Repository
	 */
	public function repository() {
		if ( null === $this->repository ) {
			$this->repository = new Layout_Repository();
		}

		return $this->repository;
	}

	/**
	 * Returns the builder manager.
	 *
	 * @since 1.0.0
	 *
	 * @return Builder_Manager
	 */
	public function builders() {
		if ( null === $this->builders ) {
			$this->builders = new Builder_Manager();
		}

		return $this->builders;
	}

	/**
	 * Returns the template library.
	 *
	 * @since 1.0.0
	 *
	 * @return Template_Library
	 */
	public function library() {
		if ( null === $this->library ) {
			$this->library = new Template_Library( $this->builders() );
		}

		return $this->library;
	}

	/**
	 * Returns the importer.
	 *
	 * @since 1.0.0
	 *
	 * @return Importer
	 */
	public function importer() {
		if ( null === $this->importer ) {
			$this->importer = new Importer( $this->repository(), $this->library() );
		}

		return $this->importer;
	}

	/**
	 * Returns the frontend subsystem.
	 *
	 * @since 1.0.0
	 *
	 * @return Frontend
	 */
	public function frontend() {
		if ( null === $this->frontend ) {
			$this->frontend = new Frontend( $this );
		}

		return $this->frontend;
	}

	/**
	 * Returns the admin subsystem.
	 *
	 * @since 1.0.0
	 *
	 * @return Admin
	 */
	public function admin() {
		if ( null === $this->admin ) {
			$this->admin = new Admin( $this );
		}

		return $this->admin;
	}

	/**
	 * Prevents cloning.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning is not allowed.', 'qevora-layouts' ), '1.0.0' );
	}

	/**
	 * Prevents unserialization.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing is not allowed.', 'qevora-layouts' ), '1.0.0' );
	}
}
