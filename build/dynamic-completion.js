( function ( wp ) {
	'use strict';
	if ( ! wp || ! wp.blocks || ! wp.blockEditor || ! wp.components || ! wp.element ) return;
	var el = wp.element.createElement;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var InnerBlocks = wp.blockEditor.InnerBlocks;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var RangeControl = wp.components.RangeControl;
	var SelectControl = wp.components.SelectControl;
	var __ = wp.i18n.__;
	function pathPanel( props, extra ) {
		return el( InspectorControls, {}, el( PanelBody, { title: __( 'Row binding', 'cresco-canvas' ), initialOpen: true },
			el( TextControl, { label: __( 'Field path', 'cresco-canvas' ), value: props.attributes.path || '', onChange: function ( value ) { props.setAttributes( { path: value } ); } } ), extra || null ) );
	}
	wp.blocks.registerBlockType( 'cresco/acf-row-image', {
		apiVersion: 3, title: __( 'ACF Row Image', 'cresco-canvas' ), icon: 'format-image', category: 'cresco-canvas',
		attributes: { path: { type: 'string', default: '' }, altPath: { type: 'string', default: '' }, size: { type: 'string', default: 'large' }, fallbackUrl: { type: 'string', default: '' } },
		edit: function ( props ) { return el( 'div', { className: props.className + ' cresco-row-binding-placeholder' }, pathPanel( props, el( TextControl, { label: __( 'Alt field path', 'cresco-canvas' ), value: props.attributes.altPath || '', onChange: function ( value ) { props.setAttributes( { altPath: value } ); } } ) ), __( 'Dynamic row image', 'cresco-canvas' ) ); }, save: function () { return null; }
	} );
	wp.blocks.registerBlockType( 'cresco/acf-row-gallery', {
		apiVersion: 3, title: __( 'ACF Row Gallery', 'cresco-canvas' ), icon: 'format-gallery', category: 'cresco-canvas',
		attributes: { path: { type: 'string', default: '' }, size: { type: 'string', default: 'medium' }, columns: { type: 'number', default: 3 }, limit: { type: 'number', default: 12 } },
		edit: function ( props ) { return el( 'div', { className: props.className + ' cresco-row-binding-placeholder' }, pathPanel( props, el( RangeControl, { label: __( 'Columns', 'cresco-canvas' ), min: 1, max: 6, value: props.attributes.columns, onChange: function ( value ) { props.setAttributes( { columns: value } ); } } ) ), __( 'Dynamic row gallery', 'cresco-canvas' ) ); }, save: function () { return null; }
	} );
	wp.blocks.registerBlockType( 'cresco/acf-row-relationship', {
		apiVersion: 3, title: __( 'ACF Row Relationship', 'cresco-canvas' ), icon: 'admin-links', category: 'cresco-canvas',
		attributes: { path: { type: 'string', default: '' }, limit: { type: 'number', default: 12 }, columns: { type: 'number', default: 3 }, emptyMessage: { type: 'string', default: '' } },
		edit: function ( props ) { return el( 'div', { className: props.className }, pathPanel( props, el( RangeControl, { label: __( 'Limit', 'cresco-canvas' ), min: 1, max: 24, value: props.attributes.limit, onChange: function ( value ) { props.setAttributes( { limit: value } ); } } ) ), el( InnerBlocks, { allowedBlocks: [ 'core/heading', 'core/paragraph', 'core/image', 'core/group', 'cresco/dynamic-field', 'cresco/dynamic-image' ], template: [ [ 'cresco/dynamic-field', { field: 'title', tagName: 'h3', linkTo: 'post' } ] ] } ) ); },
		save: function () { return el( InnerBlocks.Content ); }
	} );
} )( window.wp );
