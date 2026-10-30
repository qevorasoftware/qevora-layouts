<?php
/**
 * Columns Footer demo template.
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

$qdfc_name     = (string) get_bloginfo( 'name' );
$qdfc_tagline  = (string) get_bloginfo( 'description' );
$qdfc_admin    = (string) get_bloginfo( 'admin_email' );
$qdfc_year     = gmdate( 'Y' );
$qdfc_has_menu = $qevora_menu_id > 0 && function_exists( 'wp_nav_menu' );
?>
<div class="qdfc">
	<div class="qdfc__grid">
		<div class="qdfc__col qdfc__col--about">
			<h2 class="qdfc__heading"><?php echo esc_html( $qdfc_name ); ?></h2>
			<?php if ( '' !== $qdfc_tagline ) : ?>
				<p class="qdfc__text"><?php echo esc_html( $qdfc_tagline ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( $qdfc_has_menu ) : ?>
			<div class="qdfc__col qdfc__col--menu">
				<h2 class="qdfc__heading"><?php esc_html_e( 'Explore', 'qevora-layouts' ); ?></h2>
				<?php
				wp_nav_menu(
					array(
						'menu'        => $qevora_menu_id,
						'container'   => '',
						'menu_class'  => 'qdfc__menu',
						'fallback_cb' => false,
						'depth'       => 1,
					)
				);
				?>
			</div>
		<?php endif; ?>

		<div class="qdfc__col qdfc__col--contact">
			<h2 class="qdfc__heading"><?php esc_html_e( 'Contact', 'qevora-layouts' ); ?></h2>
			<?php if ( '' !== $qdfc_admin ) : ?>
				<p class="qdfc__text">
					<a class="qdfc__link" href="mailto:<?php echo esc_attr( antispambot( $qdfc_admin ) ); ?>"><?php echo esc_html( antispambot( $qdfc_admin ) ); ?></a>
				</p>
			<?php endif; ?>
			<p class="qdfc__text">
				<a class="qdfc__link" href="<?php echo esc_url( (string) home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to home', 'qevora-layouts' ); ?></a>
			</p>
		</div>
	</div>

	<p class="qdfc__credit">
		<?php
		echo esc_html(
			sprintf(
				/* translators: 1: current year, 2: site name. */
				__( '&copy; %1$s %2$s. All rights reserved.', 'qevora-layouts' ),
				$qdfc_year,
				$qdfc_name
			)
		);
		?>
	</p>
</div>
