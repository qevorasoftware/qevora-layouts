<?php
/**
 * Simple Footer Bar demo - customisable settings.
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
		'copyright'        => array(
			'label'   => __( 'Copyright line', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty for an automatic "(c) year site name" line.', 'qevora-layouts' ),
		),
		'show_back_to_top' => array(
			'label'   => __( 'Show the back-to-top button', 'qevora-layouts' ),
			'type'    => 'checkbox',
			'default' => true,
		),
		'top_label'        => array(
			'label'   => __( 'Back-to-top label', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty for the default wording.', 'qevora-layouts' ),
		),
	),
);
