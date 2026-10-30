<?php
/**
 * Template library view.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 *
 * @var array<string,mixed> $context View context prepared by Template_Library_Page::render().
 */

use QevoraLayouts\Admin\Actions;
use QevoraLayouts\Admin\Admin;
use QevoraLayouts\Core\Layout_Post_Type;
use QevoraLayouts\Conditions\Condition_Sanitizer;
use QevoraLayouts\Import\Demo_Registry;
use QevoraLayouts\Import\Importer;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$qevora_templates = isset( $context['templates'] ) && is_array( $context['templates'] ) ? $context['templates'] : array();
$qevora_filters   = isset( $context['filters'] ) && is_array( $context['filters'] ) ? $context['filters'] : array();
$qevora_settings  = isset( $context['settings'] ) && is_array( $context['settings'] ) ? $context['settings'] : array();
$qevora_imported  = isset( $context['imported'] ) && is_array( $context['imported'] ) ? $context['imported'] : array();
$qevora_problems  = isset( $context['problems'] ) && is_array( $context['problems'] ) ? $context['problems'] : array();
$qevora_types     = isset( $context['types'] ) && is_array( $context['types'] ) ? $context['types'] : array();
$qevora_can       = ! empty( $context['can_publish'] );
$qevora_import    = isset( $context['import_url'] ) ? (string) $context['import_url'] : admin_url( 'admin-post.php' );
$qevora_refresh   = isset( $context['refresh_url'] ) ? (string) $context['refresh_url'] : '';
$qevora_settings_url = isset( $context['settings_url'] ) ? (string) $context['settings_url'] : '';
$qevora_placeholder  = isset( $context['placeholder'] ) ? (string) $context['placeholder'] : '';
$qevora_policy    = isset( $qevora_settings['policy'] ) ? (string) $qevora_settings['policy'] : 'skip';
$qevora_scopes    = Importer::display_scopes();
?>
<div class="wrap qevora-layouts-wrap qevora-layouts-library">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Template Library', 'qevora-layouts' ); ?></h1>

	<?php if ( ! empty( $qevora_settings_url ) ) : ?>
		<a href="<?php echo esc_url( $qevora_settings_url ); ?>" class="page-title-action"><?php esc_html_e( 'Import settings', 'qevora-layouts' ); ?></a>
	<?php endif; ?>

	<hr class="wp-header-end" />

	<p class="qevora-layouts-intro">
		<?php esc_html_e( 'Put a bundled PHP demo live in one click, or import your own validated .json template. Imports never overwrite an existing layout: a template that was imported before is skipped unless you choose to import a second copy.', 'qevora-layouts' ); ?>
	</p>

	<?php if ( ! empty( $qevora_problems ) ) : ?>
		<div class="notice notice-warning inline qevora-library-problems">
			<p><strong><?php esc_html_e( 'Library diagnostics', 'qevora-layouts' ); ?></strong></p>
			<ul>
				<?php foreach ( $qevora_problems as $qevora_problem ) : ?>
					<li><?php echo esc_html( (string) $qevora_problem ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<div class="qevora-library__toolbar">
		<form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>" class="qevora-library__filters">
			<input type="hidden" name="post_type" value="<?php echo esc_attr( Layout_Post_Type::POST_TYPE ); ?>" />
			<input type="hidden" name="page" value="<?php echo esc_attr( Admin::LIBRARY_SLUG ); ?>" />

			<label for="ql-type-filter" class="screen-reader-text"><?php esc_html_e( 'Filter by type', 'qevora-layouts' ); ?></label>
			<select id="ql-type-filter" name="ql_type">
				<option value=""><?php esc_html_e( 'All types', 'qevora-layouts' ); ?></option>
				<?php foreach ( $qevora_types as $qevora_slug => $qevora_type ) : ?>
					<option value="<?php echo esc_attr( (string) $qevora_slug ); ?>" <?php selected( isset( $qevora_filters['type'] ) ? $qevora_filters['type'] : '', (string) $qevora_slug ); ?>>
						<?php echo esc_html( isset( $qevora_type['label'] ) ? (string) $qevora_type['label'] : (string) $qevora_slug ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<label for="ql-origin-filter" class="screen-reader-text"><?php esc_html_e( 'Filter by source', 'qevora-layouts' ); ?></label>
			<select id="ql-origin-filter" name="ql_origin">
				<option value=""><?php esc_html_e( 'All sources', 'qevora-layouts' ); ?></option>
				<option value="remote" <?php selected( isset( $qevora_filters['origin'] ) ? $qevora_filters['origin'] : '', 'remote' ); ?>><?php esc_html_e( 'Remote library', 'qevora-layouts' ); ?></option>
			</select>

			<label for="ql-search-filter" class="screen-reader-text"><?php esc_html_e( 'Search templates', 'qevora-layouts' ); ?></label>
			<input type="search" id="ql-search-filter" name="ql_search" value="<?php echo esc_attr( isset( $qevora_filters['search'] ) ? (string) $qevora_filters['search'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Search templates', 'qevora-layouts' ); ?>" />

			<?php submit_button( __( 'Filter', 'qevora-layouts' ), 'secondary', 'ql_filter', false ); ?>
		</form>

		<?php if ( ! empty( $qevora_refresh ) ) : ?>
			<p class="qevora-library__refresh">
				<a class="button button-secondary" href="<?php echo esc_url( $qevora_refresh ); ?>"><?php esc_html_e( 'Refresh remote library', 'qevora-layouts' ); ?></a>
			</p>
		<?php endif; ?>
	</div>

	<div class="qevora-library__sources">
		<div class="card qevora-library__upload">
			<h2 class="title"><?php esc_html_e( 'Import a template file', 'qevora-layouts' ); ?></h2>

			<?php if ( empty( $qevora_settings['uploads_allowed'] ) ) : ?>
				<p class="description">
					<?php esc_html_e( 'File uploads are disabled in Settings → Template library and imports.', 'qevora-layouts' ); ?>
				</p>
			<?php elseif ( $qevora_can ) : ?>
				<form method="post" action="<?php echo esc_url( $qevora_import ); ?>" enctype="multipart/form-data" class="qevora-library__upload-form" data-qevora-import="file">
					<input type="hidden" name="action" value="<?php echo esc_attr( Actions::IMPORT_ACTION ); ?>" />
					<?php wp_nonce_field( Actions::IMPORT_ACTION, '_wpnonce' ); ?>

					<p>
						<label for="qevora-template-file"><?php esc_html_e( 'Template file (.json)', 'qevora-layouts' ); ?></label><br />
						<input type="file" id="qevora-template-file" name="qevora_template_file" accept=".json,application/json" required="required" />
					</p>

					<p>
						<label for="qevora-upload-status"><?php esc_html_e( 'Import as', 'qevora-layouts' ); ?></label>
						<select id="qevora-upload-status" name="status">
							<option value="publish"><?php esc_html_e( 'Enabled (published)', 'qevora-layouts' ); ?></option>
							<option value="draft"><?php esc_html_e( 'Disabled (draft)', 'qevora-layouts' ); ?></option>
						</select>
					</p>

					<p>
						<label for="qevora-upload-policy"><?php esc_html_e( 'If already imported', 'qevora-layouts' ); ?></label>
						<select id="qevora-upload-policy" name="on_duplicate">
							<option value="skip" <?php selected( $qevora_policy, 'skip' ); ?>><?php esc_html_e( 'Skip', 'qevora-layouts' ); ?></option>
							<option value="copy" <?php selected( $qevora_policy, 'copy' ); ?>><?php esc_html_e( 'Import a copy', 'qevora-layouts' ); ?></option>
						</select>
					</p>

					<p>
						<input type="hidden" name="hide_theme_part" value="0" />
						<label for="qevora-upload-hide">
							<input type="checkbox" id="qevora-upload-hide" name="hide_theme_part" value="1" checked="checked" />
							<?php esc_html_e( 'Hide the theme header/footer while an imported header or footer is displayed', 'qevora-layouts' ); ?>
						</label>
					</p>
					<p>
						<?php submit_button( __( 'Upload and import', 'qevora-layouts' ), 'primary', 'qevora_upload_import', false ); ?>
						<span class="spinner qevora-library__spinner" aria-hidden="true"></span>
					</p>

					<p class="description" data-qevora-import-message role="status" aria-live="polite">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: maximum template size in kilobytes. */
								__( 'Files are validated before anything is created and are never stored on the server. Maximum size: %d KB.', 'qevora-layouts' ),
								isset( $qevora_settings['max_size_kb'] ) ? (int) $qevora_settings['max_size_kb'] : 2048
							)
						);
						?>
					</p>
				</form>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'You are not allowed to import templates.', 'qevora-layouts' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="card qevora-library__remote">
			<h2 class="title"><?php esc_html_e( 'Remote library', 'qevora-layouts' ); ?></h2>

			<?php if ( empty( $qevora_settings['remote_enabled'] ) ) : ?>
				<p class="description">
					<?php esc_html_e( 'Disabled. The bundled demos above need no network access. To import templates from your own library, enable it and enter an https endpoint in Settings.', 'qevora-layouts' ); ?>
				</p>
				<?php if ( ! empty( $qevora_settings_url ) ) : ?>
					<p><a class="button button-secondary" href="<?php echo esc_url( $qevora_settings_url ); ?>"><?php esc_html_e( 'Open settings', 'qevora-layouts' ); ?></a></p>
				<?php endif; ?>
			<?php else : ?>
				<p>
					<?php
					$qevora_host = wp_parse_url( isset( $qevora_settings['remote_url'] ) ? (string) $qevora_settings['remote_url'] : '' );
					echo esc_html(
						sprintf(
							/* translators: %s: remote library host. */
							__( 'Enabled for %s. Requests are limited to this https host.', 'qevora-layouts' ),
							isset( $qevora_host['host'] ) ? (string) $qevora_host['host'] : ''
						)
					);
					?>
				</p>
			<?php endif; ?>
		</div>
	</div>


	<?php $qevora_demos = Demo_Registry::for_display(); ?>
	<?php if ( ! empty( $qevora_demos ) ) : ?>
		<div class="qevora-library__demos qevora-demos">
			<h2 class="title"><?php esc_html_e( 'Ready-made demos (rendered by the plugin)', 'qevora-layouts' ); ?></h2>
			<p>
				<?php esc_html_e( 'Each card below shows the demo rendering live, straight from its PHP file - what you see here is exactly the header, footer or section your site will display after import. Every demo is one self-contained PHP file (markup, settings, CSS and JavaScript), fully responsive, with no block editor and no page builder; demos with a menu can create a real WordPress menu for you at import.', 'qevora-layouts' ); ?>
			</p>

			<div class="qevora-library__grid">
				<?php foreach ( $qevora_demos as $qevora_demo ) : ?>
					<article class="qevora-template qevora-demo-card" data-demo="<?php echo esc_attr( (string) $qevora_demo['id'] ); ?>">
						<div class="qevora-template__preview qevora-template__preview--live">
							<div class="qevora-demo-live" aria-hidden="true">
								<div class="qevora-demo-live__inner">
									<?php echo Demo_Registry::preview_html( (string) $qevora_demo['id'], 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Bundled demo markup; escaped inside the demo file. ?>
								</div>
							</div>
							<button
								type="button"
								class="button qevora-template__preview-button"
								data-qevora-preview
								data-preview-html="#qevora-demo-dialog-source-<?php echo esc_attr( (string) $qevora_demo['id'] ); ?>"
								data-preview-name="<?php echo esc_attr( (string) $qevora_demo['name'] ); ?>"
								data-preview-description="<?php echo esc_attr( (string) $qevora_demo['description'] ); ?>"
								data-preview-type="<?php echo esc_attr( (string) $qevora_demo['type_label'] ); ?>"
								data-preview-builder="<?php esc_attr_e( 'One PHP file: markup, settings, CSS and JS', 'qevora-layouts' ); ?>"
								data-preview-scope="<?php esc_attr_e( 'Entire site by default', 'qevora-layouts' ); ?>"
							>
								<?php esc_html_e( 'Preview larger', 'qevora-layouts' ); ?>
							</button>
						</div>

						<div class="qevora-demo-live-source" id="qevora-demo-dialog-source-<?php echo esc_attr( (string) $qevora_demo['id'] ); ?>" hidden>
							<div class="qevora-demo-live">
								<div class="qevora-demo-live__inner">
									<?php echo Demo_Registry::preview_html( (string) $qevora_demo['id'], 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Bundled demo markup; escaped inside the demo file. ?>
								</div>
							</div>
						</div>

						<div class="qevora-demo-card__info">
							<div class="qevora-template__body">
							<h2 class="qevora-template__title"><?php echo esc_html( (string) $qevora_demo['name'] ); ?></h2>
							<p class="qevora-template__meta">
								<?php echo esc_html( (string) $qevora_demo['type_label'] ); ?> &middot; <?php esc_html_e( 'PHP demo', 'qevora-layouts' ); ?>
							</p>
							<p><?php echo esc_html( (string) $qevora_demo['description'] ); ?></p>
						</div>

						<?php if ( $qevora_can ) : ?>
							<form class="qevora-template__form" method="post" action="<?php echo esc_url( $qevora_import ); ?>" data-qevora-import="template">
								<input type="hidden" name="action" value="<?php echo esc_attr( Actions::IMPORT_ACTION ); ?>" />
								<input type="hidden" name="demo" value="<?php echo esc_attr( (string) $qevora_demo['id'] ); ?>" />
								<?php wp_nonce_field( Actions::IMPORT_ACTION, '_wpnonce' ); ?>

								<p>
									<label for="qevora-demo-scope-<?php echo esc_attr( (string) $qevora_demo['id'] ); ?>"><?php esc_html_e( 'Display on', 'qevora-layouts' ); ?></label>
									<select id="qevora-demo-scope-<?php echo esc_attr( (string) $qevora_demo['id'] ); ?>" name="display_scope">
										<?php foreach ( $qevora_scopes as $qevora_scope_id => $qevora_scope_label ) : ?>
											<option value="<?php echo esc_attr( (string) $qevora_scope_id ); ?>" <?php selected( $qevora_scope_id, 'entire_site' ); ?>><?php echo esc_html( (string) $qevora_scope_label ); ?></option>
										<?php endforeach; ?>
									</select>

									<label for="qevora-demo-status-<?php echo esc_attr( (string) $qevora_demo['id'] ); ?>" class="screen-reader-text"><?php esc_html_e( 'Import as', 'qevora-layouts' ); ?></label>
									<select id="qevora-demo-status-<?php echo esc_attr( (string) $qevora_demo['id'] ); ?>" name="status">
										<option value="publish"><?php esc_html_e( 'Enabled (display now)', 'qevora-layouts' ); ?></option>
										<option value="draft"><?php esc_html_e( 'Disabled (draft)', 'qevora-layouts' ); ?></option>
									</select>
								</p>

								<?php if ( ! empty( $qevora_demo['menu'] ) ) : ?>
									<p>
										<input type="hidden" name="create_menu" value="0" />
										<label for="qevora-demo-menu-<?php echo esc_attr( (string) $qevora_demo['id'] ); ?>">
											<input type="checkbox" id="qevora-demo-menu-<?php echo esc_attr( (string) $qevora_demo['id'] ); ?>" name="create_menu" value="1" checked="checked" />
											<?php echo esc_html( '' !== (string) $qevora_demo['menu_label'] ? (string) $qevora_demo['menu_label'] : __( 'Create a sample menu', 'qevora-layouts' ) ); ?>
										</label>
									</p>
								<?php endif; ?>

								<p>
									<input type="hidden" name="hide_theme_part" value="0" />
									<label for="qevora-demo-hide-<?php echo esc_attr( (string) $qevora_demo['id'] ); ?>">
										<input type="checkbox" id="qevora-demo-hide-<?php echo esc_attr( (string) $qevora_demo['id'] ); ?>" name="hide_theme_part" value="1" checked="checked" />
										<?php esc_html_e( 'Hide the theme header/footer while this demo is displayed', 'qevora-layouts' ); ?>
									</label>
								</p>

								<p>
									<?php submit_button( __( 'Import demo', 'qevora-layouts' ), 'primary small', 'qevora_import_demo', false ); ?>
									<span class="spinner qevora-library__spinner" aria-hidden="true"></span>
								</p>

								<p class="description qevora-template__message" role="status" aria-live="polite"></p>
							</form>
						<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( empty( $qevora_templates ) ) : ?>
		<div class="qevora-empty-state">
			<h2><?php esc_html_e( 'No templates to show', 'qevora-layouts' ); ?></h2>
			<p>
				<?php esc_html_e( 'No importable JSON templates are registered right now - the bundled demos above cover headers, footers and sections without any editor content. Sites can still register their own template files with the qevora_layouts_bundled_template_files filter, or enable the remote library in Settings.', 'qevora-layouts' ); ?>
			</p>
			<?php if ( ! empty( $qevora_settings_url ) ) : ?>
				<p><a class="button button-primary" href="<?php echo esc_url( $qevora_settings_url ); ?>"><?php esc_html_e( 'Open import settings', 'qevora-layouts' ); ?></a></p>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<div class="qevora-library__grid">
			<?php foreach ( $qevora_templates as $qevora_template ) : ?>
				<?php
				$qevora_id       = isset( $qevora_template['id'] ) ? (string) $qevora_template['id'] : '';
				$qevora_name     = isset( $qevora_template['name'] ) ? (string) $qevora_template['name'] : '';
				$qevora_type     = isset( $qevora_template['type'] ) ? (string) $qevora_template['type'] : '';
				$qevora_type_lbl = isset( $qevora_template['type_label'] ) ? (string) $qevora_template['type_label'] : Layout_Post_Type::get_type_label( $qevora_type );
				$qevora_desc     = isset( $qevora_template['description'] ) ? (string) $qevora_template['description'] : '';
				$qevora_builder  = isset( $qevora_template['builder'] ) ? (string) $qevora_template['builder'] : 'block';
				$qevora_origin   = isset( $qevora_template['origin'] ) ? (string) $qevora_template['origin'] : 'bundled';
				$qevora_tags     = isset( $qevora_template['tags'] ) && is_array( $qevora_template['tags'] ) ? $qevora_template['tags'] : array();
				$qevora_missing  = isset( $qevora_template['missing'] ) && is_array( $qevora_template['missing'] ) ? $qevora_template['missing'] : array();
				$qevora_preview  = isset( $qevora_template['preview_url'] ) ? (string) $qevora_template['preview_url'] : '';
				$qevora_existing = isset( $qevora_imported[ $qevora_id ] ) ? (array) $qevora_imported[ $qevora_id ] : array();
				?>
				<article class="qevora-template" data-template="<?php echo esc_attr( $qevora_id ); ?>">
					<div class="qevora-template__preview">
						<?php if ( '' !== $qevora_preview ) : ?>
							<img src="<?php echo esc_url( $qevora_preview ); ?>" alt="" width="400" height="220" loading="lazy" />
						<?php else : ?>
							<img src="<?php echo esc_url( $qevora_placeholder ); ?>" alt="" width="400" height="220" loading="lazy" />
						<?php endif; ?>

						<button
							type="button"
							class="button button-secondary button-small qevora-template__preview-button"
							data-qevora-preview
							data-preview-url="<?php echo esc_url( '' !== $qevora_preview ? $qevora_preview : $qevora_placeholder ); ?>"
							data-preview-name="<?php echo esc_attr( $qevora_name ); ?>"
							data-preview-description="<?php echo esc_attr( $qevora_desc ); ?>"
							data-preview-type="<?php echo esc_attr( $qevora_type_lbl ); ?>"
							data-preview-builder="<?php echo esc_attr( 'elementor' === $qevora_builder ? __( 'Requires Elementor', 'qevora-layouts' ) : __( 'Block editor', 'qevora-layouts' ) ); ?>"
							data-preview-scope="<?php echo esc_attr( isset( $qevora_template['conditions_summary'] ) ? (string) $qevora_template['conditions_summary'] : '' ); ?>"
						>
							<span aria-hidden="true">&#128065;</span> <?php esc_html_e( 'Preview', 'qevora-layouts' ); ?>
						</button>
					</div>

					<div class="qevora-template__body">
						<h2 class="qevora-template__title"><?php echo esc_html( $qevora_name ); ?></h2>

						<p class="qevora-template__meta">
							<span class="qevora-layouts-badge qevora-layouts-badge--<?php echo esc_attr( $qevora_type ); ?>"><?php echo esc_html( $qevora_type_lbl ); ?></span>
							<span class="qevora-template__builder">
								<?php echo esc_html( 'elementor' === $qevora_builder ? __( 'Requires Elementor', 'qevora-layouts' ) : __( 'Block editor', 'qevora-layouts' ) ); ?>
							</span>
							<span class="qevora-template__origin">
								<?php echo esc_html( 'remote' === $qevora_origin ? __( 'Remote', 'qevora-layouts' ) : __( 'Bundled', 'qevora-layouts' ) ); ?>
							</span>
						</p>

						<?php if ( '' !== $qevora_desc ) : ?>
							<p class="qevora-template__description"><?php echo esc_html( $qevora_desc ); ?></p>
						<?php endif; ?>

						<p class="qevora-template__scope">
							<strong><?php esc_html_e( 'Displays on:', 'qevora-layouts' ); ?></strong>
							<?php
							echo esc_html(
								isset( $qevora_template['conditions_summary'] ) && '' !== (string) $qevora_template['conditions_summary']
									? (string) $qevora_template['conditions_summary']
									: Condition_Sanitizer::summarize( array() )
							);
							?>
						</p>

						<?php if ( ! empty( $qevora_tags ) ) : ?>
							<ul class="qevora-template__tags">
								<?php foreach ( array_slice( $qevora_tags, 0, 5 ) as $qevora_tag ) : ?>
									<li><?php echo esc_html( (string) $qevora_tag ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>

						<?php if ( ! empty( $qevora_missing ) ) : ?>
							<p class="qevora-template__warning">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: comma separated list of missing plugins. */
										__( 'Not available yet: %s must be active.', 'qevora-layouts' ),
										implode( ', ', $qevora_missing )
									)
								);
								?>
							</p>
						<?php endif; ?>

						<?php if ( ! empty( $qevora_existing ) ) : ?>
							<p class="qevora-template__imported">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: number of existing layouts. */
										_n( 'Already imported once.', 'Already imported %d times.', count( $qevora_existing ), 'qevora-layouts' ),
										count( $qevora_existing )
									)
								);
								?>
								<a href="<?php echo esc_url( get_edit_post_link( (int) $qevora_existing[0], 'raw' ) ); ?>"><?php esc_html_e( 'Edit the existing layout', 'qevora-layouts' ); ?></a>
							</p>
						<?php endif; ?>
					</div>

					<?php if ( $qevora_can ) : ?>
						<form class="qevora-template__form" method="post" action="<?php echo esc_url( $qevora_import ); ?>" data-qevora-import="template">
							<input type="hidden" name="action" value="<?php echo esc_attr( Actions::IMPORT_ACTION ); ?>" />
							<input type="hidden" name="template_id" value="<?php echo esc_attr( $qevora_id ); ?>" />
							<input type="hidden" name="origin" value="<?php echo esc_attr( $qevora_origin ); ?>" />
							<?php wp_nonce_field( Actions::IMPORT_ACTION, '_wpnonce' ); ?>

							<p>
								<label for="qevora-scope-<?php echo esc_attr( $qevora_id ); ?>"><?php esc_html_e( 'Display on', 'qevora-layouts' ); ?></label>
								<select id="qevora-scope-<?php echo esc_attr( $qevora_id ); ?>" name="display_scope">
									<?php foreach ( $qevora_scopes as $qevora_scope_id => $qevora_scope_label ) : ?>
										<option value="<?php echo esc_attr( (string) $qevora_scope_id ); ?>" <?php selected( $qevora_scope_id, 'template' ); ?>><?php echo esc_html( (string) $qevora_scope_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</p>

							<?php if ( 'header' === $qevora_type || 'footer' === $qevora_type ) : ?>
								<p class="qevora-template__hide">
									<input type="hidden" name="hide_theme_part" value="0" />
									<label for="qevora-hide-<?php echo esc_attr( $qevora_id ); ?>">
										<input type="checkbox" id="qevora-hide-<?php echo esc_attr( $qevora_id ); ?>" name="hide_theme_part" value="1" checked="checked" />
										<?php esc_html_e( 'Hide the theme header/footer while this layout is displayed', 'qevora-layouts' ); ?>
									</label>
								</p>
							<?php endif; ?>

							<p>
								<label for="qevora-status-<?php echo esc_attr( $qevora_id ); ?>" class="screen-reader-text"><?php esc_html_e( 'Import as', 'qevora-layouts' ); ?></label>
								<select id="qevora-status-<?php echo esc_attr( $qevora_id ); ?>" name="status">
									<option value="publish"><?php esc_html_e( 'Enabled (display now)', 'qevora-layouts' ); ?></option>
									<option value="draft"><?php esc_html_e( 'Disabled (draft)', 'qevora-layouts' ); ?></option>
								</select>

								<label for="qevora-policy-<?php echo esc_attr( $qevora_id ); ?>" class="screen-reader-text"><?php esc_html_e( 'If already imported', 'qevora-layouts' ); ?></label>
								<select id="qevora-policy-<?php echo esc_attr( $qevora_id ); ?>" name="on_duplicate">
									<option value="skip" <?php selected( $qevora_policy, 'skip' ); ?>><?php esc_html_e( 'Skip duplicates', 'qevora-layouts' ); ?></option>
									<option value="copy" <?php selected( $qevora_policy, 'copy' ); ?>><?php esc_html_e( 'Import a copy', 'qevora-layouts' ); ?></option>
								</select>
							</p>

							<p>
								<?php submit_button( __( 'Import', 'qevora-layouts' ), 'primary small', 'qevora_import_template', false ); ?>
								<span class="spinner qevora-library__spinner" aria-hidden="true"></span>
							</p>

							<p class="description qevora-template__message" role="status" aria-live="polite"></p>
						</form>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="qevora-preview-dialog" data-qevora-preview-dialog hidden role="dialog" aria-modal="true" aria-labelledby="qevora-preview-dialog-title">
		<div class="qevora-preview-dialog__backdrop" data-qevora-preview-close></div>
		<div class="qevora-preview-dialog__panel" role="document">
			<button type="button" class="qevora-preview-dialog__close button button-secondary button-small" data-qevora-preview-close>
				<?php esc_html_e( 'Close preview', 'qevora-layouts' ); ?>
			</button>
			<img class="qevora-preview-dialog__image" src="" alt="" />
			<div class="qevora-preview-dialog__live" hidden></div>
			<h2 id="qevora-preview-dialog-title" class="qevora-preview-dialog__title"></h2>
			<p class="qevora-preview-dialog__meta"></p>
			<p class="qevora-preview-dialog__description"></p>
		</div>
	</div>
</div>
