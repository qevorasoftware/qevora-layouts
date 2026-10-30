<?php
/**
 * Shared import request handler.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Admin;

use QevoraLayouts\Conditions\Condition_Sanitizer;
use QevoraLayouts\Core\Capabilities;
use QevoraLayouts\Core\Layout_Post_Type;
use QevoraLayouts\Core\Plugin;
use QevoraLayouts\Core\Settings;
use QevoraLayouts\Import\Demo_Importer;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns an import request (AJAX or plain form POST) into an import result.
 *
 * One implementation is used by both entry points so the behaviour, the
 * validation and the capability checks cannot drift apart.
 *
 * @since 1.0.0
 */
class Import_Handler {

	/**
	 * Handles an import request.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $params Request parameters (already unslashed).
	 * @param array<string,mixed> $files  One `$_FILES` entry map, may be empty.
	 * @return array<string,mixed>|WP_Error Result array or error.
	 */
	public static function handle( array $params, array $files = array() ) {
		if ( ! Capabilities::can_publish_layouts() ) {
			return new WP_Error( 'qevora_layouts_forbidden', __( 'You are not allowed to import templates.', 'qevora-layouts' ), array( 'status' => 403 ) );
		}

		$plugin   = Plugin::instance();
		$importer = $plugin->importer();

		$options = array(
			'on_duplicate'  => self::sanitize_policy( isset( $params['on_duplicate'] ) ? $params['on_duplicate'] : '' ),
			'status'        => self::sanitize_status( isset( $params['status'] ) ? $params['status'] : 'publish' ),
			'replace_id'    => isset( $params['replace_id'] ) ? (int) $params['replace_id'] : 0,
			'display_scope' => isset( $params['display_scope'] ) ? sanitize_key( (string) $params['display_scope'] ) : '',
			'hide_theme_part' => self::checkbox_param( $params, 'hide_theme_part' ),
		);

		// Explicit condition sets arrive from the REST API only; admin forms
		// use the whitelisted display scopes instead.
		if ( isset( $params['conditions'] ) && is_array( $params['conditions'] ) ) {
			$options['conditions'] = $params['conditions'];
		}

		$template_id = Layout_Post_Type::sanitize_identifier( isset( $params['template_id'] ) ? $params['template_id'] : '' );
		$origin      = isset( $params['origin'] ) && in_array( $params['origin'], array( 'bundled', 'remote' ), true ) ? $params['origin'] : '';

		// 0. Bundled PHP demos (own templates, stylesheets and menus).
		$demo = isset( $params['demo'] ) ? Layout_Post_Type::sanitize_identifier( (string) $params['demo'] ) : '';

		if ( '' !== $demo ) {
			$demo_importer = new Demo_Importer( $plugin->repository() );
			$demo_result   = $demo_importer->import(
				$demo,
				array_merge(
					$options,
					array( 'create_menu' => self::checkbox_param( $params, 'create_menu' ) )
				)
			);

			if ( is_wp_error( $demo_result ) ) {
				return $demo_result;
			}

			return self::normalize( $demo_result );
		}

		// 1. Import from the library (bundled or remote).
		if ( '' !== $template_id ) {
			if ( 'remote' === $origin && ! Settings::is_enabled( 'remote_library_enabled' ) ) {
				return new WP_Error( 'qevora_layouts_remote_disabled', __( 'The remote template library is disabled.', 'qevora-layouts' ), array( 'status' => 400 ) );
			}

			$single = $importer->import_from_library(
				$template_id,
				array_merge(
					$options,
					array( 'origin' => $origin )
				)
			);

			if ( is_wp_error( $single ) ) {
				return $single;
			}

			return self::normalize( is_array( $single ) ? $single : array() );
		}

		// 2. Import from an uploaded file.
		if ( ! Settings::is_enabled( 'allow_uploaded_imports' ) ) {
			return new WP_Error( 'qevora_layouts_uploads_disabled', __( 'Uploading template files is disabled in Settings → Template library and imports.', 'qevora-layouts' ), array( 'status' => 400 ) );
		}

		$file = self::find_uploaded_file( $files );

		if ( null === $file ) {
			return new WP_Error( 'qevora_layouts_no_file', __( 'Choose a .json template file to import.', 'qevora-layouts' ), array( 'status' => 400 ) );
		}

		$validated = $importer->read_uploaded_file( $file, $options );

		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$errors = isset( $validated['errors'] ) ? (array) $validated['errors'] : array();

		if ( empty( $validated['ok'] ) || empty( $validated['templates'] ) ) {
			if ( empty( $errors ) ) {
				$errors = array( __( 'The file contains no usable template.', 'qevora-layouts' ) );
			}

			return new WP_Error( 'qevora_layouts_invalid_template', implode( ' ', $errors ) );
		}

		$result = $importer->import_templates( (array) $validated['templates'], $options );

		if ( ! empty( $errors ) ) {
			$result['warnings'] = array_merge( (array) $result['warnings'], $errors );
		}

		if ( ! empty( $validated['warnings'] ) ) {
			$result['warnings'] = array_merge( (array) $result['warnings'], (array) $validated['warnings'] );
		}

		return self::normalize( $result );
	}

	/**
	 * Reads an optional checkbox parameter.
	 *
	 * Import forms pair a hidden `0` input with the checkbox so the value is
	 * always explicit; the REST API sends a real boolean. `null` means the
	 * parameter was not submitted and the layout default (false) applies.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $params Request parameters.
	 * @param string              $key    Parameter key.
	 * @return bool|null
	 */
	private static function checkbox_param( array $params, $key ) {
		if ( ! isset( $params[ $key ] ) ) {
			return null;
		}

		return Layout_Post_Type::sanitize_hide_theme_part( $params[ $key ] );
	}

	/**
	 * Plain language summary of where an imported layout will appear.
	 *
	 * Shown straight after the import so a user immediately knows whether
	 * the demo will show on the page they are looking at.
	 *
	 * @since 1.0.0
	 *
	 * @param int $layout_id Layout post ID.
	 * @return string Summary, empty when it cannot be determined.
	 */
	private static function display_summary_for( $layout_id ) {
		if ( $layout_id <= 0 || ! function_exists( 'get_post_meta' ) ) {
			return '';
		}

		$conditions = get_post_meta( $layout_id, '_qevora_layout_conditions', true );

		return Condition_Sanitizer::summarize( is_array( $conditions ) ? $conditions : array() );
	}

	/**
	 * Normalizes an import result into one predictable shape.
	 *
	 * A single-template import and a multi-template import return different
	 * structures; the admin UI only ever has to deal with the aggregate one.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $result Raw importer result.
	 * @return array<string,mixed>
	 */
	private static function normalize( array $result ) {
		if ( isset( $result['imported'] ) || isset( $result['skipped'] ) || isset( $result['failed'] ) ) {
			$normalized = array(
				'ok'        => empty( $result['failed'] ) || ! empty( $result['imported'] ),
				'imported'  => isset( $result['imported'] ) ? (array) $result['imported'] : array(),
				'skipped'   => isset( $result['skipped'] ) ? (array) $result['skipped'] : array(),
				'failed'    => isset( $result['failed'] ) ? (array) $result['failed'] : array(),
				'warnings'  => isset( $result['warnings'] ) ? (array) $result['warnings'] : array(),
				'layout_id' => 0,
			);
		} else {
			$action = isset( $result['action'] ) ? (string) $result['action'] : 'failed';

			$normalized = array(
				'ok'        => in_array( $action, array( 'created', 'copied', 'updated', 'skipped' ), true ),
				'imported'  => array(),
				'skipped'   => array(),
				'failed'    => array(),
				'warnings'  => isset( $result['warnings'] ) ? (array) $result['warnings'] : array(),
				'layout_id' => isset( $result['id'] ) ? (int) $result['id'] : 0,
			);

			if ( 'skipped' === $action ) {
				$normalized['skipped'][] = $result;
			} elseif ( in_array( $action, array( 'created', 'copied', 'updated' ), true ) ) {
				$normalized['imported'][] = $result;
			} else {
				$normalized['failed'][] = $result;
			}
		}

		if ( 0 === $normalized['layout_id'] && ! empty( $normalized['imported'][0]['id'] ) ) {
			$normalized['layout_id'] = (int) $normalized['imported'][0]['id'];
		}

		$normalized['edit_link'] = $normalized['layout_id'] > 0
			? (string) get_edit_post_link( $normalized['layout_id'], 'raw' )
			: admin_url( 'edit.php?post_type=' . Layout_Post_Type::POST_TYPE );

		return $normalized;
	}

	/**
	 * Finds the uploaded template file.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $files Files map.
	 * @return array<string,mixed>|null
	 */
	private static function find_uploaded_file( array $files ) {
		foreach ( array( 'qevora_template_file', 'template_file', 'file' ) as $key ) {
			if ( isset( $files[ $key ] ) && is_array( $files[ $key ] ) && isset( $files[ $key ]['tmp_name'] ) ) {
				return $files[ $key ];
			}
		}

		return null;
	}

	/**
	 * Sanitizes the duplicate policy.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value.
	 * @return string skip|copy.
	 */
	public static function sanitize_policy( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';

		return 'copy' === $value ? 'copy' : 'skip';
	}

	/**
	 * Sanitizes the post status used for imported layouts.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value.
	 * @return string publish|draft.
	 */
	public static function sanitize_status( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';

		return 'draft' === $value ? 'draft' : 'publish';
	}

	/**
	 * Flattens per-template errors into a list of messages.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int,array<string,mixed>> $failed Failed entries.
	 * @return string[]
	 */
	public static function flatten_errors( array $failed ) {
		$messages = array();

		foreach ( $failed as $entry ) {
			if ( ! empty( $entry['errors'] ) && is_array( $entry['errors'] ) ) {
				foreach ( $entry['errors'] as $error ) {
					$messages[] = (string) $error;
				}

				continue;
			}

			if ( ! empty( $entry['name'] ) ) {
				/* translators: %s: template name. */
				$messages[] = sprintf( __( 'The template "%s" could not be imported.', 'qevora-layouts' ), (string) $entry['name'] );
			}
		}

		return array_slice( array_values( array_unique( $messages ) ), 0, 10 );
	}

	/**
	 * Builds a human readable summary of an import result.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed>|WP_Error $result Import result.
	 * @return array{message:string,type:string,details:string[],edit_link:string,layout_id:int}
	 */
	public static function summarize( $result ) {
		if ( is_wp_error( $result ) ) {
			$data    = $result->get_error_data();
			$details = array();

			if ( is_array( $data ) && isset( $data['result'] ) && is_array( $data['result'] ) ) {
				$details = self::flatten_errors( isset( $data['result']['failed'] ) ? (array) $data['result']['failed'] : array() );
			}

			if ( empty( $details ) ) {
				$details = array( $result->get_error_message() );
			}

			return array(
				'message'   => __( 'The import did not finish.', 'qevora-layouts' ),
				'type'      => 'error',
				'details'   => $details,
				'edit_link' => '',
				'layout_id' => 0,
			);
		}

		$result   = is_array( $result ) ? self::normalize( $result ) : array();
		$imported = isset( $result['imported'] ) && is_array( $result['imported'] ) ? $result['imported'] : array();
		$skipped  = isset( $result['skipped'] ) && is_array( $result['skipped'] ) ? $result['skipped'] : array();
		$failed   = isset( $result['failed'] ) && is_array( $result['failed'] ) ? $result['failed'] : array();
		$details  = array();

		foreach ( $imported as $entry ) {
			if ( empty( $entry['name'] ) ) {
				continue;
			}

			$line = sprintf(
				/* translators: 1: template name, 2: what happened. */
				__( '%1$s — %2$s', 'qevora-layouts' ),
				(string) $entry['name'],
				self::action_label( isset( $entry['action'] ) ? (string) $entry['action'] : '' )
			);

			$display = self::display_summary_for( isset( $entry['id'] ) ? (int) $entry['id'] : 0 );

			if ( '' !== $display ) {
				$line .= ' · ' . sprintf(
					/* translators: %s: where the imported layout displays. */
					__( 'Displays on: %s', 'qevora-layouts' ),
					$display
				);
			}

			$details[] = $line;
		}

		foreach ( $skipped as $entry ) {
			$details[] = ! empty( $entry['reason'] ) ? (string) $entry['reason'] : self::action_label( 'skipped' );
		}

		foreach ( self::flatten_errors( $failed ) as $error ) {
			$details[] = $error;
		}

		foreach ( (array) ( isset( $result['warnings'] ) ? $result['warnings'] : array() ) as $warning ) {
			$details[] = (string) $warning;
		}

		if ( ! empty( $failed ) ) {
			$type = ! empty( $imported ) ? 'warning' : 'error';
		} elseif ( ! empty( $skipped ) && empty( $imported ) ) {
			$type = 'info';
		} else {
			$type = 'success';
		}

		return array(
			'message'   => sprintf(
				/* translators: 1: number imported, 2: number skipped, 3: number failed. */
				__( 'Import finished: %1$d imported, %2$d skipped, %3$d failed.', 'qevora-layouts' ),
				count( $imported ),
				count( $skipped ),
				count( $failed )
			),
			'type'      => $type,
			'details'   => array_slice( $details, 0, 12 ),
			'edit_link' => isset( $result['edit_link'] ) ? (string) $result['edit_link'] : '',
			'layout_id' => isset( $result['layout_id'] ) ? (int) $result['layout_id'] : 0,
		);
	}

	/**
	 * Returns a label for an import action.
	 *
	 * @since 1.0.0
	 *
	 * @param string $action Action key.
	 * @return string
	 */
	private static function action_label( $action ) {
		$labels = array(
			'created' => __( 'layout created', 'qevora-layouts' ),
			'copied'  => __( 'second copy created', 'qevora-layouts' ),
			'updated' => __( 'existing layout replaced', 'qevora-layouts' ),
			'skipped' => __( 'skipped (already imported)', 'qevora-layouts' ),
			'failed'  => __( 'failed', 'qevora-layouts' ),
		);

		return isset( $labels[ $action ] ) ? $labels[ $action ] : (string) $action;
	}

}
