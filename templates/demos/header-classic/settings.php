<?php
/**
 * Classic Header demo - customisable settings.
 *
 * Every demo keeps its own data in its own PHP file. The metabox on the
 * layout edit screen renders exactly these fields, and `template.php`
 * renders exactly these values, so nothing lives in the block editor.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'fields' => array(
		'site_title'    => array(
			'label'   => __( 'Site title', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty to use the site title from Settings - General.', 'qevora-layouts' ),
		),
		'accent_color'  => array(
			'label'   => __( 'Brand mark colour', 'qevora-layouts' ),
			'type'    => 'color',
			'default' => '#2271b1',
		),
		'sticky_header' => array(
			'label'   => __( 'Stick the header to the top while scrolling', 'qevora-layouts' ),
			'type'    => 'checkbox',
			'default' => false,
		),
	),
);
