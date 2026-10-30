/**
 * Classic Header demo: mobile menu toggle.
 * Vanilla JS, no dependencies. Loaded in the footer only when the demo renders.
 */
( function () {
	'use strict';

	function init( root ) {
		if ( root.getAttribute( 'data-qdhc-ready' ) === '1' ) {
			return;
		}
		root.setAttribute( 'data-qdhc-ready', '1' );

		var toggle = root.querySelector( '[data-qdhc-toggle]' );
		var nav = root.querySelector( '[data-qdhc-nav]' );

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

		document.addEventListener( 'click', function ( event ) {
			if ( nav.classList.contains( 'is-open' ) && ! nav.contains( event.target ) && ! toggle.contains( event.target ) ) {
				close();
			}
		} );
	}

	function boot() {
		var roots = document.querySelectorAll( '.qdhc' );
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
