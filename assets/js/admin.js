/**
 * Qevora Layouts — shared admin behaviour.
 *
 * Loaded on plugin screens only. Everything degrades gracefully: without this
 * file the meta boxes, the library screen and the status screen still work
 * through plain form posts.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */
/* global jQuery, window, document, navigator */
( function ( window, document, $ ) {
	'use strict';

	var settings = window.qevoraLayoutsAdmin || {};

	/**
	 * Public helpers shared with conditions.js and library.js.
	 *
	 * @namespace qevoraLayouts
	 */
	var api = window.qevoraLayouts || {};

	api.settings = settings;

	/**
	 * Returns a translated string with a fallback.
	 *
	 * @since 1.0.0
	 *
	 * @param {string} key      Key inside the localized `i18n` object.
	 * @param {string} fallback Default text.
	 * @return {string} Translated string.
	 */
	api.text = function ( key, fallback ) {
		var bag = settings.i18n || {};

		return bag[ key ] ? bag[ key ] : fallback;
	};

	/**
	 * Sends an admin-ajax request.
	 *
	 * @since 1.0.0
	 *
	 * @param {string} action AJAX action.
	 * @param {Object} data   Request data.
	 * @return {Object} jQuery promise resolving with the `data` payload.
	 */
	api.ajax = function ( action, data ) {
		var payload = $.extend(
			{
				action: action,
				nonce: settings.nonce || ''
			},
			data || {}
		);

		var request = $.post( settings.ajaxUrl || window.ajaxurl || '', payload );
		var deferred = $.Deferred();

		request
			.done( function ( response ) {
				if ( response && true === response.success ) {
					deferred.resolve( response.data || {} );
					return;
				}

				var message = api.text( 'error', 'Something went wrong. Please try again.' );

				if ( response && response.data && response.data.message ) {
					message = response.data.message;
				}

				deferred.reject( message, response );
			} )
			.fail( function ( jqXHR, textStatus ) {
				deferred.reject(
					'error' === textStatus
						? message( jqXHR )
						: api.text( 'error', 'Something went wrong. Please try again.' ),
					jqXHR
				);
			} );

		return deferred.promise();
	};

	/**
	 * Builds an error message for a failed HTTP request.
	 *
	 * @since 1.0.0
	 *
	 * @param {Object} jqXHR jQuery XHR object.
	 * @return {string} Message.
	 */
	function message( jqXHR ) {
		var status = jqXHR && jqXHR.status ? parseInt( jqXHR.status, 10 ) : 0;

		if ( 0 === status ) {
			return api.text( 'networkError', 'The request did not reach the server. Check your connection and try again.' );
		}

		if ( 403 === status ) {
			return api.text( 'forbidden', 'You are not allowed to do this, or your session expired.' );
		}

		var payload = jqXHR && jqXHR.responseJSON ? jqXHR.responseJSON : null;

		if ( payload && payload.data && payload.data.message ) {
			return payload.data.message;
		}

		return api.text( 'error', 'Something went wrong. Please try again.' );
	}

	/**
	 * Announces a message to screen readers.
	 *
	 * @since 1.0.0
	 *
	 * @param {string} text Message.
	 * @return {void}
	 */
	api.announce = function ( text ) {
		var region = document.getElementById( 'qevora-a11y-speak' );

		if ( ! region ) {
			region = document.createElement( 'div' );
			region.id = 'qevora-a11y-speak';
			region.className = 'screen-reader-text';
			region.setAttribute( 'role', 'status' );
			region.setAttribute( 'aria-live', 'polite' );
			document.body.appendChild( region );
		}

		window.setTimeout( function () {
			region.textContent = '';
		}, 100 );

		window.setTimeout( function () {
			region.textContent = text;
		}, 200 );
	};

	window.qevoraLayouts = api;

	$( function () {
		initEditor();
		initStatusReport();
		initRowActions();
	} );

	/**
	 * Meta box behaviour on the layout editor.
	 *
	 * @since 1.0.0
	 *
	 * @return {void}
	 */
	function initEditor() {
		var $type = $( '#qevora-layout-type-select' );
		var $placement = $( '#qevora-layout-placement' );

		if ( ! $type.length ) {
			return;
		}

		/**
		 * Shows or hides the conditional field groups.
		 *
		 * @since 1.0.0
		 *
		 * @return {void}
		 */
		function sync() {
			var type = $type.val();
			var placement = $placement.length ? $placement.val() : '';
			var source = $( 'input[name="qevora_layout[content_source]"]:checked' ).val() || 'self';

			toggle( '[data-qevora-hook-fields]', 'custom_hook' === placement );
			toggle( '[data-qevora-reference-fields]', 'reference' === source );
			toggle( '[data-qevora-placement-fields]', 'section' !== type );

			if ( $placement.length ) {
				rebuildPlacements( type, placement );
			}
		}

		/**
		 * Rebuilds the placement choices for the selected layout type.
		 *
		 * @since 1.0.0
		 *
		 * @param {string} type      Layout type.
		 * @param {string} placement Currently selected placement.
		 * @return {void}
		 */
		function rebuildPlacements( type, placement ) {
			var choices = 'footer' === type ? settings.footerPlaces || {} : settings.placements || {};
			var $select = $placement;
			var keep = [];

			if ( 'section' === type ) {
				choices = {
					custom_hook: pick( settings.placements || {}, 'custom_hook' ) || 'Custom theme hook',
					manual: pick( settings.placements || {}, 'manual' ) || 'Manual only (shortcode, block or template tag)'
				};
			}

			$select.find( 'option' ).each( function () {
				var value = $( this ).val();

				if ( '' !== value && ! choices[ value ] ) {
					keep.push( value );
					$( this ).remove();
				}
			} );

			$.each( choices, function ( value, label ) {
				if ( $select.find( 'option[value="' + value + '"]' ).length ) {
					return;
				}

				$select.append( $( '<option></option>' ).val( value ).text( label ) );
			} );

			if ( keep.length && -1 !== $.inArray( placement, keep ) ) {
				$select.val( '' );
				placement = '';
			}

			$select.val( choices[ placement ] ? placement : '' );
		}

		/**
		 * Picks one entry from an object.
		 *
		 * @since 1.0.0
		 *
		 * @param {Object} bag Object.
		 * @param {string} key Key.
		 * @return {string} Value or an empty string.
		 */
		function pick( bag, key ) {
			return bag && bag[ key ] ? bag[ key ] : '';
		}

		/**
		 * Shows or hides a field group.
		 *
		 * @since 1.0.0
		 *
		 * @param {string} selector CSS selector.
		 * @param {boolean} visible Whether the group applies.
		 * @return {void}
		 */
		function toggle( selector, visible ) {
			var $group = $( selector );

			if ( ! $group.length ) {
				return;
			}

			$group.toggleClass( 'qevora-field--hidden', ! visible );
			$group.attr( 'aria-hidden', visible ? 'false' : 'true' );

			$group.find( 'input, select, textarea, button' ).each( function () {
				if ( visible ) {
					$( this ).removeAttr( 'tabindex' );
				} else {
					$( this ).attr( 'tabindex', '-1' );
				}
			} );
		}

		$type.on( 'change', sync );
		$placement.on( 'change', sync );
		$( document ).on( 'change', 'input[name="qevora_layout[content_source]"]', sync );

		sync();
	}

	/**
	 * System status screen behaviour.
	 *
	 * @since 1.0.0
	 *
	 * @return {void}
	 */
	function initStatusReport() {
		var $copy = $( '#qevora-copy-status' );
		var $toggle = $( '#qevora-toggle-status-text' );

		if ( ! $copy.length && ! $toggle.length ) {
			return;
		}

		if ( $toggle.length ) {
			$toggle.on( 'click', function () {
				var $wrap = $( '#qevora-status-text-wrap' );
				var expanded = 'true' === $toggle.attr( 'aria-expanded' );

				$wrap.prop( 'hidden', expanded );
				$toggle.attr( 'aria-expanded', expanded ? 'false' : 'true' );

				if ( ! expanded ) {
					$wrap.find( 'textarea' ).trigger( 'focus' );
				}
			} );
		}

		$copy.on( 'click', function () {
			var $area = $( '#' + ( $copy.data( 'target' ) || 'qevora-status-text' ) );
			var text = $area.length ? $area.val() : '';

			if ( ! text ) {
				api.announce( api.text( 'nothingToCopy', 'There is nothing to copy.' ) );
				return;
			}

			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then( function () {
					announceCopied( $copy );
				}, function () {
					legacyCopy( $area, $copy );
				} );

				return;
			}

			legacyCopy( $area, $copy );
		} );

		/**
		 * Copies with the deprecated execCommand path.
		 *
		 * @since 1.0.0
		 *
		 * @param {Object} $area Textarea.
		 * @param {Object} $button Button.
		 * @return {void}
		 */
		function legacyCopy( $area, $button ) {
			var ok = false;

			$area.prop( 'hidden', false ).trigger( 'focus' ).trigger( 'select' );

			try {
				ok = document.execCommand( 'copy' );
			} catch ( error ) {
				ok = false;
			}

			if ( ok ) {
				announceCopied( $button );
				return;
			}

			api.announce( api.text( 'selectManually', 'Copy the text manually from the report box.' ) );
		}

		/**
		 * Confirms the copy visually and to screen readers.
		 *
		 * @since 1.0.0
		 *
		 * @param {Object} $button Button.
		 * @return {void}
		 */
		function announceCopied( $button ) {
			var label = api.text( 'copied', 'Copied to the clipboard.' );

			$button.addClass( 'qevora-button--done' ).text( label );
			api.announce( label );

			window.setTimeout( function () {
				$button.removeClass( 'qevora-button--done' ).text( api.text( 'copyReport', 'Copy status report' ) );
			}, 2500 );
		}
	}

	/**
	 * Confirms destructive row actions in the layout list.
	 *
	 * @since 1.0.0
	 *
	 * @return {void}
	 */
	function initRowActions() {
		$( document ).on( 'click', '.qevora-row-action--confirm', function ( event ) {
			var label = $( this ).data( 'confirm' );

			if ( label && ! window.confirm( String( label ) ) ) {
				event.preventDefault();
			}
		} );
	}
} )( window, document, jQuery );
