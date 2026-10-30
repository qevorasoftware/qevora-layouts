<?php
/**
 * Tests for the CSS the frontend prints inline.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */

namespace QevoraLayouts\Tests\Unit\Frontend;

use QevoraLayouts\Frontend\Frontend;
use QevoraLayouts\Tests\TestCase;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The plugin prints one `<style>` element when a site asks it to hide the
 * theme header or footer. That block is the only place where a stored string
 * reaches the page outside of escaped markup, so it is validated twice: once
 * when the selectors are saved and again right before they are printed.
 *
 * @since 1.0.0
 */
class Frontend_Security_Test extends TestCase {

	/**
	 * Calls the private validator.
	 *
	 * @param mixed $css Candidate CSS.
	 * @return string
	 */
	private function sanitize( $css ) {
		$reflection = new \ReflectionClass( Frontend::class );
		$frontend   = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'sanitize_css_block' );

		// setAccessible() is a no-op since PHP 8.1 and deprecated since 8.5.
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return (string) $method->invoke( $frontend, $css );
	}

	/**
	 * A plain declaration block survives.
	 *
	 * @return void
	 */
	public function test_valid_css_is_kept() {
		$this->assertSame( '.site-header{display:none !important;}', $this->sanitize( '.site-header{display:none !important;}' ) );
		$this->assertSame( '.a, .b{display:none}', $this->sanitize( '.a, .b{display:none}' ) );
		$this->assertSame( '#masthead{display:none}', $this->sanitize( "  #masthead{display:none}  " ) );
		$this->assertSame( 'header.site-header{display:none}', $this->sanitize( 'header.site-header{display:none}' ) );
	}

	/**
	 * Anything that could escape the style element is dropped.
	 *
	 * @return void
	 */
	public function test_markup_is_dropped() {
		$this->assertSame( '', $this->sanitize( '.a{display:none}</style><script>alert(1)</script>' ) );
		$this->assertSame( '', $this->sanitize( '<style>.a{display:none}</style>' ) );
		$this->assertSame( '', $this->sanitize( '.a{content:"<b>"}' ) );
	}

	/**
	 * Every known CSS injection vector is refused.
	 *
	 * @return void
	 */
	public function test_dangerous_css_is_dropped() {
		$cases = array(
			'.a{background:javascript:alert(1)}',
			'.a{width:expression(alert(1))}',
			'@import url(https://evil.example.org/x.css);',
			'.a{display:none}@import "evil.css";',
			'@charset "UTF-8";.a{display:none}',
			'.a{behavior:url(evil.htc)}',
			'.a{background:url(https://evil.example.org/track.gif)}',
			'.a{background:url(data:text/html;base64,PHNjcmlwdD4=)}',
			'.a{display:none}<?php echo 1;',
			'.a{display:none;/* comment */}',
		);

		foreach ( $cases as $css ) {
			$this->assertSame( '', $this->sanitize( $css ), 'This CSS must be refused: ' . $css );
		}
	}

	/**
	 * Only one selector list and one declaration block are accepted.
	 *
	 * @return void
	 */
	public function test_only_a_single_block_is_accepted() {
		$this->assertSame( '', $this->sanitize( '.a{display:none}.b{display:block}' ) );
		$this->assertSame( '', $this->sanitize( '.a{display:none} trailing' ) );
		$this->assertSame( '', $this->sanitize( '.a{display:none' ) );
		$this->assertSame( '', $this->sanitize( 'display:none}' ) );
		$this->assertSame( '', $this->sanitize( 'display:none' ) );
		$this->assertSame( '', $this->sanitize( '{display:none}' ) );
	}

	/**
	 * Empty, oversized and non-string input is refused.
	 *
	 * @return void
	 */
	public function test_degenerate_input_is_refused() {
		$this->assertSame( '', $this->sanitize( '' ) );
		$this->assertSame( '', $this->sanitize( '   ' ) );
		$this->assertSame( '', $this->sanitize( null ) );
		$this->assertSame( '', $this->sanitize( array( '.a{display:none}' ) ) );
		$this->assertSame( '', $this->sanitize( str_repeat( '.a,', 1400 ) . '.b{display:none}' ), 'Anything over 4000 bytes is refused.' );
		$this->assertSame( '.a{display:none}', $this->sanitize( str_repeat( ' ', 10 ) . '.a{display:none}' ) );
	}
}
