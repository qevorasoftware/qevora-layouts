<?php
/**
 * Frequently Asked Questions demo - customisable settings.
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
		'title'     => array(
			'label'   => __( 'Section title', 'qevora-layouts' ),
			'type'    => 'text',
			'default' => '',
			'help'    => __( 'Leave empty for the default wording.', 'qevora-layouts' ),
		),
		'items'     => array(
			'label'   => __( 'Questions and answers', 'qevora-layouts' ),
			'type'    => 'textarea',
			'default' => "How do I edit this demo? | Open this layout and use the demo settings box - no block editor needed.
Where does the markup come from? | From a PHP template file inside the plugin, rendered on every request.
Is it responsive? | Yes, every demo ships its own responsive stylesheet.",
			'help'    => __( 'One entry per line, written as: question | answer', 'qevora-layouts' ),
		),
		'open_first' => array(
			'label'   => __( 'Open the first question by default', 'qevora-layouts' ),
			'type'    => 'checkbox',
			'default' => true,
		),
	),
);
