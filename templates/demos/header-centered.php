<?php
/**
 * Qevora Layouts bundled demo: 'Centered Header'.
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
		'id'          => 'header-centered',
		'name'        => __( 'Centered Header', 'qevora-layouts' ),
		'type'        => 'header',
		'description' => __( 'A centered brand with the navigation neatly below it, collapsing into a full-width tap menu on phones. Pure plugin-rendered HTML, CSS and JavaScript - responsive at every width, no editor content involved.', 'qevora-layouts' ),
		'menu'        => true,
		'menu_label'  => __( 'Create a sample menu (Home plus your published pages)', 'qevora-layouts' ),
		'tags'        => array( 'header', 'php', 'responsive', 'menu', 'centered' ),
	),
	'settings' => array(
	'fields' => array(
		'site_title'    => array(
			'label'   => __( 'Site title', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty to use the site title from Settings - General.', 'qevora-layouts' ),
		),
		'show_tagline'  => array(
			'label'   => __( 'Show the tagline under the title', 'qevora-layouts' ),
			'type'    => 'checkbox',
			'default' => true,
		),
		'tagline'       => array(
			'label'   => __( 'Tagline', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty to use the tagline from Settings - General.', 'qevora-layouts' ),
		),
		'sticky_header' => array(
			'label'   => __( 'Stick the header to the top while scrolling', 'qevora-layouts' ),
			'type'    => 'checkbox',
			'default' => false,
		),
	),
),
	'css'      => <<<'QEVORA_DEMO_CSS'
/* Centered Header demo - scoped, responsive, no dependencies. */
.qdhh{--qdhh-bg:#0f172a;--qdhh-fg:#f8fafc;--qdhh-muted:#94a3b8;--qdhh-accent:#38bdf8;background:var(--qdhh-bg);color:var(--qdhh-fg);font-size:16px;line-height:1.5}
.qdhh__brand-row{display:flex;align-items:center;justify-content:center;position:relative;max-width:1200px;margin:0 auto;padding:26px 20px 18px}
.qdhh__brand{text-align:center;text-decoration:none;color:inherit}
.qdhh__name{display:block;font-size:1.7rem;font-weight:800;letter-spacing:.02em}
.qdhh__tagline{display:block;margin-top:4px;color:var(--qdhh-muted);font-size:.85rem;text-transform:uppercase;letter-spacing:.14em}
.qdhh__toggle{position:absolute;right:16px;top:50%;transform:translateY(-50%);display:none;width:44px;height:44px;align-items:center;justify-content:center;font-size:22px;color:var(--qdhh-fg);background:transparent;border:1px solid rgba(248,250,252,.25);border-radius:8px;cursor:pointer}
.qdhh__nav{border-top:1px solid rgba(248,250,252,.12)}
.qdhh__menu{display:flex;flex-wrap:wrap;justify-content:center;gap:6px;max-width:1200px;margin:0 auto;padding:10px 20px;list-style:none}
.qdhh__menu a{display:block;padding:8px 16px;border-radius:999px;text-decoration:none;color:var(--qdhh-muted);font-weight:600;font-size:.95rem;transition:color .15s ease,background .15s ease}
.qdhh__menu a:hover,.qdhh__menu a:focus,.qdhh__menu .current-menu-item>a{color:var(--qdhh-fg);background:rgba(56,189,248,.16)}
.qdhh__sr{position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0}
@media (max-width:782px){
	.qdhh__brand-row{padding:18px 64px 14px}
	.qdhh__name{font-size:1.35rem}
	.qdhh__toggle{display:flex}
	.qdhh__nav{display:none;border-top:0}
	.qdhh__nav.is-open{display:block;border-top:1px solid rgba(248,250,252,.12)}
	.qdhh__menu{flex-direction:column;gap:0;padding:8px 16px 14px}
	.qdhh__menu a{padding:13px 14px;border-radius:10px}
}

/* Sticky variant, switched on from the demo settings metabox. */
.qdhh--sticky {
	position: sticky;
	top: 0;
	z-index: 9999;
}
QEVORA_DEMO_CSS,
	'js'       => <<<'QEVORA_DEMO_JS'
/**
 * Centered Header demo: mobile menu toggle. Vanilla JS, no dependencies.
 */
( function () {
	'use strict';

	function init( root ) {
		if ( root.getAttribute( 'data-qdhh-ready' ) === '1' ) {
			return;
		}
		root.setAttribute( 'data-qdhh-ready', '1' );

		var toggle = root.querySelector( '[data-qdhh-toggle]' );
		var nav = root.querySelector( '[data-qdhh-nav]' );

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
	}

	function boot() {
		var roots = document.querySelectorAll( '.qdhh' );
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

		
$qdhh_set      = (array) $qevora_demo_settings;
$qdhh_name     = '' !== (string) $qdhh_set['site_title'] ? (string) $qdhh_set['site_title'] : (string) get_bloginfo( 'name' );
$qdhh_tagline  = ! empty( $qdhh_set['show_tagline'] ) ? ( '' !== (string) $qdhh_set['tagline'] ? (string) $qdhh_set['tagline'] : (string) get_bloginfo( 'description' ) ) : '';
$qdhh_sticky   = ! empty( $qdhh_set['sticky_header'] );
$qdhh_home     = (string) home_url( '/' );
$qdhh_nav      = 'qdhh-nav-' . (int) $qevora_layout->id;
$qdhh_has_menu = $qevora_menu_id > 0 && function_exists( 'wp_nav_menu' );
?>
<div class="qdhh<?php echo $qdhh_sticky ? ' qdhh--sticky' : ''; ?>">
	<div class="qdhh__brand-row">
		<a class="qdhh__brand" href="<?php echo esc_url( $qdhh_home ); ?>">
			<span class="qdhh__name"><?php echo esc_html( $qdhh_name ); ?></span>
			<?php if ( '' !== $qdhh_tagline ) : ?>
				<span class="qdhh__tagline"><?php echo esc_html( $qdhh_tagline ); ?></span>
			<?php endif; ?>
		</a>
		<?php if ( $qdhh_has_menu ) : ?>
			<button type="button" class="qdhh__toggle" data-qdhh-toggle aria-expanded="false" aria-controls="<?php echo esc_attr( $qdhh_nav ); ?>">
				<span class="qdhh__sr"><?php esc_html_e( 'Menu', 'qevora-layouts' ); ?></span>
				<span aria-hidden="true">&#9776;</span>
			</button>
		<?php endif; ?>
	</div>

	<?php if ( $qdhh_has_menu ) : ?>
		<nav id="<?php echo esc_attr( $qdhh_nav ); ?>" class="qdhh__nav" data-qdhh-nav aria-label="<?php esc_attr_e( 'Primary menu', 'qevora-layouts' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'menu'        => $qevora_menu_id,
					'container'   => '',
					'menu_class'  => 'qdhh__menu',
					'fallback_cb' => false,
					'depth'       => 1,
				)
			);
			?>
		</nav>
	<?php endif; ?>
</div>
		<?php

		return ob_get_clean();
	},
);
