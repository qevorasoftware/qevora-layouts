<?php
/**
 * Standalone test runner.
 *
 * Runs the plugin unit tests without Composer, PHPUnit or a database:
 *
 *     php tests/run.php
 *     php tests/run.php --filter=Import
 *     php tests/run.php --verbose
 *
 * The WordPress functions the tested classes use come from `tests/stubs/`.
 * They cover sanitizing, hooks, options, the object cache, an in-memory post
 * store and the query conditionals. Anything that needs MySQL, the REST server,
 * the block parser or a real theme is out of scope; those paths are covered by
 * the manual checklist in `tests/INTEGRATION.md`.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

// This file is a CLI entry point, not a web request.
if ( 'cli' !== PHP_SAPI && php_sapi_name() !== 'cli' ) {
	header( 'Content-Type: text/plain; charset=utf-8' );
}

$root = dirname( __DIR__ );

/* -------------------------------------------------------------------------
 * Constants the plugin expects.
 * ---------------------------------------------------------------------- */

defined( 'ABSPATH' ) || define( 'ABSPATH', $root . '/wordpress/' );
defined( 'WPINC' ) || define( 'WPINC', 'wp-includes' );
defined( 'WP_DEBUG' ) || define( 'WP_DEBUG', true );
defined( 'WP_DEBUG_LOG' ) || define( 'WP_DEBUG_LOG', false );
defined( 'WP_DEBUG_DISPLAY' ) || define( 'WP_DEBUG_DISPLAY', false );

defined( 'MINUTE_IN_SECONDS' ) || define( 'MINUTE_IN_SECONDS', 60 );
defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 3600 );
defined( 'DAY_IN_SECONDS' ) || define( 'DAY_IN_SECONDS', 86400 );
defined( 'WEEK_IN_SECONDS' ) || define( 'WEEK_IN_SECONDS', 604800 );
defined( 'MONTH_IN_SECONDS' ) || define( 'MONTH_IN_SECONDS', 2592000 );
defined( 'YEAR_IN_SECONDS' ) || define( 'YEAR_IN_SECONDS', 31536000 );
defined( 'KB_IN_BYTES' ) || define( 'KB_IN_BYTES', 1024 );
defined( 'MB_IN_BYTES' ) || define( 'MB_IN_BYTES', 1048576 );

defined( 'QEVORA_LAYOUTS_VERSION' ) || define( 'QEVORA_LAYOUTS_VERSION', '1.0.0' );
defined( 'QEVORA_LAYOUTS_FILE' ) || define( 'QEVORA_LAYOUTS_FILE', $root . '/qevora-layouts.php' );
defined( 'QEVORA_LAYOUTS_BASENAME' ) || define( 'QEVORA_LAYOUTS_BASENAME', 'qevora-layouts/qevora-layouts.php' );
defined( 'QEVORA_LAYOUTS_DIR' ) || define( 'QEVORA_LAYOUTS_DIR', $root . '/' );
defined( 'QEVORA_LAYOUTS_URL' ) || define( 'QEVORA_LAYOUTS_URL', 'https://example.org/wp-content/plugins/qevora-layouts/' );
defined( 'QEVORA_LAYOUTS_MIN_PHP' ) || define( 'QEVORA_LAYOUTS_MIN_PHP', '7.4' );
defined( 'QEVORA_LAYOUTS_MIN_WP' ) || define( 'QEVORA_LAYOUTS_MIN_WP', '6.4' );
defined( 'QEVORA_LAYOUTS_POST_TYPE' ) || define( 'QEVORA_LAYOUTS_POST_TYPE', 'qevora_layout' );
defined( 'QEVORA_LAYOUTS_OPTION' ) || define( 'QEVORA_LAYOUTS_OPTION', 'qevora_layouts_settings' );
defined( 'QEVORA_LAYOUTS_CACHE_GROUP' ) || define( 'QEVORA_LAYOUTS_CACHE_GROUP', 'qevora_layouts' );

global $wp_version, $wpdb;

$wp_version = '7.1.4';
$wpdb       = null;

/* -------------------------------------------------------------------------
 * Bootstrap.
 * ---------------------------------------------------------------------- */

require_once $root . '/tests/stubs/wordpress.php';
require_once $root . '/includes/Core/Autoloader.php';

QevoraLayouts\Core\Autoloader::register();

require_once $root . '/tests/TestCase.php';

use QevoraLayouts\Tests\Assertion_Failed;
use QevoraLayouts\Tests\TestCase;

/**
 * Collects the test files.
 *
 * @param string $root Plugin root.
 * @param string $filter Optional filter substring.
 * @return string[]
 */
function qevora_layouts_collect_tests( $root, $filter = '' ) {
	$directories = array( $root . '/tests/Unit', $root . '/tests/Integration' );
	$files       = array();

	foreach ( $directories as $directory ) {
		if ( ! is_dir( $directory ) ) {
			continue;
		}

		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ) );

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() || '_Test.php' !== substr( $file->getFilename(), -9 ) ) {
				continue;
			}

			$files[] = $file->getPathname();
		}
	}

	sort( $files );

	if ( '' === $filter ) {
		return $files;
	}

	return array_values(
		array_filter(
			$files,
			function ( $path ) use ( $filter ) {
				return false !== stripos( $path, $filter );
			}
		)
	);
}

/**
 * Derives the fully qualified class name of a test file.
 *
 * @param string $path File path.
 * @param string $root Plugin root.
 * @return string
 */
function qevora_layouts_test_class( $path, $root ) {
	$relative = trim( str_replace( '\\', '/', substr( $path, strlen( $root ) ) ), '/' );
	$relative = preg_replace( '#^tests/#', '', $relative );
	$relative = preg_replace( '#\.php$#', '', $relative );

	return 'QevoraLayouts\\Tests\\' . str_replace( '/', '\\', $relative );
}

/**
 * Parses CLI options.
 *
 * @return array{filter:string,verbose:bool}
 */
function qevora_layouts_options() {
	$options = array(
		'filter'  => '',
		'verbose' => false,
	);

	foreach ( array_slice( (array) $_SERVER['argv'], 1 ) as $argument ) {
		$argument = (string) $argument;

		if ( 0 === strpos( $argument, '--filter=' ) ) {
			$options['filter'] = substr( $argument, 9 );
			continue;
		}

		if ( '--verbose' === $argument || '-v' === $argument ) {
			$options['verbose'] = true;
		}
	}

	return $options;
}

$options = qevora_layouts_options();
$root    = str_replace( '\\', '/', $root );
$files   = qevora_layouts_collect_tests( $root, $options['filter'] );

echo 'Qevora Layouts test suite' . PHP_EOL;
echo 'PHP ' . PHP_VERSION . ' · ' . count( $files ) . ' test file(s)' . ( '' !== $options['filter'] ? ' · filter "' . $options['filter'] . '"' : '' ) . PHP_EOL;
echo str_repeat( '-', 72 ) . PHP_EOL;

$tests      = 0;
$assertions = 0;
$failures   = array();
$started    = microtime( true );

foreach ( $files as $file ) {
	require_once $file;

	$class = qevora_layouts_test_class( $file, $root );

	if ( ! class_exists( $class ) ) {
		$failures[] = array(
			'test'    => $class,
			'message' => 'The class was not found in ' . basename( $file ) . '. Check the namespace: it must be ' . $class . '.',
		);

		echo basename( $file ) . ' … MISSING CLASS' . PHP_EOL;

		continue;
	}

	$reflection = new ReflectionClass( $class );

	if ( $reflection->isAbstract() ) {
		continue;
	}

	echo basename( str_replace( '_Test.php', '', $file ) ) . ' ';

	$methods = array();

	foreach ( $reflection->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ) {
		if ( 0 === strpos( $method->getName(), 'test_' ) ) {
			$methods[] = $method->getName();
		}
	}

	sort( $methods );

	foreach ( $methods as $method ) {
		++$tests;

		/** @var TestCase $instance */
		$instance = $reflection->newInstance();
		$instance->setUp();
		$instance->reset_assertion_count();

		try {
			$instance->$method();
			$assertions += $instance->get_assertion_count();
			echo '.';
		} catch ( Assertion_Failed $failure ) {
			$assertions += $instance->get_assertion_count();
			$failures[]  = array(
				'test'    => $class . '::' . $method,
				'message' => $failure->getMessage(),
				'trace'   => $failure->getTraceAsString(),
			);
			echo 'F';
		} catch ( Throwable $error ) {
			$assertions += $instance->get_assertion_count();
			$failures[]  = array(
				'test'    => $class . '::' . $method,
				'message' => get_class( $error ) . ': ' . $error->getMessage() . ' in ' . basename( $error->getFile() ) . ':' . $error->getLine(),
				'trace'   => $error->getTraceAsString(),
			);
			echo 'E';
		} finally {
			$instance->tearDown();
		}
	}

	echo ' ' . count( $methods ) . ' test(s)' . PHP_EOL;
}

$duration = microtime( true ) - $started;

echo str_repeat( '-', 72 ) . PHP_EOL;

if ( ! empty( $failures ) ) {
	echo count( $failures ) . ' failing test(s):' . PHP_EOL . PHP_EOL;

	foreach ( $failures as $index => $failure ) {
		echo ( $index + 1 ) . ') ' . $failure['test'] . PHP_EOL;
		echo '   ' . str_replace( PHP_EOL, PHP_EOL . '   ', $failure['message'] ) . PHP_EOL;

		if ( $options['verbose'] && ! empty( $failure['trace'] ) ) {
			$lines = array_slice( explode( PHP_EOL, $failure['trace'] ), 0, 8 );

			foreach ( $lines as $line ) {
				echo '   ' . $line . PHP_EOL;
			}
		}

		echo PHP_EOL;
	}
}

echo 'Tests: ' . $tests . ', Assertions: ' . $assertions . ', Failures: ' . count( $failures ) . PHP_EOL;
echo 'Time: ' . number_format( $duration, 3 ) . 's' . PHP_EOL;
echo ( empty( $failures ) ? 'OK' : 'FAILED' ) . PHP_EOL;

exit( empty( $failures ) ? 0 : 1 );
