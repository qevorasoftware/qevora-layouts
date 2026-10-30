<?php
/**
 * Qevora Layouts bundled demo: 'Hero Section'.
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
		'id'          => 'section-hero',
		'name'        => __( 'Hero Section', 'qevora-layouts' ),
		'type'        => 'section',
		'description' => __( 'A full-width welcome band with a heading, a supporting line and one call-to-action button. Rendered entirely by the plugin with plain HTML and its own responsive stylesheet; every text, the alignment and the colours are customisable from the layout edit screen.', 'qevora-layouts' ),
		'menu'        => false,
		'menu_label'  => '',
		'tags'        => array( 'section', 'php', 'responsive', 'hero' ),
	),
	'settings' => array(
	'fields' => array(
		'heading'          => array(
			'label'   => __( 'Heading', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty to use the site title.', 'qevora-layouts' ),
		),
		'subheading'       => array(
			'label'   => __( 'Supporting line', 'qevora-layouts' ),
			'type'    => 'textarea',
			'default' => '',
			'help'    => __( 'Leave empty to use the site tagline.', 'qevora-layouts' ),
		),
		'button_label'     => array(
			'label'   => __( 'Button label', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty to hide the button.', 'qevora-layouts' ),
		),
		'button_url'       => array(
			'label'   => __( 'Button link', 'qevora-layouts' ),
			'type'    => 'url',
			'default' => '',
			'help'    => __( 'Leave empty to link to the front page.', 'qevora-layouts' ),
		),
		'align'            => array(
			'label'   => __( 'Text alignment', 'qevora-layouts' ),
			'type'    => 'select',
			'default' => 'center',
			'options' => array(
				'center' => __( 'Centred', 'qevora-layouts' ),
				'left'   => __( 'Left', 'qevora-layouts' ),
			),
		),
		'background_color' => array(
			'label'   => __( 'Background colour', 'qevora-layouts' ),
			'type'    => 'color',
			'default' => '#0f172a',
		),
		'accent_color'     => array(
			'label'   => __( 'Button colour', 'qevora-layouts' ),
			'type'    => 'color',
			'default' => '#2271b1',
		),
	),
),
	'css'      => <<<'QEVORA_DEMO_CSS'
/*
 * Hero Section demo - scoped, responsive stylesheet.
 * Only used while this demo renders. Colours come from the demo settings
 * through the custom properties set on the root element.
 */

.qdsh {
	background: var(--qdsh-bg, #0f172a);
	color: #fff;
	padding: 72px 20px;
}

.qdsh__inner {
	max-width: 860px;
	margin: 0 auto;
	text-align: center;
}

.qdsh--left .qdsh__inner {
	text-align: left;
	margin: 0;
}

.qdsh__heading {
	margin: 0 0 12px;
	font-size: 40px;
	line-height: 1.15;
	font-weight: 700;
	color: inherit;
}

.qdsh__sub {
	margin: 0 0 26px;
	font-size: 18px;
	line-height: 1.6;
	opacity: 0.85;
}

.qdsh__btn {
	display: inline-block;
	padding: 12px 26px;
	border-radius: 6px;
	background: var(--qdsh-accent, #2271b1);
	color: #fff;
	text-decoration: none;
	font-weight: 600;
}

.qdsh__btn:hover,
.qdsh__btn:focus {
	filter: brightness(1.1);
	color: #fff;
}

@media (max-width: 600px) {
	.qdsh {
		padding: 48px 16px;
	}

	.qdsh__heading {
		font-size: 28px;
	}

	.qdsh__sub {
		font-size: 16px;
	}
}
QEVORA_DEMO_CSS,
	'js'       => <<<'QEVORA_DEMO_JS'

QEVORA_DEMO_JS,
	'render'   => static function ( $qevora_layout, $qevora_demo, $qevora_menu_id, $qevora_demo_settings ) {
		ob_start();

		
$qdsh         = (array) $qevora_demo_settings;
$qdsh_heading = '' !== (string) $qdsh['heading'] ? (string) $qdsh['heading'] : (string) get_bloginfo( 'name' );
$qdsh_sub     = '' !== (string) $qdsh['subheading'] ? (string) $qdsh['subheading'] : (string) get_bloginfo( 'description' );
$qdsh_url     = '' !== (string) $qdsh['button_url'] ? (string) $qdsh['button_url'] : (string) home_url( '/' );
$qdsh_align   = 'left' === $qdsh['align'] ? ' qdsh--left' : '';
?>
<section
	class="qdsh<?php echo esc_attr( $qdsh_align ); ?>"
	style="--qdsh-bg: <?php echo esc_attr( (string) $qdsh['background_color'] ); ?>; --qdsh-accent: <?php echo esc_attr( (string) $qdsh['accent_color'] ); ?>;"
>
	<div class="qdsh__inner">
		<h2 class="qdsh__heading"><?php echo esc_html( $qdsh_heading ); ?></h2>
		<?php if ( '' !== $qdsh_sub ) : ?>
			<p class="qdsh__sub"><?php echo esc_html( $qdsh_sub ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== (string) $qdsh['button_label'] ) : ?>
			<a class="qdsh__btn" href="<?php echo esc_url( $qdsh_url ); ?>"><?php echo esc_html( (string) $qdsh['button_label'] ); ?></a>
		<?php endif; ?>
	</div>
</section>
		<?php

		return ob_get_clean();
	},
);
