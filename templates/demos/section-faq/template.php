<?php
/**
 * Frequently Asked Questions demo template.
 *
 * Rendered by Demo_Adapter. Receives:
 *
 * @var \QevoraLayouts\Core\Layout  $qevora_layout        The layout being rendered.
 * @var array<string,mixed>         $qevora_demo          Demo manifest.
 * @var int                         $qevora_menu_id       Nav menu ID (0 when none).
 * @var array<string,mixed>         $qevora_demo_settings Customised settings for this demo.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
