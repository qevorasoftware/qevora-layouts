<?php
/**
 * Uninstall handler.
 *
 * Runs only when WordPress removes the plugin. Deleting content is opt-in:
 * unless the site owner ticked "Delete all plugin data when the plugin is
 * uninstalled" in Settings → Data and uninstall, this file does nothing at all
 * and every layout stays in the database.
 *
 * Nothing here depends on the plugin being loaded, so the file keeps working
 * even when the plugin folder is already partly removed.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

// Never run outside of a real uninstall request.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Post type created by the plugin.
 *
 * @since 1.0.0
 * @var   string
 */
$qevora_layouts_post_type = 'qevora_layout';

/**
 * Options created by the plugin.
 *
 * @since 1.0.0
 * @var   string[]
 */
$qevora_layouts_options = array(
	'qevora_layouts_settings',
	'qevora_layouts_version',
	'qevora_layouts_cache_version',
);

/**
 * Post meta keys created by the plugin.
 *
 * @since 1.0.0
 * @var   string[]
 */
$qevora_layouts_meta_keys = array(
	'_qevora_layout_type',
	'_qevora_layout_priority',
	'_qevora_layout_conditions',
	'_qevora_layout_placement',
	'_qevora_layout_hook',
	'_qevora_layout_hook_priority',
	'_qevora_layout_builder',
	'_qevora_layout_content_source',
	'_qevora_layout_reference_id',
	'_qevora_layout_template_id',
	'_qevora_layout_import_source',
	'_qevora_layout_imported_at',
);

/**
 * Transient prefix used for admin notices.
 *
 * @since 1.0.0
 * @var   string
 */
$qevora_layouts_notice_prefix = 'qevora_layouts_notices_';

/**
 * Removes every trace of the plugin from one site.
 *
 * Deletes the layout posts (permanently, including their revisions), the
 * plugin meta keys, the plugin options and the per-user notice transients.
 * It never touches posts of another type, theme files, other plugins' options
 * or anything outside the WordPress database.
 *
 * @since 1.0.0
 *
 * @param string $post_type      Layout post type.
 * @param string[] $options      Option names.
 * @param string[] $meta_keys    Meta keys.
 * @param string $notice_prefix  Notice transient prefix.
 * @return void
 */
function qevora_layouts_uninstall_site( $post_type, array $options, array $meta_keys, $notice_prefix ) {
	// 1. Remove layouts in small batches so large sites do not time out.
	$deleted = 0;

	do {
		$ids = get_posts(
			array(
				'post_type'              => $post_type,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash', 'auto-draft', 'inherit' ),
				'posts_per_page'         => 100,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
			)
		);

		if ( empty( $ids ) ) {
			break;
		}

		foreach ( $ids as $id ) {
			// Revisions and attachments of a layout go with it.
			wp_delete_post( (int) $id, true );
			++$deleted;
		}
	} while ( count( $ids ) > 0 && $deleted < 5000 );

	// 2. Remove leftover meta rows, including orphans from deleted layouts.
	foreach ( $meta_keys as $meta_key ) {
		delete_post_meta_by_key( (string) $meta_key );
	}

	// 3. Remove per-user admin notice transients.
	$user_ids = get_users(
		array(
			'fields' => 'ID',
			'number' => 500,
		)
	);

	foreach ( (array) $user_ids as $user_id ) {
		delete_transient( $notice_prefix . (int) $user_id );
	}

	// 4. Remove plugin options.
	foreach ( $options as $option ) {
		delete_option( (string) $option );
	}
}

/**
 * Custom capabilities added to roles during activation.
 *
 * @since 1.0.0
 * @var   string[]
 */
$qevora_layouts_caps = array(
	'edit_qevora_layouts',
	'publish_qevora_layouts',
	'delete_qevora_layouts',
);

/**
 * Removes the plugin capabilities from every role of the current site.
 *
 * Kept inline on purpose: uninstall must keep working even if the plugin
 * files can no longer be autoloaded.
 *
 * @since 1.0.0
 *
 * @param string[] $caps Capability names to remove.
 * @return void
 */
function qevora_layouts_uninstall_caps( array $caps ) {
	foreach ( array_keys( wp_roles()->roles ) as $qevora_layouts_role_name ) {
		$qevora_layouts_role = get_role( (string) $qevora_layouts_role_name );

		if ( ! $qevora_layouts_role ) {
			continue;
		}

		foreach ( $caps as $qevora_layouts_cap ) {
			if ( $qevora_layouts_role->has_cap( (string) $qevora_layouts_cap ) ) {
				$qevora_layouts_role->remove_cap( (string) $qevora_layouts_cap );
			}
		}
	}
}

$qevora_layouts_settings   = get_option( 'qevora_layouts_settings', array() );
$qevora_layouts_delete_all = is_array( $qevora_layouts_settings ) && ! empty( $qevora_layouts_settings['delete_data_on_uninstall'] );

/*
 * Role capabilities are configuration, not user content: they are removed on
 * every uninstall, even when the layouts themselves are kept.
 */
if ( is_multisite() ) {
	$qevora_layouts_cap_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( (array) $qevora_layouts_cap_sites as $qevora_layouts_cap_site_id ) {
		switch_to_blog( (int) $qevora_layouts_cap_site_id );
		qevora_layouts_uninstall_caps( $qevora_layouts_caps );
		restore_current_blog();
	}
} else {
	qevora_layouts_uninstall_caps( $qevora_layouts_caps );
}

// Opt-in only: keep every layout when the site owner did not ask for removal.
if ( ! $qevora_layouts_delete_all ) {
	return;
}

if ( is_multisite() ) {
	$qevora_layouts_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( (array) $qevora_layouts_sites as $qevora_layouts_site_id ) {
		switch_to_blog( (int) $qevora_layouts_site_id );

		qevora_layouts_uninstall_site(
			$qevora_layouts_post_type,
			$qevora_layouts_options,
			$qevora_layouts_meta_keys,
			$qevora_layouts_notice_prefix
		);

		restore_current_blog();
	}
} else {
	qevora_layouts_uninstall_site(
		$qevora_layouts_post_type,
		$qevora_layouts_options,
		$qevora_layouts_meta_keys,
		$qevora_layouts_notice_prefix
	);
}
