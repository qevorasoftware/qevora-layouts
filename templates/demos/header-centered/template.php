<?php
/**
 * Centered Header demo template.
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

$qdhh_set      = (array) $qevora_demo_settings;
$qdhh_name     = '' !== (string) $qdhh_set['site_title'] ? (string) $qdhh_set['site_title'] : (string) get_bloginfo( 'name' );
$qdhh_tagline  = ! empty( $qdhh_set['show_tagline'] ) ? ( '' !== (string) $qdhh_set['tagline'] ? (string) $qdhh_set['tagline'] : (string) get_bloginfo( 'description' ) ) : '';
$qdhh_sticky   = ! empty( $qdhh_set['sticky_header'] );
$qdhh_home     = (string) home_url( '/' );
$qdhh_nav      = 'qdhh-nav-' . (int) $qevora_layout->id;
$qdhh_has_menu = $qevora_menu_id > 0 && function_exists( 'wp_nav_menu' );
?>
<div class="qdhh<?php echo $qdhh_sticky ? ' qdhh--sticky' : ''; ?>">
	<div class="qdhh__brand-row">
		<a class="qdhh__brand" href="<?php echo esc_url( $qdhh_home ); ?>">
			<span class="qdhh__name"><?php echo esc_html( $qdhh_name ); ?></span>
			<?php if ( '' !== $qdhh_tagline ) : ?>
				<span class="qdhh__tagline"><?php echo esc_html( $qdhh_tagline ); ?></span>
			<?php endif; ?>
		</a>
		<?php if ( $qdhh_has_menu ) : ?>
			<button type="button" class="qdhh__toggle" data-qdhh-toggle aria-expanded="false" aria-controls="<?php echo esc_attr( $qdhh_nav ); ?>">
				<span class="qdhh__sr"><?php esc_html_e( 'Menu', 'qevora-layouts' ); ?></span>
				<span aria-hidden="true">&#9776;</span>
			</button>
		<?php endif; ?>
	</div>

	<?php if ( $qdhh_has_menu ) : ?>
		<nav id="<?php echo esc_attr( $qdhh_nav ); ?>" class="qdhh__nav" data-qdhh-nav aria-label="<?php esc_attr_e( 'Primary menu', 'qevora-layouts' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'menu'        => $qevora_menu_id,
					'container'   => '',
					'menu_class'  => 'qdhh__menu',
					'fallback_cb' => false,
					'depth'       => 1,
				)
			);
			?>
		</nav>
	<?php endif; ?>
</div>
