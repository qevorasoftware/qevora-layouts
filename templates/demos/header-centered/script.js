/**
 * Centered Header demo: mobile menu toggle. Vanilla JS, no dependencies.
 */
( function () {
	'use strict';

	function init( root ) {
		if ( root.getAttribute( 'data-qdhh-ready' ) === '1' ) {
			return;
		}
		root.setAttribute( 'data-qdhh-ready', '1' );

		var toggle = root.querySelector( '[data-qdhh-toggle]' );
		var nav = root.querySelector( '[data-qdhh-nav]' );

		if ( ! toggle || ! nav ) {
			return;
		}

		function close() {
			nav.classList.remove( 'is-open' );
			toggle.setAttribute( 'aria-expanded', 'false' );
		}

		toggle.addEventListener( 'click', function () {
			var open = nav.classList.toggle( 'is-open' );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( ( 27 === event.keyCode || 'Escape' === event.key ) && nav.classList.contains( 'is-open' ) ) {
				close();
				toggle.focus();
			}
		} );
	}

	function boot() {
		var roots = document.querySelectorAll( '.qdhh' );
		for ( var i = 0; i < roots.length; i++ ) {
			init( roots[ i ] );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
