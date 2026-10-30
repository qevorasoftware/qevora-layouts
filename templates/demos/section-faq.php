<?php
/**
 * Qevora Layouts bundled demo: 'Frequently Asked Questions'.
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
		'id'          => 'section-faq',
		'name'        => __( 'Frequently Asked Questions', 'qevora-layouts' ),
		'type'        => 'section',
		'description' => __( 'An accessible question-and-answer accordion: plain HTML, its own stylesheet and a few lines of vanilla JavaScript for the open/close behaviour. Questions and answers are edited as plain lines of text on the layout edit screen.', 'qevora-layouts' ),
		'menu'        => false,
		'menu_label'  => '',
		'tags'        => array( 'section', 'php', 'responsive', 'faq' ),
	),
	'settings' => array(
	'fields' => array(
		'title'     => array(
			'label'   => __( 'Section title', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty for the default wording.', 'qevora-layouts' ),
		),
		'items'     => array(
			'label'   => __( 'Questions and answers', 'qevora-layouts' ),
			'type'    => 'textarea',
			'default' => "How do I edit this demo? | Open this layout and use the demo settings box - no block editor needed.
Where does the markup come from? | From a PHP template file inside the plugin, rendered on every request.
Is it responsive? | Yes, every demo ships its own responsive stylesheet.",
			'help'    => __( 'One entry per line, written as: question | answer', 'qevora-layouts' ),
		),
		'open_first' => array(
			'label'   => __( 'Open the first question by default', 'qevora-layouts' ),
			'type'    => 'checkbox',
			'default' => true,
		),
	),
),
	'css'      => <<<'QEVORA_DEMO_CSS'
/*
 * Frequently Asked Questions demo - scoped, responsive stylesheet.
 * Only used while this demo renders.
 */

.qdsf {
	padding: 48px 20px;
}

.qdsf__inner {
	max-width: 760px;
	margin: 0 auto;
}

.qdsf__title {
	margin: 0 0 20px;
	font-size: 28px;
	font-weight: 700;
}

.qdsf__item {
	border: 1px solid #dcdcde;
	border-radius: 6px;
	margin-bottom: 10px;
	background: #fff;
	overflow: hidden;
}

.qdsf__q-head {
	margin: 0;
	font-size: inherit;
}

.qdsf__q {
	width: 100%;
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 14px 16px;
	background: none;
	border: 0;
	cursor: pointer;
	font: inherit;
	font-weight: 600;
	text-align: left;
	color: inherit;
}

.qdsf__chev {
	flex: 0 0 auto;
	width: 10px;
	height: 10px;
	border-right: 2px solid currentColor;
	border-bottom: 2px solid currentColor;
	transform: rotate(45deg);
	transition: transform 0.2s ease;
}

.qdsf__item--open .qdsf__chev {
	transform: rotate(225deg);
}

.qdsf__a {
	padding: 0 16px 14px;
	line-height: 1.6;
}

.qdsf__a p {
	margin: 0;
}

.qdsf__empty {
	color: #646970;
}

@media (max-width: 600px) {
	.qdsf {
		padding: 32px 14px;
	}

	.qdsf__title {
		font-size: 23px;
	}
}
QEVORA_DEMO_CSS,
	'js'       => <<<'QEVORA_DEMO_JS'
/**
 * Frequently Asked Questions demo - accordion behaviour.
 *
 * Loaded only while this demo renders. Plain JavaScript, no dependencies.
 */
( function () {
	'use strict';

	function init( root ) {
		var toggles = root.querySelectorAll( '[data-qdsf-toggle]' );

		Array.prototype.forEach.call( toggles, function ( button ) {
			button.addEventListener( 'click', function () {
				var item = button.closest( '.qdsf__item' );
				var panelId = button.getAttribute( 'aria-controls' );
				var panel = panelId ? document.getElementById( panelId ) : null;
				var open = button.getAttribute( 'aria-expanded' ) === 'true';

				button.setAttribute( 'aria-expanded', open ? 'false' : 'true' );

				if ( panel ) {
					if ( open ) {
						panel.setAttribute( 'hidden', 'hidden' );
					} else {
						panel.removeAttribute( 'hidden' );
					}
				}

				if ( item ) {
					item.classList.toggle( 'qdsf__item--open', ! open );
				}
			} );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			Array.prototype.forEach.call( document.querySelectorAll( '.qdsf' ), init );
		} );
	} else {
		Array.prototype.forEach.call( document.querySelectorAll( '.qdsf' ), init );
	}
}() );
QEVORA_DEMO_JS,
	'render'   => static function ( $qevora_layout, $qevora_demo, $qevora_menu_id, $qevora_demo_settings ) {
		ob_start();

		
$qdsf      = (array) $qevora_demo_settings;
$qdsf_rows = array();

foreach ( preg_split( '/\r\n|\r|\n/', (string) $qdsf['items'] ) as $qdsf_line ) {
	$qdsf_line = trim( (string) $qdsf_line );

	if ( '' === $qdsf_line ) {
		continue;
	}

	$qdsf_parts = array_map( 'trim', explode( '|', $qdsf_line, 2 ) );

	if ( '' !== $qdsf_parts[0] ) {
		$qdsf_rows[] = array(
			'q'      => $qdsf_parts[0],
			'a'      => isset( $qdsf_parts[1] ) ? $qdsf_parts[1] : '',
			'open'   => (bool) $qdsf['open_first'] && empty( $qdsf_rows ),
		);
	}
}
?>
<section class="qdsf">
	<div class="qdsf__inner">
		<h2 class="qdsf__title">
			<?php
			echo esc_html(
				'' !== (string) $qdsf['title'] ? (string) $qdsf['title'] : __( 'Frequently asked questions', 'qevora-layouts' )
			);
			?>
		</h2>

		<?php if ( empty( $qdsf_rows ) ) : ?>
			<p class="qdsf__empty"><?php esc_html_e( 'Add questions in the demo settings of this layout.', 'qevora-layouts' ); ?></p>
		<?php else : ?>
			<div class="qdsf__list" data-qdsf-list>
				<?php foreach ( $qdsf_rows as $qdsf_i => $qdsf_row ) : ?>
					<div class="qdsf__item<?php echo $qdsf_row['open'] ? ' qdsf__item--open' : ''; ?>">
						<h3 class="qdsf__q-head">
							<button type="button" class="qdsf__q" data-qdsf-toggle aria-expanded="<?php echo $qdsf_row['open'] ? 'true' : 'false'; ?>" aria-controls="qdsf-a-<?php echo (int) $qevora_layout->id; ?>-<?php echo (int) $qdsf_i; ?>">
								<span class="qdsf__q-text"><?php echo esc_html( $qdsf_row['q'] ); ?></span>
								<span class="qdsf__chev" aria-hidden="true"></span>
							</button>
						</h3>
						<div class="qdsf__a" id="qdsf-a-<?php echo (int) $qevora_layout->id; ?>-<?php echo (int) $qdsf_i; ?>"<?php echo $qdsf_row['open'] ? '' : ' hidden'; ?>>
							<p><?php echo esc_html( $qdsf_row['a'] ); ?></p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
		<?php

		return ob_get_clean();
	},
);
