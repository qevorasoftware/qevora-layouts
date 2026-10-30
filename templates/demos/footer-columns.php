<?php
/**
 * Qevora Layouts bundled demo: 'Columns Footer'.
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
		'id'          => 'footer-columns',
		'name'        => __( 'Columns Footer', 'qevora-layouts' ),
		'type'        => 'footer',
		'description' => __( 'A three column footer: your site name and tagline, an optional navigation menu, and contact details pulled from the site info. The grid folds into a single column on phones. Rendered by the plugin with its own stylesheet - nothing is stored in the editor.', 'qevora-layouts' ),
		'menu'        => true,
		'menu_label'  => __( 'Create a sample footer menu (Home plus your published pages)', 'qevora-layouts' ),
		'tags'        => array( 'footer', 'php', 'responsive', 'menu', 'columns' ),
	),
	'settings' => array(
	'fields' => array(
		'heading'       => array(
			'label'   => __( 'About column heading', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty to use the site title.', 'qevora-layouts' ),
		),
		'tagline'       => array(
			'label'   => __( 'About text', 'qevora-layouts' ),
			'type'    => 'textarea',
			'default' => '',
			'help'    => __( 'Leave empty to use the site tagline.', 'qevora-layouts' ),
		),
		'explore_title' => array(
			'label'   => __( 'Menu column heading', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty for the default wording.', 'qevora-layouts' ),
		),
		'contact_title' => array(
			'label'   => __( 'Contact column heading', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty for the default wording.', 'qevora-layouts' ),
		),
		'copyright'     => array(
			'label'   => __( 'Copyright line', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty for an automatic "(c) year site name" line.', 'qevora-layouts' ),
		),
	),
),
	'css'      => <<<'QEVORA_DEMO_CSS'
/* Columns Footer demo - scoped, responsive, no dependencies. */
.qdfc{--qdfc-bg:#111827;--qdfc-fg:#e5e7eb;--qdfc-muted:#9ca3af;--qdfc-accent:#60a5fa;--qdfc-line:rgba(229,231,235,.12);background:var(--qdfc-bg);color:var(--qdfc-fg);font-size:16px;line-height:1.6;padding:44px 20px 24px}
.qdfc__grid{display:grid;grid-template-columns:2fr 1fr 1.4fr;gap:32px;max-width:1200px;margin:0 auto}
.qdfc__heading{margin:0 0 10px;font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.14em;color:var(--qdfc-muted)}
.qdfc__col--about .qdfc__heading{font-size:1.15rem;text-transform:none;letter-spacing:0;color:var(--qdfc-fg)}
.qdfc__text{margin:0 0 8px;color:var(--qdfc-muted);font-size:.95rem}
.qdfc__link{color:var(--qdfc-accent);text-decoration:none}
.qdfc__link:hover,.qdfc__link:focus{text-decoration:underline}
.qdfc__menu{margin:0;padding:0;list-style:none}
.qdfc__menu li{margin:0 0 6px}
.qdfc__menu a{color:var(--qdfc-muted);text-decoration:none;font-size:.95rem}
.qdfc__menu a:hover,.qdfc__menu a:focus,.qdfc__menu .current-menu-item>a{color:var(--qdfc-accent)}
.qdfc__credit{max-width:1200px;margin:34px auto 0;padding-top:18px;border-top:1px solid var(--qdfc-line);color:var(--qdfc-muted);font-size:.85rem;text-align:center}
@media (max-width:782px){
	.qdfc{padding:34px 16px 20px}
	.qdfc__grid{grid-template-columns:1fr;gap:24px}
	.qdfc__credit{margin-top:24px}
}
QEVORA_DEMO_CSS,
	'js'       => <<<'QEVORA_DEMO_JS'

QEVORA_DEMO_JS,
	'render'   => static function ( $qevora_layout, $qevora_demo, $qevora_menu_id, $qevora_demo_settings ) {
		ob_start();

		
$qdfc_set      = (array) $qevora_demo_settings;
$qdfc_name     = '' !== (string) $qdfc_set['heading'] ? (string) $qdfc_set['heading'] : (string) get_bloginfo( 'name' );
$qdfc_tagline  = '' !== (string) $qdfc_set['tagline'] ? (string) $qdfc_set['tagline'] : (string) get_bloginfo( 'description' );
$qdfc_explore  = '' !== (string) $qdfc_set['explore_title'] ? (string) $qdfc_set['explore_title'] : __( 'Explore', 'qevora-layouts' );
$qdfc_contact  = '' !== (string) $qdfc_set['contact_title'] ? (string) $qdfc_set['contact_title'] : __( 'Contact', 'qevora-layouts' );
$qdfc_copy     = (string) $qdfc_set['copyright'];
$qdfc_admin    = (string) get_bloginfo( 'admin_email' );
$qdfc_year     = gmdate( 'Y' );
$qdfc_has_menu = $qevora_menu_id > 0 && function_exists( 'wp_nav_menu' );
?>
<div class="qdfc">
	<div class="qdfc__grid">
		<div class="qdfc__col qdfc__col--about">
			<h2 class="qdfc__heading"><?php echo esc_html( $qdfc_name ); ?></h2>
			<?php if ( '' !== $qdfc_tagline ) : ?>
				<p class="qdfc__text"><?php echo esc_html( $qdfc_tagline ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( $qdfc_has_menu ) : ?>
			<div class="qdfc__col qdfc__col--menu">
				<h2 class="qdfc__heading"><?php echo esc_html( $qdfc_explore ); ?></h2>
				<?php
				wp_nav_menu(
					array(
						'menu'        => $qevora_menu_id,
						'container'   => '',
						'menu_class'  => 'qdfc__menu',
						'fallback_cb' => false,
						'depth'       => 1,
					)
				);
				?>
			</div>
		<?php endif; ?>

		<div class="qdfc__col qdfc__col--contact">
			<h2 class="qdfc__heading"><?php echo esc_html( $qdfc_contact ); ?></h2>
			<?php if ( '' !== $qdfc_admin ) : ?>
				<p class="qdfc__text">
					<a class="qdfc__link" href="mailto:<?php echo esc_attr( antispambot( $qdfc_admin ) ); ?>"><?php echo esc_html( antispambot( $qdfc_admin ) ); ?></a>
				</p>
			<?php endif; ?>
			<p class="qdfc__text">
				<a class="qdfc__link" href="<?php echo esc_url( (string) home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to home', 'qevora-layouts' ); ?></a>
			</p>
		</div>
	</div>

	<p class="qdfc__credit">
		<?php
		if ( '' !== $qdfc_copy ) {
			echo esc_html( $qdfc_copy );
		} else {
			echo esc_html(
				sprintf(
					/* translators: 1: current year, 2: site name. */
					__( '&copy; %1$s %2$s. All rights reserved.', 'qevora-layouts' ),
					$qdfc_year,
					$qdfc_name
				)
			);
		}
		?>
	</p>
</div>
		<?php

		return ob_get_clean();
	},
);
