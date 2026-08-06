( function ( wp, window, document ) {
	'use strict';

	if ( ! wp || ! wp.hooks || ! wp.compose || ! wp.element || ! wp.data ) return;

	var Cresco = window.CrescoCanvas || {};
	var addFilter = wp.hooks.addFilter;
	var createHigherOrderComponent = wp.compose.createHigherOrderComponent;
	var createElement = wp.element.createElement;
	var STYLE_ID = 'cresco-style-engine-responsive-preview';
	var devices = [ 'wide', 'desktop', 'laptop', 'tablet', 'mobile' ];
	var migrationScheduled = false;
	var syncing = false;

	var map = [
		[ [ 'dimensions', 'width' ], 'width', 'width' ],
		[ [ 'dimensions', 'minHeight' ], 'minHeight', 'min-height' ],
		[ [ 'dimensions', 'height' ], 'height', 'height' ],
		[ [ 'dimensions', 'maxWidth' ], 'maxWidth', 'max-width' ],
		[ [ 'spacing', 'margin', 'top' ], 'marginTop', 'margin-top' ],
		[ [ 'spacing', 'margin', 'right' ], 'marginRight', 'margin-right' ],
		[ [ 'spacing', 'margin', 'bottom' ], 'marginBottom', 'margin-bottom' ],
		[ [ 'spacing', 'margin', 'left' ], 'marginLeft', 'margin-left' ],
		[ [ 'spacing', 'padding', 'top' ], 'paddingTop', 'padding-top' ],
		[ [ 'spacing', 'padding', 'right' ], 'paddingRight', 'padding-right' ],
		[ [ 'spacing', 'padding', 'bottom' ], 'paddingBottom', 'padding-bottom' ],
		[ [ 'spacing', 'padding', 'left' ], 'paddingLeft', 'padding-left' ],
		[ [ 'color', 'text' ], 'color', 'color' ],
		[ [ 'color', 'background' ], 'backgroundColor', 'background-color' ],
		[ [ 'border', 'radius' ], 'borderRadius', 'border-radius' ],
		[ [ 'typography', 'fontSize' ], 'fontSize', 'font-size' ],
		[ [ 'typography', 'lineHeight' ], 'lineHeight', 'line-height' ],
		[ [ 'effects', 'opacity' ], 'opacity', 'opacity' ],
		[ [ 'effects', 'transform' ], 'transform', 'transform' ],
		[ [ 'effects', 'boxShadow' ], 'boxShadow', 'box-shadow' ],
		[ [ 'position', 'top' ], 'top', 'top' ],
		[ [ 'position', 'right' ], 'right', 'right' ],
		[ [ 'position', 'bottom' ], 'bottom', 'bottom' ],
		[ [ 'position', 'left' ], 'left', 'left' ],
		[ [ 'position', 'zIndex' ], 'zIndex', 'z-index' ],
		[ [ 'position', 'overflow' ], 'overflow', 'overflow' ]
	];

	function clone( value ) {
		return value && typeof value === 'object' ? JSON.parse( JSON.stringify( value ) ) : {};
	}

	function mergeLegacy( attrs ) {
		var metadata = attrs.metadata && typeof attrs.metadata === 'object' ? attrs.metadata : {};
		var managed = clone( metadata.crescoStyle || attrs.crescoStyle || {} );
		var legacy = attrs.style && typeof attrs.style === 'object' ? attrs.style : {};
		[ 'dimensions', 'spacing', 'color', 'border', 'typography', 'effects', 'position' ].forEach( function ( group ) {
			if ( ! managed[ group ] && legacy[ group ] && typeof legacy[ group ] === 'object' ) managed[ group ] = clone( legacy[ group ] );
		} );
		return managed;
	}

	function path( value, keys, fallback ) {
		for ( var index = 0; index < keys.length; index += 1 ) {
			if ( ! value || typeof value !== 'object' || ! Object.prototype.hasOwnProperty.call( value, keys[ index ] ) ) return fallback;
			value = value[ keys[ index ] ];
		}
		return value;
	}

	function editorStyle( attrs ) {
		var value = mergeLegacy( attrs || {} );
		var css = {};
		map.forEach( function ( item ) {
			var result = path( value, item[ 0 ], undefined );
			if ( result !== undefined && result !== '' && result !== null ) css[ item[ 1 ] ] = result;
		} );
		var position = path( value, [ 'position', 'type' ], '' );
		if ( position && position !== 'static' ) css.position = position;

		var responsive = value.responsive && typeof value.responsive === 'object' ? value.responsive : {};
		[ 'desktop', 'laptop', 'tablet', 'mobile' ].forEach( function ( device ) {
			var deviceStyle = responsive[ device ];
			if ( ! deviceStyle || typeof deviceStyle !== 'object' ) return;
			map.forEach( function ( item ) {
				var result = path( deviceStyle, item[ 0 ], undefined );
				if ( result !== undefined && result !== '' && result !== null ) css[ '--cc-r-' + device + '-' + item[ 2 ] ] = result;
			} );
			var devicePosition = path( deviceStyle, [ 'position', 'type' ], '' );
			if ( devicePosition && devicePosition !== 'static' ) css[ '--cc-r-' + device + '-position' ] = devicePosition;
		} );
		return css;
	}

	function responsivePreviewCss() {
		return [ 'desktop', 'laptop', 'tablet', 'mobile' ].map( function ( device ) {
			var declarations = map.map( function ( item ) {
				return item[ 2 ] + ':var(--cc-r-' + device + '-' + item[ 2 ] + ')!important;';
			} ).join( '' ) + 'position:var(--cc-r-' + device + '-position)!important;';
			return '.cresco-device-' + device + ' .cresco-style-engine{' + declarations + '}';
		} ).join( '' );
	}

	function injectPreviewStyle( targetDocument ) {
		if ( ! targetDocument || ! targetDocument.head || targetDocument.getElementById( STYLE_ID ) ) return;
		var style = targetDocument.createElement( 'style' );
		style.id = STYLE_ID;
		style.textContent = responsivePreviewCss();
		targetDocument.head.appendChild( style );
	}

	function editorDocuments() {
		var result = [ document ];
		document.querySelectorAll( 'iframe[name="editor-canvas"]' ).forEach( function ( iframe ) {
			try { if ( iframe.contentDocument ) result.push( iframe.contentDocument ); } catch ( error ) {}
		} );
		return result;
	}

	function syncDevice() {
		var device = Cresco.ui && Cresco.ui.getState ? Cresco.ui.getState().device : 'wide';
		if ( devices.indexOf( device ) === -1 ) device = 'wide';
		editorDocuments().forEach( function ( targetDocument ) {
			injectPreviewStyle( targetDocument );
			devices.forEach( function ( item ) { targetDocument.documentElement.classList.toggle( 'cresco-device-' + item, item === device ); } );
		} );
	}

	addFilter( 'blocks.registerBlockType', 'cresco-canvas/style-attributes', function ( settings ) {
		settings.attributes = Object.assign( {}, settings.attributes || {}, {
			crescoStyle: { type: 'object', default: {} },
			crescoStyleVersion: { type: 'number', default: 2 }
		} );
		return settings;
	} );

	addFilter( 'editor.BlockListBlock', 'cresco-canvas/style-preview', createHigherOrderComponent( function ( BlockListBlock ) {
		return function ( props ) {
			var existing = props.wrapperProps || {};
			var wrapperProps = Object.assign( {}, existing, {
				className: [ existing.className || '', 'cresco-style-engine' ].filter( Boolean ).join( ' ' ),
				style: Object.assign( {}, existing.style || {}, editorStyle( props.attributes || {} ) )
			} );
			return createElement( BlockListBlock, Object.assign( {}, props, { wrapperProps: wrapperProps } ) );
		};
	}, 'withCrescoStylePreview' ) );

	function migrateLegacyStyles() {
		migrationScheduled = false;
		if ( syncing ) return;
		var selector = wp.data.select( 'core/block-editor' );
		var dispatcher = wp.data.dispatch( 'core/block-editor' );
		if ( ! selector || ! dispatcher || ! selector.getBlocks || ! dispatcher.updateBlockAttributes ) return;
		var updates = [];
		function walk( blocks ) {
			( blocks || [] ).forEach( function ( block ) {
				var attrs = block.attributes || {};
				if ( attrs.crescoStyle && typeof attrs.crescoStyle === 'object' ) {
					var metadata = attrs.metadata && typeof attrs.metadata === 'object' ? attrs.metadata : {};
					if ( JSON.stringify( metadata.crescoStyle || {} ) !== JSON.stringify( attrs.crescoStyle ) || Number( metadata.crescoStyleVersion || 0 ) < 2 ) {
						updates.push( { clientId: block.clientId, metadata: Object.assign( {}, metadata, { crescoStyle: clone( attrs.crescoStyle ), crescoStyleVersion: 2 } ) } );
					}
				}
				if ( block.innerBlocks && block.innerBlocks.length ) walk( block.innerBlocks );
			} );
		}
		walk( selector.getBlocks() );
		if ( ! updates.length ) return;
		syncing = true;
		updates.forEach( function ( item ) { dispatcher.updateBlockAttributes( item.clientId, { metadata: item.metadata } ); } );
		syncing = false;
	}

	function scheduleMigration() {
		if ( migrationScheduled || syncing ) return;
		migrationScheduled = true;
		if ( typeof window.requestIdleCallback === 'function' ) window.requestIdleCallback( migrateLegacyStyles, { timeout: 500 } );
		else window.setTimeout( migrateLegacyStyles, 60 );
	}

	wp.data.subscribe( scheduleMigration );
	if ( Cresco.ui && Cresco.ui.subscribe ) Cresco.ui.subscribe( syncDevice );
	var observer = new MutationObserver( syncDevice );
	observer.observe( document.documentElement, { childList: true, subtree: true } );
	syncDevice();
	scheduleMigration();
} )( window.wp, window, document );
