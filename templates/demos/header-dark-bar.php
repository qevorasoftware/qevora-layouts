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

$qhdb_menus = array();

if ( function_exists( 'wp_get_nav_menus' ) ) {
	foreach ( wp_get_nav_menus() as $qhdb_menu_object ) {
		$qhdb_menus[ (string) (int) $qhdb_menu_object->term_id ] = (string) $qhdb_menu_object->name;
	}
}

return array(
	'manifest' => array(
		'id'          => 'header-dark-bar',
		'name'        => __( 'Dark Bar Header', 'qevora-layouts' ),
		'type'        => 'header',
		'description' => __( 'A floating dark navigation bar with your logo, a centred uppercase menu with dot separators and a light call-to-action button. Rendered entirely by the plugin from this one PHP file - responsive, with a tap-friendly slide-down menu on phones.', 'qevora-layouts' ),
		'menu'        => true,
		'menu_label'  => __( 'Create a sample menu (Home plus your published pages)', 'qevora-layouts' ),
		'tags'        => array( 'header', 'php', 'responsive', 'menu', 'dark' ),
	),
	'settings' => array(
		'fields' => array(
			'logo_image'   => array(
				'label'   => __( 'Logo image', 'qevora-layouts' ),
				'type'    => 'image',
				'default' => '',
				'help'    => __( 'Pick a square image from the media library. Leave empty to show the first letter of your site name on a coloured tile instead.', 'qevora-layouts' ),
			),
			'menu'         => array(
				'label'   => __( 'Menu to display', 'qevora-layouts' ),
				'type'    => 'select',
				'default' => '',
				'options' => array( '' => __( 'Default menu (the one created with this layout)', 'qevora-layouts' ) ) + $qhdb_menus,
				'help'    => __( 'Choose any menu you built under Appearance → Menus; manage its links there.', 'qevora-layouts' ),
			),
			'button_label' => array(
				'label'   => __( 'Button label', 'qevora-layouts' ),
				'type'    => 'text',
				'default' => '',
				'help'    => __( 'Leave empty for the default wording, or switch the button off below.', 'qevora-layouts' ),
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
			'sticky_header' => array(
				'label'   => __( 'Keep the header fixed at the top while scrolling', 'qevora-layouts' ),
				'type'    => 'checkbox',
				'default' => false,
				'help'    => __( 'The bar sticks to the top of the window as visitors scroll down the page.', 'qevora-layouts' ),
			),
			'show_search'  => array(
				'label'   => __( 'Show a search button in the header', 'qevora-layouts' ),
				'type'    => 'checkbox',
				'default' => true,
				'help'    => __( 'Clicking the magnifier opens a search box under the bar with live title suggestions from your site; submitting it runs a normal WordPress search.', 'qevora-layouts' ),
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

.qhdb .qhdb__mark {
	width: 40px;
	height: 40px;
	border-radius: 10px;
	background: #f4511e;
	color: #fff;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	font-weight: 800;
	font-size: 19px;
	text-transform: uppercase;
}

.qhdb .qhdb__logo-img {
	width: 40px;
	height: 40px;
	border-radius: 10px;
	object-fit: cover;
	display: block;
}

.qhdb__nav {
	flex: 1 1 auto;
	display: flex;
	justify-content: center;
	min-width: 0;
}

.qhdb .qhdb__menu,
.qhdb .qhdb__menu ul {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	list-style: none;
	margin: 0;
	padding: 0;
}

.qhdb .qhdb__menu li {
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

.qhdb .qhdb__menu a {
	color: #fff;
	text-decoration: none;
	text-transform: uppercase;
	letter-spacing: 0.12em;
	font-size: 11.5px;
	font-weight: 600;
	padding: 9px 2px;
}

.qhdb .qhdb__menu li > span {
	color: #fff;
	text-transform: uppercase;
	letter-spacing: 0.12em;
	font-size: 11.5px;
	font-weight: 600;
	padding: 9px 2px;
}

.qhdb__menu a:hover,
.qhdb__menu a:focus,
.qhdb__menu .current-menu-item > a {
	color: #f4511e;
}

.qhdb .qhdb__cta {
	flex: 0 0 auto;
	margin-left: auto;
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

.qhdb .qhdb__cta:hover,
.qhdb .qhdb__cta:focus {
	filter: brightness( 0.94 );
	color: #101012;
}

/* Doubled selectors keep theme reset stylesheets (which often target
   bare buttons) from overriding the toggle or its bars. */
.qhdb .qhdb__toggle {
	display: none;
	flex-direction: column;
	justify-content: center;
	gap: 5px;
	width: 42px;
	height: 42px;
	padding: 9px;
	margin-left: 0;
	background: transparent;
	border: 1px solid rgba( 255, 255, 255, 0.25 );
	border-radius: 8px;
	cursor: pointer;
}

.qhdb .qhdb__toggle-bar {
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
	overflow: hidden;
	clip: rect( 0 0 0 0 );
	white-space: nowrap;
	border: 0;
}

/* "Fixed header" option: the bar sticks to the top of the window. */
.qhdb.qhdb--sticky {
	position: sticky;
	top: 0;
	z-index: 9999;
}

.admin-bar .qhdb.qhdb--sticky {
	top: 32px;
}

/* Search button in the bar and the slide-open panel below it. */
.qhdb .qhdb__search-toggle {
	flex: 0 0 auto;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 42px;
	height: 42px;
	padding: 0;
	background: transparent;
	border: 1px solid rgba( 255, 255, 255, 0.25 );
	border-radius: 8px;
	color: #fff;
	cursor: pointer;
}

.qhdb .qhdb__search-toggle:hover,
.qhdb .qhdb__search-toggle:focus {
	border-color: #f4511e;
	color: #f4511e;
}

.qhdb .qhdb__search-toggle svg {
	width: 18px;
	height: 18px;
	display: block;
	fill: none;
	stroke: currentColor;
	stroke-width: 2;
	stroke-linecap: round;
}

.qhdb .qhdb__search {
	max-width: 1160px;
	margin: 10px auto 0;
}

.qhdb .qhdb__search[hidden] {
	display: none;
}

.qhdb .qhdb__search-form {
	display: flex;
	gap: 8px;
	background: #fff;
	border-radius: 10px;
	padding: 8px;
	box-shadow: 0 12px 32px rgba( 0, 0, 0, 0.28 );
}

.qhdb .qhdb__search-input {
	flex: 1 1 auto;
	min-width: 0;
	border: 0;
	background: transparent;
	color: #101012;
	font-size: 15px;
	padding: 8px 10px;
}

.qhdb .qhdb__search-input:focus {
	outline: 2px solid #f4511e;
	border-radius: 6px;
}

.qhdb .qhdb__search-submit {
	flex: 0 0 auto;
	background: #0b0b0d;
	color: #fff;
	border: 0;
	border-radius: 8px;
	padding: 10px 18px;
	text-transform: uppercase;
	letter-spacing: 0.1em;
	font-size: 11px;
	font-weight: 700;
	cursor: pointer;
}

.qhdb .qhdb__search-suggest {
	display: flex;
	flex-direction: column;
	margin: -2px 0 0;
	padding: 6px 8px 8px;
	background: #fff;
	border-radius: 0 0 10px 10px;
	box-shadow: 0 14px 30px rgba( 0, 0, 0, 0.18 );
}

.qhdb .qhdb__search-suggest[hidden] {
	display: none;
}

.qhdb .qhdb__search-suggestion {
	color: #101012;
	text-decoration: none;
	padding: 9px 10px;
	border-radius: 6px;
	font-size: 14px;
}

.qhdb .qhdb__search-suggestion:hover,
.qhdb .qhdb__search-suggestion:focus {
	background: #f4f4f5;
	color: #f4511e;
}

/* Phones and small tablets: the bar wraps and the menu opens inside it,
   in normal flow, so no ancestor can clip an absolute dropdown. */
@media ( max-width: 782px ) {
	.qhdb {
		padding: 10px 10px;
	}

	.qhdb .qhdb__bar {
		gap: 10px;
		padding: 8px 10px;
		flex-wrap: wrap;
	}

	.qhdb .qhdb__toggle {
		display: flex;
	}

	.qhdb .qhdb__nav {
		display: none;
		order: 3;
		flex: 1 1 100%;
		position: static;
		margin: 4px 0 0;
		padding: 6px 4px 4px;
		background: transparent;
		border-top: 1px solid rgba( 255, 255, 255, 0.16 );
		border-radius: 0;
		box-shadow: none;
		justify-content: flex-start;
	}

	.qhdb .qhdb__nav.is-open {
		display: block;
	}

	.qhdb .qhdb__menu,
	.qhdb .qhdb__menu ul {
		flex-direction: column;
		align-items: stretch;
		width: 100%;
	}

	.qhdb .qhdb__menu li + li::before {
		content: none;
	}

	.qhdb .qhdb__menu a {
		padding: 12px 4px;
		font-size: 12.5px;
	}

	.qhdb .qhdb__cta {
		margin-left: auto;
		padding: 10px 14px;
	}
}

/* Very small phones: keep the bar to one tidy row. */
@media ( max-width: 480px ) {
	.qhdb .qhdb__cta {
		padding: 9px 10px;
		font-size: 10px;
		letter-spacing: 0.06em;
	}

	.qhdb .qhdb__mark,
	.qhdb .qhdb__logo-img {
		width: 34px;
		height: 34px;
	}

	.qhdb .qhdb__toggle,
	.qhdb .qhdb__search-toggle {
		width: 38px;
		height: 38px;
	}
}
QEVORA_DEMO_CSS,
	'js'       => '',
	'render'   => static function ( $qevora_layout, $qevora_demo, $qevora_menu_id, $qevora_demo_settings ) {
		ob_start();

		$qhdb        = (array) $qevora_demo_settings + array(
			'sticky_header' => false,
			'show_search'   => true,
		);
		$qhdb_logo   = (string) $qhdb['logo_image'];
		$qhdb_label  = '' !== (string) $qhdb['button_label'] ? (string) $qhdb['button_label'] : __( 'Book a call', 'qevora-layouts' );
		$qhdb_url    = '' !== (string) $qhdb['button_url'] ? (string) $qhdb['button_url'] : (string) home_url( '/' );
		$qhdb_show   = ! empty( $qhdb['show_button'] );
		$qhdb_name   = (string) get_bloginfo( 'name' );
		$qhdb_mark   = function_exists( 'mb_substr' ) ? mb_substr( $qhdb_name, 0, 1 ) : substr( $qhdb_name, 0, 1 );
		$qhdb_nav    = 'qhdb-nav-' . (int) $qevora_layout->id;
		$qhdb_chosen = isset( $qhdb['menu'] ) ? (int) $qhdb['menu'] : 0;

		if ( $qhdb_chosen > 0 && ( ! function_exists( 'wp_get_nav_menu_object' ) || ! wp_get_nav_menu_object( $qhdb_chosen ) ) ) {
			$qhdb_chosen = 0;
		}

		$qhdb_menu_id  = $qhdb_chosen > 0 ? $qhdb_chosen : (int) $qevora_menu_id;
		$qhdb_has_menu = $qhdb_menu_id > 0 && function_exists( 'wp_nav_menu' );
		$qhdb_preview  = is_object( $qevora_layout ) && ! empty( $qevora_layout->preview );
		$qhdb_sticky   = ! empty( $qhdb['sticky_header'] );
		$qhdb_search   = ! empty( $qhdb['show_search'] );
		$qhdb_rest     = function_exists( 'rest_url' ) ? (string) rest_url( 'wp/v2/search' ) : '';
		?>
<div class="qhdb<?php echo $qhdb_sticky ? ' qhdb--sticky' : ''; ?>">
	<div class="qhdb__bar">
		<a class="qhdb__logo" href="<?php echo esc_url( (string) home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( $qhdb_name ); ?>" data-qhdb-fallback="<?php echo esc_attr( $qhdb_mark ); ?>">
			<?php if ( '' !== $qhdb_logo ) : ?>
				<img class="qhdb__logo-img" src="<?php echo esc_url( $qhdb_logo ); ?>" alt="<?php echo esc_attr( $qhdb_name ); ?>" />
			<?php else : ?>
				<span class="qhdb__mark" aria-hidden="true"><?php echo esc_html( $qhdb_mark ); ?></span>
			<?php endif; ?>
		</a>

		<?php if ( $qhdb_has_menu ) : ?>
			<nav id="<?php echo esc_attr( $qhdb_nav ); ?>" class="qhdb__nav" data-qhdb-nav aria-label="<?php esc_attr_e( 'Primary menu', 'qevora-layouts' ); ?>">
				<?php
				wp_nav_menu(
					array(
						'menu'        => $qhdb_menu_id,
						'container'   => '',
						'menu_class'  => 'qhdb__menu',
						'fallback_cb' => false,
						'depth'       => 1,
					)
				);
				?>
			</nav>
		<?php endif; ?>

		<?php
		if ( ! $qhdb_has_menu && $qhdb_preview ) :
			// Previews show a representative sample menu so the card, the
			// dialog and the metabox look like the finished header. The
			// front end never invents links.
			?>
			<nav class="qhdb__nav" aria-hidden="true">
				<ul class="qhdb__menu">
					<?php foreach ( array( 'Home', 'About', 'Service', 'Gallery', 'Contact' ) as $qhdb_sample ) : ?>
						<li><span><?php echo esc_html( $qhdb_sample ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>

		<?php if ( $qhdb_show ) : ?>
			<a class="qhdb__cta" href="<?php echo esc_url( $qhdb_url ); ?>"><?php echo esc_html( $qhdb_label ); ?></a>
		<?php endif; ?>

		<?php if ( $qhdb_search ) : ?>
			<button type="button" class="qhdb__search-toggle" data-qhdb-search-toggle aria-expanded="false" aria-controls="<?php echo esc_attr( 'qhdb-search-' . (int) $qevora_layout->id ); ?>">
				<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
					<circle cx="11" cy="11" r="7"></circle>
					<line x1="16.5" y1="16.5" x2="21" y2="21"></line>
				</svg>
				<span class="qhdb__sr"><?php esc_html_e( 'Search', 'qevora-layouts' ); ?></span>
			</button>
		<?php endif; ?>

		<?php if ( $qhdb_has_menu ) : ?>
			<button type="button" class="qhdb__toggle" data-qhdb-toggle aria-expanded="false" aria-controls="<?php echo esc_attr( $qhdb_nav ); ?>">
				<span class="qhdb__toggle-bar" aria-hidden="true"></span>
				<span class="qhdb__toggle-bar" aria-hidden="true"></span>
				<span class="qhdb__toggle-bar" aria-hidden="true"></span>
				<span class="qhdb__sr"><?php esc_html_e( 'Menu', 'qevora-layouts' ); ?></span>
			</button>
		<?php endif; ?>
	</div>

	<?php if ( $qhdb_search ) : ?>
		<div class="qhdb__search" id="<?php echo esc_attr( 'qhdb-search-' . (int) $qevora_layout->id ); ?>" data-qhdb-search data-qhdb-rest="<?php echo esc_url( $qhdb_rest ); ?>" hidden>
			<form class="qhdb__search-form" role="search" method="get" action="<?php echo esc_url( (string) home_url( '/' ) ); ?>">
				<label class="qhdb__sr" for="<?php echo esc_attr( 'qhdb-search-input-' . (int) $qevora_layout->id ); ?>"><?php esc_html_e( 'Search this site', 'qevora-layouts' ); ?></label>
				<input class="qhdb__search-input" id="<?php echo esc_attr( 'qhdb-search-input-' . (int) $qevora_layout->id ); ?>" type="search" name="s" autocomplete="off" placeholder="<?php esc_attr_e( 'Type to search - suggestions appear as you type', 'qevora-layouts' ); ?>" />
				<button class="qhdb__search-submit" type="submit"><?php esc_html_e( 'Search', 'qevora-layouts' ); ?></button>
			</form>
			<div class="qhdb__search-suggest" data-qhdb-suggest hidden></div>
		</div>
	<?php endif; ?>
</div>
		<?php
			// Ships with the markup so the mobile menu works even when a
			// theme or cache plugin drops enqueued footer scripts.
			?>
<script>
( function () {
	'use strict';
	var script = document.currentScript;
	var host = script ? script.previousElementSibling : null;
	var root = host && host.classList && host.classList.contains( 'qhdb' ) ? host : null;
	if ( ! root ) { return; }
	var nav = root.querySelector( '[data-qhdb-nav]' );
	var toggle = root.querySelector( '[data-qhdb-toggle]' );
	if ( nav && toggle ) {
		toggle.addEventListener( 'click', function () {
			var open = nav.classList.toggle( 'is-open' );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );
	}
	var panel = root.querySelector( '[data-qhdb-search]' );
	var searchToggle = root.querySelector( '[data-qhdb-search-toggle]' );
	var input = panel ? panel.querySelector( 'input[name="s"]' ) : null;
	var suggest = panel ? panel.querySelector( '[data-qhdb-suggest]' ) : null;
	var rest = panel ? ( panel.getAttribute( 'data-qhdb-rest' ) || '' ) : '';
	var timer = 0;
	if ( ! panel || ! searchToggle ) { return; }
	function setOpen( open ) {
		panel.hidden = ! open;
		searchToggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		if ( open && input ) { input.focus(); }
		if ( ! open && suggest ) { suggest.hidden = true; }
	}
	searchToggle.addEventListener( 'click', function () { setOpen( panel.hidden ); } );
	document.addEventListener( 'click', function ( event ) {
		if ( panel.hidden ) { return; }
		if ( panel.contains( event.target ) || searchToggle.contains( event.target ) ) { return; }
		setOpen( false );
	} );
	if ( ! input || ! suggest ) { return; }
	input.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key ) { setOpen( false ); searchToggle.focus(); }
	} );
	input.addEventListener( 'input', function () {
		var query = input.value.trim();
		if ( timer ) { window.clearTimeout( timer ); }
		if ( query.length < 2 || ! rest || ! window.fetch ) {
			suggest.hidden = true;
			suggest.innerHTML = '';
			return;
		}
		timer = window.setTimeout( function () {
			var separator = rest.indexOf( '?' ) > -1 ? '&' : '?';
			window.fetch( rest + separator + 'search=' + window.encodeURIComponent( query ) + '&per_page=6' )
				.then( function ( response ) { return response.ok ? response.json() : []; } )
				.then( function ( data ) {
					var items = Array.isArray( data ) ? data : [];
					suggest.innerHTML = '';
					for ( var i = 0; i < items.length; i++ ) {
						var link = document.createElement( 'a' );
						link.href = items[i].url;
						link.className = 'qhdb__search-suggestion';
						link.textContent = items[i].title;
						suggest.appendChild( link );
					}
					suggest.hidden = 0 === items.length;
				} )
				.catch( function () { suggest.hidden = true; } );
		}, 180 );
	} );
}() );
</script>
		<?php

		return ob_get_clean();
	},
);
