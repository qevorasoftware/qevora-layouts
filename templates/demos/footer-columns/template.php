<?php
/**
 * Columns Footer demo template.
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

$qdfc_set      = (array) $qevora_demo_settings;
$qdfc_name     = '' !== (string) $qdfc_set['heading'] ? (string) $qdfc_set['heading'] : (string) get_bloginfo( 'name' );
$qdfc_tagline  = '' !== (string) $qdfc_set['tagline'] ? (string) $qdfc_set['tagline'] : (string) get_bloginfo( 'description' );
$qdfc_explore  = '' !== (string) $qdfc_set['explore_title'] ? (string) $qdfc_set['explore_title'] : __( 'Explore', 'qevora-layouts' );
$qdfc_contact  = '' !== (string) $qdfc_set['contact_title'] ? (string) $qdfc_set['contact_title'] : __( 'Contact', 'qevora-layouts' );
$qdfc_copy     = (string) $qdfc_set['copyright'];
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
				<h2 class="qdfc__heading"><?php echo esc_html( $qdfc_explore ); ?></h2>
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
			<h2 class="qdfc__heading"><?php echo esc_html( $qdfc_contact ); ?></h2>
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
		if ( '' !== $qdfc_copy ) {
			echo esc_html( $qdfc_copy );
		} else {
			echo esc_html(
				sprintf(
					/* translators: 1: current year, 2: site name. */
					__( '&copy; %1$s %2$s. All rights reserved.', 'qevora-layouts' ),
					$qdfc_year,
					$qdfc_name
				)
			);
		}
		?>
	</p>
</div>
