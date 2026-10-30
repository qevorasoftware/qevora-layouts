<?php
/**
 * Layout wrapper template.
 *
 * Themes can override this file by copying it to
 * `<theme>/qevora-layouts/layout-wrapper.php`.
 *
 * Available variables:
 *
 * @var QevoraLayouts\Core\Layout  $qevora_layout     The layout being rendered.
 * @var string                     $qevora_content    Inner markup produced by the builder adapter.
 * @var array<string,string|int>   $qevora_attributes Attributes for the wrapper element.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$qevora_attr_string = '';

foreach ( (array) $qevora_attributes as $qevora_attr_name => $qevora_attr_value ) {
	$qevora_attr_string .= ' ' . esc_attr( (string) $qevora_attr_name ) . '="' . esc_attr( (string) $qevora_attr_value ) . '"';
}
?>
<div<?php echo $qevora_attr_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every attribute is escaped above. ?>><?php echo $qevora_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered layout markup. ?></div>
