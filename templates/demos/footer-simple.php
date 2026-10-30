<?php
/**
 * Qevora Layouts bundled demo: 'Simple Footer Bar'.
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
		'id'          => 'footer-simple',
		'name'        => __( 'Simple Footer Bar', 'qevora-layouts' ),
		'type'        => 'footer',
		'description' => __( 'A slim footer bar with the copyright line and a smooth back-to-top button. Tiny, fast and fully responsive - rendered by the plugin with its own stylesheet and a few lines of vanilla JavaScript.', 'qevora-layouts' ),
		'menu'        => false,
		'menu_label'  => '',
		'tags'        => array( 'footer', 'php', 'responsive', 'minimal' ),
	),
	'settings' => array(
	'fields' => array(
		'copyright'        => array(
			'label'   => __( 'Copyright line', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty for an automatic "(c) year site name" line.', 'qevora-layouts' ),
		),
		'show_back_to_top' => array(
			'label'   => __( 'Show the back-to-top button', 'qevora-layouts' ),
			'type'    => 'checkbox',
			'default' => true,
		),
		'top_label'        => array(
			'label'   => __( 'Back-to-top label', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty for the default wording.', 'qevora-layouts' ),
		),
	),
),
	'css'      => <<<'QEVORA_DEMO_CSS'
/* Simple Footer Bar demo - scoped, responsive, no dependencies. */
.qdfs{--qdfs-bg:#1e293b;--qdfs-fg:#e2e8f0;--qdfs-muted:#94a3b8;--qdfs-accent:#34d399;background:var(--qdfs-bg);color:var(--qdfs-fg);font-size:15px;line-height:1.5}
.qdfs__inner{display:flex;align-items:center;justify-content:space-between;gap:12px;max-width:1200px;margin:0 auto;padding:16px 20px}
.qdfs__copy{margin:0;color:var(--qdfs-muted)}
.qdfs__top{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:transparent;border:1px solid rgba(226,232,240,.3);border-radius:999px;color:var(--qdfs-fg);font-size:.85rem;font-weight:600;cursor:pointer;transition:border-color .15s ease,color .15s ease}
.qdfs__top:hover,.qdfs__top:focus{border-color:var(--qdfs-accent);color:var(--qdfs-accent)}
@media (max-width:480px){
	.qdfs__inner{flex-direction:column;text-align:center;padding:16px}
}
QEVORA_DEMO_CSS,
	'js'       => <<<'QEVORA_DEMO_JS'
/**
 * Simple Footer Bar demo: smooth back-to-top button. Vanilla JS.
 */
( function () {
	'use strict';

	function init( root ) {
		if ( root.getAttribute( 'data-qdfs-ready' ) === '1' ) {
			return;
		}
		root.setAttribute( 'data-qdfs-ready', '1' );

		var button = root.querySelector( '[data-qdfs-top]' );

		if ( ! button ) {
			return;
		}

		button.addEventListener( 'click', function () {
			window.scrollTo( { top: 0, behavior: 'smooth' } );
		} );
	}

	function boot() {
		var roots = document.querySelectorAll( '.qdfs' );
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

		
$qdfs_set  = (array) $qevora_demo_settings;
$qdfs_name = (string) get_bloginfo( 'name' );
$qdfs_year = gmdate( 'Y' );
$qdfs_copy = (string) $qdfs_set['copyright'];
$qdfs_top  = '' !== (string) $qdfs_set['top_label'] ? (string) $qdfs_set['top_label'] : __( 'Back to top', 'qevora-layouts' );
$qdfs_show = ! empty( $qdfs_set['show_back_to_top'] );
?>
<div class="qdfs">
	<div class="qdfs__inner">
		<p class="qdfs__copy">
			<?php
			if ( '' !== $qdfs_copy ) {
				echo esc_html( $qdfs_copy );
			} else {
				echo esc_html(
					sprintf(
						/* translators: 1: current year, 2: site name. */
						__( '&copy; %1$s %2$s', 'qevora-layouts' ),
						$qdfs_year,
						$qdfs_name
					)
				);
			}
			?>
		</p>
		<?php if ( $qdfs_show ) : ?>
			<button type="button" class="qdfs__top" data-qdfs-top>
				<span aria-hidden="true">&uarr;</span>
				<?php echo esc_html( $qdfs_top ); ?>
			</button>
		<?php endif; ?>
	</div>
</div>
		<?php

		return ob_get_clean();
	},
);
