<?php
/**
 * Cache helpers.
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
 * Small caching layer with a versioned key space.
 *
 * Persistent values are stored in transients whose key contains a version
 * number. Bumping the version invalidates everything at once, which works for
 * every object cache backend (including the default non-persistent one) without
 * relying on group flushing.
 *
 * @since 1.0.0
 */
class Cache {

	/**
	 * Option that stores the current cache version.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	const VERSION_OPTION = 'qevora_layouts_cache_version';

	/**
	 * Version resolved for the current request.
	 *
	 * @since 1.0.0
	 * @var   int|null
	 */
	private static $version = null;

	/**
	 * Per-request memo store.
	 *
	 * @since 1.0.0
	 * @var   array<string,mixed>
	 */
	private static $request_cache = array();

	/**
	 * Returns the current cache version.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public static function get_version() {
		if ( null === self::$version ) {
			$stored        = (int) get_option( self::VERSION_OPTION, 0 );
			self::$version = $stored > 0 ? $stored : 1;
		}

		return self::$version;
	}

	/**
	 * Invalidates every cached value.
	 *
	 * @since 1.0.0
	 *
	 * @return int The new version.
	 */
	public static function bump_version() {
		self::$request_cache = array();
		self::$version       = (int) gmdate( 'U' );

		update_option( self::VERSION_OPTION, self::$version, false );

		/**
		 * Fires after the layout cache has been invalidated.
		 *
		 * @since 1.0.0
		 *
		 * @param int $version New cache version.
		 */
		do_action( 'qevora_layouts_cache_flushed', self::$version );

		return self::$version;
	}

	/**
	 * Builds a namespaced, versioned cache key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Raw key.
	 * @return string
	 */
	public static function key( $key ) {
		return 'ql_' . self::get_version() . '_' . preg_replace( '/[^a-z0-9_\-]/i', '_', (string) $key );
	}

	/**
	 * Reads a value from the request cache, then the object cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Raw key.
	 * @return mixed|null Null when not cached.
	 */
	public static function get( $key ) {
		$namespaced = self::key( $key );

		if ( array_key_exists( $namespaced, self::$request_cache ) ) {
			return self::$request_cache[ $namespaced ];
		}

		$found = false;
		$value = wp_cache_get( $namespaced, QEVORA_LAYOUTS_CACHE_GROUP, false, $found );

		if ( $found ) {
			self::$request_cache[ $namespaced ] = $value;

			return $value;
		}

		return null;
	}

	/**
	 * Writes a value to the request and object cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key   Raw key.
	 * @param mixed  $value Value.
	 * @param int    $ttl   Optional persistent TTL in seconds. 0 = request only.
	 * @return void
	 */
	public static function set( $key, $value, $ttl = 0 ) {
		$namespaced = self::key( $key );

		self::$request_cache[ $namespaced ] = $value;

		if ( $ttl > 0 ) {
			wp_cache_set( $namespaced, $value, QEVORA_LAYOUTS_CACHE_GROUP, $ttl );
		}
	}

	/**
	 * Deletes a value from both caches.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Raw key.
	 * @return void
	 */
	public static function delete( $key ) {
		$namespaced = self::key( $key );

		unset( self::$request_cache[ $namespaced ] );
		wp_cache_delete( $namespaced, QEVORA_LAYOUTS_CACHE_GROUP );
	}

	/**
	 * Returns a value, computing and caching it when missing.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $key      Raw key.
	 * @param callable $callback Producer.
	 * @param int      $ttl      Optional persistent TTL in seconds.
	 * @return mixed
	 */
	public static function remember( $key, callable $callback, $ttl = 0 ) {
		$cached = self::get( $key );

		if ( null !== $cached ) {
			return $cached;
		}

		$value = $callback();

		self::set( $key, $value, $ttl );

		return $value;
	}

	/**
	 * Clears the per-request memo store without touching the cache version.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function flush_request_cache() {
		self::$request_cache = array();
	}

	/**
	 * Returns the number of values held in the request cache. Used by tests.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public static function request_cache_size() {
		return count( self::$request_cache );
	}
}
