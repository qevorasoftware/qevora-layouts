<?php
/**
 * Frontend subsystem.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Frontend;

use QevoraLayouts\Core\Layout;
use QevoraLayouts\Core\Logger;
use QevoraLayouts\Core\Plugin;
use QevoraLayouts\Core\Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decides which layouts render on the current request and where.
 *
 * Resolution happens once, on `template_redirect`, after the main query is
 * ready. The result (the "plan") is then consumed by the placement hooks, by
 * the asset loader and by the builder adapters, so a request never resolves
 * conditions twice.
 *
 * @since 1.0.0
 */
class Frontend {

	/**
	 * Plugin instance.
	 *
	 * @since 1.0.0
	 * @var   Plugin
	 */
	private $plugin;

	/**
	 * Renderer.
	 *
	 * @since 1.0.0
	 * @var   Renderer|null
	 */
	private $renderer = null;

	/**
	 * Whether the plan has been resolved.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	private $resolved = false;

	/**
	 * Resolved layouts, keyed by type.
	 *
	 * @since 1.0.0
	 * @var   array<string,Layout|null>
	 */
	private $plan = array(
		'header' => null,
		'footer' => null,
	);

	/**
	 * Theme parts suppressed on this request, keyed by part slug.
	 *
	 * Set when a planned layout asks to hide the theme's own header or
	 * footer, so the two never render at the same time.
	 *
	 * @since 1.0.0
	 * @var   array<string,bool>
	 */
	private $suppressed = array();

	/**
	 * Whether the block-theme template part filter was added.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	private $part_filter_added = false;

	/**
	 * Whether the frontend should render at all.
	 *
	 * @since 1.0.0
	 * @var   bool|null
	 */
	private $should_run = null;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Returns the renderer.
	 *
	 * @since 1.0.0
	 *
	 * @return Renderer
	 */
	public function renderer() {
		if ( null === $this->renderer ) {
			$this->renderer = new Renderer( $this->plugin );
		}

		return $this->renderer;
	}

	/**
	 * Registers frontend hooks. Runs on `init` priority 20.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register() {
		Shortcode::register();
		Layout_Block::register();

		add_action( 'template_redirect', array( $this, 'resolve' ), 5 );
		add_action( 'wp_body_open', array( $this, 'render_wp_body_open' ), 5 );
		add_action( 'get_header', array( $this, 'render_get_header' ), 5 );
		add_action( 'get_footer', array( $this, 'render_get_footer' ), 5 );
		add_action( 'wp_footer', array( $this, 'render_wp_footer' ), 5 );
		add_action( 'wp_head', array( $this, 'print_hide_css' ), 100 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
		add_filter( 'body_class', array( $this, 'body_class' ), 20 );

		/**
		 * Fires after the frontend hooks have been registered.
		 *
		 * @since 1.0.0
		 *
		 * @param self $frontend Frontend subsystem.
		 */
		do_action( 'qevora_layouts_frontend_registered', $this );
	}

	/**
	 * Whether the plugin should render on this request.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function should_run() {
		if ( null !== $this->should_run ) {
			return $this->should_run;
		}

		$run = true;

		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			$run = false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$run = false;
		}

		if ( function_exists( 'is_feed' ) && is_feed() ) {
			$run = false;
		}

		if ( $run && ! did_action( 'wp' ) && ! doing_action( 'template_redirect' ) ) {
			// The main query is not ready yet; conditions cannot be evaluated.
			$run = false;
		}

		/**
		 * Filters whether layouts render on the current request.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $run Whether to render.
		 */
		$this->should_run = (bool) apply_filters( 'qevora_layouts_should_run', $run );

		return $this->should_run;
	}

	/**
	 * Resolves the plan for the current request.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,Layout|null>
	 */
	public function resolve() {
		if ( $this->resolved ) {
			return $this->plan;
		}

		$this->resolved = true;

		if ( ! $this->should_run() ) {
			return $this->plan;
		}

		$repository = $this->plugin->repository();

		foreach ( array_keys( $this->plan ) as $type ) {
			$layout = $repository->get_active_layout( $type );

			if ( ! $layout instanceof Layout ) {
				continue;
			}

			$placement = $this->resolve_placement( $layout->get_effective_placement() );

			if ( 'manual' === $placement ) {
				Logger::debug( 'Layout is manual only.', array( 'layout_id' => $layout->id, 'type' => $type ) );
				continue;
			}

			// A layout may replace the theme's own header or footer.
			if ( ! empty( $layout->hide_theme_part ) ) {
				$this->suppress_part( $type );
			}

			if ( 'custom_hook' === $placement ) {
				$this->register_custom_hook( $layout );
				continue;
			}

			$this->plan[ $type ] = $layout;
		}

		Logger::debug(
			'Resolved layout plan.',
			array(
				'header' => $this->plan['header'] instanceof Layout ? $this->plan['header']->id : 0,
				'footer' => $this->plan['footer'] instanceof Layout ? $this->plan['footer']->id : 0,
			)
		);

		/**
		 * Filters the resolved layout plan.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,Layout|null> $plan     Layouts per type.
		 * @param self                      $frontend Frontend subsystem.
		 */
		$this->plan = (array) apply_filters( 'qevora_layouts_plan', $this->plan, $this );

		return $this->plan;
	}

	/**
	 * Registers the theme hook requested by a layout.
	 *
	 * @since 1.0.0
	 *
	 * @param Layout $layout Layout with a `custom_hook` placement.
	 * @return void
	 */
	private function register_custom_hook( Layout $layout ) {
		$hook = $layout->hook;

		if ( '' === $hook ) {
			Logger::warning( 'Layout requests a custom hook but no hook name is set.', array( 'layout_id' => $layout->id ) );

			return;
		}

		$type = $layout->type;

		add_action(
			$hook,
			function () use ( $type, $layout ) {
				$this->render_slot( $type, $layout );
			},
			$layout->hook_priority,
			0
		);

		Logger::debug( 'Registered custom layout hook.', array( 'hook' => $hook, 'layout_id' => $layout->id ) );
	}

	/**
	 * Returns the resolved layout for a type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Layout type.
	 * @return Layout|null
	 */
	public function get_planned_layout( $type ) {
		$this->resolve();

		return isset( $this->plan[ $type ] ) ? $this->plan[ $type ] : null;
	}

	/**
	 * Maps a stored placement onto the hook that actually fires.
	 *
	 * Block themes render through `template-canvas.php`, which never runs
	 * `get_header` or `get_footer`. A layout stored with one of those
	 * placements therefore moves to `wp_body_open` or `wp_footer`, both of
	 * which the canvas always fires. Classic themes keep the stored hook.
	 *
	 * @since 1.0.0
	 *
	 * @param string $placement Stored placement slug.
	 * @return string Placement slug for the active theme.
	 */
	private function resolve_placement( $placement ) {
		if ( 'get_header' !== $placement && 'get_footer' !== $placement ) {
			return $placement;
		}

		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			return 'get_header' === $placement ? 'wp_body_open' : 'wp_footer';
		}

		return $placement;
	}

	/**
	 * Renders every planned layout that belongs to a placement.
	 *
	 * @since 1.0.0
	 *
	 * @param string $placement Placement slug.
	 * @return void
	 */
	private function render_placement( $placement ) {
		$this->resolve();

		foreach ( $this->plan as $type => $layout ) {
			if ( ! $layout instanceof Layout ) {
				continue;
			}

			if ( $this->resolve_placement( $layout->get_effective_placement() ) !== $placement ) {
				continue;
			}

			$this->render_slot( $type, $layout );
		}
	}

	/**
	 * Renders one slot.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $type   Slot name (header|footer).
	 * @param Layout|null $layout Layout to render, null to use the plan.
	 * @return void
	 */
	public function render_slot( $type, ?Layout $layout = null ) {
		$this->resolve();

		if ( ! $this->should_run() ) {
			return;
		}

		if ( null === $layout ) {
			$layout = $this->get_planned_layout( $type );
		}

		if ( ! $layout instanceof Layout ) {
			return;
		}

		if ( ! Duplicate_Guard::should_render( $type ) ) {
			Logger::debug( 'Skipped duplicate slot.', array( 'slot' => $type ) );

			return;
		}

		$html = $this->renderer()->render_layout( $layout, (string) $type );

		if ( '' === $html ) {
			return;
		}

		// Markup produced by the builder adapters; layout content is sanitized
		// on save and never evaluated.
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered layout markup.
	}

	/**
	 * Placement callback: top of the body.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_wp_body_open() {
		$this->render_placement( 'wp_body_open' );
	}

	/**
	 * Placement callback: start of the theme header.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_get_header() {
		$this->render_placement( 'get_header' );
	}

	/**
	 * Placement callback: start of the theme footer.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_get_footer() {
		$this->render_placement( 'get_footer' );
	}

	/**
	 * Placement callback: end of the page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_wp_footer() {
		$this->render_placement( 'wp_footer' );
	}

	/**
	 * Marks a theme part as suppressed for this request.
	 *
	 * On block themes the matching `core/template-part` block is removed
	 * from the response outright through the `render_block` filter; the
	 * inline CSS in {@see Frontend::print_hide_css()} covers classic themes
	 * and acts as a fallback. Theme files are never modified and the
	 * suppression disappears the moment the layout flag is unchecked.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Part slug: header or footer.
	 * @return void
	 */
	private function suppress_part( $type ) {
		if ( 'header' !== $type && 'footer' !== $type ) {
			return;
		}

		$this->suppressed[ $type ] = true;

		if ( ! $this->part_filter_added && function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			$this->part_filter_added = true;

			add_filter( 'render_block', array( $this, 'suppress_template_part' ), 10, 2 );
		}
	}

	/**
	 * Whether a theme part is suppressed on this request.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Part slug: header or footer.
	 * @return bool
	 */
	public function is_part_suppressed( $type ) {
		return ! empty( $this->suppressed[ $type ] );
	}

	/**
	 * `render_block` callback: drops the theme's own header or footer
	 * template part on block themes while a layout replaces it.
	 *
	 * @since 1.0.0
	 *
	 * @param string              $block_content Rendered block HTML.
	 * @param array<string,mixed> $block         Parsed block.
	 * @return string
	 */
	public function suppress_template_part( $block_content, $block ) {
		if ( ! is_array( $block ) || ! isset( $block['blockName'] ) || 'core/template-part' !== $block['blockName'] ) {
			return $block_content;
		}

		$slug = isset( $block['attrs']['slug'] ) ? (string) $block['attrs']['slug'] : '';

		if ( ( 'header' === $slug || 'footer' === $slug ) && ! empty( $this->suppressed[ $slug ] ) ) {
			return '';
		}

		return $block_content;
	}

	/**
	 * Conservative default selectors for the theme's own header or footer.
	 *
	 * Covers the markup of the default WordPress themes and the most common
	 * classic themes (`#masthead`, `.site-header`, `#colophon`,
	 * `.site-footer`) plus the block-theme template part wrappers. Themes
	 * with unusual markup can be targeted through the filter below or the
	 * custom selectors on the settings page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Part slug: header or footer.
	 * @return string[] Sanitized selectors.
	 */
	public static function default_hide_selectors( $type ) {
		$selectors = 'header' === $type
			? array( '#masthead', '#site-header', '.site-header', 'header.wp-block-template-part' )
			: array( '#colophon', '#site-footer', '.site-footer', 'footer.wp-block-template-part' );

		/**
		 * Filters the default CSS selectors used to hide a theme part that a
		 * layout replaces.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $selectors Default selectors.
		 * @param string   $type      Part slug: header or footer.
		 */
		$selectors = (array) apply_filters( 'qevora_layouts_theme_part_selectors', $selectors, $type );

		$clean = array();

		foreach ( $selectors as $selector ) {
			$selector = Settings::sanitize_css_selectors( (string) $selector );

			if ( '' !== $selector ) {
				$clean[] = $selector;
			}
		}

		return $clean;
	}

	/**
	 * Prints the opt-in CSS that hides the theme's own header or footer.
	 *
	 * Only printed when a layout of the same type actually renders on this
	 * request, so the theme markup is never removed without a replacement.
	 * Selectors come from the layout's own hide flag (a conservative default
	 * list) and from the optional site-wide selectors in the settings.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function print_hide_css() {
		if ( ! Settings::is_enabled( 'inline_hide_css' ) ) {
			return;
		}

		$this->resolve();

		$rules = array();

		foreach ( array( 'header', 'footer' ) as $type ) {
			$planned    = $this->plan[ $type ] instanceof Layout;
			$suppressed = ! empty( $this->suppressed[ $type ] );

			if ( ! $planned && ! $suppressed ) {
				continue;
			}

			$selectors = $suppressed ? self::default_hide_selectors( $type ) : array();

			$setting_key = 'header' === $type ? 'hide_theme_header_selectors' : 'hide_theme_footer_selectors';
			$extra       = Settings::sanitize_css_selectors( (string) Settings::get( $setting_key, '' ) );

			if ( '' !== $extra ) {
				$selectors[] = $extra;
			}

			if ( ! empty( $selectors ) ) {
				$rules[] = implode( ', ', $selectors );
			}
		}

		if ( empty( $rules ) ) {
			return;
		}

		/**
		 * Filters the CSS printed to hide the theme's own header or footer.
		 *
		 * @since 1.0.0
		 *
		 * @param string $css       CSS declaration block.
		 * @param string[] $selectors Accepted selectors.
		 */
		$css = (string) apply_filters( 'qevora_layouts_hide_theme_css', implode( ', ', $rules ) . '{display:none !important;}', $rules );

		if ( '' === trim( $css ) ) {
			return;
		}

		// Re-validate whatever came back from the filter before printing.
		$css = $this->sanitize_css_block( $css );

		if ( '' === $css ) {
			return;
		}

		echo '<style id="qevora-layouts-hide-theme">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Validated CSS, no markup or URL characters possible.
	}

	/**
	 * Defensive check on a CSS block before it is printed inside `<style>`.
	 *
	 * @since 1.0.0
	 *
	 * @param string $css Candidate CSS.
	 * @return string Sanitized CSS or an empty string.
	 */
	private function sanitize_css_block( $css ) {
		// The value can come back from a filter, so it may not even be a string.
		if ( ! is_string( $css ) && ! is_numeric( $css ) ) {
			return '';
		}

		$css = trim( (string) $css );

		if ( '' === $css || strlen( $css ) > 4000 ) {
			return '';
		}

		// Markup could escape the <style> element.
		if ( false !== strpos( $css, '<' ) ) {
			return '';
		}

		$forbidden = array( 'javascript:', 'expression(', '@import', '@charset', 'behavior:', 'url(', '<?', '/*' );

		foreach ( $forbidden as $needle ) {
			if ( false !== stripos( $css, $needle ) ) {
				return '';
			}
		}

		// Exactly one selector list followed by exactly one declaration block.
		if ( 1 !== preg_match( '/^[^{}]+\{[^{}]*\}$/', $css ) ) {
			return '';
		}

		return $css;
	}

	/**
	 * Loads the small frontend stylesheet only when a layout renders.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		$this->resolve();

		if ( ! $this->should_run() ) {
			return;
		}

		$needed = false;

		foreach ( $this->plan as $layout ) {
			if ( $layout instanceof Layout ) {
				$needed = true;
				break;
			}
		}

		/**
		 * Filters whether the plugin frontend stylesheet is loaded.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $needed Whether to enqueue.
		 */
		$needed = (bool) apply_filters( 'qevora_layouts_enqueue_frontend_assets', $needed );

		if ( ! $needed ) {
			return;
		}

		wp_register_style(
			'qevora-layouts-frontend',
			QEVORA_LAYOUTS_URL . 'assets/css/frontend.css',
			array(),
			QEVORA_LAYOUTS_VERSION
		);

		wp_enqueue_style( 'qevora-layouts-frontend' );
	}

	/**
	 * Adds body classes describing the active layouts.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public function body_class( $classes ) {
		$classes = is_array( $classes ) ? $classes : array();

		if ( ! $this->should_run() ) {
			return $classes;
		}

		$this->resolve();

		foreach ( $this->plan as $type => $layout ) {
			if ( $layout instanceof Layout ) {
				$classes[] = 'has-qevora-' . sanitize_html_class( $type );
			}
		}

		return $classes;
	}

	/**
	 * Resets internal state. Used by tests and previews.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function reset() {
		$this->resolved   = false;
		$this->should_run = null;
		$this->plan       = array(
			'header' => null,
			'footer' => null,
		);
		$this->suppressed = array();

		Duplicate_Guard::reset();
	}
}
