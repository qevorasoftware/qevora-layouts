<?php
/**
 * Call to Action Section demo template.
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
