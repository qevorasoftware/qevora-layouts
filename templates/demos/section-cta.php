<?php
/**
 * Qevora Layouts bundled demo: 'Call to Action Section'.
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
		'id'          => 'section-cta',
		'name'        => __( 'Call to Action Section', 'qevora-layouts' ),
		'type'        => 'section',
		'description' => __( 'A compact coloured band with a title, one line of text and a button - the classic \'ready to get started?\' strip. Rendered entirely by the plugin; texts, link and colours are customisable from the layout edit screen.', 'qevora-layouts' ),
		'menu'        => false,
		'menu_label'  => '',
		'tags'        => array( 'section', 'php', 'responsive', 'cta' ),
	),
	'settings' => array(
	'fields' => array(
		'title'            => array(
			'label'   => __( 'Title', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty for the default wording.', 'qevora-layouts' ),
		),
		'text'             => array(
			'label'   => __( 'Text', 'qevora-layouts' ),
			'type'    => 'textarea',
			'default' => '',
			'help'    => __( 'Leave empty for the default wording.', 'qevora-layouts' ),
		),
		'button_label'     => array(
			'label'   => __( 'Button label', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty for the default wording.', 'qevora-layouts' ),
		),
		'button_url'       => array(
			'label'   => __( 'Button link', 'qevora-layouts' ),
			'type'    => 'url',
			'default' => '',
			'help'    => __( 'Leave empty to link to the front page.', 'qevora-layouts' ),
		),
		'background_color' => array(
			'label'   => __( 'Background colour', 'qevora-layouts' ),
			'type'    => 'color',
			'default' => '#2271b1',
		),
		'text_color'       => array(
			'label'   => __( 'Text colour', 'qevora-layouts' ),
			'type'    => 'color',
			'default' => '#ffffff',
		),
	),
),
	'css'      => <<<'QEVORA_DEMO_CSS'
/*
 * Call to Action Section demo - scoped, responsive stylesheet.
 * Only used while this demo renders.
 */

.qdsc {
	background: var(--qdsc-bg, #2271b1);
	color: var(--qdsc-fg, #fff);
	padding: 40px 20px;
}

.qdsc__inner {
	max-width: 960px;
	margin: 0 auto;
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 24px;
	flex-wrap: wrap;
}

.qdsc__title {
	margin: 0 0 6px;
	font-size: 26px;
	line-height: 1.25;
	font-weight: 700;
	color: inherit;
}

.qdsc__text {
	margin: 0;
	font-size: 16px;
	line-height: 1.6;
	opacity: 0.92;
}

.qdsc__btn {
	flex: 0 0 auto;
	padding: 12px 24px;
	border-radius: 6px;
	background: var(--qdsc-fg, #fff);
	color: var(--qdsc-bg, #2271b1);
	text-decoration: none;
	font-weight: 600;
}

.qdsc__btn:hover,
.qdsc__btn:focus {
	filter: brightness(0.95);
	color: var(--qdsc-bg, #2271b1);
}

@media (max-width: 600px) {
	.qdsc__inner {
		flex-direction: column;
		align-items: flex-start;
	}

	.qdsc__title {
		font-size: 22px;
	}
}
QEVORA_DEMO_CSS,
	'js'       => <<<'QEVORA_DEMO_JS'

QEVORA_DEMO_JS,
	'render'   => static function ( $qevora_layout, $qevora_demo, $qevora_menu_id, $qevora_demo_settings ) {
		ob_start();

		
$qdsc       = (array) $qevora_demo_settings;
$qdsc_title = '' !== (string) $qdsc['title'] ? (string) $qdsc['title'] : __( 'Ready to get started?', 'qevora-layouts' );
$qdsc_text  = '' !== (string) $qdsc['text'] ? (string) $qdsc['text'] : __( 'Tell your visitors what to do next - one clear sentence is plenty.', 'qevora-layouts' );
$qdsc_label = '' !== (string) $qdsc['button_label'] ? (string) $qdsc['button_label'] : __( 'Contact us', 'qevora-layouts' );
$qdsc_url   = '' !== (string) $qdsc['button_url'] ? (string) $qdsc['button_url'] : (string) home_url( '/' );
?>
<section
	class="qdsc"
	style="--qdsc-bg: <?php echo esc_attr( (string) $qdsc['background_color'] ); ?>; --qdsc-fg: <?php echo esc_attr( (string) $qdsc['text_color'] ); ?>;"
>
	<div class="qdsc__inner">
		<div class="qdsc__copy">
			<h2 class="qdsc__title"><?php echo esc_html( $qdsc_title ); ?></h2>
			<?php if ( '' !== $qdsc_text ) : ?>
				<p class="qdsc__text"><?php echo esc_html( $qdsc_text ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( '' !== $qdsc_label ) : ?>
			<a class="qdsc__btn" href="<?php echo esc_url( $qdsc_url ); ?>"><?php echo esc_html( $qdsc_label ); ?></a>
		<?php endif; ?>
	</div>
</section>
		<?php

		return ob_get_clean();
	},
);
