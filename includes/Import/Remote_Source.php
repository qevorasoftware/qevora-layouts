<?php
/**
 * Remote template source.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Import;

use QevoraLayouts\Core\Cache;
use QevoraLayouts\Core\Logger;
use QevoraLayouts\Core\Settings;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optional remote endpoint for the template library.
 *
 * There is no built-in or hard-coded remote service. The endpoint is whatever
 * an administrator enters in Settings → Template Library, it must be https, and
 * remote fetching stays disabled until it is switched on. Bundled starter
 * templates work without any network access.
 *
 * Expected response formats (documented in README.md):
 *
 * 1. A full list:      `{ "templates": [ { …full template… } ] }`
 * 2. A list of summaries, each with its own `url` (same host only), fetched
 *    when the user imports that single template.
 *
 * Every response is treated as untrusted and passes the same validation as a
 * bundled or uploaded file.
 *
 * @since 1.0.0
 */
class Remote_Source {

	/**
	 * Request timeout in seconds.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	const TIMEOUT = 15;

	/**
	 * Maximum accepted response size in bytes.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	const MAX_RESPONSE_BYTES = 8388608; // 8 MB.

	/**
	 * Whether the remote library is switched on and configured.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return Settings::is_enabled( 'remote_library_enabled' ) && '' !== self::get_url();
	}

	/**
	 * Returns the configured endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function get_url() {
		return Settings::sanitize_library_url( (string) Settings::get( 'remote_library_url', '' ) );
	}

	/**
	 * Fetches the template index.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $force Whether to bypass the cache.
	 * @return array<int,array<string,mixed>>|WP_Error Raw template payloads.
	 */
	public static function fetch_index( $force = false ) {
		if ( ! self::is_enabled() ) {
			return new WP_Error(
				'qevora_layouts_remote_disabled',
				__( 'The remote template library is disabled. Enable it and set an https endpoint in Settings → Template Library.', 'qevora-layouts' )
			);
		}

		$url       = self::get_url();
		$cache_key = 'remote_index_' . md5( $url );

		if ( ! $force ) {
			$cached = Cache::get( $cache_key );

			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$response = self::request( $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$payload = Importer::decode_json( $response );

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$items = array();

		if ( isset( $payload['templates'] ) && is_array( $payload['templates'] ) ) {
			$items = $payload['templates'];
		} elseif ( self::is_list( $payload ) ) {
			$items = $payload;
		}

		$items = array_slice( array_values( array_filter( $items, 'is_array' ) ), 0, 100 );

		Cache::set( $cache_key, $items, 12 * HOUR_IN_SECONDS );

		Logger::debug( 'Fetched the remote template index.', array( 'count' => count( $items ) ) );

		return $items;
	}

	/**
	 * Fetches one template referenced by a library summary.
	 *
	 * @since 1.0.0
	 *
	 * @param Template $template Summary template holding a `url`.
	 * @return Template|WP_Error Fully populated template.
	 */
	public static function fetch_template( Template $template ) {
		if ( '' === $template->url ) {
			return new WP_Error( 'qevora_layouts_remote_missing_url', __( 'This template has no download URL.', 'qevora-layouts' ) );
		}

		if ( ! self::url_is_allowed( $template->url ) ) {
			return new WP_Error(
				'qevora_layouts_remote_host_mismatch',
				__( 'The template download URL is not on the configured library host.', 'qevora-layouts' )
			);
		}

		$response = self::request( $template->url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$payload = Importer::decode_json( $response );

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		if ( isset( $payload['template'] ) && is_array( $payload['template'] ) ) {
			$payload = $payload['template'];
		}

		$result = Template_Validator::validate(
			$payload,
			array(
				'origin'     => 'remote',
				'require_id' => true,
			)
		);

		if ( ! $result['ok'] || null === $result['template'] ) {
			return new WP_Error( 'qevora_layouts_remote_invalid_template', implode( ' ', $result['errors'] ) );
		}

		$full          = $result['template'];
		$full->url     = $template->url;
		$full->origin  = 'remote';
		$full->preview = '' !== $template->preview ? $template->preview : $full->preview;

		return $full;
	}

	/**
	 * Performs a validated https GET request.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url Target URL.
	 * @return string|WP_Error Response body.
	 */
	public static function request( $url ) {
		if ( ! self::url_is_allowed( $url ) ) {
			return new WP_Error( 'qevora_layouts_remote_invalid_url', __( 'Only https URLs on the configured library host are allowed.', 'qevora-layouts' ) );
		}

		$args = array(
			'timeout'     => self::TIMEOUT,
			'redirection' => 2,
			'user-agent'  => 'QevoraLayouts/' . QEVORA_LAYOUTS_VERSION . ' (+https://qevora.com)',
			'headers'     => array(
				'Accept' => 'application/json',
			),
			/**
			 * Template downloads are plain data; cookies are never sent.
			 */
			'cookies'     => array(),
		);

		/**
		 * Filters the arguments of the remote library request.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,mixed> $args wp_remote_get() arguments.
		 * @param string              $url  Request URL.
		 */
		$args = apply_filters( 'qevora_layouts_remote_request_args', $args, $url );

		$response = wp_remote_get( esc_url_raw( $url ), $args );

		if ( is_wp_error( $response ) ) {
			Logger::warning( 'Remote library request failed.', array( 'error' => $response->get_error_message() ) );

			return new WP_Error(
				'qevora_layouts_remote_error',
				sprintf(
					/* translators: %s: transport error message. */
					__( 'The template library could not be reached: %s', 'qevora-layouts' ),
					$response->get_error_message()
				)
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return new WP_Error(
				'qevora_layouts_remote_bad_status',
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'The template library returned HTTP status %d.', 'qevora-layouts' ),
					$code
				)
			);
		}

		$type = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );

		if ( '' !== $type && false === strpos( $type, 'json' ) && false === strpos( $type, 'text' ) ) {
			return new WP_Error( 'qevora_layouts_remote_bad_type', __( 'The template library returned an unexpected content type.', 'qevora-layouts' ) );
		}

		$body = (string) wp_remote_retrieve_body( $response );

		if ( strlen( $body ) > self::MAX_RESPONSE_BYTES ) {
			return new WP_Error( 'qevora_layouts_remote_too_large', __( 'The template library response is too large.', 'qevora-layouts' ) );
		}

		return $body;
	}

	/**
	 * Whether a URL may be requested.
	 *
	 * The URL must be https and must live on the host of the configured
	 * endpoint, so a malicious index cannot point the plugin at an arbitrary
	 * server.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $url Candidate URL.
	 * @return bool
	 */
	public static function url_is_allowed( $url ) {
		if ( ! is_string( $url ) || '' === trim( $url ) ) {
			return false;
		}

		$base = self::get_url();

		if ( '' === $base ) {
			return false;
		}

		$clean = esc_url_raw( trim( $url ), array( 'https' ) );

		if ( '' === $clean ) {
			return false;
		}

		$parts      = wp_parse_url( $clean );
		$base_parts = wp_parse_url( $base );

		if ( empty( $parts['scheme'] ) || 'https' !== strtolower( (string) $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}

		if ( empty( $base_parts['scheme'] ) || 'https' !== strtolower( (string) $base_parts['scheme'] ) || empty( $base_parts['host'] ) ) {
			return false;
		}

		return strtolower( (string) $parts['host'] ) === strtolower( (string) $base_parts['host'] );
	}

	/**
	 * Drops the cached index.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function clear_cache() {
		Cache::delete( 'remote_index_' . md5( self::get_url() ) );
		Cache::bump_version();
	}

	/**
	 * Whether an array is a plain list.
	 *
	 * @since 1.0.0
	 *
	 * @param array<mixed> $value Array.
	 * @return bool
	 */
	private static function is_list( array $value ) {
		if ( array() === $value ) {
			return true;
		}

		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
