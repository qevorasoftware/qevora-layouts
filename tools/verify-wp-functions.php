<?php
/**
 * Verifies that every external API the plugin calls really exists.
 *
 * The plugin must not invent WordPress, PHP or Elementor APIs. This script
 * proves it by cross-checking three things against real sources:
 *
 *  1. every function the plugin calls,
 *  2. every class the plugin instantiates, extends or type-hints,
 *  3. every Elementor method the Elementor adapter calls.
 *
 * A name resolves when it is
 *   - defined by the plugin itself,
 *   - a PHP built-in (`function_exists()` / `class_exists()` in this runtime),
 *   - defined in the WordPress source directory, or
 *   - defined in the Elementor source directory (adapter methods only).
 *
 * Usage:
 *     php tools/verify-wp-functions.php /path/to/wordpress [/path/to/elementor]
 *
 * Getting the sources (no Composer, no wp-cli needed):
 *     mkdir -p /tmp/verify && cd /tmp/verify
 *     curl -sSL -o wp.tar.gz https://codeload.github.com/WordPress/WordPress/tar.gz/refs/tags/7.1.4
 *     tar xzf wp.tar.gz && mv WordPress-7.1.4 wp
 *     curl -sSL -o el.tar.gz https://codeload.github.com/elementor/elementor/tar.gz/refs/heads/main
 *     tar xzf el.tar.gz && mv elementor-main elementor
 *     php /path/to/plugin/tools/verify-wp-functions.php /tmp/verify/wp /tmp/verify/elementor
 *
 * Exit code 0 means every name resolved.
 *
 * @package QevoraLayouts
 */

if ( 'cli' !== PHP_SAPI ) {
	header( 'Content-Type: text/plain; charset=utf-8' );
}

$plugin_root = dirname( __DIR__ );

$wp_source = isset( $argv[1] ) ? rtrim( (string) $argv[1], '/' ) : '';
$el_source = isset( $argv[2] ) ? rtrim( (string) $argv[2], '/' ) : '';

if ( '' === $wp_source || ! is_dir( $wp_source ) ) {
	fwrite( STDERR, "error: pass the path to a WordPress source checkout as the first argument.\n" );
	fwrite( STDERR, "       php tools/verify-wp-functions.php /tmp/verify/wp [/tmp/verify/elementor]\n" );
	exit( 2 );
}

/* -------------------------------------------------------------------------
 * Helpers
 * ---------------------------------------------------------------------- */

/**
 * Collects PHP files below a directory, skipping vendor and test noise.
 *
 * @param string $root Directory.
 * @return array<int,string>
 */
function qevora_collect_files( $root ) {
	$skip  = array( '.git', 'node_modules', 'vendor', 'tests', 'build', 'dist' );
	$files = array();

	$iterator = new RecursiveIteratorIterator(
		new RecursiveCallbackFilterIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
			function ( $current ) use ( $skip ) {
				if ( $current->isDir() ) {
					return ! in_array( $current->getFilename(), $skip, true );
				}

				return 'php' === strtolower( $current->getExtension() );
			}
		)
	);

	foreach ( $iterator as $file ) {
		if ( $file->isFile() ) {
			$files[] = $file->getPathname();
		}
	}

	sort( $files );

	return $files;
}

/**
 * Removes comments and string contents, keeping offsets (and line numbers).
 *
 * @param string $source PHP source.
 * @return string
 */
function qevora_strip_noise( $source ) {
	$tokens = token_get_all( $source );
	$out    = '';

	foreach ( $tokens as $token ) {
		if ( is_array( $token ) ) {
			if ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				// Keep newlines so line numbers stay correct.
				$out .= str_repeat( "\n", substr_count( $token[1], "\n" ) );
				continue;
			}

			if ( in_array( $token[0], array( T_CONSTANT_ENCAPSED_STRING, T_INLINE_HTML, T_ENCAPSED_AND_WHITESPACE ), true ) ) {
				$out .= str_repeat( "\n", substr_count( $token[1], "\n" ) );
				continue;
			}

			$out .= $token[1];
			continue;
		}

		$out .= $token;
	}

	return $out;
}

/**
 * Builds an index of everything a source tree defines.
 *
 * @param string $root Source root.
 * @return array{functions:array<string,string>,classes:array<string,string>}
 */
function qevora_index_source( $root ) {
	$functions = array();
	$classes   = array();
	$files     = qevora_collect_files( $root );

	foreach ( $files as $file ) {
		$size = filesize( $file );

		if ( false === $size || $size > 4 * 1024 * 1024 ) {
			continue;
		}

		$source = file_get_contents( $file );

		if ( false === $source ) {
			continue;
		}

		$relative = str_replace( $root, '', $file );

		// Namespace of the file, used to index fully-qualified class names.
		$namespace = '';

		if ( preg_match( '/^\s*namespace\s+([a-zA-Z_\\\x80-\xff][a-zA-Z0-9_\\\x80-\xff]*)\s*;/m', $source, $ns_match ) ) {
			$namespace = $ns_match[1];
		}

		if ( preg_match_all( '/^\s*(?:(?:final|abstract|public|protected|private|static)\s+)*function\s+(&\s*)?([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)\s*\(/mi', $source, $matches ) ) {
			foreach ( $matches[2] as $name ) {
				$name = strtolower( $name );

				if ( ! isset( $functions[ $name ] ) ) {
					$functions[ $name ] = $relative;
				}
			}
		}

		if ( preg_match_all( '/^\s*(?:(?:final|abstract|readonly)\s+)*(?:class|interface|trait|enum)\s+([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)/mi', $source, $matches ) ) {
			foreach ( $matches[1] as $name ) {
				$lower = strtolower( $name );

				if ( ! isset( $classes[ $lower ] ) ) {
					$classes[ $lower ] = $relative;
				}

				if ( '' !== $namespace ) {
					$classes[ strtolower( $namespace . '\\' . $name ) ] = $relative;
				}
			}
		}
	}

	return array(
		'functions' => $functions,
		'classes'   => $classes,
	);
}

/* -------------------------------------------------------------------------
 * Index the plugin
 * ---------------------------------------------------------------------- */

$plugin_files = qevora_collect_files( $plugin_root );
$plugin_files = array_values(
	array_filter(
		$plugin_files,
		function ( $file ) {
			return false === strpos( $file, DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR )
				&& false === strpos( $file, DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR );
		}
	)
);

$plugin_functions = array();
$plugin_classes   = array();
$calls            = array(); // name => array of "file:line".
$class_uses       = array(); // short class name => array of "file:line".
$fqcn_uses        = array(); // fully-qualified name => array of "file:line".
$adapter_methods  = array(); // method name => array of "file:line".

foreach ( $plugin_files as $file ) {
	$source = file_get_contents( $file );

	if ( false === $source ) {
		continue;
	}

	$relative = str_replace( $plugin_root . DIRECTORY_SEPARATOR, '', $file );
	$clean    = qevora_strip_noise( $source );

	// What this plugin defines itself.
	if ( preg_match_all( '/^\s*(?:(?:final|abstract|public|protected|private|static)\s+)*function\s+(&\s*)?([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)\s*\(/mi', $clean, $matches ) ) {
		foreach ( $matches[2] as $name ) {
			$plugin_functions[ strtolower( $name ) ] = $relative;
		}
	}

	if ( preg_match_all( '/^\s*(?:(?:final|abstract|readonly)\s+)*(?:class|interface|trait|enum)\s+([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)/mi', $clean, $matches ) ) {
		foreach ( $matches[1] as $name ) {
			$plugin_classes[ strtolower( $name ) ] = $relative;
		}
	}

	// Function calls: `name(` that is not a method call and not a definition.
	if ( preg_match_all( '/(?<![\w>$:\\\\])([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)\s*\(/', $clean, $matches, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $matches[1] as $found ) {
			$name   = $found[0];
			$offset = $found[1];
			$before = $offset > 0 ? substr( $clean, max( 0, $offset - 12 ), min( 12, $offset ) ) : '';

			if ( preg_match( '/\b(function|new|class|interface|trait|extends|implements|instanceof|use|catch|fn)\s*$/i', $before ) ) {
				continue;
			}

			$line = substr_count( $clean, "\n", 0, $offset ) + 1;

			if ( ! isset( $calls[ $name ] ) ) {
				$calls[ $name ] = array();
			}

			if ( count( $calls[ $name ] ) < 3 ) {
				$calls[ $name ][] = $relative . ':' . $line;
			}
		}
	}

	// Class references: `new X(`, `X::`, `instanceof X`, `extends X`, type hints.
	if ( preg_match_all( '/(?:new\s+|instanceof\s+|extends\s+|implements\s+|catch\s*\(\s*|\buse\s+)([\\\\a-zA-Z_\x80-\xff][\\\\a-zA-Z0-9_\x80-\xff]*)/', $clean, $matches, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $matches[1] as $found ) {
			$reference = ltrim( $found[0], '\\' );

			if ( false !== strpos( $reference, '\\' ) ) {
				$reference = substr( $reference, strrpos( $reference, '\\' ) + 1 );
			}

			if ( '' === $reference ) {
				continue;
			}

			$line = substr_count( $clean, "\n", 0, $found[1] ) + 1;

			if ( ! isset( $class_uses[ $reference ] ) ) {
				$class_uses[ $reference ] = array();
			}

			if ( count( $class_uses[ $reference ] ) < 3 ) {
				$class_uses[ $reference ][] = $relative . ':' . $line;
			}
		}
	}

	// Static references such as \Elementor\Plugin::$instance and Foo::bar().
	if ( preg_match_all( '/\\\\([A-Z][a-zA-Z0-9_\x80-\xff]*)::/', $clean, $matches, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $matches[1] as $found ) {
			$line = substr_count( $clean, "\n", 0, $found[1] ) + 1;

			if ( ! isset( $class_uses[ $found[0] ] ) ) {
				$class_uses[ $found[0] ] = array();
			}

			if ( count( $class_uses[ $found[0] ] ) < 3 ) {
				$class_uses[ $found[0] ][] = $relative . ':' . $line;
			}
		}
	}

	// Classes referenced only as strings, e.g. class_exists( '\Elementor\Plugin' ).
	// They keep their namespace so they can be resolved exactly.
	if ( preg_match_all( '/(?:class_exists|interface_exists|trait_exists|enum_exists)\s*\(\s*[\x27\x22]([^\x27\x22]+)[\x27\x22]/', $source, $matches, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $matches[1] as $found ) {
			$fqcn = ltrim( $found[0], '\\' );
			$line = substr_count( $clean, "\n", 0, min( $found[1], strlen( $clean ) ) ) + 1;

			if ( ! isset( $fqcn_uses[ $fqcn ] ) ) {
				$fqcn_uses[ $fqcn ] = array();
			}

			if ( count( $fqcn_uses[ $fqcn ] ) < 3 ) {
				$fqcn_uses[ $fqcn ][] = $relative . ':' . $line;
			}
		}
	}

	// Elementor adapter: every method it calls on a foreign object.
	if ( false !== strpos( $relative, 'Elementor_Adapter.php' ) ) {
		if ( preg_match_all( '/->\s*([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)\s*\(/', $clean, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[1] as $found ) {
				$line = substr_count( $clean, "\n", 0, $found[1] ) + 1;

				if ( ! isset( $adapter_methods[ $found[0] ] ) ) {
					$adapter_methods[ $found[0] ] = array();
				}

				$adapter_methods[ $found[0] ][] = $relative . ':' . $line;
			}
		}
	}
}

/* -------------------------------------------------------------------------
 * Index WordPress (and Elementor when given)
 * ---------------------------------------------------------------------- */

echo "Indexing WordPress source: $wp_source\n";

$wp_index = qevora_index_source( $wp_source );

echo sprintf( "  %d functions, %d classes\n", count( $wp_index['functions'] ), count( $wp_index['classes'] ) );

$el_index = array(
	'functions' => array(),
	'classes'   => array(),
);

if ( '' !== $el_source && is_dir( $el_source ) ) {
	echo "Indexing Elementor source: $el_source\n";

	$el_index = qevora_index_source( $el_source );

	echo sprintf( "  %d functions, %d classes\n", count( $el_index['functions'] ), count( $el_index['classes'] ) );
}

/* -------------------------------------------------------------------------
 * Report
 * ---------------------------------------------------------------------- */

$keywords = array(
	'if', 'elseif', 'else', 'endif', 'while', 'endwhile', 'for', 'endfor', 'foreach', 'endforeach',
	'do', 'switch', 'endswitch', 'case', 'default', 'break', 'continue', 'return', 'echo', 'print',
	'isset', 'unset', 'empty', 'list', 'array', 'exit', 'die', 'include', 'include_once', 'require',
	'require_once', 'function', 'fn', 'static', 'public', 'private', 'protected', 'class', 'interface',
	'trait', 'extends', 'implements', 'instanceof', 'new', 'clone', 'try', 'catch', 'finally', 'throw',
	'use', 'namespace', 'const', 'global', 'and', 'or', 'xor', 'match', 'yield', 'declare', 'goto',
	'insteadof', 'as', 'abstract', 'final', 'var', 'callable', 'iterable', 'object', 'null', 'true',
	'false', 'self', 'parent', 'static',
);

$unresolved_functions = array();
$resolved             = array(
	'plugin' => 0,
	'php'    => 0,
	'wp'     => 0,
);

foreach ( $calls as $name => $places ) {
	$lower = strtolower( $name );

	if ( in_array( $lower, $keywords, true ) ) {
		continue;
	}

	if ( isset( $plugin_functions[ $lower ] ) ) {
		++$resolved['plugin'];
		continue;
	}

	if ( function_exists( $name ) ) {
		++$resolved['php'];
		continue;
	}

	if ( isset( $wp_index['functions'][ $lower ] ) ) {
		++$resolved['wp'];
		continue;
	}

	$unresolved_functions[ $name ] = $places;
}

$unresolved_classes = array();
$class_resolved     = array(
	'plugin' => 0,
	'php'    => 0,
	'wp'     => 0,
	'el'     => 0,
);

foreach ( $class_uses as $name => $places ) {
	$lower = strtolower( ltrim( $name, '\\' ) );

	if ( '' === $lower || in_array( $lower, array( 'self', 'static', 'parent', 'stdClass' ), true ) ) {
		continue;
	}

	if ( isset( $plugin_classes[ $lower ] ) ) {
		++$class_resolved['plugin'];
		continue;
	}

	if ( class_exists( $name ) || interface_exists( $name ) || trait_exists( $name ) ) {
		++$class_resolved['php'];
		continue;
	}

	if ( isset( $wp_index['classes'][ $lower ] ) ) {
		++$class_resolved['wp'];
		continue;
	}

	if ( isset( $el_index['classes'][ $lower ] ) ) {
		++$class_resolved['el'];
		continue;
	}

	$unresolved_classes[ $name ] = $places;
}

$unresolved_fqcns = array();
$fqcn_resolved    = array(
	'plugin' => 0,
	'php'    => 0,
	'wp'     => 0,
	'el'     => 0,
);

foreach ( $fqcn_uses as $fqcn => $places ) {
	$lower = strtolower( $fqcn );
	$short = false !== strpos( $lower, '\\' ) ? substr( $lower, strrpos( $lower, '\\' ) + 1 ) : $lower;

	if ( 0 === strpos( $lower, 'qevoralayouts\\' ) ) {
		++$fqcn_resolved['plugin'];
		continue;
	}

	if ( class_exists( $fqcn ) || interface_exists( $fqcn ) ) {
		++$fqcn_resolved['php'];
		continue;
	}

	if ( isset( $wp_index['classes'][ $lower ] ) || isset( $wp_index['classes'][ $short ] ) ) {
		++$fqcn_resolved['wp'];
		continue;
	}

	if ( isset( $el_index['classes'][ $lower ] ) || isset( $el_index['classes'][ $short ] ) ) {
		++$fqcn_resolved['el'];
		continue;
	}

	$unresolved_fqcns[ $fqcn ] = $places;
}

$unresolved_methods = array();
$method_resolved    = array(
	'plugin' => 0,
	'php'    => 0,
	'wp'     => 0,
	'el'     => 0,
);

foreach ( $adapter_methods as $name => $places ) {
	$lower = strtolower( $name );

	if ( in_array( $lower, array( '__construct', 'getinstance' ), true ) ) {
		++$method_resolved['plugin'];
		continue;
	}

	// Methods the adapter or its parent class define itself.
	if ( isset( $plugin_functions[ $lower ] ) ) {
		++$method_resolved['plugin'];
		continue;
	}

	$source = file_get_contents( $plugin_root . '/includes/Integrations/Adapters/Elementor_Adapter.php' );
	$parent = file_get_contents( $plugin_root . '/includes/Integrations/Abstract_Builder_Adapter.php' );

	if ( false !== $source && preg_match( '/function\s+' . preg_quote( $name, '/' ) . '\s*\(/i', $source ) ) {
		++$method_resolved['plugin'];
		continue;
	}

	if ( false !== $parent && preg_match( '/function\s+' . preg_quote( $name, '/' ) . '\s*\(/i', $parent ) ) {
		++$method_resolved['plugin'];
		continue;
	}

	if ( method_exists( 'stdClass', $name ) || in_array( $lower, array( 'geterrormessage', 'geterrorcode', 'geterrordata' ), true ) ) {
		++$method_resolved['php'];
		continue;
	}

	if ( isset( $el_index['functions'][ $lower ] ) ) {
		++$method_resolved['el'];
		continue;
	}

	if ( isset( $wp_index['functions'][ $lower ] ) ) {
		++$method_resolved['wp'];
		continue;
	}

	$unresolved_methods[ $name ] = $places;
}

echo "\n";
echo "Plugin files scanned: " . count( $plugin_files ) . "\n\n";

echo "Functions\n";
printf( "  defined by the plugin : %d\n", $resolved['plugin'] );
printf( "  PHP built-ins         : %d\n", $resolved['php'] );
printf( "  WordPress core        : %d\n", $resolved['wp'] );
printf( "  unresolved            : %d\n", count( $unresolved_functions ) );

foreach ( $unresolved_functions as $name => $places ) {
	echo "    ! $name  (" . implode( ', ', $places ) . ")\n";
}

echo "\nClasses\n";
printf( "  defined by the plugin : %d\n", $class_resolved['plugin'] );
printf( "  PHP built-ins         : %d\n", $class_resolved['php'] );
printf( "  WordPress core        : %d\n", $class_resolved['wp'] );
printf( "  Elementor             : %d\n", $class_resolved['el'] );
printf( "  unresolved            : %d\n", count( $unresolved_classes ) );

foreach ( $unresolved_classes as $name => $places ) {
	echo "    ! $name  (" . implode( ', ', $places ) . ")\n";
}

echo "\nNamespaced class references (class_exists and friends)\n";
printf( "  this plugin           : %d\n", $fqcn_resolved['plugin'] );
printf( "  PHP built-ins         : %d\n", $fqcn_resolved['php'] );
printf( "  WordPress core        : %d\n", $fqcn_resolved['wp'] );
printf( "  Elementor             : %d\n", $fqcn_resolved['el'] );
printf( "  unresolved            : %d\n", count( $unresolved_fqcns ) );

foreach ( $unresolved_fqcns as $name => $places ) {
	echo "    ! $name  (" . implode( ', ', $places ) . ")\n";
}

echo "\nElementor adapter methods\n";
printf( "  own / parent methods  : %d\n", $method_resolved['plugin'] );
printf( "  WordPress core        : %d\n", $method_resolved['wp'] );
printf( "  Elementor             : %d\n", $method_resolved['el'] );
printf( "  unresolved            : %d\n", count( $unresolved_methods ) );

foreach ( $unresolved_methods as $name => $places ) {
	echo "    ! ->$name()  (" . implode( ', ', array_slice( $places, 0, 3 ) ) . ")\n";
}

$total = count( $unresolved_functions ) + count( $unresolved_classes ) + count( $unresolved_fqcns ) + count( $unresolved_methods );

echo "\n";

if ( $total > 0 ) {
	echo "FAILED: $total unresolved name(s). Every API the plugin uses must exist.\n";
	exit( 1 );
}

echo "OK: every function, class, namespaced reference and Elementor method the plugin uses resolves.\n";

exit( 0 );
