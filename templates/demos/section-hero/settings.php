<?php
/**
 * Hero Section demo - customisable settings.
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
		'heading'          => array(
			'label'   => __( 'Heading', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty to use the site title.', 'qevora-layouts' ),
		),
		'subheading'       => array(
			'label'   => __( 'Supporting line', 'qevora-layouts' ),
			'type'    => 'textarea',
			'default' => '',
			'help'    => __( 'Leave empty to use the site tagline.', 'qevora-layouts' ),
		),
		'button_label'     => array(
			'label'   => __( 'Button label', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty to hide the button.', 'qevora-layouts' ),
		),
		'button_url'       => array(
			'label'   => __( 'Button link', 'qevora-layouts' ),
			'type'    => 'url',
			'default' => '',
			'help'    => __( 'Leave empty to link to the front page.', 'qevora-layouts' ),
		),
		'align'            => array(
			'label'   => __( 'Text alignment', 'qevora-layouts' ),
			'type'    => 'select',
			'default' => 'center',
			'options' => array(
				'center' => __( 'Centred', 'qevora-layouts' ),
				'left'   => __( 'Left', 'qevora-layouts' ),
			),
		),
		'background_color' => array(
			'label'   => __( 'Background colour', 'qevora-layouts' ),
			'type'    => 'color',
			'default' => '#0f172a',
		),
		'accent_color'     => array(
			'label'   => __( 'Button colour', 'qevora-layouts' ),
			'type'    => 'color',
			'default' => '#2271b1',
		),
	),
);
