<?php
/**
 * Hero Section demo template.
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
