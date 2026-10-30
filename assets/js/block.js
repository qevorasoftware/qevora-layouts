/**
 * Qevora Layout block — editor script.
 *
 * A dynamic block: it stores only the layout ID and the wrapper preference, and
 * the server renders the layout with the same code path as the header and
 * footer placements. Written in plain JavaScript against the public
 * `wp.*` packages, so no build step is needed.
 *
 * @package QevoraLayouts
 * @since   1.0.0
 */
/* global window, wp */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.blocks.registerBlockType ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useEffect = wp.element.useEffect;
	var useState = wp.element.useState;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var ServerSideRender = wp.serverSideRender;
	var components = wp.components || {};
	var apiFetch = wp.apiFetch;

	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var ToggleControl = components.ToggleControl;
	var Spinner = components.Spinner;
	var Placeholder = components.Placeholder;
	var Disabled = components.Disabled;
	var Notice = components.Notice;
	var ExternalLink = components.ExternalLink;

	/**
	 * Loads the layouts the current user may insert.
	 *
	 * @since 1.0.0
	 *
	 * @return {Object} State: layouts, loading and error.
	 */
	function useLayouts() {
		var state = useState( [] );
		var layouts = state[ 0 ];
		var setLayouts = state[ 1 ];

		var loadingState = useState( true );
		var loading = loadingState[ 0 ];
		var setLoading = loadingState[ 1 ];

		var errorState = useState( '' );
		var error = errorState[ 0 ];
		var setError = errorState[ 1 ];

		useEffect(
			function () {
				var cancelled = false;

				if ( ! apiFetch ) {
					setLoading( false );
					setError( __( 'The layout list could not be loaded in this WordPress version.', 'qevora-layouts' ) );

					return function () {
						cancelled = true;
					};
				}

				apiFetch( { path: '/qevora-layouts/v1/layouts?per_page=100&status=any' } )
					.then( function ( items ) {
						if ( cancelled ) {
							return;
						}

						setLayouts( items && items.length ? items : [] );
						setLoading( false );
					} )
					.catch( function ( response ) {
						if ( cancelled ) {
							return;
						}

						setError(
							response && response.message
								? response.message
								: __( 'The layout list could not be loaded.', 'qevora-layouts' )
						);
						setLoading( false );
					} );

				return function () {
					cancelled = true;
				};
			},
			[]
		);

		return { layouts: layouts, loading: loading, error: error };
	}

	/**
	 * Builds the option list for the layout select.
	 *
	 * @since 1.0.0
	 *
	 * @param {Array} layouts Layouts returned by the REST API.
	 * @return {Array} Options for SelectControl.
	 */
	function buildOptions( layouts ) {
		var options = [
			{
				label: __( 'Select a layout…', 'qevora-layouts' ),
				value: 0
			}
		];

		( layouts || [] ).forEach( function ( layout ) {
			var label = layout.title || sprintf( __( 'Layout #%d', 'qevora-layouts' ), layout.id );

			if ( layout.type_label ) {
				label += ' · ' + layout.type_label;
			}

			if ( ! layout.enabled ) {
				label += ' · ' + __( 'disabled', 'qevora-layouts' );
			}

			options.push( { label: label, value: layout.id } );
		} );

		return options;
	}

	/**
	 * Editor component.
	 *
	 * @since 1.0.0
	 *
	 * @param {Object} props Block props.
	 * @return {Object} Element.
	 */
	function Edit( props ) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;
		var blockProps = useBlockProps();
		var data = useLayouts();
		var layoutId = attributes.layoutId ? parseInt( attributes.layoutId, 10 ) : 0;
		var selected = null;

		( data.layouts || [] ).forEach( function ( layout ) {
			if ( parseInt( layout.id, 10 ) === layoutId ) {
				selected = layout;
			}
		} );

		var inspector = null;

		if ( InspectorControls && PanelBody && SelectControl ) {
			inspector = el(
				InspectorControls,
				{},
				el(
					PanelBody,
					{ title: __( 'Layout', 'qevora-layouts' ), initialOpen: true },
					SelectControl
						? el( SelectControl, {
								label: __( 'Which layout should be rendered here?', 'qevora-layouts' ),
								value: layoutId,
								options: buildOptions( data.layouts ),
								onChange: function ( value ) {
									setAttributes( { layoutId: parseInt( value, 10 ) || 0 } );
								}
						  } )
						: null,
					ToggleControl
						? el( ToggleControl, {
								label: __( 'Wrap the layout in a container div', 'qevora-layouts' ),
								help: __( 'Turn this off when the surrounding markup already provides spacing.', 'qevora-layouts' ),
								checked: !! attributes.wrapper,
								onChange: function ( value ) {
									setAttributes( { wrapper: !! value } );
								}
						  } )
						: null,
					selected && selected.edit_link && ExternalLink
						? el(
								ExternalLink,
								{ href: selected.edit_link },
								__( 'Edit this layout', 'qevora-layouts' )
						  )
						: null
				)
			);
		}

		var body;

		if ( data.loading && Spinner ) {
			body = el( Placeholder, { label: __( 'Qevora Layout', 'qevora-layouts' ) }, el( Spinner, {} ) );
		} else if ( data.error && Notice ) {
			body = el(
				Notice,
				{ status: 'error', isDismissible: false },
				data.error
			);
		} else if ( ! layoutId ) {
			body = el(
				Placeholder,
				{
					label: __( 'Qevora Layout', 'qevora-layouts' ),
					instructions: __( 'Pick a header, footer or reusable section to render in this spot.', 'qevora-layouts' )
				},
				SelectControl
					? el( SelectControl, {
							label: __( 'Layout', 'qevora-layouts' ),
							value: 0,
							options: buildOptions( data.layouts ),
							onChange: function ( value ) {
								setAttributes( { layoutId: parseInt( value, 10 ) || 0 } );
							}
					  } )
					: null
			);
		} else if ( ServerSideRender && Disabled ) {
			body = el(
				Disabled,
				{},
				el( ServerSideRender, { block: 'qevora/layout', attributes: attributes } )
			);
		} else {
			body = el(
				'p',
				{ className: 'qevora-layouts-notice' },
				sprintf( __( 'Rendering layout #%d. Preview needs the ServerSideRender component.', 'qevora-layouts' ), layoutId )
			);
		}

		return el( Fragment, {}, inspector, el( 'div', blockProps, body ) );
	}

	registerBlockType( 'qevora/layout', {
		apiVersion: 3,
		title: __( 'Qevora Layout', 'qevora-layouts' ),
		description: __( 'Renders a header, footer or reusable section built with Qevora Layouts.', 'qevora-layouts' ),
		category: 'widgets',
		icon: 'layout',
		keywords: [
			__( 'header', 'qevora-layouts' ),
			__( 'footer', 'qevora-layouts' ),
			__( 'section', 'qevora-layouts' )
		],
		supports: {
			html: false,
			anchor: true,
			align: [ 'wide', 'full' ]
		},
		attributes: {
			layoutId: {
				type: 'number',
				default: 0
			},
			wrapper: {
				type: 'boolean',
				default: true
			},
			align: {
				type: 'string',
				default: ''
			}
		},
		edit: Edit,
		save: function () {
			return null;
		}
	} );
} )( window.wp );
