( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.element || ! wp.data || ! wp.components || ! wp.blocks ) return;

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useEffect = wp.element.useEffect;
	var useMemo = wp.element.useMemo;
	var useState = wp.element.useState;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;
	var RangeControl = wp.components.RangeControl;
	var Button = wp.components.Button;
	var Notice = wp.components.Notice;
	var getBlockType = wp.blocks.getBlockType;
	var HOST_ID = 'cresco-canvas-persistent-inspector';
	var ROOT_ID = 'cresco-canvas-persistent-inspector-root';
	var mountedRoot = null;

	function clone( value ) {
		return value && typeof value === 'object' ? JSON.parse( JSON.stringify( value ) ) : {};
	}

	function getPath( object, path, fallback ) {
		var current = object;
		for ( var index = 0; index < path.length; index += 1 ) {
			if ( ! current || typeof current !== 'object' || ! Object.prototype.hasOwnProperty.call( current, path[ index ] ) ) return fallback;
			current = current[ path[ index ] ];
		}
		return current;
	}

	function setPath( object, path, value ) {
		var next = clone( object );
		var current = next;
		for ( var index = 0; index < path.length - 1; index += 1 ) {
			if ( ! current[ path[ index ] ] || typeof current[ path[ index ] ] !== 'object' ) current[ path[ index ] ] = {};
			current = current[ path[ index ] ];
		}
		if ( value === undefined || value === '' ) delete current[ path[ path.length - 1 ] ];
		else current[ path[ path.length - 1 ] ] = value;
		return next;
	}

	function normalizeUnit( value ) {
		value = String( value == null ? '' : value ).trim();
		if ( ! value ) return undefined;
		return /^-?\d+(\.\d+)?$/.test( value ) ? value + 'px' : value;
	}

	function managedStyle( attributes ) {
		var metadata = attributes && attributes.metadata && typeof attributes.metadata === 'object' ? attributes.metadata : {};
		var managed = clone( metadata.crescoStyle || attributes.crescoStyle || {} );
		var legacy = attributes && attributes.style && typeof attributes.style === 'object' ? attributes.style : {};
		[ 'dimensions', 'spacing', 'color', 'border', 'typography', 'effects', 'position' ].forEach( function ( group ) {
			if ( ! managed[ group ] && legacy[ group ] && typeof legacy[ group ] === 'object' ) managed[ group ] = clone( legacy[ group ] );
		} );
		return managed;
	}

	function labelForBlock( block ) {
		if ( ! block ) return 'Widget';
		var type = getBlockType( block.name );
		return type && type.title ? String( type.title ) : String( block.name || 'Widget' );
	}

	function PersistentInspector() {
		var tabState = useState( 'content' );
		var activeTab = tabState[ 0 ];
		var setActiveTab = tabState[ 1 ];
		var selected = useSelect( function ( select ) {
			var editor = select( 'core/block-editor' );
			var clientId = editor && editor.getSelectedBlockClientId ? editor.getSelectedBlockClientId() : null;
			return clientId && editor.getBlock ? editor.getBlock( clientId ) : null;
		}, [] );
		var dispatch = useDispatch( 'core/block-editor' );
		var title = useMemo( function () { return labelForBlock( selected ); }, [ selected && selected.name ] );

		useEffect( function () {
			if ( document.body ) document.body.classList.toggle( 'cresco-persistent-inspector-open', Boolean( selected ) );
			if ( selected ) setActiveTab( 'content' );
			return function () {
				if ( document.body ) document.body.classList.remove( 'cresco-persistent-inspector-open' );
			};
		}, [ selected && selected.clientId ] );

		function update( patch ) {
			if ( selected && dispatch && dispatch.updateBlockAttributes ) dispatch.updateBlockAttributes( selected.clientId, patch );
		}

		function updateManaged( path, value ) {
			if ( ! selected ) return;
			var attributes = selected.attributes || {};
			var style = setPath( managedStyle( attributes ), path, value );
			var metadata = clone( attributes.metadata || {} );
			metadata.crescoStyle = style;
			metadata.crescoStyleVersion = 1;
			var patch = { metadata: metadata };
			if ( Object.prototype.hasOwnProperty.call( attributes, 'crescoStyle' ) ) {
				patch.crescoStyle = style;
				patch.crescoStyleVersion = 1;
			}
			update( patch );
		}

		function resetGroup( group ) {
			if ( ! selected ) return;
			var attributes = selected.attributes || {};
			var style = managedStyle( attributes );
			delete style[ group ];
			var metadata = clone( attributes.metadata || {} );
			metadata.crescoStyle = style;
			metadata.crescoStyleVersion = 1;
			update( { metadata: metadata } );
		}

		function duplicateSelected() {
			if ( selected && dispatch && dispatch.duplicateBlocks ) dispatch.duplicateBlocks( [ selected.clientId ] );
		}

		function removeSelected() {
			if ( selected && dispatch && dispatch.removeBlock ) dispatch.removeBlock( selected.clientId );
		}

		if ( ! selected ) {
			return el( 'div', { className: 'cc-persistent-inspector__empty' },
				el( 'strong', null, 'Select a widget' ),
				el( 'p', null, 'Click any widget on the canvas to edit it here.' )
			);
		}

		var attributes = selected.attributes || {};
		var style = managedStyle( attributes );

		function fieldExists( key ) {
			return Object.prototype.hasOwnProperty.call( attributes, key );
		}

		function spacingControl( label, path ) {
			var current = getPath( style, path, {} ) || {};
			return el( 'section', { className: 'cc-persistent-inspector__group' },
				el( 'div', { className: 'cc-persistent-inspector__group-title' }, label ),
				el( 'div', { className: 'cc-persistent-inspector__quad' },
					[ 'top', 'right', 'bottom', 'left' ].map( function ( side ) {
						return el( TextControl, {
							key: side,
							label: side.charAt( 0 ).toUpperCase(),
							value: current[ side ] || '',
							placeholder: '0',
							onChange: function ( value ) { updateManaged( path.concat( side ), normalizeUnit( value ) ); }
						} );
					} )
				)
			);
		}

		function ContentTab() {
			var fields = [];
			if ( fieldExists( 'content' ) ) fields.push( el( TextareaControl, { key: 'content', label: 'Content', value: attributes.content || '', onChange: function ( value ) { update( { content: value } ); } } ) );
			if ( fieldExists( 'text' ) ) fields.push( el( TextControl, { key: 'text', label: 'Text', value: attributes.text || '', onChange: function ( value ) { update( { text: value } ); } } ) );
			if ( fieldExists( 'url' ) ) fields.push( el( TextControl, { key: 'url', label: 'Link URL', value: attributes.url || '', onChange: function ( value ) { update( { url: value } ); } } ) );
			if ( fieldExists( 'alt' ) ) fields.push( el( TextControl, { key: 'alt', label: 'Alternative text', value: attributes.alt || '', onChange: function ( value ) { update( { alt: value } ); } } ) );
			if ( fieldExists( 'level' ) ) fields.push( el( SelectControl, { key: 'level', label: 'Heading level', value: String( attributes.level || 2 ), options: [ 1, 2, 3, 4, 5, 6 ].map( function ( level ) { return { label: 'H' + level, value: String( level ) }; } ), onChange: function ( value ) { update( { level: parseInt( value, 10 ) } ); } } ) );
			if ( fieldExists( 'align' ) ) fields.push( el( SelectControl, { key: 'align', label: 'Alignment', value: attributes.align || '', options: [ { label: 'Default', value: '' }, { label: 'Wide', value: 'wide' }, { label: 'Full width', value: 'full' }, { label: 'Left', value: 'left' }, { label: 'Center', value: 'center' }, { label: 'Right', value: 'right' } ], onChange: function ( value ) { update( { align: value || undefined } ); } } ) );
			return fields.length ? el( Fragment, null, fields ) : el( Notice, { status: 'info', isDismissible: false }, 'Edit this widget content directly on the canvas. Layout and appearance are available in the other tabs.' );
		}

		function StyleTab() {
			return el( Fragment, null,
				el( 'section', { className: 'cc-persistent-inspector__group' },
					el( 'div', { className: 'cc-persistent-inspector__group-title' }, 'Size' ),
					el( TextControl, { label: 'Width', value: getPath( style, [ 'dimensions', 'width' ], '' ), placeholder: '100% or 640px', onChange: function ( value ) { updateManaged( [ 'dimensions', 'width' ], normalizeUnit( value ) ); } } ),
					el( TextControl, { label: 'Maximum width', value: getPath( style, [ 'dimensions', 'maxWidth' ], '' ), placeholder: '1200px', onChange: function ( value ) { updateManaged( [ 'dimensions', 'maxWidth' ], normalizeUnit( value ) ); } } ),
					el( TextControl, { label: 'Minimum height', value: getPath( style, [ 'dimensions', 'minHeight' ], '' ), placeholder: '320px', onChange: function ( value ) { updateManaged( [ 'dimensions', 'minHeight' ], normalizeUnit( value ) ); } } )
				),
				spacingControl( 'Margin', [ 'spacing', 'margin' ] ),
				spacingControl( 'Padding', [ 'spacing', 'padding' ] ),
				el( 'section', { className: 'cc-persistent-inspector__group' },
					el( 'div', { className: 'cc-persistent-inspector__group-title' }, 'Appearance' ),
					el( TextControl, { label: 'Text color', value: getPath( style, [ 'color', 'text' ], '' ), placeholder: '#101828', onChange: function ( value ) { updateManaged( [ 'color', 'text' ], value || undefined ); } } ),
					el( TextControl, { label: 'Background color', value: getPath( style, [ 'color', 'background' ], '' ), placeholder: '#ffffff', onChange: function ( value ) { updateManaged( [ 'color', 'background' ], value || undefined ); } } ),
					el( TextControl, { label: 'Border radius', value: getPath( style, [ 'border', 'radius' ], '' ), placeholder: '8px', onChange: function ( value ) { updateManaged( [ 'border', 'radius' ], normalizeUnit( value ) ); } } ),
					el( TextControl, { label: 'Font size', value: getPath( style, [ 'typography', 'fontSize' ], '' ), placeholder: '18px', onChange: function ( value ) { updateManaged( [ 'typography', 'fontSize' ], normalizeUnit( value ) ); } } ),
					el( TextControl, { label: 'Line height', value: getPath( style, [ 'typography', 'lineHeight' ], '' ), placeholder: '1.5', onChange: function ( value ) { updateManaged( [ 'typography', 'lineHeight' ], value || undefined ); } } )
				),
				el( Button, { variant: 'secondary', onClick: function () { resetGroup( 'spacing' ); resetGroup( 'dimensions' ); } }, 'Reset layout' )
			);
		}

		function AdvancedTab() {
			var className = attributes.className || '';
			function toggleClass( token, enabled ) {
				var values = className.split( /\s+/ ).filter( Boolean ).filter( function ( value ) { return value !== token; } );
				if ( enabled ) values.push( token );
				update( { className: values.join( ' ' ) } );
			}
			return el( Fragment, null,
				el( 'section', { className: 'cc-persistent-inspector__group' },
					el( 'div', { className: 'cc-persistent-inspector__group-title' }, 'Effects' ),
					el( RangeControl, { label: 'Opacity', min: 0, max: 100, value: Math.round( 100 * parseFloat( getPath( style, [ 'effects', 'opacity' ], 1 ) || 1 ) ), onChange: function ( value ) { updateManaged( [ 'effects', 'opacity' ], value / 100 ); } } ),
					el( TextControl, { label: 'Transform', value: getPath( style, [ 'effects', 'transform' ], '' ), placeholder: 'translateY(-4px)', onChange: function ( value ) { updateManaged( [ 'effects', 'transform' ], value || undefined ); } } ),
					el( TextControl, { label: 'Box shadow', value: getPath( style, [ 'effects', 'boxShadow' ], '' ), placeholder: '0 8px 24px rgba(0,0,0,.12)', onChange: function ( value ) { updateManaged( [ 'effects', 'boxShadow' ], value || undefined ); } } )
				),
				el( 'section', { className: 'cc-persistent-inspector__group' },
					el( 'div', { className: 'cc-persistent-inspector__group-title' }, 'Responsive visibility' ),
					el( ToggleControl, { label: 'Hide on desktop', checked: className.indexOf( 'cresco-hide-desktop' ) !== -1, onChange: function ( value ) { toggleClass( 'cresco-hide-desktop', value ); } } ),
					el( ToggleControl, { label: 'Hide on tablet', checked: className.indexOf( 'cresco-hide-tablet' ) !== -1, onChange: function ( value ) { toggleClass( 'cresco-hide-tablet', value ); } } ),
					el( ToggleControl, { label: 'Hide on mobile', checked: className.indexOf( 'cresco-hide-mobile' ) !== -1, onChange: function ( value ) { toggleClass( 'cresco-hide-mobile', value ); } } )
				),
				el( 'section', { className: 'cc-persistent-inspector__group' },
					el( 'div', { className: 'cc-persistent-inspector__group-title' }, 'Position' ),
					el( SelectControl, { label: 'Position', value: getPath( style, [ 'position', 'type' ], 'static' ), options: [ 'static', 'relative', 'absolute', 'fixed', 'sticky' ].map( function ( value ) { return { label: value.charAt( 0 ).toUpperCase() + value.slice( 1 ), value: value }; } ), onChange: function ( value ) { updateManaged( [ 'position', 'type' ], value ); } } ),
					[ 'top', 'right', 'bottom', 'left' ].map( function ( side ) { return el( TextControl, { key: side, label: side.charAt( 0 ).toUpperCase() + side.slice( 1 ), value: getPath( style, [ 'position', side ], '' ), onChange: function ( value ) { updateManaged( [ 'position', side ], normalizeUnit( value ) ); } } ); } ),
					el( TextControl, { label: 'Z-index', value: String( getPath( style, [ 'position', 'zIndex' ], '' ) ), onChange: function ( value ) { updateManaged( [ 'position', 'zIndex' ], value || undefined ); } } ),
					el( SelectControl, { label: 'Overflow', value: getPath( style, [ 'position', 'overflow' ], 'visible' ), options: [ 'visible', 'hidden', 'clip', 'auto', 'scroll' ].map( function ( value ) { return { label: value.charAt( 0 ).toUpperCase() + value.slice( 1 ), value: value }; } ), onChange: function ( value ) { updateManaged( [ 'position', 'overflow' ], value ); } } )
				),
				el( 'section', { className: 'cc-persistent-inspector__group' },
					el( 'div', { className: 'cc-persistent-inspector__group-title' }, 'HTML' ),
					el( TextControl, { label: 'HTML anchor', value: attributes.anchor || '', onChange: function ( value ) { update( { anchor: String( value ).replace( /[^a-zA-Z0-9\-_:.]/g, '' ) } ); } } ),
					el( TextControl, { label: 'Additional CSS classes', value: attributes.className || '', onChange: function ( value ) { update( { className: value } ); } } )
				)
			);
		}

		var tabs = [
			{ id: 'content', label: 'Content' },
			{ id: 'style', label: 'Style' },
			{ id: 'advanced', label: 'Advanced' }
		];

		return el( Fragment, null,
			el( 'header', { className: 'cc-persistent-inspector__header' },
				el( 'div', { className: 'cc-persistent-inspector__title' },
					el( 'span', null, 'Edit widget' ),
					el( 'strong', null, title ),
					el( 'code', null, selected.name )
				),
				el( 'div', { className: 'cc-persistent-inspector__actions' },
					el( Button, { icon: 'admin-page', label: 'Duplicate widget', onClick: duplicateSelected } ),
					el( Button, { icon: 'trash', label: 'Delete widget', isDestructive: true, onClick: removeSelected } )
				)
			),
			el( 'nav', { className: 'cc-persistent-inspector__tabs', 'aria-label': 'Widget editing sections' },
				tabs.map( function ( tab ) {
					return el( 'button', { key: tab.id, type: 'button', className: activeTab === tab.id ? 'is-active' : '', onClick: function () { setActiveTab( tab.id ); } }, tab.label );
				} )
			),
			el( 'div', { className: 'cc-persistent-inspector__body' },
				activeTab === 'content' && el( ContentTab ),
				activeTab === 'style' && el( StyleTab ),
				activeTab === 'advanced' && el( AdvancedTab )
			)
		);
	}

	function ensureHost() {
		var existing = document.getElementById( HOST_ID );
		if ( existing ) return existing;
		var bodyShell = document.querySelector( '.interface-interface-skeleton__body' );
		var content = document.querySelector( '.interface-interface-skeleton__content' );
		if ( ! bodyShell || ! content ) return null;
		var host = document.createElement( 'aside' );
		host.id = HOST_ID;
		host.className = 'cresco-canvas-widget-inspector cresco-canvas-persistent-inspector';
		host.setAttribute( 'aria-label', 'Cresco widget editor' );
		var root = document.createElement( 'div' );
		root.id = ROOT_ID;
		host.appendChild( root );
		bodyShell.insertBefore( host, content );
		return host;
	}

	function mount() {
		var host = ensureHost();
		if ( ! host || mountedRoot ) return Boolean( host );
		var rootNode = document.getElementById( ROOT_ID );
		if ( ! rootNode ) return false;
		if ( typeof wp.element.createRoot === 'function' ) {
			mountedRoot = wp.element.createRoot( rootNode );
			mountedRoot.render( el( PersistentInspector ) );
		} else if ( typeof wp.element.render === 'function' ) {
			wp.element.render( el( PersistentInspector ), rootNode );
			mountedRoot = true;
		} else {
			return false;
		}
		return true;
	}

	function start() {
		if ( mount() ) return;
		var observer = new MutationObserver( function () {
			if ( mount() ) observer.disconnect();
		} );
		observer.observe( document.body, { childList: true, subtree: true } );
	}

	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	else start();
} )( window.wp );
