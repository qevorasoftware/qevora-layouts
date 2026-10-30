<?php
/**
 * System status view.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 *
 * @var array<string,mixed> $context View context prepared by System_Status_Page::render().
 */

use QevoraLayouts\Support\Environment;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$qevora_report = isset( $context['report'] ) && is_array( $context['report'] ) ? $context['report'] : array();
$qevora_text   = isset( $context['report_text'] ) ? (string) $context['report_text'] : Environment::report_to_text( $qevora_report );
$qevora_log    = isset( $context['log'] ) && is_array( $context['log'] ) ? $context['log'] : array();
$qevora_flush  = isset( $context['flush_url'] ) ? (string) $context['flush_url'] : '';
?>
<div class="wrap qevora-layouts-wrap qevora-layouts-status">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'System Status', 'qevora-layouts' ); ?></h1>

	<?php if ( '' !== $qevora_flush ) : ?>
		<a class="page-title-action" href="<?php echo esc_url( $qevora_flush ); ?>"><?php esc_html_e( 'Clear layout caches', 'qevora-layouts' ); ?></a>
	<?php endif; ?>

	<hr class="wp-header-end" />

	<p class="qevora-layouts-intro">
		<?php esc_html_e( 'This page is only visible to administrators. It contains no secrets or tokens. Share the copy below with your developer or with support, never in a public place.', 'qevora-layouts' ); ?>
	</p>

	<p>
		<button type="button" class="button button-secondary" id="qevora-copy-status" data-target="qevora-status-text">
			<?php esc_html_e( 'Copy status report', 'qevora-layouts' ); ?>
		</button>
		<button type="button" class="button button-link" id="qevora-toggle-status-text" aria-expanded="false" aria-controls="qevora-status-text-wrap">
			<?php esc_html_e( 'Show as text', 'qevora-layouts' ); ?>
		</button>
	</p>

	<div class="qevora-status__text" id="qevora-status-text-wrap" hidden>
		<label class="screen-reader-text" for="qevora-status-text"><?php esc_html_e( 'Status report as plain text', 'qevora-layouts' ); ?></label>
		<textarea id="qevora-status-text" readonly="readonly" rows="14" class="large-text code"><?php echo esc_textarea( $qevora_text ); ?></textarea>
	</div>

	<?php foreach ( $qevora_report as $qevora_section ) : ?>
		<?php
		if ( empty( $qevora_section['title'] ) || empty( $qevora_section['rows'] ) ) {
			continue;
		}
		?>
		<div class="card qevora-status__section">
			<h2 class="title"><?php echo esc_html( (string) $qevora_section['title'] ); ?></h2>

			<table class="widefat striped qevora-status__table">
				<caption class="screen-reader-text"><?php echo esc_html( (string) $qevora_section['title'] ); ?></caption>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Setting', 'qevora-layouts' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Value', 'qevora-layouts' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( (array) $qevora_section['rows'] as $qevora_row ) : ?>
						<?php
						$qevora_status = isset( $qevora_row['status'] ) ? (string) $qevora_row['status'] : '';
						$qevora_value  = isset( $qevora_row['value'] ) ? $qevora_row['value'] : '';

						if ( is_bool( $qevora_value ) ) {
							$qevora_value = $qevora_value ? __( 'Yes', 'qevora-layouts' ) : __( 'No', 'qevora-layouts' );
						} elseif ( is_array( $qevora_value ) ) {
							$qevora_value = implode( ', ', array_map( 'strval', $qevora_value ) );
						} elseif ( is_scalar( $qevora_value ) ) {
							$qevora_value = (string) $qevora_value;
						} else {
							$qevora_value = '';
						}

						if ( '' === $qevora_value ) {
							$qevora_value = '—';
						}
						?>
						<tr class="qevora-status__row qevora-status__row--<?php echo esc_attr( in_array( $qevora_status, array( 'ok', 'warn', 'info' ), true ) ? $qevora_status : 'plain' ); ?>">
							<th scope="row">
								<?php echo esc_html( isset( $qevora_row['label'] ) ? (string) $qevora_row['label'] : '' ); ?>
								<?php if ( ! empty( $qevora_row['note'] ) ) : ?>
									<p class="description"><?php echo esc_html( (string) $qevora_row['note'] ); ?></p>
								<?php endif; ?>
							</th>
							<td>
								<?php echo esc_html( $qevora_value ); ?>
								<?php if ( 'ok' === $qevora_status ) : ?>
									<span class="dashicons dashicons-yes-alt qevora-status__icon qevora-status__icon--ok" aria-hidden="true"></span>
									<span class="screen-reader-text"><?php esc_html_e( 'OK', 'qevora-layouts' ); ?></span>
								<?php elseif ( 'warn' === $qevora_status ) : ?>
									<span class="dashicons dashicons-warning qevora-status__icon qevora-status__icon--warn" aria-hidden="true"></span>
									<span class="screen-reader-text"><?php esc_html_e( 'Needs attention', 'qevora-layouts' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endforeach; ?>

	<div class="card qevora-status__section">
		<h2 class="title"><?php esc_html_e( 'Troubleshooting', 'qevora-layouts' ); ?></h2>
		<ol class="qevora-status__steps">
			<li><?php esc_html_e( 'A layout does not appear: check that it is published (enabled), that its display conditions include the current view, and that header or footer rendering is not switched off in Settings.', 'qevora-layouts' ); ?></li>
			<li><?php esc_html_e( 'Two headers appear: your theme still prints its own header. Add the theme header selector to Settings → Theme integration, or set the layout placement to "Manual only" and call qevora_layouts_the_header() in a child theme.', 'qevora-layouts' ); ?></li>
			<li><?php esc_html_e( 'Nothing changed after an edit: click "Clear layout caches" above, then reload the page. Page caches and CDNs also need to be purged.', 'qevora-layouts' ); ?></li>
			<li><?php esc_html_e( 'A block theme: get_header and get_footer do not fire. Use wp_body_open, wp_footer, a custom hook or the manual placement.', 'qevora-layouts' ); ?></li>
			<li><?php esc_html_e( 'Import failed: read the message shown on the Template Library screen; it names the exact rule the file broke.', 'qevora-layouts' ); ?></li>
			<li><?php esc_html_e( 'Deeper diagnostics: enable Debug mode in Settings, reproduce the problem, then read the messages below and in your PHP error log.', 'qevora-layouts' ); ?></li>
		</ol>
	</div>

	<div class="card qevora-status__section">
		<h2 class="title"><?php esc_html_e( 'Debug messages (this request)', 'qevora-layouts' ); ?></h2>

		<?php if ( empty( $qevora_log ) ) : ?>
			<p class="description"><?php esc_html_e( 'Debug mode is off, or nothing was logged during this request. Messages are also written to the PHP error log when debug mode is on.', 'qevora-layouts' ); ?></p>
		<?php else : ?>
			<table class="widefat striped qevora-status__table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Time (UTC)', 'qevora-layouts' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Level', 'qevora-layouts' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Message', 'qevora-layouts' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_reverse( $qevora_log ) as $qevora_entry ) : ?>
						<tr>
							<td><?php echo esc_html( isset( $qevora_entry['time'] ) ? (string) $qevora_entry['time'] : '' ); ?></td>
							<td><?php echo esc_html( isset( $qevora_entry['level'] ) ? (string) $qevora_entry['level'] : '' ); ?></td>
							<td>
								<?php echo esc_html( isset( $qevora_entry['message'] ) ? (string) $qevora_entry['message'] : '' ); ?>
								<?php if ( ! empty( $qevora_entry['context'] ) ) : ?>
									<code><?php echo esc_html( wp_json_encode( $qevora_entry['context'] ) ); ?></code>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
