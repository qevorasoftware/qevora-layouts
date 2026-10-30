<?php
/**
 * Minimal test case for the standalone runner.
 *
 * PHPUnit is not available in every environment where this plugin is built, so
 * the test suite ships with a tiny assertion layer that has the same method
 * names. Tests written against it can be moved to PHPUnit later by extending
 * `PHPUnit\Framework\TestCase` instead.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Tests;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thrown when an assertion fails.
 */
class Assertion_Failed extends \RuntimeException {
}

/**
 * Base test case.
 *
 * @since 1.0.0
 */
abstract class TestCase {

	/**
	 * Number of assertions made by the current test.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	protected $assertions = 0;

	/**
	 * Runs before each test.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function setUp() {
		\Qevora_Test_Env::reset();
	}

	/**
	 * Runs after each test.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function tearDown() {
		\Qevora_Test_Env::reset();
	}

	/**
	 * Returns the number of assertions made.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function get_assertion_count() {
		return (int) $this->assertions;
	}

	/**
	 * Resets the assertion counter.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function reset_assertion_count() {
		$this->assertions = 0;
	}

	/**
	 * Asserts a value is true.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $value   Value.
	 * @param string $message Message.
	 * @return void
	 */
	protected function assertTrue( $value, $message = '' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		++$this->assertions;

		if ( true !== $value ) {
			$this->fail( $message ?: 'Expected true, got ' . $this->export( $value ) . '.' );
		}
	}

	/**
	 * Asserts a value is false.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $value   Value.
	 * @param string $message Message.
	 * @return void
	 */
	protected function assertFalse( $value, $message = '' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		++$this->assertions;

		if ( false !== $value ) {
			$this->fail( $message ?: 'Expected false, got ' . $this->export( $value ) . '.' );
		}
	}

	/**
	 * Asserts two values are identical.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $expected Expected value.
	 * @param mixed  $actual   Actual value.
	 * @param string $message  Message.
	 * @return void
	 */
	protected function assertSame( $expected, $actual, $message = '' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		++$this->assertions;

		if ( $expected !== $actual ) {
			$this->fail(
				( $message ? $message . "\n     " : '' ) .
				'Expected ' . $this->export( $expected ) . ', got ' . $this->export( $actual ) . '.'
			);
		}
	}

	/**
	 * Asserts two values are not identical.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $expected Expected value.
	 * @param mixed  $actual   Actual value.
	 * @param string $message  Message.
	 * @return void
	 */
	protected function assertNotSame( $expected, $actual, $message = '' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		++$this->assertions;

		if ( $expected === $actual ) {
			$this->fail( $message ?: 'Expected a value different from ' . $this->export( $expected ) . '.' );
		}
	}

	/**
	 * Asserts two values are loosely equal.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $expected Expected value.
	 * @param mixed  $actual   Actual value.
	 * @param string $message  Message.
	 * @return void
	 */
	protected function assertEquals( $expected, $actual, $message = '' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		++$this->assertions;

		if ( $expected != $actual ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			$this->fail(
				( $message ? $message . "\n     " : '' ) .
				'Expected ' . $this->export( $expected ) . ', got ' . $this->export( $actual ) . '.'
			);
		}
	}

	/**
	 * Asserts a value is null.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $value   Value.
	 * @param string $message Message.
	 * @return void
	 */
	protected function assertNull( $value, $message = '' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		++$this->assertions;

		if ( null !== $value ) {
			$this->fail( $message ?: 'Expected null, got ' . $this->export( $value ) . '.' );
		}
	}

	/**
	 * Asserts a value is not null.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $value   Value.
	 * @param string $message Message.
	 * @return void
	 */
	protected function assertNotNull( $value, $message = '' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		++$this->assertions;

		if ( null === $value ) {
			$this->fail( $message ?: 'Expected a value, got null.' );
		}
	}

	/**
	 * Asserts a value is empty.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $value   Value.
	 * @param string $message Message.
	 * @return void
	 */
	protected function assertEmpty( $value, $message = '' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		++$this->assertions;

		if ( ! empty( $value ) ) {
			$this->fail( $message ?: 'Expected an empty value, got ' . $this->export( $value ) . '.' );
		}
	}

	/**
	 * Asserts a value is not empty.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $value   Value.
	 * @param string $message Message.
	 * @return void
	 */
	protected function assertNotEmpty( $value, $message = '' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		++$this->assertions;

		if ( empty( $value ) ) {
			$this->fail( $message ?: 'Expected a non-empty value.' );
		}
	}

	/**
	 * Asserts the size of a countable value.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $expected Expected count.
	 * @param mixed  $actual   Countable.
	 * @param string $message  Message.
	 * @return void
	 */
	protected function assertCount( $expected, $actual, $message = '' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		++$this->assertions;

		$count = is_array( $actual ) || $actual instanceof \Countable ? count( $actual ) : -1;

		if ( (int) $expected !== $count ) {
			$this->fail(
				( $message ? $message . "\n     " : '' ) .
				'Expected ' . (int) $expected . ' items, got ' . $count . '.'
			);
		}
	}

	/**
	 * Asserts an array contains a value.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $needle   Needle.
	 * @param mixed  $haystack Haystack.
	 * @param string $message  Message.
	 * @return void
	 */
	protected function assertContains( $needle, $haystack, $message = '' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		++$this->assertions;

		if ( ! is_array( $haystack ) || ! in_array( $needle, $haystack, true ) ) {
			$this->fail( $message ?: 'Expected the list to contain ' . $this->export( $needle ) . '.' );
		}
	}

	/**
	 * Asserts an array does not contain a value.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $needle   Needle.
	 * @param mixed  $haystack Haystack.
	 * @param string $message  Message.
	 * @return void
	 */
	protected function assertNotContains( $needle, $haystack, $message = '' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		++$this->assertions;

		if ( is_array( $haystack ) && in_array( $needle, $haystack, true ) ) {
			$this->fail( $message ?: 'Expected the list not to contain ' . $this->export( $needle ) . '.' );
		}
	}

	/**
	 * Asserts a string contains a needle.
	 *
	 * @since 1.0.0
	 *
	 * @param string $needle   Needle.
	 * @param string $haystack Haystack.
	 * @param string $message  Message.
	 * @return void
	 */
	protected function assertStringContainsString( $needle, $haystack, $message = '' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		++$this->assertions;

		if ( false === strpos( (string) $haystack, (string) $needle ) ) {
			$this->fail(
				( $message ? $message . "\n     " : '' ) .
				'Expected the string to contain "' . $needle . '". Got: ' . $this->export( $haystack ) . '.'
			);
		}
	}

	/**
	 * Asserts a string does not contain a needle.
	 *
	 * @since 1.0.0
	 *
	 * @param string $needle   Needle.
	 * @param string $haystack Haystack.
	 * @param string $message  Message.
	 * @return void
	 */
	protected function assertStringNotContainsString( $needle, $haystack, $message = '' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		++$this->assertions;

		if ( false !== strpos( (string) $haystack, (string) $needle ) ) {
			$this->fail( $message ?: 'Expected the string not to contain "' . $needle . '".' );
		}
	}

	/**
	 * Asserts an array has a key.
	 *
	 * @since 1.0.0
	 *
	 * @param string|int $key     Key.
	 * @param mixed      $array   Array.
	 * @param string     $message Message.
	 * @return void
	 */
	protected function assertArrayHasKey( $key, $array, $message = '' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		++$this->assertions;

		if ( ! is_array( $array ) || ! array_key_exists( $key, $array ) ) {
			$this->fail( $message ?: 'Expected the array to have the key "' . $key . '".' );
		}
	}

	/**
	 * Asserts an object is an instance of a class.
	 *
	 * @since 1.0.0
	 *
	 * @param string $expected Class name.
	 * @param mixed  $actual   Value.
	 * @param string $message  Message.
	 * @return void
	 */
	protected function assertInstanceOf( $expected, $actual, $message = '' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		++$this->assertions;

		if ( ! $actual instanceof $expected ) {
			$this->fail(
				( $message ? $message . "\n     " : '' ) .
				'Expected an instance of ' . $expected . ', got ' . $this->export( $actual ) . '.'
			);
		}
	}

	/**
	 * Asserts a WP_Error was returned.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $value   Value.
	 * @param string $message Message.
	 * @return \WP_Error
	 */
	protected function assertIsError( $value, $message = '' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		++$this->assertions;

		if ( ! $value instanceof \WP_Error ) {
			$this->fail( $message ?: 'Expected a WP_Error, got ' . $this->export( $value ) . '.' );
		}

		return $value;
	}

	/**
	 * Asserts a value is greater than another.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $expected Lower bound.
	 * @param mixed  $actual   Value.
	 * @param string $message  Message.
	 * @return void
	 */
	protected function assertGreaterThan( $expected, $actual, $message = '' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		++$this->assertions;

		if ( ! ( $actual > $expected ) ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			$this->fail( $message ?: 'Expected a value greater than ' . $this->export( $expected ) . ', got ' . $this->export( $actual ) . '.' );
		}
	}

	/**
	 * Asserts a callback throws.
	 *
	 * @since 1.0.0
	 *
	 * @param callable $callback Callback.
	 * @param string   $message  Message.
	 * @return void
	 */
	protected function assertThrows( callable $callback, $message = '' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		++$this->assertions;

		try {
			$callback();
		} catch ( \Throwable $error ) {
			return;
		}

		$this->fail( $message ?: 'Expected the callback to throw.' );
	}

	/**
	 * Records a failure.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Message.
	 * @return void
	 */
	protected function fail( $message = '' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		throw new Assertion_Failed( (string) $message );
	}

	/**
	 * Exports a value for a failure message.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function export( $value ) {
		if ( null === $value ) {
			return 'null';
		}

		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( is_string( $value ) ) {
			$value = strlen( $value ) > 160 ? substr( $value, 0, 160 ) . '…' : $value;

			return '"' . str_replace( array( "\n", "\r", "\t" ), array( '\n', '\r', '\t' ), $value ) . '"';
		}

		if ( is_array( $value ) ) {
			return 'array(' . count( $value ) . ') ' . substr( (string) json_encode( $value ), 0, 200 );
		}

		if ( is_object( $value ) ) {
			return get_class( $value );
		}

		return (string) $value;
	}
}
