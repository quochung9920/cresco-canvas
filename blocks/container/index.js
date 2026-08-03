( function ( blocks, blockEditor, components, element, i18n ) {
	'use strict';

	var registerBlockType = blocks.registerBlockType;
	var InnerBlocks = blockEditor.InnerBlocks;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var RangeControl = components.RangeControl;
	var SelectControl = components.SelectControl;
	var ColorPalette = components.ColorPalette;
	var el = element.createElement;
	var __ = i18n.__;

	function styleFromAttributes( attributes ) {
		return {
			display: attributes.layoutMode,
			flexDirection: attributes.layoutMode === 'flex' ? attributes.direction : undefined,
			justifyContent: attributes.justify,
			alignItems: attributes.align,
			gap: attributes.gap + 'px',
			padding: [ attributes.paddingTop, attributes.paddingRight, attributes.paddingBottom, attributes.paddingLeft ].join( 'px ' ) + 'px',
			maxWidth: attributes.maxWidth + 'px',
			marginLeft: 'auto',
			marginRight: 'auto',
			background: attributes.background || undefined,
		};
	}

	registerBlockType( 'cresco/container', {
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;
			var blockProps = useBlockProps( { className: 'cc-container', style: styleFromAttributes( a ) } );

			return el(
				element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el( PanelBody, { title: __( 'Layout', 'cresco-canvas' ), initialOpen: true },
						el( SelectControl, { label: __( 'Layout mode', 'cresco-canvas' ), value: a.layoutMode, options: [ { label: 'Flex', value: 'flex' }, { label: 'Grid', value: 'grid' }, { label: 'Block', value: 'block' } ], onChange: function ( value ) { set( { layoutMode: value } ); } } ),
						el( SelectControl, { label: __( 'Direction', 'cresco-canvas' ), value: a.direction, options: [ { label: 'Column', value: 'column' }, { label: 'Row', value: 'row' } ], onChange: function ( value ) { set( { direction: value } ); } } ),
						el( SelectControl, { label: __( 'Justify', 'cresco-canvas' ), value: a.justify, options: [ { label: 'Start', value: 'flex-start' }, { label: 'Center', value: 'center' }, { label: 'End', value: 'flex-end' }, { label: 'Space between', value: 'space-between' } ], onChange: function ( value ) { set( { justify: value } ); } } ),
						el( SelectControl, { label: __( 'Align', 'cresco-canvas' ), value: a.align, options: [ { label: 'Stretch', value: 'stretch' }, { label: 'Start', value: 'flex-start' }, { label: 'Center', value: 'center' }, { label: 'End', value: 'flex-end' } ], onChange: function ( value ) { set( { align: value } ); } } ),
						el( RangeControl, { label: __( 'Gap', 'cresco-canvas' ), value: a.gap, min: 0, max: 160, onChange: function ( value ) { set( { gap: value } ); } } ),
						el( RangeControl, { label: __( 'Maximum width', 'cresco-canvas' ), value: a.maxWidth, min: 320, max: 2560, onChange: function ( value ) { set( { maxWidth: value } ); } } )
					),
					el( PanelBody, { title: __( 'Spacing', 'cresco-canvas' ), initialOpen: false },
						[ 'Top', 'Right', 'Bottom', 'Left' ].map( function ( side ) {
							var key = 'padding' + side;
							return el( RangeControl, { key: key, label: __( 'Padding ' + side.toLowerCase(), 'cresco-canvas' ), value: a[ key ], min: 0, max: 240, onChange: function ( value ) { var update = {}; update[ key ] = value; set( update ); } } );
						} )
					),
					el( PanelBody, { title: __( 'Style', 'cresco-canvas' ), initialOpen: false },
						el( 'p', null, __( 'Background', 'cresco-canvas' ) ),
						el( ColorPalette, { value: a.background, onChange: function ( value ) { set( { background: value || '' } ); } } )
					)
				),
				el( 'div', blockProps, el( InnerBlocks, { renderAppender: InnerBlocks.ButtonBlockAppender } ) )
			);
		},
		save: function ( props ) {
			var blockProps = useBlockProps.save( { className: 'cc-container', style: styleFromAttributes( props.attributes ) } );
			return el( 'div', blockProps, el( InnerBlocks.Content ) );
		},
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
