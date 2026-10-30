<?php
/**
 * Qevora Layouts bundled demo: 'Classic Header'.
 *
 * One demo, one PHP file: the manifest, the customisable settings, the
 * scoped stylesheet, the vanilla JavaScript and the server-side render all
 * live in this single file. Nothing here ever touches the block editor -
 * the admin screen offers the settings below as a metabox and the front end
 * renders this file on every request, so what the library previews is
 * exactly what the site displays.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'manifest' => array(
		'id'          => 'header-classic',
		'name'        => __( 'Classic Header', 'qevora-layouts' ),
		'type'        => 'header',
		'description' => __( 'Site title with a colored brand mark on the left, a clean navigation menu on the right, and a tap-friendly slide-down menu on phones. Rendered entirely by the plugin with plain HTML, CSS and a little JavaScript - no page builder and no block editor needed.', 'qevora-layouts' ),
		'menu'        => true,
		'menu_label'  => __( 'Create a sample menu (Home plus your published pages)', 'qevora-layouts' ),
		'tags'        => array( 'header', 'php', 'responsive', 'menu' ),
	),
	'settings' => array(
	'fields' => array(
		'site_title'    => array(
			'label'   => __( 'Site title', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty to use the site title from Settings - General.', 'qevora-layouts' ),
		),
		'accent_color'  => array(
			'label'   => __( 'Brand mark colour', 'qevora-layouts' ),
			'type'    => 'color',
			'default' => '#2271b1',
		),
		'sticky_header' => array(
			'label'   => __( 'Stick the header to the top while scrolling', 'qevora-layouts' ),
			'type'    => 'checkbox',
			'default' => false,
		),
	),
),
	'css'      => <<<'QEVORA_DEMO_CSS'
/* Classic Header demo - scoped, responsive, no dependencies. */
.qdhc{--qdhc-bg:#ffffff;--qdhc-fg:#1e1e1e;--qdhc-accent:#2563eb;--qdhc-line:#e5e7eb;background:var(--qdhc-bg);color:var(--qdhc-fg);border-bottom:1px solid var(--qdhc-line);font-size:16px;line-height:1.5}
.qdhc__inner{display:flex;align-items:center;justify-content:space-between;gap:16px;max-width:1200px;margin:0 auto;padding:14px 20px}
.qdhc__brand{display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit}
.qdhc__mark{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;background:var(--qdhc-accent);color:#fff;font-weight:700;text-transform:uppercase}
.qdhc__name{font-size:1.2rem;font-weight:700;letter-spacing:-0.01em}
.qdhc__nav{display:block}
.qdhc__menu,.qdhc__menu ul{display:flex;flex-wrap:wrap;gap:4px;margin:0;padding:0;list-style:none}
.qdhc__menu ul{position:absolute;top:100%;left:0;min-width:200px;flex-direction:column;background:var(--qdhc-bg);border:1px solid var(--qdhc-line);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.08);padding:6px;opacity:0;visibility:hidden;transform:translateY(6px);transition:opacity .15s ease,transform .15s ease}
.qdhc__menu li{position:relative}
.qdhc__menu a{display:block;padding:8px 12px;border-radius:6px;text-decoration:none;color:inherit;font-weight:500}
.qdhc__menu a:hover,.qdhc__menu a:focus,.qdhc__menu .current-menu-item>a{background:#f3f4f6;color:var(--qdhc-accent)}
.qdhc__menu li:hover>ul,.qdhc__menu li:focus-within>ul{opacity:1;visibility:visible;transform:translateY(0)}
.qdhc__toggle{display:none;flex-direction:column;justify-content:center;gap:5px;width:42px;height:42px;padding:9px;background:transparent;border:1px solid var(--qdhc-line);border-radius:8px;cursor:pointer}
.qdhc__toggle-bar{display:block;height:2px;width:100%;background:var(--qdhc-fg);border-radius:2px}
.qdhc__sr{position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0}
@media (max-width:782px){
	.qdhc__inner{flex-wrap:wrap;padding:12px 16px}
	.qdhc__toggle{display:flex;order:2}
	.qdhc__nav{order:3;flex-basis:100%;display:none;border-top:1px solid var(--qdhc-line);margin-top:10px;padding-top:6px}
	.qdhc__nav.is-open{display:block}
	.qdhc__menu,.qdhc__menu ul{flex-direction:column;gap:0}
	.qdhc__menu ul{position:static;opacity:1;visibility:visible;transform:none;box-shadow:none;border:0;border-radius:0;padding:0 0 0 14px;background:transparent}
	.qdhc__menu a{padding:12px 10px;border-radius:8px}
}

/* Sticky variant, switched on from the demo settings metabox. */
.qdhc--sticky {
	position: sticky;
	top: 0;
	z-index: 9999;
}
QEVORA_DEMO_CSS,
	'js'       => <<<'QEVORA_DEMO_JS'
/**
 * Classic Header demo: mobile menu toggle.
 * Vanilla JS, no dependencies. Loaded in the footer only when the demo renders.
 */
( function () {
	'use strict';

	function init( root ) {
		if ( root.getAttribute( 'data-qdhc-ready' ) === '1' ) {
			return;
		}
		root.setAttribute( 'data-qdhc-ready', '1' );

		var toggle = root.querySelector( '[data-qdhc-toggle]' );
		var nav = root.querySelector( '[data-qdhc-nav]' );

		if ( ! toggle || ! nav ) {
			return;
		}

		function close() {
			nav.classList.remove( 'is-open' );
			toggle.setAttribute( 'aria-expanded', 'false' );
		}

		toggle.addEventListener( 'click', function () {
			var open = nav.classList.toggle( 'is-open' );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( ( 27 === event.keyCode || 'Escape' === event.key ) && nav.classList.contains( 'is-open' ) ) {
				close();
				toggle.focus();
			}
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( nav.classList.contains( 'is-open' ) && ! nav.contains( event.target ) && ! toggle.contains( event.target ) ) {
				close();
			}
		} );
	}

	function boot() {
		var roots = document.querySelectorAll( '.qdhc' );
		for ( var i = 0; i < roots.length; i++ ) {
			init( roots[ i ] );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
QEVORA_DEMO_JS,
	'render'   => static function ( $qevora_layout, $qevora_demo, $qevora_menu_id, $qevora_demo_settings ) {
		ob_start();

		
$qdhc_set    = (array) $qevora_demo_settings;
$qdhc_name   = '' !== (string) $qdhc_set['site_title'] ? (string) $qdhc_set['site_title'] : (string) get_bloginfo( 'name' );
$qdhc_accent = (string) $qdhc_set['accent_color'];
$qdhc_sticky = ! empty( $qdhc_set['sticky_header'] );
$qdhc_home = (string) home_url( '/' );
$qdhc_mark = function_exists( 'mb_substr' ) ? mb_substr( $qdhc_name, 0, 1 ) : substr( $qdhc_name, 0, 1 );
$qdhc_nav  = 'qdhc-nav-' . (int) $qevora_layout->id;
?>
<div class="qdhc<?php echo $qdhc_sticky ? ' qdhc--sticky' : ''; ?>">
	<div class="qdhc__inner">
		<a class="qdhc__brand" href="<?php echo esc_url( $qdhc_home ); ?>">
			<span class="qdhc__mark" style="background: <?php echo esc_attr( $qdhc_accent ); ?>;" aria-hidden="true"><?php echo esc_html( $qdhc_mark ); ?></span>
			<span class="qdhc__name"><?php echo esc_html( $qdhc_name ); ?></span>
		</a>

		<?php if ( $qevora_menu_id > 0 && function_exists( 'wp_nav_menu' ) ) : ?>
			<button type="button" class="qdhc__toggle" data-qdhc-toggle aria-expanded="false" aria-controls="<?php echo esc_attr( $qdhc_nav ); ?>">
				<span class="qdhc__toggle-bar" aria-hidden="true"></span>
				<span class="qdhc__toggle-bar" aria-hidden="true"></span>
				<span class="qdhc__toggle-bar" aria-hidden="true"></span>
				<span class="qdhc__sr"><?php esc_html_e( 'Menu', 'qevora-layouts' ); ?></span>
			</button>
			<nav id="<?php echo esc_attr( $qdhc_nav ); ?>" class="qdhc__nav" data-qdhc-nav aria-label="<?php esc_attr_e( 'Primary menu', 'qevora-layouts' ); ?>">
				<?php
				wp_nav_menu(
					array(
						'menu'        => $qevora_menu_id,
						'container'   => '',
						'menu_class'  => 'qdhc__menu',
						'fallback_cb' => false,
						'depth'       => 2,
					)
				);
				?>
			</nav>
		<?php endif; ?>
	</div>
</div>
		<?php

		return ob_get_clean();
	},
);
