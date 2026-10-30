<?php
/**
 * Settings screen view.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 *
 * @var array<string,mixed> $context View context prepared by Settings_Page::render().
 */

use QevoraLayouts\Admin\Admin;
use QevoraLayouts\Admin\Settings_Page;
use QevoraLayouts\Core\Layout_Post_Type;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$qevora_page = isset( $context['page'] ) ? (string) $context['page'] : Admin::SETTINGS_SLUG;
?>
<div class="wrap qevora-layouts-wrap qevora-layouts-settings">
	<h1><?php esc_html_e( 'Qevora Layouts Settings', 'qevora-layouts' ); ?></h1>

	<p class="qevora-layouts-intro">
		<?php esc_html_e( 'These settings control how layouts render, which builders are used and how templates are imported. Every layout can override the placement individually.', 'qevora-layouts' ); ?>
	</p>

	<div class="qevora-layouts-settings__body">
		<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post" class="qevora-layouts-settings__form">
			<?php
			settings_fields( Settings_Page::GROUP );
			do_settings_sections( $qevora_page );
			submit_button( __( 'Save settings', 'qevora-layouts' ) );
			?>
		</form>

		<div class="qevora-layouts-settings__aside">
			<div class="card">
				<h2 class="title"><?php esc_html_e( 'Getting started', 'qevora-layouts' ); ?></h2>
				<ol>
					<li>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Layout_Post_Type::POST_TYPE ) ); ?>">
							<?php esc_html_e( 'Create a layout', 'qevora-layouts' ); ?>
						</a>
					</li>
					<li>
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Layout_Post_Type::POST_TYPE ) ); ?>">
							<?php esc_html_e( 'Manage all layouts', 'qevora-layouts' ); ?>
						</a>
					</li>
					<li>
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Layout_Post_Type::POST_TYPE . '&page=' . Admin::LIBRARY_SLUG ) ); ?>">
							<?php esc_html_e( 'Import a starter template', 'qevora-layouts' ); ?>
						</a>
					</li>
					<li>
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Layout_Post_Type::POST_TYPE . '&page=' . Admin::STATUS_SLUG ) ); ?>">
							<?php esc_html_e( 'Check the system status', 'qevora-layouts' ); ?>
						</a>
					</li>
				</ol>
			</div>

			<div class="card">
				<h2 class="title"><?php esc_html_e( 'About theme headers', 'qevora-layouts' ); ?></h2>
				<p>
					<?php esc_html_e( 'This plugin never removes your theme header or footer on its own. A layout is inserted at the placement you choose, and only the CSS selectors you enter in "Theme integration" hide the theme markup.', 'qevora-layouts' ); ?>
				</p>
				<p>
					<?php esc_html_e( 'If you see two headers, either hide the theme one with the selectors above, switch the layout placement to "Manual only" and call the template tag in your theme, or ask your theme for a hook name and use the "Custom theme hook" placement.', 'qevora-layouts' ); ?>
				</p>
			</div>
		</div>
	</div>
</div>
