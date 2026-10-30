<?php
/**
 * Simple Footer Bar demo template.
 *
 * Rendered by Demo_Adapter. Receives:
 *
 * @var \QevoraLayouts\Core\Layout  $qevora_layout  The layout being rendered.
 * @var array<string,mixed>         $qevora_demo    Demo manifest.
 * @var int                         $qevora_menu_id Nav menu ID (0 when none).
 * @var array<string,mixed>         $qevora_demo_settings Customised settings for this demo.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
