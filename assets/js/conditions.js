/**
 * Qevora Layouts — display conditions UI.
 *
 * Adds and removes condition rows, keeps the value fields in sync with the
 * chosen condition and looks up posts, terms and users by name so IDs never
 * have to be typed by hand. Existing rows are saved by the browser even when
 * this file fails to load.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */
/* global jQuery, window, document, qevoraLayouts */
( function ( window, document, $ ) {
	'use strict';

	var api = window.qevoraLayouts || {};
	var settings = api.settings || window.qevoraLayoutsAdmin || {};
	var definitions = settings.conditions || {};

	var TEMPLATE_SELECTOR = 'template.qevora-conditions__template';
	var TEMPLATE_BASE = 'qevora_conditions[include][0]';
	var SEARCH_DEBOUNCE = 350;

	$( function () {
		if ( ! $( '.qevora-conditions' ).length ) {
			return;
		}

		$( '.qevora-condition' ).each( function () {
			applyType( this );
		} );

		$( document ).on( 'click', '.qevora-conditions__add', onAdd );
		$( document ).on( 'click', '.qevora-condition__remove', onRemove );
		$( document ).on( 'change', '.qevora-condition__type', onTypeChange );
		$( document ).on( 'click', '.qevora-condition__search', onSearchClick );
		$( document ).on( 'click', '.qevora-condition__go', onSearchRun );
		$( document ).on( 'click', '.qevora-condition__close', onSearchClose );
		$( document ).on( 'keyup', '.qevora-condition__query', onSearchKey );
		$( document ).on( 'click', '.qevora-condition__result', onResultPick );
		$( document ).on( 'keydown', '.qevora-condition__result', onResultKeys );
	} );

	/**
	 * Adds a new condition row.
	 *
	 * @since 1.0.0
	 *
	 * @param {Event} event Click event.
	 * @return {void}
	 */
	function onAdd( event ) {
		event.preventDefault();

		var $button = $( event.currentTarget );
		var list = String( $button.data( 'list' ) || 'include' );
		var $fieldset = $button.closest( '.qevora-conditions' );
		var $rows = $fieldset.find( '> .qevora-conditions__rows' );

		if ( ! $rows.length ) {
			$rows = $fieldset.find( '.qevora-conditions__rows' ).first();
		}

		var markup = templateMarkup();

		if ( ! markup ) {
			return;
		}

		var index = $rows.children( '.qevora-condition' ).length;
		var base = 'qevora_conditions[' + list + '][' + index + ']';

		markup = markup.split( TEMPLATE_BASE ).join( base );

		var $row = $( markup );

		$row.removeClass( 'qevora-condition--empty' ).removeAttr( 'aria-hidden' );
		$rows.append( $row );

		applyType( $row[ 0 ] );

		var $select = $row.find( '.qevora-condition__type' );

		if ( $select.length ) {
			$select.trigger( 'focus' );
		}

		if ( api.announce ) {
			api.announce(
				api.text( 'conditionAdded', 'Condition added. Choose a condition type.' )
			);
		}
	}

	/**
	 * Returns the markup of the empty row template.
	 *
	 * @since 1.0.0
	 *
	 * @return {string} Markup, empty when unavailable.
	 */
	function templateMarkup() {
		var node = document.querySelector( TEMPLATE_SELECTOR );

		if ( ! node ) {
			return '';
		}

		if ( 'content' in node && node.content ) {
			var holder = document.createElement( 'div' );

			holder.appendChild( node.content.cloneNode( true ) );

			return holder.innerHTML;
		}

		return node.innerHTML || '';
	}

	/**
	 * Removes a condition row and renumbers the remaining rows.
	 *
	 * @since 1.0.0
	 *
	 * @param {Event} event Click event.
	 * @return {void}
	 */
	function onRemove( event ) {
		event.preventDefault();

		var $row = $( event.currentTarget ).closest( '.qevora-condition' );
		var $rows = $row.parent();
		var list = String( $row.closest( '.qevora-conditions' ).data( 'list' ) || 'include' );

		$row.remove();
		renumber( $rows, list );

		if ( api.announce ) {
			api.announce( api.text( 'conditionRemoved', 'Condition removed.' ) );
		}
	}

	/**
	 * Renumbers row names and ids after a removal.
	 *
	 * @since 1.0.0
	 *
	 * @param {Object} $rows Rows container.
	 * @param {string} list  include|exclude.
	 * @return {void}
	 */
	function renumber( $rows, list ) {
		$rows.children( '.qevora-condition' ).each( function ( index ) {
			var base = 'qevora_conditions[' + list + '][' + index + ']';

			$( this )
				.find( '[name], [id]' )
				.each( function () {
					var $field = $( this );
					var name = $field.attr( 'name' );
					var id = $field.attr( 'id' );

					if ( name ) {
						$field.attr( 'name', name.replace( /qevora_conditions\[[^\]]*\]\[\d+\]/, base ) );
					}

					if ( id ) {
						$field.attr( 'id', id.replace( /qevora_conditions\[[^\]]*\]\[\d+\]/, base ) );
					}
				} );

			$( this )
				.find( 'label[for]' )
				.each( function () {
					var $label = $( this );

					$label.attr( 'for', $label.attr( 'for' ).replace( /qevora_conditions\[[^\]]*\]\[\d+\]/, base ) );
				} );

			$( this )
				.find( '.qevora-condition__search[data-target]' )
				.each( function () {
					var $button = $( this );
					var target = String( $button.data( 'target' ) || '' );

					$button.attr( 'data-target', target.replace( /qevora_conditions\[[^\]]*\]\[\d+\]/, base ) );
				} );
		} );
	}

	/**
	 * Reacts to a condition type change.
	 *
	 * @since 1.0.0
	 *
	 * @param {Event} event Change event.
	 * @return {void}
	 */
	function onTypeChange( event ) {
		applyType( $( event.currentTarget ).closest( '.qevora-condition' )[ 0 ] );
	}

	/**
	 * Shows only the value fields the selected condition needs.
	 *
	 * @since 1.0.0
	 *
	 * @param {HTMLElement} row Condition row.
	 * @return {void}
	 */
	function applyType( row ) {
		if ( ! row ) {
			return;
		}

		var $row = $( row );
		var $select = $row.find( '.qevora-condition__type' );
		var type = $select.length ? String( $select.val() || '' ) : '';
		var definition = definitions[ type ] || {};
		var valueType = definition.value || 'none';

		$row.attr( 'data-type', type );

		$row.find( '.qevora-condition__value' ).each( function () {
			var $value = $( this );
			var key = String( $value.data( 'value' ) || '' );
			var visible = key === valueType || ( 'terms' === key && 'taxonomy' === type );

			$value.prop( 'hidden', ! visible );

			if ( ! visible ) {
				clearValue( $value );
			}
		} );

		$row.find( '.qevora-condition__description' ).text( definition.description || '' );
		$row.find( '.qevora-condition__results' ).empty().prop( 'hidden', true );
		$row.find( '.qevora-condition__searchbar' ).remove();
	}

	/**
	 * Resets the inputs of a hidden value block so stale data is never saved.
	 *
	 * @since 1.0.0
	 *
	 * @param {Object} $value Value block.
	 * @return {void}
	 */
	function clearValue( $value ) {
		$value.find( 'input[type="text"], input[type="search"], input[type="number"]' ).val( '' );
		$value.find( 'select' ).val( '' );
		$value.find( 'input[type="checkbox"]' ).prop( 'checked', false );
	}

	/**
	 * Reveals the lookup box for an ID field.
	 *
	 * @since 1.0.0
	 *
	 * @param {Event} event Click event.
	 * @return {void}
	 */
	function onSearchClick( event ) {
		event.preventDefault();

		var $button = $( event.currentTarget );
		var $value = $button.closest( '.qevora-condition__value' );

		if ( $value.find( '.qevora-condition__searchbar' ).length ) {
			$value.find( '.qevora-condition__query' ).trigger( 'focus' );
			return;
		}

		var kind = String( $button.data( 'search' ) || 'posts' );
		var label = 'terms' === kind
			? api.text( 'searchTerms', 'Search terms' )
			: ( 'users' === kind ? api.text( 'searchUsers', 'Search users' ) : api.text( 'searchPosts', 'Search posts and pages' ) );

		var $bar = $(
			'<span class="qevora-condition__searchbar">' +
				'<label class="screen-reader-text"></label>' +
				'<input type="search" class="qevora-condition__query" autocomplete="off" />' +
				'<button type="button" class="button qevora-condition__go"></button>' +
				'<button type="button" class="button-link qevora-condition__close"></button>' +
			'</span>'
		);

		$bar.find( 'label' ).text( label );
		$bar.find( '.qevora-condition__query' ).attr( 'placeholder', api.text( 'searchPlaceholder', 'Type at least two characters…' ) );
		$bar.find( '.qevora-condition__go' ).text( api.text( 'search', 'Search' ) );
		$bar.find( '.qevora-condition__close' ).text( api.text( 'close', 'Close' ) );

		$value.find( '.qevora-condition__results' ).before( $bar ).prop( 'hidden', false ).empty();
		$bar.find( '.qevora-condition__query' ).trigger( 'focus' );
	}

	/**
	 * Handles typing in the lookup box.
	 *
	 * @since 1.0.0
	 *
	 * @param {Event} event Key event.
	 * @return {void}
	 */
	function onSearchKey( event ) {
		var $input = $( event.currentTarget );
		var $value = $input.closest( '.qevora-condition__value' );

		if ( 27 === event.keyCode ) {
			closeLookup( $value );
			return;
		}

		if ( 13 === event.keyCode ) {
			event.preventDefault();
			runSearch( $value );
			return;
		}

		window.clearTimeout( $value.data( 'qevoraTimer' ) );

		$value.data(
			'qevoraTimer',
			window.setTimeout( function () {
				runSearch( $value );
			}, SEARCH_DEBOUNCE )
		);
	}

	/**
	 * Runs the lookup.
	 *
	 * @since 1.0.0
	 *
	 * @param {Event} event Click event.
	 * @return {void}
	 */
	function onSearchRun( event ) {
		event.preventDefault();

		runSearch( $( event.currentTarget ).closest( '.qevora-condition__value' ) );
	}

	/**
	 * Performs the AJAX lookup and renders the results.
	 *
	 * @since 1.0.0
	 *
	 * @param {Object} $value Value block.
	 * @return {void}
	 */
	function runSearch( $value ) {
		if ( ! $value.length ) {
			return;
		}

		var term = String( $value.find( '.qevora-condition__query' ).val() || '' ).trim();
		var $results = $value.find( '.qevora-condition__results' );

		if ( term.length < 2 ) {
			renderResults( $results, [], api.text( 'searchHint', 'Type at least two characters to search.' ) );
			return;
		}

		var $row = $value.closest( '.qevora-condition' );
		var kind = String( $value.find( '.qevora-condition__ids' ).data( 'search' ) || 'posts' );
		var data = {
			kind: kind,
			search: term
		};

		if ( 'posts' === kind ) {
			var type = String( $row.attr( 'data-type' ) || '' );

			if ( 'post' === type || 'page' === type ) {
				data.post_type = type;
			} else {
				data.post_type = String( $row.find( '.qevora-condition__value[data-value="post_type"] select' ).val() || '' );
			}
		}

		if ( 'terms' === kind ) {
			data.taxonomy = String( $row.find( '.qevora-condition__value[data-value="taxonomy"] select' ).val() || '' );
		}

		renderResults( $results, [], api.text( 'searching', 'Searching…' ) );

		if ( ! api.ajax ) {
			return;
		}

		api
			.ajax( 'qevora_layouts_search', data )
			.done( function ( payload ) {
				renderResults( $results, payload.items || [], '' );
			} )
			.fail( function ( failure ) {
				renderResults( $results, [], String( failure || api.text( 'error', 'Something went wrong. Please try again.' ) ) );
			} );
	}

	/**
	 * Renders lookup results.
	 *
	 * @since 1.0.0
	 *
	 * @param {Object} $results Results container.
	 * @param {Array}  items    Result items.
	 * @param {string} note     Status note.
	 * @return {void}
	 */
	function renderResults( $results, items, note ) {
		$results.empty().prop( 'hidden', false );

		if ( note ) {
			$results.append( $( '<span class="qevora-condition__note"></span>' ).text( note ) );
		}

		$.each( items, function ( index, item ) {
			if ( ! item || ! item.id ) {
				return;
			}

			var $option = $( '<button type="button" role="option" aria-selected="false" class="qevora-condition__result"></button>' );

			$option.attr( 'data-id', String( item.id ) ).text( String( item.label || item.id ) );
			$results.append( $option );
		} );

		if ( ! note && ! items.length ) {
			$results.append(
				$( '<span class="qevora-condition__note"></span>' ).text(
					api.text( 'noResults', 'No results.' )
				)
			);
		}
	}

	/**
	 * Adds a picked ID to the field.
	 *
	 * @since 1.0.0
	 *
	 * @param {Event} event Click event.
	 * @return {void}
	 */
	function onResultPick( event ) {
		event.preventDefault();

		var $option = $( event.currentTarget );
		var $value = $option.closest( '.qevora-condition__value' );
		var $input = $value.find( '.qevora-condition__ids' );
		var id = String( $option.data( 'id' ) || '' );

		if ( ! id || ! $input.length ) {
			return;
		}

		var current = String( $input.val() || '' )
			.split( ',' )
			.map( function ( part ) {
				return part.trim();
			} )
			.filter( function ( part ) {
				return '' !== part;
			} );

		if ( -1 !== $.inArray( id, current ) ) {
			if ( api.announce ) {
				api.announce( api.text( 'alreadyAdded', 'That ID is already in the list.' ) );
			}

			return;
		}

		current.push( id );
		$input.val( current.join( ', ' ) ).trigger( 'change' );

		$option.attr( 'aria-selected', 'true' ).addClass( 'is-added' );

		if ( api.announce ) {
			api.announce(
				api.text( 'added', 'Added.' ) + ' ' + String( $option.text() )
			);
		}
	}

	/**
	 * Keyboard navigation inside the result list.
	 *
	 * @since 1.0.0
	 *
	 * @param {Event} event Keydown event.
	 * @return {void}
	 */
	function onResultKeys( event ) {
		var $options = $( event.currentTarget ).closest( '.qevora-condition__results' ).find( '.qevora-condition__result' );
		var index = $options.index( event.currentTarget );

		if ( 40 === event.keyCode && index < $options.length - 1 ) {
			event.preventDefault();
			$options.eq( index + 1 ).trigger( 'focus' );
		}

		if ( 38 === event.keyCode && index > 0 ) {
			event.preventDefault();
			$options.eq( index - 1 ).trigger( 'focus' );
		}

		if ( 27 === event.keyCode ) {
			event.preventDefault();
			$( event.currentTarget ).closest( '.qevora-condition__value' ).find( '.qevora-condition__query' ).trigger( 'focus' );
		}
	}

	/**
	 * Closes the lookup box.
	 *
	 * @since 1.0.0
	 *
	 * @param {Event} event Click event.
	 * @return {void}
	 */
	function onSearchClose( event ) {
		if ( event && event.preventDefault ) {
			event.preventDefault();
		}

		closeLookup( $( event.currentTarget ).closest( '.qevora-condition__value' ) );
	}

	/**
	 * Removes the lookup box and its results.
	 *
	 * @since 1.0.0
	 *
	 * @param {Object} $value Value block.
	 * @return {void}
	 */
	function closeLookup( $value ) {
		if ( ! $value || ! $value.length ) {
			return;
		}

		window.clearTimeout( $value.data( 'qevoraTimer' ) );
		$value.find( '.qevora-condition__searchbar' ).remove();
		$value.find( '.qevora-condition__results' ).empty().prop( 'hidden', true );
	}
} )( window, document, jQuery );
