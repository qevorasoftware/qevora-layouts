<?php
/**
 * Columns Footer demo - customisable settings.
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
		'heading'       => array(
			'label'   => __( 'About column heading', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty to use the site title.', 'qevora-layouts' ),
		),
		'tagline'       => array(
			'label'   => __( 'About text', 'qevora-layouts' ),
			'type'    => 'textarea',
			'default' => '',
			'help'    => __( 'Leave empty to use the site tagline.', 'qevora-layouts' ),
		),
		'explore_title' => array(
			'label'   => __( 'Menu column heading', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty for the default wording.', 'qevora-layouts' ),
		),
		'contact_title' => array(
			'label'   => __( 'Contact column heading', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty for the default wording.', 'qevora-layouts' ),
		),
		'copyright'     => array(
			'label'   => __( 'Copyright line', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty for an automatic "(c) year site name" line.', 'qevora-layouts' ),
		),
	),
);
