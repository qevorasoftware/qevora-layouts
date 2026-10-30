/**
 * Qevora Layouts — template library screen.
 *
 * Turns the import forms into AJAX submissions with a progress indicator and
 * actionable messages. Every form keeps working as a plain POST when this file
 * does not load or when the request cannot be sent, so an import never depends
 * on JavaScript.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */
/* global jQuery, window, document, FormData, qevoraLayouts */
( function ( window, document, $ ) {
	'use strict';

	var api = window.qevoraLayouts || {};
	var settings = api.settings || window.qevoraLayoutsAdmin || {};

	$( function () {
		if ( ! $( '.qevora-layouts-library' ).length ) {
			return;
		}

		$( document ).on( 'submit', 'form[data-qevora-import]', onSubmit );
		$( document ).on( 'change', 'input[type="file"][name="qevora_template_file"]', onFileChange );
		$( document ).on( 'click', '[data-qevora-preview]', onPreviewOpen );
		$( document ).on( 'click', '[data-qevora-preview-close]', onPreviewClose );
		$( document ).on( 'keydown', onPreviewKeydown );
	} );

	/**
	 * Opens the accessible preview dialog for one template.
	 *
	 * @since 1.0.0
	 *
	 * @param {Event} event Click event.
	 * @return {void}
	 */
	function onPreviewOpen( event ) {
		event.preventDefault();

		var button = event.currentTarget;
		var $dialog = $( '[data-qevora-preview-dialog]' );

		if ( ! $dialog.length ) {
			return;
		}

		$dialog.data( 'qevora-opener', button );

		$dialog.find( '.qevora-preview-dialog__image' )
			.attr( 'src', String( button.getAttribute( 'data-preview-url' ) || '' ) )
			.attr( 'alt', String( button.getAttribute( 'data-preview-name' ) || '' ) );

		$dialog.find( '.qevora-preview-dialog__title' )
			.text( String( button.getAttribute( 'data-preview-name' ) || '' ) );

		$dialog.find( '.qevora-preview-dialog__description' )
			.text( String( button.getAttribute( 'data-preview-description' ) || '' ) );

		var meta = [
			button.getAttribute( 'data-preview-type' ),
			button.getAttribute( 'data-preview-builder' ),
			button.getAttribute( 'data-preview-scope' )
		].filter( function ( part ) {
			return !! part;
		} );

		$dialog.find( '.qevora-preview-dialog__meta' ).text( meta.join( ' · ' ) );

		$dialog.prop( 'hidden', false );
		$( document.body ).addClass( 'qevora-preview-open' );
		$dialog.find( '.qevora-preview-dialog__close' ).trigger( 'focus' );
	}

	/**
	 * Closes the preview dialog and restores focus.
	 *
	 * @since 1.0.0
	 *
	 * @param {Event} event Click event.
	 * @return {void}
	 */
	function onPreviewClose( event ) {
		event.preventDefault();

		var $dialog = $( '[data-qevora-preview-dialog]' );

		$dialog.prop( 'hidden', true );
		$( document.body ).removeClass( 'qevora-preview-open' );

		var opener = $dialog.data( 'qevora-opener' );

		if ( opener ) {
			$( opener ).trigger( 'focus' );
		}
	}

	/**
	 * Closes the preview dialog with the Escape key.
	 *
	 * @since 1.0.0
	 *
	 * @param {Event} event Keydown event.
	 * @return {void}
	 */
	function onPreviewKeydown( event ) {
		if ( 'Escape' !== event.key && 'Esc' !== event.key ) {
			return;
		}

		var $dialog = $( '[data-qevora-preview-dialog]' );

		if ( ! $dialog.length || $dialog.prop( 'hidden' ) ) {
			return;
		}

		onPreviewClose( event );
	}

	/**
	 * Validates the chosen file before it is sent.
	 *
	 * @since 1.0.0
	 *
	 * @param {Event} event Change event.
	 * @return {void}
	 */
	function onFileChange( event ) {
		var input = event.currentTarget;
		var file = input.files && input.files[ 0 ] ? input.files[ 0 ] : null;
		var $message = $( input ).closest( 'form' ).find( '[data-qevora-import-message]' );

		if ( ! file || ! $message.length ) {
			return;
		}

		var name = String( file.name || '' ).toLowerCase();

		if ( name.length < 6 || '.json' !== name.substr( name.length - 5 ) ) {
			$message
				.addClass( 'qevora-message--error' )
				.text( api.text( 'wrongFileType', 'Only .json template files are accepted.' ) );

			return;
		}

		$message
			.removeClass( 'qevora-message--error qevora-message--success' )
			.text(
				String( file.name ) + ' · ' + formatSize( file.size ) + ' · ' +
				api.text( 'readyToImport', 'Ready to import.' )
			);
	}

	/**
	 * Submits an import form over AJAX.
	 *
	 * @since 1.0.0
	 *
	 * @param {Event} event Submit event.
	 * @return {void}
	 */
	function onSubmit( event ) {
		var form = event.currentTarget;

		// A previous AJAX attempt failed at transport level: let the browser post it.
		if ( form.getAttribute( 'data-qevora-fallback' ) === '1' ) {
			return;
		}

		if ( ! settings.ajaxUrl || ! window.FormData ) {
			return;
		}

		event.preventDefault();

		var $form = $( form );
		var $message = $form.find( '[data-qevora-import-message], .qevora-template__message' ).first();
		var $spinner = $form.find( '.qevora-library__spinner' );
		var $submit = $form.find( 'input[type="submit"], button[type="submit"]' );
		var formData = new FormData( form );

		formData.append( 'nonce', settings.nonce || '' );

		setBusy( $form, $spinner, $submit, true );
		write( $message, '', api.text( 'importing', 'Importing…' ) );

		$.ajax(
			{
				url: settings.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				xhr: function () {
					var xhr = $.ajaxSettings.xhr();

					if ( xhr && xhr.upload ) {
						xhr.upload.addEventListener(
							'progress',
							function ( progress ) {
								if ( ! progress.lengthComputable || ! progress.total ) {
									return;
								}

								var percent = Math.round( ( progress.loaded / progress.total ) * 100 );

								write(
									$message,
									'',
									api.text( 'uploading', 'Uploading…' ) + ' ' + percent + '%'
								);
							},
							false
						);
					}

					return xhr;
				}
			}
		)
			.done( function ( response ) {
				setBusy( $form, $spinner, $submit, false );

				if ( response && true === response.success ) {
					renderSummary( $message, response.data && response.data.summary ? response.data.summary : null, 'success' );
					$form.find( 'input[type="file"]' ).val( '' );
					return;
				}

				renderSummary( $message, response && response.data && response.data.summary ? response.data.summary : null, 'error' );
			} )
			.fail( function ( jqXHR ) {
				setBusy( $form, $spinner, $submit, false );

				var status = jqXHR && jqXHR.status ? parseInt( jqXHR.status, 10 ) : 0;
				var payload = jqXHR && jqXHR.responseJSON ? jqXHR.responseJSON : null;

				if ( payload && payload.data && payload.data.summary ) {
					renderSummary( $message, payload.data.summary, 'error' );
					return;
				}

				if ( payload && payload.data && payload.data.message ) {
					write( $message, 'error', String( payload.data.message ) );
					return;
				}

				if ( 0 === status ) {
					// No answer from the server at all: fall back to the plain form post.
					write(
						$message,
						'info',
						api.text( 'fallingBack', 'The request could not be sent. Submitting the form directly…' )
					);

					form.setAttribute( 'data-qevora-fallback', '1' );
					window.setTimeout( function () {
						form.submit();
					}, 800 );

					return;
				}

				write( $message, 'error', api.text( 'error', 'Something went wrong. Please try again.' ) );
			} );
	}

	/**
	 * Renders an import summary.
	 *
	 * @since 1.0.0
	 *
	 * @param {Object} $message Message container.
	 * @param {Object} summary  Summary payload.
	 * @param {string} tone     success|error|info.
	 * @return {void}
	 */
	function renderSummary( $message, summary, tone ) {
		if ( ! summary || ! summary.message ) {
			write( $message, tone, api.text( 'imported', 'Imported.' ) );
			return;
		}

		write( $message, summary.type || tone, String( summary.message ) );

		var details = summary.details || [];

		if ( details.length ) {
			var $list = $( '<ul class="qevora-message__details"></ul>' );

			$.each( details, function ( index, detail ) {
				$list.append( $( '<li></li>' ).text( String( detail ) ) );
			} );

			$message.append( $list );
		}

		if ( summary.edit_link ) {
			var $link = $( '<a class="qevora-message__link"></a>' );

			$link
				.attr( 'href', String( summary.edit_link ) )
				.text( summary.layout_id ? api.text( 'editLayout', 'Open the imported layout' ) : api.text( 'allLayouts', 'Open all layouts' ) );

			$message.append( $link );
		}
	}

	/**
	 * Writes a message and announces it.
	 *
	 * @since 1.0.0
	 *
	 * @param {Object} $message Message container.
	 * @param {string} tone     success|error|warning|info.
	 * @param {string} text     Message.
	 * @return {void}
	 */
	function write( $message, tone, text ) {
		if ( ! $message.length ) {
			return;
		}

		$message
			.removeClass( 'qevora-message--success qevora-message--error qevora-message--warning qevora-message--info' )
			.addClass( tone ? 'qevora-message--' + tone : '' )
			.text( text );

		if ( api.announce && text ) {
			api.announce( text );
		}
	}

	/**
	 * Toggles the busy state of a form.
	 *
	 * @since 1.0.0
	 *
	 * @param {Object} $form    Form.
	 * @param {Object} $spinner Spinner element.
	 * @param {Object} $submit  Submit control.
	 * @param {boolean} busy    Whether work is in progress.
	 * @return {void}
	 */
	function setBusy( $form, $spinner, $submit, busy ) {
		$form.attr( 'aria-busy', busy ? 'true' : 'false' );
		$spinner.toggleClass( 'is-active', busy );
		$submit.prop( 'disabled', busy );
	}

	/**
	 * Formats a byte count.
	 *
	 * @since 1.0.0
	 *
	 * @param {number} bytes Bytes.
	 * @return {string} Human readable size.
	 */
	function formatSize( bytes ) {
		var size = parseInt( bytes, 10 );

		if ( ! size || size < 0 ) {
			return '0 KB';
		}

		if ( size < 1024 ) {
			return size + ' B';
		}

		return Math.round( size / 1024 ) + ' KB';
	}
} )( window, document, jQuery );
