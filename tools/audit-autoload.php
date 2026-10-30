<?php
/**
 * Development audit: proves the autoloader can find every class the plugin
 * declares, and that every class reference resolves the way PHP will resolve
 * it at runtime (namespace-relative rules included). Also flags PHP 8-only
 * syntax that would fatal on the PHP 7.4 floor the plugin advertises.
 *
 * The name-resolution check exists because a reference such as
 * `QevoraLayouts\Support\Requirements` written inside `namespace QevoraLayouts\Core`
 * without a leading backslash or a `use` import resolves to
 * `QevoraLayouts\Core\QevoraLayouts\Support\Requirements` at runtime - a fatal
 * error that syntax checks and naive greps cannot see.
 *
 * Usage: php tools/audit-autoload.php [plugin-root]
 *
 * @package QevoraLayouts
 */

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$root = isset( $argv[1] ) ? rtrim( $argv[1], '/' ) : dirname( __DIR__ );

/**
 * Collect PHP files.
 *
 * @param string $dir Directory.
 * @return array<int,string>
 */
function qevora_audit_files( $dir ) {
	$out  = array();
	$iter = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );

	foreach ( $iter as $file ) {
		if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
			$out[] = $file->getPathname();
		}
	}

	sort( $out );

	return $out;
}

/**
 * Strip comments and string literals so only code tokens remain.
 *
 * @param string $source PHP source.
 * @return string Code-only source.
 */
function qevora_audit_code_only( $source ) {
	$tokens = token_get_all( $source );
	$code   = '';

	foreach ( $tokens as $token ) {
		if ( is_array( $token ) ) {
			if ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				$code .= str_repeat( "\n", substr_count( $token[1], "\n" ) );
				continue;
			}

			if ( T_CONSTANT_ENCAPSED_STRING === $token[0] ) {
				$code .= "''";
				continue;
			}

			if ( T_ENCAPSED_AND_WHITESPACE === $token[0] ) {
				continue;
			}

			$code .= $token[1];
			continue;
		}

		$code .= $token;
	}

	// Drop double-quoted string interiors that survived as variables only.
	$code = preg_replace( '/"[^"]*"/', '""', $code );

	// Drop `use ...;` and `namespace ...;` statements from the code stream.
	$code = preg_replace( '/^\s*(?:use\s+(?:function\s+|const\s+)?[^;]+;|namespace\s+[^;]+;)/m', '', $code );

	return $code;
}

$files = array();

foreach ( qevora_audit_files( $root ) as $file ) {
	$relative = str_replace( $root . '/', '', $file );

	if ( 0 === strpos( $relative, 'tests/' ) || 0 === strpos( $relative, 'tools/' ) || 0 === strpos( $relative, 'release/' ) ) {
		continue;
	}

	$files[ $relative ] = $file;
}

$declared  = array(); // FQCN => relative file that declares it.
$problems  = array();
$syntax_8x = array();
$resolved  = array(); // FQCN => places referencing it.

foreach ( $files as $relative => $file ) {
	$source = (string) file_get_contents( $file );

	$namespace = '';

	if ( preg_match( '/^\s*namespace\s+([A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff\\\]*)\s*;/m', $source, $m ) ) {
		$namespace = $m[1];
	}

	if ( preg_match_all( '/^\s*(?:(?:final|abstract|readonly)\s+)*(?:class|interface|trait)\s+([A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)/m', $source, $m ) ) {
		foreach ( $m[1] as $name ) {
			$declared[ '' === $namespace ? $name : $namespace . '\\' . $name ] = $relative;
		}
	}

	// Import map: alias => fully qualified name.
	$imports = array();

	if ( preg_match_all( '/^\s*use\s+([A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff\\\]*)(?:\s+as\s+([A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*))?\s*;/m', $source, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $use ) {
			$alias = isset( $use[2] ) && '' !== $use[2] ? $use[2] : substr( $use[1], (int) strrpos( $use[1], '\\' ) + 1 );

			$imports[ $alias ] = $use[1];
		}
	}

	$code = qevora_audit_code_only( $source );

	// PHP 8+ syntax that parses fine on 8.x but fatals on 7.4.
	$checks = array(
		'match expression'      => '/(?<![A-Za-z0-9_])match\s*\(/',
		'nullsafe operator'     => '/\?->/',
		'readonly property'     => '/(?<![A-Za-z0-9_])readonly\s+(?:static\s+)?\$/',
		'enum'                  => '/^\s*enum\s+/m',
		'constructor promotion' => '/function\s+__construct\s*\([^)]*(?:public|protected|private)\s+(?:static\s+)?\$/',
		'union type'            => '/\):\s*[A-Za-z_\\\]+\|[A-Za-z_\\\|]+/',
		'first-class callable'  => '/[A-Za-z0-9_]\(\s*\.\.\.\s*\)/',
		'attribute'             => '/^#\[/m',
		'never return type'     => '/:\s*never\b/',
		'mixed type'            => '/:\s*mixed\b/',
		'PHP 8.0 function'      => '/(?<![A-Za-z0-9_\\\])(str_contains|str_starts_with|str_ends_with|array_is_list|get_debug_type)\s*\(/',
	);

	foreach ( $checks as $label => $pattern ) {
		if ( preg_match_all( $pattern, $code, $found, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $found[0] as $hit ) {
				$line        = substr_count( $code, "\n", 0, $hit[1] ) + 1;
				$syntax_8x[] = $relative . ':' . $line . '  ' . $label . '  ' . trim( $hit[0] );
			}
		}
	}

	// Every namespaced identifier chain, resolved the way PHP resolves it.
	if ( preg_match_all( '/(?<![A-Za-z0-9_\\\])(\\\\?[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(?:\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)+)/', $code, $found, PREG_OFFSET_CAPTURE ) ) {
		foreach ( $found[1] as $hit ) {
			$chain = $hit[0];
			$line  = substr_count( $code, "\n", 0, $hit[1] ) + 1;

			if ( '\\' === $chain[0] ) {
				$absolute = substr( $chain, 1 );
			} else {
				$first  = strstr( $chain . '\\', '\\', true );
				$rest   = substr( $chain, strlen( $first ) );
				$absolute = isset( $imports[ $first ] ) ? $imports[ $first ] . $rest : ( '' === $namespace ? $chain : $namespace . '\\' . $chain );
			}

			if ( 0 !== stripos( $absolute, 'QevoraLayouts\\' ) ) {
				continue;
			}

			$resolved[ $absolute ][] = $relative . ':' . $line;

			// Writing "QevoraLayouts\..." unqualified inside another namespace
			// of this plugin always resolves to the wrong class.
			if ( '\\' !== $chain[0] && 0 === strpos( $chain, 'QevoraLayouts\\' ) && '' !== $namespace && ! isset( $imports['QevoraLayouts'] ) ) {
				$problems[] = sprintf(
					'relative namespace reference: %s:%d writes "%s" which PHP resolves to "%s" (add a leading backslash or a use import)',
					$relative,
					$line,
					$chain,
					$absolute
				);
				continue;
			}

			if ( ! isset( $declared[ $absolute ] ) ) {
				$problems[] = sprintf( 'missing class: %s referenced at %s:%d does not exist', $absolute, $relative, $line );
			}
		}
	}
}

// 1) Every declared class must live where the autoloader looks for it.
foreach ( $declared as $fqcn => $relative ) {
	if ( 0 !== strpos( $fqcn, 'QevoraLayouts\\' ) ) {
		continue;
	}

	$expected = 'includes/' . str_replace( '\\', '/', substr( $fqcn, strlen( 'QevoraLayouts\\' ) ) ) . '.php';

	if ( $expected !== $relative ) {
		$problems[] = "autoload mismatch: $fqcn is declared in $relative but the autoloader looks in $expected";
	}
}

echo "Files scanned        : " . count( $files ) . "\n";
echo "Classes declared     : " . count( $declared ) . "\n";
echo "Class references     : " . count( $resolved ) . "\n\n";

if ( $problems ) {
	echo "NAME RESOLUTION PROBLEMS\n";

	foreach ( array_unique( $problems ) as $problem ) {
		echo "  ! $problem\n";
	}
} else {
	echo "Name resolution: every declared and referenced class resolves to an existing file.\n";
}

echo "\n";

if ( $syntax_8x ) {
	echo "PHP 8-ONLY CONSTRUCTS (fatal on the 7.4 floor)\n";

	foreach ( array_unique( $syntax_8x ) as $hit ) {
		echo "  ! $hit\n";
	}
} else {
	echo "PHP 7.4 syntax floor: no PHP 8-only constructs found.\n";
}

exit( ( $problems || $syntax_8x ) ? 1 : 0 );
