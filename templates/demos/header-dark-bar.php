<?php
/**
 * Qevora Layouts bundled demo: Dark Bar Header.
 *
 * One demo, one PHP file: the manifest, the customisable settings, the
 * scoped stylesheet, the vanilla JavaScript and the server-side render all
 * live in this single file. Nothing here ever touches the block editor -
 * the admin screen offers the settings below as a metabox with a live
 * preview of this very markup, and the front end renders this file on
 * every request, so what the library and the editor preview is exactly
 * what the site displays.
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
		'id'          => 'header-dark-bar',
		'name'        => __( 'Dark Bar Header', 'qevora-layouts' ),
		'type'        => 'header',
		'description' => __( 'A floating dark navigation bar with a coloured logo tile, a centred uppercase menu with dot separators and a light call-to-action button. Rendered entirely by the plugin from this one PHP file - responsive, with a tap-friendly slide-down menu on phones.', 'qevora-layouts' ),
		'menu'        => true,
		'menu_label'  => __( 'Create a sample menu (Home plus your published pages)', 'qevora-layouts' ),
		'tags'        => array( 'header', 'php', 'responsive', 'menu', 'dark' ),
	),
	'settings' => array(
		'fields' => array(
			'accent_color' => array(
				'label'   => __( 'Logo tile colour', 'qevora-layouts' ),
				'type'    => 'color',
				'default' => '#f4511e',
			),
			'button_label' => array(
				'label'   => __( 'Button label', 'qevora-layouts' ),
				'type'    => 'text',
				'default' => '',
				'help'    => __( 'Leave empty for the default wording, or clear it in combination with the link to hide the button.', 'qevora-layouts' ),
			),
			'button_url'   => array(
				'label'   => __( 'Button link', 'qevora-layouts' ),
				'type'    => 'url',
				'default' => '',
				'help'    => __( 'Leave empty to link to the front page.', 'qevora-layouts' ),
			),
			'show_button'  => array(
				'label'   => __( 'Show the call-to-action button', 'qevora-layouts' ),
				'type'    => 'checkbox',
				'default' => true,
			),
		),
	),
	'css'      => <<<'QEVORA_DEMO_CSS'
/*
 * Dark Bar Header demo - scoped, responsive stylesheet.
 * Lives inside the demo's single PHP file and prints inline under the
 * qevora-demo-header-dark-bar handle only while this demo renders.
 */

.qhdb {
	padding: 14px 16px;
}

.qhdb__bar {
	max-width: 1160px;
	margin: 0 auto;
	display: flex;
	align-items: center;
	gap: 18px;
	background: #0b0b0d;
	border-radius: 12px;
	padding: 10px 14px;
	box-shadow: 0 12px 32px rgba( 0, 0, 0, 0.28 );
	position: relative;
}

.qhdb__logo {
	flex: 0 0 auto;
	display: inline-flex;
	border-radius: 10px;
	text-decoration: none;
}

.qhdb__mark {
	width: 40px;
	height: 40px;
	border-radius: 10px;
	background: var( --qhdb-accent, #f4511e );
	color: #fff;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	font-weight: 800;
	font-size: 19px;
	text-transform: uppercase;
}

.qhdb__nav {
	flex: 1 1 auto;
	display: flex;
	justify-content: center;
}

.qhdb__menu,
.qhdb__menu ul {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	list-style: none;
	margin: 0;
	padding: 0;
}

.qhdb__menu li {
	position: relative;
	display: flex;
	align-items: center;
}

.qhdb__menu li + li::before {
	content: "\2022";
	color: rgba( 255, 255, 255, 0.4 );
	margin: 0 12px;
	font-size: 10px;
}

.qhdb__menu a {
	color: #fff;
	text-decoration: none;
	text-transform: uppercase;
	letter-spacing: 0.12em;
	font-size: 11.5px;
	font-weight: 600;
	padding: 9px 2px;
}

.qhdb__menu a:hover,
.qhdb__menu a:focus,
.qhdb__menu .current-menu-item > a {
	color: var( --qhdb-accent, #f4511e );
}

.qhdb__cta {
	flex: 0 0 auto;
	background: #fff;
	color: #101012;
	border-radius: 8px;
	padding: 12px 20px;
	text-transform: uppercase;
	letter-spacing: 0.1em;
	font-size: 11.5px;
	font-weight: 700;
	text-decoration: none;
	white-space: nowrap;
}

.qhdb__cta:hover,
.qhdb__cta:focus {
	filter: brightness( 0.94 );
	color: #101012;
}

.qhdb__toggle {
	display: none;
	flex-direction: column;
	justify-content: center;
	gap: 5px;
	width: 42px;
	height: 42px;
	padding: 9px;
	margin-left: auto;
	background: transparent;
	border: 1px solid rgba( 255, 255, 255, 0.25 );
	border-radius: 8px;
	cursor: pointer;
}

.qhdb__toggle-bar {
	display: block;
	height: 2px;
	width: 100%;
	background: #fff;
	border-radius: 2px;
}

.qhdb__sr {
	position: absolute;
	width: 1px;
	height: 1px;
	padding: 0;
	margin: -1px;
	overflow: hidden;
	clip: rect( 0 0 0 0 );
	white-space: nowrap;
	border: 0;
}

@media ( max-width: 782px ) {
	.qhdb {
		padding: 10px 10px;
	}

	.qhdb__toggle {
		display: flex;
	}

	.qhdb__nav {
		display: none;
		position: absolute;
		top: 100%;
		left: 0;
		right: 0;
		background: #0b0b0d;
		border-radius: 12px;
		box-shadow: 0 12px 32px rgba( 0, 0, 0, 0.28 );
		margin-top: 8px;
		padding: 8px 14px;
		justify-content: flex-start;
	}

	.qhdb__nav.is-open {
		display: block;
	}

	.qhdb__menu,
	.qhdb__menu ul {
		flex-direction: column;
		align-items: stretch;
		width: 100%;
	}

	.qhdb__menu li + li::before {
		content: none;
	}

	.qhdb__menu a {
		padding: 12px 4px;
		font-size: 12.5px;
	}

	.qhdb__cta {
		margin-left: auto;
		padding: 10px 14px;
	}
}
QEVORA_DEMO_CSS,
	'js'       => <<<'QEVORA_DEMO_JS'
/**
 * Dark Bar Header demo - mobile menu behaviour.
 *
 * Lives inside the demo's single PHP file and prints inline under the
 * qevora-demo-header-dark-bar-js handle only while this demo renders.
 */
( function () {
	'use strict';

	function init( root ) {
		var toggle = root.querySelector( '[data-qhdb-toggle]' );
		var nav = root.querySelector( '[data-qhdb-nav]' );

		if ( ! toggle || ! nav ) {
			return;
		}

		toggle.addEventListener( 'click', function () {
			var open = nav.classList.toggle( 'is-open' );

			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			Array.prototype.forEach.call( document.querySelectorAll( '.qhdb' ), init );
		} );
	} else {
		Array.prototype.forEach.call( document.querySelectorAll( '.qhdb' ), init );
	}
}() );
QEVORA_DEMO_JS,
	'render'   => static function ( $qevora_layout, $qevora_demo, $qevora_menu_id, $qevora_demo_settings ) {
		ob_start();

		$qhdb         = (array) $qevora_demo_settings;
		$qhdb_accent  = (string) $qhdb['accent_color'];
		$qhdb_label   = '' !== (string) $qhdb['button_label'] ? (string) $qhdb['button_label'] : __( 'Book a call', 'qevora-layouts' );
		$qhdb_url     = '' !== (string) $qhdb['button_url'] ? (string) $qhdb['button_url'] : (string) home_url( '/' );
		$qhdb_show    = ! empty( $qhdb['show_button'] );
		$qhdb_name    = (string) get_bloginfo( 'name' );
		$qhdb_mark    = function_exists( 'mb_substr' ) ? mb_substr( $qhdb_name, 0, 1 ) : substr( $qhdb_name, 0, 1 );
		$qhdb_nav     = 'qhdb-nav-' . (int) $qevora_layout->id;
		$qhdb_has_menu = $qevora_menu_id > 0 && function_exists( 'wp_nav_menu' );
		?>
<div class="qhdb" style="--qhdb-accent: <?php echo esc_attr( $qhdb_accent ); ?>;">
	<div class="qhdb__bar">
		<a class="qhdb__logo" href="<?php echo esc_url( (string) home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( $qhdb_name ); ?>">
			<span class="qhdb__mark" aria-hidden="true"><?php echo esc_html( $qhdb_mark ); ?></span>
		</a>

		<?php if ( $qhdb_has_menu ) : ?>
			<button type="button" class="qhdb__toggle" data-qhdb-toggle aria-expanded="false" aria-controls="<?php echo esc_attr( $qhdb_nav ); ?>">
				<span class="qhdb__toggle-bar" aria-hidden="true"></span>
				<span class="qhdb__toggle-bar" aria-hidden="true"></span>
				<span class="qhdb__toggle-bar" aria-hidden="true"></span>
				<span class="qhdb__sr"><?php esc_html_e( 'Menu', 'qevora-layouts' ); ?></span>
			</button>
			<nav id="<?php echo esc_attr( $qhdb_nav ); ?>" class="qhdb__nav" data-qhdb-nav aria-label="<?php esc_attr_e( 'Primary menu', 'qevora-layouts' ); ?>">
				<?php
				wp_nav_menu(
					array(
						'menu'        => $qevora_menu_id,
						'container'   => '',
						'menu_class'  => 'qhdb__menu',
						'fallback_cb' => false,
						'depth'       => 1,
					)
				);
				?>
			</nav>
		<?php endif; ?>

		<?php if ( $qhdb_show ) : ?>
			<a class="qhdb__cta" href="<?php echo esc_url( $qhdb_url ); ?>"><?php echo esc_html( $qhdb_label ); ?></a>
		<?php endif; ?>
	</div>
</div>
		<?php

		return ob_get_clean();
	},
);
