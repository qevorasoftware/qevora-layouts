<?php
/**
 * Tests for the cache layer.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Tests\Unit\Core;

use QevoraLayouts\Core\Cache;
use QevoraLayouts\Tests\TestCase;
use Qevora_Test_Env;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every cached value carries a version so stale matches disappear on save.
 *
 * @since 1.0.0
 */
class Cache_Test extends TestCase {

	/**
	 * Starts each test from a clean, unversioned cache.
	 *
	 * Both the version and the request memo are static, so they are reset
	 * through reflection to keep the tests independent of each other.
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		$reflection = new \ReflectionClass( Cache::class );

		foreach ( array( 'version' => null, 'request_cache' => array() ) as $property => $value ) {
			$handle = $reflection->getProperty( $property );

			// setAccessible() is a no-op since PHP 8.1 and deprecated since 8.5.
			if ( PHP_VERSION_ID < 80100 ) {
				$handle->setAccessible( true );
			}

			$handle->setValue( null, $value );
		}

		Qevora_Test_Env::$options['qevora_layouts_cache_version'] = null;
	}

	/**
	 * Returns the namespaced key for a raw key.
	 *
	 * @param string $key Raw key.
	 * @return string
	 */
	private function namespaced( $key ) {
		return Cache::key( $key );
	}

	/**
	 * Reports whether a key reached the object cache.
	 *
	 * @param string $key Raw key.
	 * @return bool
	 */
	private function is_cached( $key ) {
		return isset( Qevora_Test_Env::$cache[ QEVORA_LAYOUTS_CACHE_GROUP ][ $this->namespaced( $key ) ] );
	}

	/**
	 * Returns the TTL an object-cache write was given, -1 when there was none.
	 *
	 * @param string $key Raw key.
	 * @return int
	 */
	private function cached_ttl( $key ) {
		$ttls = isset( Qevora_Test_Env::$cache_ttl[ QEVORA_LAYOUTS_CACHE_GROUP ] )
			? Qevora_Test_Env::$cache_ttl[ QEVORA_LAYOUTS_CACHE_GROUP ]
			: array();
		$name = $this->namespaced( $key );

		return isset( $ttls[ $name ] ) ? $ttls[ $name ] : -1;
	}

	/**
	 * The version travels inside every key.
	 *
	 * @return void
	 */
	public function test_keys_are_versioned_and_sanitized() {
		$this->assertSame( 1, Cache::get_version() );
		$this->assertSame( 'ql_1_candidates_header', Cache::key( 'candidates_header' ) );
		$this->assertSame( 'ql_1_a_b_c', Cache::key( 'a/b?c' ) );
		$this->assertSame( 'ql_1_key', Cache::key( 'key' ) );
	}

	/**
	 * Bumping the version invalidates every previously stored key.
	 *
	 * @return void
	 */
	public function test_bump_version_rotates_the_namespace() {
		Cache::set( 'candidates_header', array( 1 ) );

		$before = Cache::key( 'candidates_header' );

		Cache::bump_version();

		$this->assertNotSame( $before, Cache::key( 'candidates_header' ) );
		$this->assertGreaterThan( 1, Cache::get_version() );
		$this->assertSame( 0, Cache::request_cache_size(), 'Bumping the version clears the request cache.' );
		$this->assertNull( Cache::get( 'candidates_header' ), 'The old key no longer resolves.' );
	}

	/**
	 * Two invalidations in the same second must not share a version.
	 *
	 * Importing a starter pack saves several layouts in one request; a stale
	 * candidate list would survive until the TTL expired.
	 *
	 * @return void
	 */
	public function test_consecutive_bumps_never_collide() {
		$first  = Cache::bump_version();
		$second = Cache::bump_version();
		$third  = Cache::bump_version();

		$this->assertGreaterThan( $first, $second );
		$this->assertGreaterThan( $second, $third );
		$this->assertSame( $third, Cache::get_version() );
	}

	/**
	 * Flushing the cache fires an action so integrations can follow.
	 *
	 * @return void
	 */
	public function test_bump_version_fires_an_action() {
		$seen = null;

		add_action(
			'qevora_layouts_cache_flushed',
			function ( $version ) use ( &$seen ) {
				$seen = $version;
			}
		);

		Cache::bump_version();

		$this->assertSame( Cache::get_version(), $seen );
	}

	/**
	 * Without a TTL a value only lives for the current request.
	 *
	 * @return void
	 */
	public function test_set_without_ttl_is_request_only() {
		$this->assertNull( Cache::get( 'missing' ) );

		Cache::set( 'candidates_header', array( 1, 2 ) );

		$this->assertSame( array( 1, 2 ), Cache::get( 'candidates_header' ) );
		$this->assertSame( 1, Cache::request_cache_size() );
		$this->assertFalse( $this->is_cached( 'candidates_header' ), 'A request-only value never reaches the object cache.' );

		Cache::flush_request_cache();

		$this->assertNull( Cache::get( 'candidates_header' ) );
	}

	/**
	 * A positive TTL pushes the value into the object cache.
	 *
	 * @return void
	 */
	public function test_set_with_ttl_persists_to_the_object_cache() {
		Cache::set( 'candidates_footer', array( 3 ), 300 );

		$this->assertTrue( $this->is_cached( 'candidates_footer' ) );
		$this->assertSame( 300, $this->cached_ttl( 'candidates_footer' ) );

		Cache::flush_request_cache();

		$this->assertSame( array( 3 ), Cache::get( 'candidates_footer' ), 'The object cache still serves the value.' );
		$this->assertSame( 1, Cache::request_cache_size(), 'A persistent hit re-populates the request cache.' );
	}

	/**
	 * Deleting removes the value from both layers.
	 *
	 * @return void
	 */
	public function test_delete_clears_both_layers() {
		Cache::set( 'candidates_header', array( 1 ), 300 );
		Cache::delete( 'candidates_header' );

		$this->assertNull( Cache::get( 'candidates_header' ) );
		$this->assertFalse( $this->is_cached( 'candidates_header' ), 'A request-only value never reaches the object cache.' );
	}

	/**
	 * remember() runs the callback once per request.
	 *
	 * @return void
	 */
	public function test_remember_computes_once_per_request() {
		$calls    = 0;
		$callback = function () use ( &$calls ) {
			++$calls;

			return array( 'expensive' );
		};

		$this->assertSame( array( 'expensive' ), Cache::remember( 'plan_header', $callback ) );
		$this->assertSame( array( 'expensive' ), Cache::remember( 'plan_header', $callback ) );
		$this->assertSame( 1, $calls );

		Cache::flush_request_cache();

		$this->assertSame( array( 'expensive' ), Cache::remember( 'plan_header', $callback ) );
		$this->assertSame( 2, $calls, 'A request-only value is recomputed once the request cache is gone.' );
	}

	/**
	 * A TTL keeps the computed value across request-cache flushes.
	 *
	 * @return void
	 */
	public function test_remember_with_ttl_survives_a_flush() {
		$calls = 0;

		$producer = function () use ( &$calls ) {
			++$calls;

			return 'computed';
		};

		Cache::remember( 'plan_footer', $producer, 300 );
		Cache::flush_request_cache();
		$this->assertSame( 'computed', Cache::remember( 'plan_footer', $producer, 300 ) );

		$this->assertSame( 1, $calls );
	}

	/**
	 * A null result is treated as a miss.
	 *
	 * This is deliberate: caching null would make a "no layout matched" result
	 * sticky for the whole TTL, so the lookup is recomputed instead.
	 *
	 * @return void
	 */
	public function test_null_results_are_recomputed() {
		$calls = 0;

		Cache::remember(
			'nothing',
			function () use ( &$calls ) {
				++$calls;

				return null;
			}
		);
		Cache::remember(
			'nothing',
			function () use ( &$calls ) {
				++$calls;

				return null;
			}
		);

		$this->assertSame( 2, $calls );
	}

	/**
	 * A false value is cached like any other value.
	 *
	 * @return void
	 */
	public function test_false_is_a_cacheable_value() {
		$calls = 0;

		$producer = function () use ( &$calls ) {
			++$calls;

			return false;
		};

		$this->assertFalse( Cache::remember( 'flag', $producer ) );
		$this->assertFalse( Cache::remember( 'flag', $producer ) );
		$this->assertSame( 1, $calls );
	}
}
