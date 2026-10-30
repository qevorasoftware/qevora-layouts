/**
 * Frequently Asked Questions demo - accordion behaviour.
 *
 * Loaded only while this demo renders. Plain JavaScript, no dependencies.
 */
( function () {
	'use strict';

	function init( root ) {
		var toggles = root.querySelectorAll( '[data-qdsf-toggle]' );

		Array.prototype.forEach.call( toggles, function ( button ) {
			button.addEventListener( 'click', function () {
				var item = button.closest( '.qdsf__item' );
				var panelId = button.getAttribute( 'aria-controls' );
				var panel = panelId ? document.getElementById( panelId ) : null;
				var open = button.getAttribute( 'aria-expanded' ) === 'true';

				button.setAttribute( 'aria-expanded', open ? 'false' : 'true' );

				if ( panel ) {
					if ( open ) {
						panel.setAttribute( 'hidden', 'hidden' );
					} else {
						panel.removeAttribute( 'hidden' );
					}
				}

				if ( item ) {
					item.classList.toggle( 'qdsf__item--open', ! open );
				}
			} );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			Array.prototype.forEach.call( document.querySelectorAll( '.qdsf' ), init );
		} );
	} else {
		Array.prototype.forEach.call( document.querySelectorAll( '.qdsf' ), init );
	}
}() );
