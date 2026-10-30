<?php
/**
 * Qevora Layouts bundled demo: Book Store Header.
 *
 * One demo, one PHP file: manifest, settings, scoped stylesheet and the
 * server-side render all live here. The three-row shop header (announcement
 * bar, logo + category search + call block, then the category/menu bar) is
 * rendered by the plugin on every request - no block editor, no page
 * builder. Its stylesheet only ever loads while this demo renders, exactly
 * like every other bundled demo.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$qbs_menus = array();

if ( function_exists( 'wp_get_nav_menus' ) ) {
	foreach ( wp_get_nav_menus() as $qbs_menu_object ) {
		$qbs_menus[ (string) (int) $qbs_menu_object->term_id ] = (string) $qbs_menu_object->name;
	}
}

return array(
	'manifest' => array(
		'id'          => 'header-book-store',
		'name'        => __( 'Book Store Header', 'qevora-layouts' ),
		'type'        => 'header',
		'description' => __( 'A three-row shop header: a terracotta announcement bar with language and social icons, a white row with your logo, a rounded category search and a call-us block, then a rose menu bar with a categories dropdown and a WooCommerce cart bubble when the shop is active. Rendered entirely by the plugin from this one PHP file.', 'qevora-layouts' ),
		'menu'        => true,
		'menu_label'  => __( 'Create a sample menu (Home plus your published pages)', 'qevora-layouts' ),
		'tags'        => array( 'header', 'php', 'responsive', 'shop', 'search' ),
	),
	'settings' => array(
		'fields' => array(
			'logo_image'    => array(
				'label'   => __( 'Logo image', 'qevora-layouts' ),
				'type'    => 'image',
				'default' => '',
				'help'    => __( 'Pick your logo from the media library. Leave empty to show a book tile with your site name and tagline instead.', 'qevora-layouts' ),
			),
			'menu'          => array(
				'label'   => __( 'Menu to display', 'qevora-layouts' ),
				'type'    => 'select',
				'default' => '',
				'options' => array( '' => __( 'Default menu (the one created with this layout)', 'qevora-layouts' ) ) + $qbs_menus,
				'help'    => __( 'Choose any menu you built under Appearance → Menus; manage its links there.', 'qevora-layouts' ),
			),
			'delivery_note' => array(
				'label'   => __( 'Announcement bar text', 'qevora-layouts' ),
				'type'    => 'text',
				'default' => __( 'Fastest Delivery In Your City', 'qevora-layouts' ),
				'help'    => __( 'Shows on the left of the top bar, next to the delivery icon.', 'qevora-layouts' ),
			),
			'phone_number'  => array(
				'label'   => __( 'Phone number', 'qevora-layouts' ),
				'type'    => 'text',
				'default' => '',
				'help'    => __( 'Digits, spaces, + and - are kept; the number becomes a tap-to-call link. Leave empty to hide the call block.', 'qevora-layouts' ),
			),
			'social_links'  => array(
				'label'   => __( 'Social links', 'qevora-layouts' ),
				'type'    => 'textarea',
				'default' => '',
				'help'    => __( 'One URL per line, in this order: Facebook, X, Instagram, YouTube. Empty lines are skipped and icons without a URL stay hidden.', 'qevora-layouts' ),
			),
			'show_top_bar'  => array(
				'label'   => __( 'Show the announcement bar', 'qevora-layouts' ),
				'type'    => 'checkbox',
				'default' => true,
			),
			'show_search'   => array(
				'label'   => __( 'Show the category search', 'qevora-layouts' ),
				'type'    => 'checkbox',
				'default' => true,
				'help'    => __( 'The rounded search pill filters your site search by the chosen category.', 'qevora-layouts' ),
			),
			'sticky_header' => array(
				'label'   => __( 'Keep the header fixed at the top while scrolling', 'qevora-layouts' ),
				'type'    => 'checkbox',
				'default' => false,
				'help'    => __( 'All three rows stick to the top of the window as visitors scroll down the page.', 'qevora-layouts' ),
			),
		),
	),
	'css'      => <<<'QEVORA_DEMO_CSS'
/*
 * Book Store Header demo - scoped, responsive stylesheet.
 * Loads under the qevora-demo-header-book-store handle only while this
 * demo renders, so no other demo or theme picks it up.
 */

.qbs {
	padding: 0;
	color: #23343c;
}

.qbs__top {
	background: #c26650;
	color: #fff;
}

.qbs__top-inner,
.qbs__main-inner,
.qbs__nav-inner {
	max-width: 1240px;
	margin: 0 auto;
	display: flex;
	align-items: center;
	gap: 18px;
	padding: 8px 16px;
}

.qbs__note {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	margin: 0;
	font-size: 12.5px;
	font-weight: 600;
	letter-spacing: 0.02em;
}

.qbs__note svg,
.qbs__lang svg,
.qbs__cats svg {
	width: 15px;
	height: 15px;
	display: block;
	fill: none;
	stroke: currentColor;
	stroke-width: 2;
	stroke-linecap: round;
	stroke-linejoin: round;
}

.qbs__top-right {
	margin-left: auto;
	display: flex;
	align-items: center;
	gap: 12px;
}

.qbs__lang {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	font-size: 12px;
	font-weight: 600;
}

.qbs__lang svg.qbs__caret {
	width: 10px;
	height: 10px;
}

.qbs__social {
	display: flex;
	align-items: center;
	gap: 10px;
	padding-left: 12px;
	border-left: 1px solid rgba( 255, 255, 255, 0.35 );
}

.qbs__social a,
.qbs__social span {
	display: inline-flex;
	color: #fff;
}

.qbs__social svg {
	width: 14px;
	height: 14px;
	display: block;
	fill: currentColor;
	stroke: none;
}

.qbs__social a:hover,
.qbs__social a:focus {
	color: #ffe3da;
}

.qbs__account {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 34px;
	height: 34px;
	margin: -8px -16px -8px 4px;
	background: #1d4b5f;
	color: #fff;
}

.qbs__account svg {
	width: 16px;
	height: 16px;
	display: block;
	fill: currentColor;
	stroke: none;
}

.qbs__main {
	background: #fdfcfb;
	border-bottom: 1px solid #f0e7e4;
}

.qbs__main-inner {
	padding: 16px;
	gap: 24px;
}

.qbs__logo {
	flex: 0 0 auto;
	display: inline-flex;
	align-items: center;
	gap: 10px;
	text-decoration: none;
	color: #23343c;
}

.qbs__logo-img {
	width: 46px;
	height: 46px;
	border-radius: 8px;
	object-fit: cover;
	display: block;
}

.qbs__logo-tile {
	width: 46px;
	height: 46px;
	border-radius: 8px;
	background: #1d4b5f;
	color: #fff;
	display: inline-flex;
	align-items: center;
	justify-content: center;
}

.qbs__logo-tile svg {
	width: 24px;
	height: 24px;
	display: block;
	fill: none;
	stroke: currentColor;
	stroke-width: 1.8;
	stroke-linecap: round;
	stroke-linejoin: round;
}

.qbs__logo-text {
	display: flex;
	flex-direction: column;
	line-height: 1.15;
}

.qbs__logo-name {
	font-size: 24px;
	font-weight: 700;
	letter-spacing: 0.01em;
	color: #23343c;
}

.qbs__logo-tag {
	font-size: 10.5px;
	font-weight: 600;
	letter-spacing: 0.22em;
	text-transform: uppercase;
	color: #b76e79;
}

.qbs__search {
	flex: 1 1 auto;
	min-width: 0;
	display: flex;
	align-items: stretch;
	max-width: 640px;
	margin: 0 auto;
	background: #f7e9e6;
	border: 1px solid #dcafa6;
	border-radius: 999px;
	overflow: hidden;
}

.qbs__search-cat {
	flex: 0 0 auto;
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 0 12px 0 18px;
	border: 0;
	background: transparent;
	color: #6d5a55;
	font-size: 13px;
	font-weight: 600;
	cursor: pointer;
}

.qbs__search-cat svg {
	width: 10px;
	height: 10px;
	display: block;
	fill: none;
	stroke: currentColor;
	stroke-width: 2;
}

.qbs__search-divider {
	width: 1px;
	background: #dcafa6;
	margin: 8px 0;
}

.qbs__search-input {
	flex: 1 1 auto;
	min-width: 0;
	border: 0;
	background: transparent;
	color: #23343c;
	font-size: 14px;
	padding: 12px 14px;
}

.qbs__search-input:focus {
	outline: 2px solid #b76e79;
	outline-offset: -2px;
	border-radius: 999px;
}

.qbs__search-submit {
	flex: 0 0 auto;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 52px;
	margin: 5px;
	border: 0;
	border-radius: 999px;
	background: #b76e79;
	color: #fff;
	cursor: pointer;
}

.qbs__search-submit svg {
	width: 17px;
	height: 17px;
	display: block;
	fill: none;
	stroke: currentColor;
	stroke-width: 2;
	stroke-linecap: round;
}

.qbs__search-submit:hover,
.qbs__search-submit:focus {
	background: #a25b66;
}

.qbs__call {
	flex: 0 0 auto;
	display: flex;
	align-items: center;
	gap: 10px;
	text-decoration: none;
	color: #23343c;
}

.qbs__call-icon {
	width: 42px;
	height: 42px;
	border-radius: 999px;
	background: #c26650;
	color: #fff;
	display: inline-flex;
	align-items: center;
	justify-content: center;
}

.qbs__call-icon svg {
	width: 18px;
	height: 18px;
	display: block;
	fill: currentColor;
	stroke: none;
}

.qbs__call-text {
	display: flex;
	flex-direction: column;
	line-height: 1.25;
}

.qbs__call-label {
	font-size: 12px;
	font-weight: 700;
}

.qbs__call-number {
	font-size: 14px;
	font-weight: 800;
	letter-spacing: 0.02em;
}

.qbs__nav {
	background: #b76e79;
	position: relative;
}

.qbs__nav-inner {
	padding: 0 16px;
	gap: 22px;
}

.qbs__cats-wrap {
	position: relative;
	flex: 0 0 auto;
}

.qbs__cats {
	display: inline-flex;
	align-items: center;
	gap: 10px;
	padding: 13px 26px;
	background: #1d4b5f;
	border: 0;
	color: #fff;
	font-size: 13.5px;
	font-weight: 700;
	cursor: pointer;
}

.qbs__cats svg.qbs__caret {
	width: 10px;
	height: 10px;
	margin-left: auto;
}

.qbs__cats-panel {
	position: absolute;
	top: 100%;
	left: 0;
	z-index: 60;
	min-width: 220px;
	margin: 0;
	padding: 8px;
	list-style: none;
	background: #fff;
	border-radius: 0 0 10px 10px;
	box-shadow: 0 18px 34px rgba( 35, 52, 60, 0.22 );
}

.qbs__cats-panel[hidden] {
	display: none;
}

.qbs__cats-panel a,
.qbs__cats-panel span {
	display: block;
	padding: 9px 12px;
	border-radius: 6px;
	color: #23343c;
	text-decoration: none;
	font-size: 13.5px;
	font-weight: 600;
}

.qbs__cats-panel a:hover,
.qbs__cats-panel a:focus {
	background: #f7e9e6;
	color: #b76e79;
}

.qbs__navwrap {
	flex: 1 1 auto;
	min-width: 0;
	display: flex;
	justify-content: center;
}

.qbs .qbs__menu,
.qbs .qbs__menu ul {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 4px 26px;
	list-style: none;
	margin: 0;
	padding: 0;
}

.qbs .qbs__menu a,
.qbs .qbs__menu li > span {
	display: inline-block;
	padding: 14px 2px;
	color: #fff;
	text-decoration: none;
	font-size: 13.5px;
	font-weight: 700;
}

.qbs .qbs__menu a:hover,
.qbs .qbs__menu a:focus,
.qbs .qbs__menu .current-menu-item > a {
	color: #ffe3da;
}

.qbs__cart {
	flex: 0 0 auto;
	position: relative;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 44px;
	height: 44px;
	border-radius: 999px;
	background: #1d4b5f;
	color: #fff;
}

.qbs__cart svg {
	width: 19px;
	height: 19px;
	display: block;
	fill: none;
	stroke: currentColor;
	stroke-width: 2;
	stroke-linecap: round;
	stroke-linejoin: round;
}

.qbs__cart-count {
	position: absolute;
	top: -4px;
	right: -2px;
	min-width: 18px;
	height: 18px;
	padding: 0 4px;
	border-radius: 999px;
	background: #f7e9e6;
	color: #1d4b5f;
	font-size: 11px;
	font-weight: 800;
	display: inline-flex;
	align-items: center;
	justify-content: center;
}

.qbs__sr {
	position: absolute;
	width: 1px;
	height: 1px;
	padding: 0;
	overflow: hidden;
	clip: rect( 0 0 0 0 );
	white-space: nowrap;
	border: 0;
}

/* Doubled selectors keep theme reset stylesheets from flattening the
   toggle button or its bars. */
.qbs .qbs__toggle {
	display: none;
	flex-direction: column;
	justify-content: center;
	gap: 5px;
	width: 42px;
	height: 42px;
	padding: 9px;
	margin-left: auto;
	background: transparent;
	border: 1px solid rgba( 255, 255, 255, 0.45 );
	border-radius: 8px;
	cursor: pointer;
}

.qbs .qbs__toggle-bar {
	display: block;
	height: 2px;
	width: 100%;
	background: #fff;
	border-radius: 2px;
}

/* "Fixed header" option: every row sticks to the top of the window. */
.qbs.qbs--sticky {
	position: sticky;
	top: 0;
	z-index: 9999;
}

.admin-bar .qbs.qbs--sticky {
	top: 32px;
}

/* Tablets and phones: rows stack, the menu opens inside the nav bar in
   normal flow so no ancestor can clip it. */
@media ( max-width: 900px ) {
	.qbs__top-right .qbs__social,
	.qbs__top-right .qbs__lang {
		display: none;
	}

	.qbs__main-inner {
		flex-wrap: wrap;
		gap: 12px;
		padding: 12px;
	}

	.qbs__search {
		order: 3;
		flex: 1 1 100%;
		max-width: none;
	}

	.qbs__call-text {
		display: none;
	}

	.qbs__nav-inner {
		flex-wrap: wrap;
		gap: 0;
		padding: 0 10px;
	}

	.qbs__cats-wrap {
		flex: 1 1 auto;
	}

	.qbs__cats {
		width: 100%;
		padding: 12px 18px;
	}

	.qbs__cats-panel {
		position: static;
		border-radius: 0;
		box-shadow: none;
	}

	.qbs__navwrap {
		display: none;
		order: 3;
		flex: 1 1 100%;
		margin: 4px 0 0;
		padding: 6px 4px 10px;
		border-top: 1px solid rgba( 255, 255, 255, 0.25 );
	}

	.qbs__navwrap.is-open {
		display: block;
	}

	.qbs .qbs__menu,
	.qbs .qbs__menu ul {
		flex-direction: column;
		align-items: stretch;
		gap: 0;
		width: 100%;
	}

	.qbs .qbs__menu a,
	.qbs .qbs__menu li > span {
		padding: 12px 4px;
	}

	.qbs .qbs__toggle {
		display: flex;
	}

	.qbs__cart {
		margin-left: auto;
	}
}

@media ( max-width: 480px ) {
	.qbs__logo-name {
		font-size: 19px;
	}

	.qbs__logo-tag {
		display: none;
	}

	.qbs__search-cat {
		padding-left: 12px;
	}
}
QEVORA_DEMO_CSS,
	'js'       => '',
	'render'   => static function ( $qevora_layout, $qevora_demo, $qevora_menu_id, $qevora_demo_settings ) {
		ob_start();

		$qbs            = (array) $qevora_demo_settings + array(
			'delivery_note' => __( 'Fastest Delivery In Your City', 'qevora-layouts' ),
			'show_top_bar'  => true,
			'show_search'   => true,
			'sticky_header' => false,
		);
		$qbs_logo       = (string) $qbs['logo_image'];
		$qbs_note       = (string) $qbs['delivery_note'];
		$qbs_phone_raw  = (string) $qbs['phone_number'];
		$qbs_phone      = (string) preg_replace( '/[^0-9+\- ]/', '', $qbs_phone_raw );
		$qbs_name       = (string) get_bloginfo( 'name' );
		$qbs_tagline    = (string) get_bloginfo( 'description' );
		$qbs_nav        = 'qbs-nav-' . (int) $qevora_layout->id;
		$qbs_cats_id    = 'qbs-cats-' . (int) $qevora_layout->id;
		$qbs_preview    = is_object( $qevora_layout ) && ! empty( $qevora_layout->preview );
		$qbs_sticky     = ! empty( $qbs['sticky_header'] );
		$qbs_top        = ! empty( $qbs['show_top_bar'] );
		$qbs_search     = ! empty( $qbs['show_search'] );
		$qbs_chosen     = isset( $qbs['menu'] ) ? (int) $qbs['menu'] : 0;

		if ( $qbs_chosen > 0 && ( ! function_exists( 'wp_get_nav_menu_object' ) || ! wp_get_nav_menu_object( $qbs_chosen ) ) ) {
			$qbs_chosen = 0;
		}

		$qbs_menu_id  = $qbs_chosen > 0 ? $qbs_chosen : (int) $qevora_menu_id;
		$qbs_has_menu = $qbs_menu_id > 0 && function_exists( 'wp_nav_menu' );

		// Social links: one URL per line, mapped onto four known networks.
		$qbs_social_icons = array(
			'facebook'  => '<path d="M13.5 9H15V6.5h-1.9c-2 0-3.1 1.2-3.1 3.2V11H8v2.5h2V21h2.6v-7.5h2.1L15 11h-2.4V9.9c0-.6.3-.9.9-.9Z"/>',
			'x'         => '<path d="M17.8 6h-2.4l-3 3.9L9.2 6H6.5l4.3 5.6L6.3 18h2.4l3.2-4.2 3.1 4.2h2.7l-4.5-6.1L17.8 6Z"/>',
			'instagram' => '<path d="M12 8.6a3.4 3.4 0 1 0 0 6.8 3.4 3.4 0 0 0 0-6.8Zm0 5.6a2.2 2.2 0 1 1 0-4.4 2.2 2.2 0 0 1 0 4.4ZM16.9 8.4a.8.8 0 1 1-1.6 0 .8.8 0 0 1 1.6 0ZM12 6.2c-1.9 0-2.1 0-2.9.1-.7 0-1.2.1-1.7.3-.5.2-.8.4-1.2.8-.4.4-.6.7-.8 1.2-.2.5-.3 1-.3 1.7-.1.8-.1 1-.1 2.9s0 2.1.1 2.9c0 .7.1 1.2.3 1.7.2.5.4.8.8 1.2.4.4.7.6 1.2.8.5.2 1 .3 1.7.3.8.1 1 .1 2.9.1s2.1 0 2.9-.1c.7 0 1.2-.1 1.7-.3.5-.2.8-.4 1.2-.8.4-.4.6-.7.8-1.2.2-.5.3-1 .3-1.7.1-.8.1-1 .1-2.9s0-2.1-.1-2.9c0-.7-.1-1.2-.3-1.7-.2-.5-.4-.8-.8-1.2-.4-.4-.7-.6-1.2-.8-.5-.2-1-.3-1.7-.3-.8-.1-1-.1-2.9-.1Z"/>',
			'youtube'   => '<path d="M20.2 9.2a2.3 2.3 0 0 0-1.6-1.6C17.2 7.2 12 7.2 12 7.2s-5.2 0-6.6.4A2.3 2.3 0 0 0 3.8 9.2 24 24 0 0 0 3.4 12a24 24 0 0 0 .4 2.8 2.3 2.3 0 0 0 1.6 1.6c1.4.4 6.6.4 6.6.4s5.2 0 6.6-.4a2.3 2.3 0 0 0 1.6-1.6 24 24 0 0 0 .4-2.8 24 24 0 0 0-.4-2.8ZM10.6 14.2V9.8L14.2 12Z"/>',
		);
		$qbs_social_urls  = array();

		foreach ( preg_split( '/\r\n|\r|\n/', (string) $qbs['social_links'] ) as $qbs_line ) {
			$qbs_line = trim( (string) $qbs_line );

			if ( '' !== $qbs_line ) {
				$qbs_social_urls[] = esc_url( $qbs_line );
			}

			if ( count( $qbs_social_urls ) >= 4 ) {
				break;
			}
		}

		$qbs_categories = array();

		if ( ! $qbs_preview && function_exists( 'get_categories' ) ) {
			foreach ( (array) get_categories( array( 'number' => 8 ) ) as $qbs_category ) {
				$qbs_categories[] = array(
					'name' => (string) $qbs_category->name,
					'id'   => (int) $qbs_category->term_id,
					'url'  => function_exists( 'get_category_link' ) ? (string) get_category_link( (int) $qbs_category->term_id ) : '',
				);
			}
		}

		$qbs_cart_count = null;

		if ( ! $qbs_preview && function_exists( 'wc_cart_contents_count' ) && function_exists( 'wc_get_cart_url' ) ) {
			$qbs_cart_count = (int) wc_cart_contents_count();
		}

		$qbs_locale  = (string) get_locale();
		$qbs_langmap = array(
			'en' => 'English',
			'gu' => 'ગુજરાતી',
			'hi' => 'हिन्दी',
		);
		$qbs_lang    = isset( $qbs_langmap[ substr( $qbs_locale, 0, 2 ) ] ) ? $qbs_langmap[ substr( $qbs_locale, 0, 2 ) ] : $qbs_locale;
		?>
<div class="qbs<?php echo $qbs_sticky ? ' qbs--sticky' : ''; ?>">
	<?php if ( $qbs_top ) : ?>
		<div class="qbs__top">
			<div class="qbs__top-inner">
				<p class="qbs__note">
					<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 7h11v8H3zM14 10h4l3 3v2h-7z"/><circle cx="7" cy="17" r="1.6"/><circle cx="17" cy="17" r="1.6"/></svg>
					<?php echo esc_html( $qbs_note ); ?>
				</p>
				<div class="qbs__top-right">
					<span class="qbs__lang">
						<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.7 3.8 9S14.5 18.4 12 21c-2.5-2.6-3.8-5.7-3.8-9S9.5 5.6 12 3Z"/></svg>
						<?php echo esc_html( $qbs_lang ); ?>
						<svg class="qbs__caret" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6"/></svg>
					</span>
					<?php if ( $qbs_social_urls || $qbs_preview ) : ?>
						<div class="qbs__social" <?php echo $qbs_preview && ! $qbs_social_urls ? 'aria-hidden="true"' : ''; ?>>
							<?php
							$qbs_social_keys = array_keys( $qbs_social_icons );
							foreach ( $qbs_social_keys as $qbs_index => $qbs_network ) :
								$qbs_href = isset( $qbs_social_urls[ $qbs_index ] ) ? $qbs_social_urls[ $qbs_index ] : '';
								?>
								<?php if ( '' !== $qbs_href ) : ?>
									<a href="<?php echo esc_url( $qbs_href ); ?>" target="_blank" rel="noopener noreferrer">
										<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><?php echo $qbs_social_icons[ $qbs_network ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static icon paths from this file. ?></svg>
										<span class="qbs__sr"><?php echo esc_html( ucfirst( $qbs_network ) ); ?></span>
									</a>
								<?php elseif ( $qbs_preview ) : ?>
									<span>
										<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><?php echo $qbs_social_icons[ $qbs_network ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static icon paths from this file. ?></svg>
									</span>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<a class="qbs__account" href="<?php echo esc_url( (string) ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ? get_edit_profile_url() : wp_login_url() ) ); ?>">
						<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-3.3 0-7 1.7-7 4v2h14v-2c0-2.3-3.7-4-7-4Z"/></svg>
						<span class="qbs__sr"><?php esc_html_e( 'Account', 'qevora-layouts' ); ?></span>
					</a>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<div class="qbs__main">
		<div class="qbs__main-inner">
			<a class="qbs__logo" href="<?php echo esc_url( (string) home_url( '/' ) ); ?>">
				<?php if ( '' !== $qbs_logo ) : ?>
					<img class="qbs__logo-img" src="<?php echo esc_url( $qbs_logo ); ?>" alt="<?php echo esc_attr( $qbs_name ); ?>" />
				<?php else : ?>
					<span class="qbs__logo-tile" aria-hidden="true">
						<svg viewBox="0 0 24 24"><path d="M12 6c-1.8-1.3-4.2-1.6-7-1.6v13.2c2.8 0 5.2.3 7 1.6 1.8-1.3 4.2-1.6 7-1.6V4.4c-2.8 0-5.2.3-7 1.6Zm0 0v13.2"/></svg>
					</span>
				<?php endif; ?>
				<span class="qbs__logo-text">
					<span class="qbs__logo-name"><?php echo esc_html( $qbs_name ); ?></span>
					<span class="qbs__logo-tag"><?php echo esc_html( '' !== $qbs_tagline ? $qbs_tagline : __( 'Ocean of books', 'qevora-layouts' ) ); ?></span>
				</span>
			</a>

			<?php if ( $qbs_search ) : ?>
				<form class="qbs__search" role="search" method="get" action="<?php echo esc_url( (string) home_url( '/' ) ); ?>">
					<label class="qbs__sr" for="<?php echo esc_attr( 'qbs-cat-' . (int) $qevora_layout->id ); ?>"><?php esc_html_e( 'Search within a category', 'qevora-layouts' ); ?></label>
					<select class="qbs__search-cat" id="<?php echo esc_attr( 'qbs-cat-' . (int) $qevora_layout->id ); ?>" name="cat">
						<option value=""><?php esc_html_e( 'Categories', 'qevora-layouts' ); ?></option>
						<?php if ( $qbs_categories ) : ?>
							<?php foreach ( $qbs_categories as $qbs_category ) : ?>
								<option value="<?php echo esc_attr( (string) $qbs_category['id'] ); ?>"><?php echo esc_html( $qbs_category['name'] ); ?></option>
							<?php endforeach; ?>
						<?php elseif ( $qbs_preview ) : ?>
							<option value="0"><?php esc_html_e( 'Books', 'qevora-layouts' ); ?></option>
							<option value="0"><?php esc_html_e( 'Stationery', 'qevora-layouts' ); ?></option>
							<option value="0"><?php esc_html_e( 'Gifts', 'qevora-layouts' ); ?></option>
						<?php endif; ?>
					</select>
					<span class="qbs__search-divider" aria-hidden="true"></span>
					<label class="qbs__sr" for="<?php echo esc_attr( 'qbs-s-' . (int) $qevora_layout->id ); ?>"><?php esc_html_e( 'Search the site', 'qevora-layouts' ); ?></label>
					<input class="qbs__search-input" id="<?php echo esc_attr( 'qbs-s-' . (int) $qevora_layout->id ); ?>" type="search" name="s" placeholder="<?php esc_attr_e( 'Search Here...', 'qevora-layouts' ); ?>" />
					<button class="qbs__search-submit" type="submit">
						<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><line x1="16.5" y1="16.5" x2="21" y2="21"/></svg>
						<span class="qbs__sr"><?php esc_html_e( 'Search', 'qevora-layouts' ); ?></span>
					</button>
				</form>
			<?php endif; ?>

			<?php if ( '' !== $qbs_phone ) : ?>
				<a class="qbs__call" href="<?php echo esc_url( 'tel:' . rawurlencode( $qbs_phone ) ); ?>">
					<span class="qbs__call-icon">
						<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.24 11.4 11.4 0 0 0 3.6.58 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1 11.4 11.4 0 0 0 .57 3.6 1 1 0 0 1-.25 1Z"/></svg>
					</span>
					<span class="qbs__call-text">
						<span class="qbs__call-label"><?php esc_html_e( 'Call Us 24/7', 'qevora-layouts' ); ?></span>
						<span class="qbs__call-number"><?php echo esc_html( $qbs_phone ); ?></span>
					</span>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<div class="qbs__nav">
		<div class="qbs__nav-inner">
			<div class="qbs__cats-wrap">
				<button type="button" class="qbs__cats" data-qbs-cats aria-expanded="false" aria-controls="<?php echo esc_attr( $qbs_cats_id ); ?>">
					<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 6h16M4 12h10M4 18h16"/></svg>
					<?php esc_html_e( 'Categories', 'qevora-layouts' ); ?>
					<svg class="qbs__caret" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6"/></svg>
				</button>
				<ul class="qbs__cats-panel" id="<?php echo esc_attr( $qbs_cats_id ); ?>" data-qbs-cats-panel hidden>
					<?php if ( $qbs_categories ) : ?>
						<?php foreach ( $qbs_categories as $qbs_category ) : ?>
							<li><a href="<?php echo esc_url( $qbs_category['url'] ); ?>"><?php echo esc_html( $qbs_category['name'] ); ?></a></li>
						<?php endforeach; ?>
					<?php elseif ( $qbs_preview ) : ?>
						<li><span><?php esc_html_e( 'Books', 'qevora-layouts' ); ?></span></li>
						<li><span><?php esc_html_e( 'Stationery', 'qevora-layouts' ); ?></span></li>
						<li><span><?php esc_html_e( 'Gifts', 'qevora-layouts' ); ?></span></li>
					<?php endif; ?>
				</ul>
			</div>

			<div class="qbs__navwrap" id="<?php echo esc_attr( $qbs_nav ); ?>" data-qbs-navwrap>
				<?php if ( $qbs_has_menu ) : ?>
					<nav data-qbs-nav aria-label="<?php esc_attr_e( 'Primary menu', 'qevora-layouts' ); ?>">
						<?php
						wp_nav_menu(
							array(
								'menu'        => $qbs_menu_id,
								'container'   => '',
								'menu_class'  => 'qbs__menu',
								'fallback_cb' => false,
								'depth'       => 1,
							)
						);
						?>
					</nav>
				<?php elseif ( $qbs_preview ) : ?>
					<nav aria-hidden="true">
						<ul class="qbs__menu">
							<?php foreach ( array( 'Home', 'Shop', 'Vendor', 'Pages', 'Blog', 'Contact' ) as $qbs_sample ) : ?>
								<li><span><?php echo esc_html( $qbs_sample ); ?></span></li>
							<?php endforeach; ?>
						</ul>
					</nav>
				<?php endif; ?>
			</div>

			<?php if ( null !== $qbs_cart_count ) : ?>
				<a class="qbs__cart" href="<?php echo esc_url( (string) wc_get_cart_url() ); ?>">
					<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 7h12l-1.2 10.4a1.8 1.8 0 0 1-1.8 1.6H9a1.8 1.8 0 0 1-1.8-1.6Z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
					<span class="qbs__cart-count" aria-hidden="true"><?php echo esc_html( (string) $qbs_cart_count ); ?></span>
					<span class="qbs__sr"><?php esc_html_e( 'Cart', 'qevora-layouts' ); ?></span>
				</a>
			<?php endif; ?>

			<?php if ( $qbs_has_menu ) : ?>
				<button type="button" class="qbs__toggle" data-qbs-toggle aria-expanded="false" aria-controls="<?php echo esc_attr( $qbs_nav ); ?>">
					<span class="qbs__toggle-bar" aria-hidden="true"></span>
					<span class="qbs__toggle-bar" aria-hidden="true"></span>
					<span class="qbs__toggle-bar" aria-hidden="true"></span>
					<span class="qbs__sr"><?php esc_html_e( 'Menu', 'qevora-layouts' ); ?></span>
				</button>
			<?php endif; ?>
		</div>
	</div>
</div>
		<?php
		// Ships with the markup so the dropdowns and the mobile menu keep
		// working even when a theme or cache plugin drops footer scripts.
		?>
<script>
( function () {
	'use strict';
	var script = document.currentScript;
	var host = script ? script.previousElementSibling : null;
	var root = host && host.classList && host.classList.contains( 'qbs' ) ? host : null;
	if ( ! root ) { return; }
	var wrap = root.querySelector( '[data-qbs-navwrap]' );
	var toggle = root.querySelector( '[data-qbs-toggle]' );
	if ( wrap && toggle ) {
		toggle.addEventListener( 'click', function () {
			var open = wrap.classList.toggle( 'is-open' );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );
	}
	var catsButton = root.querySelector( '[data-qbs-cats]' );
	var catsPanel = root.querySelector( '[data-qbs-cats-panel]' );
	if ( catsButton && catsPanel ) {
		catsButton.addEventListener( 'click', function () {
			catsPanel.hidden = ! catsPanel.hidden;
			catsButton.setAttribute( 'aria-expanded', catsPanel.hidden ? 'false' : 'true' );
		} );
		document.addEventListener( 'click', function ( event ) {
			if ( catsPanel.hidden ) { return; }
			if ( catsPanel.contains( event.target ) || catsButton.contains( event.target ) ) { return; }
			catsPanel.hidden = true;
			catsButton.setAttribute( 'aria-expanded', 'false' );
		} );
		catsPanel.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				catsPanel.hidden = true;
				catsButton.setAttribute( 'aria-expanded', 'false' );
				catsButton.focus();
			}
		} );
	}
}() );
</script>
		<?php

		return ob_get_clean();
	},
);
