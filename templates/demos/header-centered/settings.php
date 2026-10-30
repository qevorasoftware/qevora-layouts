<?php
/**
 * Centered Header demo - customisable settings.
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
		'show_tagline'  => array(
			'label'   => __( 'Show the tagline under the title', 'qevora-layouts' ),
			'type'    => 'checkbox',
			'default' => true,
		),
		'tagline'       => array(
			'label'   => __( 'Tagline', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty to use the tagline from Settings - General.', 'qevora-layouts' ),
		),
		'sticky_header' => array(
			'label'   => __( 'Stick the header to the top while scrolling', 'qevora-layouts' ),
			'type'    => 'checkbox',
			'default' => false,
		),
	),
);
