<?php
/**
 * Debug logger.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optional, privacy conscious debug logger.
 *
 * Debug mode is off by default. When enabled, messages are sent to PHP's
 * `error_log()` (which WordPress routes to `debug.log` when `WP_DEBUG_LOG` is
 * on) and kept in a small in-memory ring buffer that the System Status screen
 * can show to administrators. Nothing is written to the database and no
 * personal data, tokens or server paths are logged by the plugin itself.
 *
 * @since 1.0.0
 */
class Logger {

	/**
	 * Maximum number of buffered messages.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	const BUFFER_SIZE = 60;

	/**
	 * Buffered messages for the current request.
	 *
	 * @since 1.0.0
	 * @var   array<int,array<string,mixed>>
	 */
	private static $buffer = array();

	/**
	 * Whether debug mode is on.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) Settings::is_enabled( 'debug_mode' );
	}

	/**
	 * Logs a message when debug mode is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $message Message.
	 * @param array<string,mixed>  $context Optional scalar context.
	 * @param string               $level   One of debug|info|warning|error.
	 * @return bool True when the message was recorded.
	 */
	public static function log( $message, array $context = array(), $level = 'info' ) {
		if ( ! self::is_enabled() ) {
			return false;
		}

		$levels = array( 'debug', 'info', 'warning', 'error' );

		if ( ! in_array( $level, $levels, true ) ) {
			$level = 'info';
		}

		$entry = array(
			'time'    => gmdate( 'Y-m-d H:i:s' ),
			'level'   => $level,
			'message' => (string) $message,
			'context' => self::flatten_context( $context ),
		);

		self::$buffer[] = $entry;

		if ( count( self::$buffer ) > self::BUFFER_SIZE ) {
			self::$buffer = array_slice( self::$buffer, -1 * self::BUFFER_SIZE );
		}

		$line = sprintf( '[Qevora Layouts] %s %s', strtoupper( $level ), $entry['message'] );

		if ( ! empty( $entry['context'] ) ) {
			$line .= ' ' . wp_json_encode( $entry['context'] );
		}

		error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Opt-in developer diagnostics.

		return true;
	}

	/**
	 * Logs a debug level message.
	 *
	 * @since 1.0.0
	 *
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Optional context.
	 * @return bool
	 */
	public static function debug( $message, array $context = array() ) {
		return self::log( $message, $context, 'debug' );
	}

	/**
	 * Logs a warning level message.
	 *
	 * @since 1.0.0
	 *
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Optional context.
	 * @return bool
	 */
	public static function warning( $message, array $context = array() ) {
		return self::log( $message, $context, 'warning' );
	}

	/**
	 * Logs an error level message.
	 *
	 * @since 1.0.0
	 *
	 * @param string              $message Message.
	 * @param array<string,mixed> $context Optional context.
	 * @return bool
	 */
	public static function error( $message, array $context = array() ) {
		return self::log( $message, $context, 'error' );
	}

	/**
	 * Returns the buffered messages.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_buffer() {
		return self::$buffer;
	}

	/**
	 * Empties the buffer.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function clear_buffer() {
		self::$buffer = array();
	}

	/**
	 * Keeps only scalar values in a context array.
	 *
	 * Prevents objects, resources or deeply nested data (which could contain
	 * personal data) from reaching the log.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $context Raw context.
	 * @return array<string,mixed>
	 */
	private static function flatten_context( array $context ) {
		$flat = array();

		foreach ( $context as $key => $value ) {
			if ( is_scalar( $value ) || null === $value ) {
				$flat[ (string) $key ] = $value;
				continue;
			}

			if ( is_array( $value ) && count( $value ) <= 10 ) {
				$nested = array();

				foreach ( $value as $nested_key => $nested_value ) {
					if ( is_scalar( $nested_value ) || null === $nested_value ) {
						$nested[ (string) $nested_key ] = $nested_value;
					}
				}

				$flat[ (string) $key ] = $nested;
				continue;
			}

			$flat[ (string) $key ] = '[' . gettype( $value ) . ']';
		}

		return $flat;
	}
}
