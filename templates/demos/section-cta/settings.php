<?php
/**
 * Call to Action Section demo - customisable settings.
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
		'title'            => array(
			'label'   => __( 'Title', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty for the default wording.', 'qevora-layouts' ),
		),
		'text'             => array(
			'label'   => __( 'Text', 'qevora-layouts' ),
			'type'    => 'textarea',
			'default' => '',
			'help'    => __( 'Leave empty for the default wording.', 'qevora-layouts' ),
		),
		'button_label'     => array(
			'label'   => __( 'Button label', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty for the default wording.', 'qevora-layouts' ),
		),
		'button_url'       => array(
			'label'   => __( 'Button link', 'qevora-layouts' ),
			'type'    => 'url',
			'default' => '',
			'help'    => __( 'Leave empty to link to the front page.', 'qevora-layouts' ),
		),
		'background_color' => array(
			'label'   => __( 'Background colour', 'qevora-layouts' ),
			'type'    => 'color',
			'default' => '#2271b1',
		),
		'text_color'       => array(
			'label'   => __( 'Text colour', 'qevora-layouts' ),
			'type'    => 'color',
			'default' => '#ffffff',
		),
	),
);
