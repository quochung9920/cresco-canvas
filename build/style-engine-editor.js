( function ( wp ) {
	'use strict';
	if ( ! wp || ! wp.hooks || ! wp.compose || ! wp.element ) return;

	var addFilter = wp.hooks.addFilter;
	var createHigherOrderComponent = wp.compose.createHigherOrderComponent;
	var createElement = wp.element.createElement;

	function clone( value ) {
		return value && typeof value === 'object' ? JSON.parse( JSON.stringify( value ) ) : {};
	}

	function mergeLegacy( attrs ) {
		var managed = clone( attrs.crescoStyle || {} );
		var legacy = attrs.style && typeof attrs.style === 'object' ? attrs.style : {};
		[ 'dimensions', 'spacing', 'color', 'border', 'typography', 'effects', 'position' ].forEach( function ( group ) {
			if ( ! managed[ group ] && legacy[ group ] && typeof legacy[ group ] === 'object' ) managed[ group ] = clone( legacy[ group ] );
		} );
		return managed;
	}

	function path( value, keys, fallback ) {
		for ( var i = 0; i < keys.length; i++ ) {
			if ( ! value || typeof value !== 'object' || ! Object.prototype.hasOwnProperty.call( value, keys[ i ] ) ) return fallback;
			value = value[ keys[ i ] ];
		}
		return value;
	}

	function editorStyle( attrs ) {
		var value = mergeLegacy( attrs || {} );
		var css = {};
		var map = [
			[ [ 'dimensions', 'width' ], 'width' ], [ [ 'dimensions', 'minHeight' ], 'minHeight' ],
			[ [ 'dimensions', 'height' ], 'height' ], [ [ 'dimensions', 'maxWidth' ], 'maxWidth' ],
			[ [ 'spacing', 'margin', 'top' ], 'marginTop' ], [ [ 'spacing', 'margin', 'right' ], 'marginRight' ],
			[ [ 'spacing', 'margin', 'bottom' ], 'marginBottom' ], [ [ 'spacing', 'margin', 'left' ], 'marginLeft' ],
			[ [ 'spacing', 'padding', 'top' ], 'paddingTop' ], [ [ 'spacing', 'padding', 'right' ], 'paddingRight' ],
			[ [ 'spacing', 'padding', 'bottom' ], 'paddingBottom' ], [ [ 'spacing', 'padding', 'left' ], 'paddingLeft' ],
			[ [ 'color', 'text' ], 'color' ], [ [ 'color', 'background' ], 'backgroundColor' ],
			[ [ 'border', 'radius' ], 'borderRadius' ], [ [ 'typography', 'fontSize' ], 'fontSize' ],
			[ [ 'typography', 'lineHeight' ], 'lineHeight' ], [ [ 'effects', 'opacity' ], 'opacity' ],
			[ [ 'effects', 'transform' ], 'transform' ], [ [ 'effects', 'boxShadow' ], 'boxShadow' ],
			[ [ 'position', 'top' ], 'top' ], [ [ 'position', 'right' ], 'right' ],
			[ [ 'position', 'bottom' ], 'bottom' ], [ [ 'position', 'left' ], 'left' ],
			[ [ 'position', 'zIndex' ], 'zIndex' ], [ [ 'position', 'overflow' ], 'overflow' ]
		];
		map.forEach( function ( item ) {
			var result = path( value, item[ 0 ], undefined );
			if ( result !== undefined && result !== '' && result !== null ) css[ item[ 1 ] ] = result;
		} );
		var position = path( value, [ 'position', 'type' ], '' );
		if ( position && position !== 'static' ) css.position = position;
		return css;
	}

	addFilter( 'blocks.registerBlockType', 'cresco-canvas/style-attributes', function ( settings ) {
		settings.attributes = Object.assign( {}, settings.attributes || {}, {
			crescoStyle: { type: 'object', default: {} },
			crescoStyleVersion: { type: 'number', default: 1 }
		} );
		return settings;
	} );

	addFilter( 'editor.BlockListBlock', 'cresco-canvas/style-preview', createHigherOrderComponent( function ( BlockListBlock ) {
		return function ( props ) {
			var existing = props.wrapperProps || {};
			var className = [ existing.className || '', 'cresco-style-engine' ].filter( Boolean ).join( ' ' );
			var wrapperProps = Object.assign( {}, existing, {
				className: className,
				style: Object.assign( {}, existing.style || {}, editorStyle( props.attributes || {} ) )
			} );
			return createElement( BlockListBlock, Object.assign( {}, props, { wrapperProps: wrapperProps } ) );
		};
	}, 'withCrescoStylePreview' ) );
} )( window.wp );
