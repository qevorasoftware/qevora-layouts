<?php
/**
 * Classic Header demo template.
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

$qdhc_name = (string) get_bloginfo( 'name' );
$qdhc_home = (string) home_url( '/' );
$qdhc_mark = function_exists( 'mb_substr' ) ? mb_substr( $qdhc_name, 0, 1 ) : substr( $qdhc_name, 0, 1 );
$qdhc_nav  = 'qdhc-nav-' . (int) $qevora_layout->id;
?>
<div class="qdhc">
	<div class="qdhc__inner">
		<a class="qdhc__brand" href="<?php echo esc_url( $qdhc_home ); ?>">
			<span class="qdhc__mark" aria-hidden="true"><?php echo esc_html( $qdhc_mark ); ?></span>
			<span class="qdhc__name"><?php echo esc_html( $qdhc_name ); ?></span>
		</a>

		<?php if ( $qevora_menu_id > 0 && function_exists( 'wp_nav_menu' ) ) : ?>
			<button type="button" class="qdhc__toggle" data-qdhc-toggle aria-expanded="false" aria-controls="<?php echo esc_attr( $qdhc_nav ); ?>">
				<span class="qdhc__toggle-bar" aria-hidden="true"></span>
				<span class="qdhc__toggle-bar" aria-hidden="true"></span>
				<span class="qdhc__toggle-bar" aria-hidden="true"></span>
				<span class="qdhc__sr"><?php esc_html_e( 'Menu', 'qevora-layouts' ); ?></span>
			</button>
			<nav id="<?php echo esc_attr( $qdhc_nav ); ?>" class="qdhc__nav" data-qdhc-nav aria-label="<?php esc_attr_e( 'Primary menu', 'qevora-layouts' ); ?>">
				<?php
				wp_nav_menu(
					array(
						'menu'        => $qevora_menu_id,
						'container'   => '',
						'menu_class'  => 'qdhc__menu',
						'fallback_cb' => false,
						'depth'       => 2,
					)
				);
				?>
			</nav>
		<?php endif; ?>
	</div>
</div>
