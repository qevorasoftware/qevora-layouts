/**
 * Simple Footer Bar demo: smooth back-to-top button. Vanilla JS.
 */
( function () {
	'use strict';

	function init( root ) {
		if ( root.getAttribute( 'data-qdfs-ready' ) === '1' ) {
			return;
		}
		root.setAttribute( 'data-qdfs-ready', '1' );

		var button = root.querySelector( '[data-qdfs-top]' );

		if ( ! button ) {
			return;
		}

		button.addEventListener( 'click', function () {
			window.scrollTo( { top: 0, behavior: 'smooth' } );
		} );
	}

	function boot() {
		var roots = document.querySelectorAll( '.qdfs' );
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
