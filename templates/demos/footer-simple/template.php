<?php
/**
 * Simple Footer Bar demo template.
 *
 * Rendered by Demo_Adapter. Receives:
 *
 * @var \QevoraLayouts\Core\Layout  $qevora_layout  The layout being rendered.
 * @var array<string,mixed>         $qevora_demo    Demo manifest.
 * @var int                         $qevora_menu_id Nav menu ID (0 when none).
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$qdfs_name = (string) get_bloginfo( 'name' );
$qdfs_year = gmdate( 'Y' );
?>
<div class="qdfs">
	<div class="qdfs__inner">
		<p class="qdfs__copy">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: current year, 2: site name. */
					__( '&copy; %1$s %2$s', 'qevora-layouts' ),
					$qdfs_year,
					$qdfs_name
				)
			);
			?>
		</p>
		<button type="button" class="qdfs__top" data-qdfs-top>
			<span aria-hidden="true">&uarr;</span>
			<?php esc_html_e( 'Back to top', 'qevora-layouts' ); ?>
		</button>
	</div>
</div>
