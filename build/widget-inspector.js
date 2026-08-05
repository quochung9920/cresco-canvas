( function ( wp ) {
	'use strict';
	if ( ! wp || ! wp.plugins || ! wp.editor || ! wp.element || ! wp.components || ! wp.data || ! wp.blocks ) return;

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useEffect = wp.element.useEffect;
	var useMemo = wp.element.useMemo;
	var __ = wp.i18n.__;
	var registerPlugin = wp.plugins.registerPlugin;
	var PluginSidebar = wp.editor.PluginSidebar;
	var PluginSidebarMoreMenuItem = wp.editor.PluginSidebarMoreMenuItem;
	var PanelBody = wp.components.PanelBody;
	var TabPanel = wp.components.TabPanel;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var RangeControl = wp.components.RangeControl;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;
	var Button = wp.components.Button;
	var Notice = wp.components.Notice;
	var ColorPalette = wp.components.ColorPalette;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var getBlockType = wp.blocks.getBlockType;

	function clone( value ) {
		return value && typeof value === 'object' ? JSON.parse( JSON.stringify( value ) ) : {};
	}

	function getPath( object, path, fallback ) {
		var current = object;
		for ( var i = 0; i < path.length; i++ ) {
			if ( ! current || typeof current !== 'object' || ! Object.prototype.hasOwnProperty.call( current, path[ i ] ) ) return fallback;
			current = current[ path[ i ] ];
		}
		return current;
	}

	function setPath( object, path, value ) {
		var next = clone( object );
		var current = next;
		for ( var i = 0; i < path.length - 1; i++ ) {
			if ( ! current[ path[ i ] ] || typeof current[ path[ i ] ] !== 'object' ) current[ path[ i ] ] = {};
			current = current[ path[ i ] ];
		}
		current[ path[ path.length - 1 ] ] = value;
		return next;
	}

	function unit( value ) {
		if ( value === '' || value === null || typeof value === 'undefined' ) return undefined;
		return /^-?\d+(\.\d+)?$/.test( String( value ) ) ? String( value ) + 'px' : String( value );
	}

	function Inspector() {
		var selected = useSelect( function ( select ) {
			var editor = select( 'core/block-editor' );
			var id = editor && editor.getSelectedBlockClientId ? editor.getSelectedBlockClientId() : null;
			return id ? editor.getBlock( id ) : null;
		}, [] );
		var dispatch = useDispatch( 'core/block-editor' );
		var editPostDispatch = useDispatch( 'core/edit-post' );

		useEffect( function () {
			if ( ! selected || ! editPostDispatch || ! editPostDispatch.openGeneralSidebar ) return;
			var current = wp.data.select( 'core/edit-post' );
			var active = current && current.getActiveGeneralSidebarName ? current.getActiveGeneralSidebarName() : '';
			if ( active && active.indexOf( 'cresco-canvas' ) !== -1 ) editPostDispatch.openGeneralSidebar( 'cresco-canvas-widget-inspector/cresco-canvas-widget-inspector' );
		}, [ selected && selected.clientId ] );

		var blockType = useMemo( function () { return selected ? getBlockType( selected.name ) : null; }, [ selected && selected.name ] );

		function update( patch ) {
			if ( selected && dispatch && dispatch.updateBlockAttributes ) dispatch.updateBlockAttributes( selected.clientId, patch );
		}

		function updateStyle( path, value ) {
			var style = setPath( selected.attributes.style || {}, path, value );
			update( { style: style } );
		}

		function resetStyleGroup( root ) {
			var style = clone( selected.attributes.style || {} );
			delete style[ root ];
			update( { style: style } );
		}

		function ContentPanel() {
			var attrs = selected.attributes || {};
			var controls = [];
			if ( Object.prototype.hasOwnProperty.call( attrs, 'content' ) ) controls.push( el( TextareaControl, { key: 'content', label: __( 'Content', 'cresco-canvas' ), value: attrs.content || '', onChange: function ( value ) { update( { content: value } ); } } ) );
			if ( Object.prototype.hasOwnProperty.call( attrs, 'text' ) ) controls.push( el( TextControl, { key: 'text', label: __( 'Text', 'cresco-canvas' ), value: attrs.text || '', onChange: function ( value ) { update( { text: value } ); } } ) );
			if ( Object.prototype.hasOwnProperty.call( attrs, 'url' ) ) controls.push( el( TextControl, { key: 'url', label: __( 'Link URL', 'cresco-canvas' ), value: attrs.url || '', onChange: function ( value ) { update( { url: value } ); } } ) );
			if ( Object.prototype.hasOwnProperty.call( attrs, 'alt' ) ) controls.push( el( TextControl, { key: 'alt', label: __( 'Alternative text', 'cresco-canvas' ), value: attrs.alt || '', onChange: function ( value ) { update( { alt: value } ); } } ) );
			if ( Object.prototype.hasOwnProperty.call( attrs, 'level' ) ) controls.push( el( SelectControl, { key: 'level', label: __( 'Heading level', 'cresco-canvas' ), value: String( attrs.level || 2 ), options: [ 1, 2, 3, 4, 5, 6 ].map( function ( level ) { return { label: 'H' + level, value: String( level ) }; } ), onChange: function ( value ) { update( { level: parseInt( value, 10 ) }; } } ) );
			return controls.length ? controls : el( Notice, { status: 'info', isDismissible: false }, __( 'This widget uses its native WordPress content controls directly on the canvas.', 'cresco-canvas' ) );
		}

		function SpacingControl( label, path ) {
			var current = getPath( selected.attributes.style || {}, path, {} ) || {};
			return el( 'div', { className: 'cc-widget-inspector__spacing' },
				el( 'strong', null, label ),
				[ 'top', 'right', 'bottom', 'left' ].map( function ( side ) {
					return el( TextControl, { key: side, label: side.charAt( 0 ).toUpperCase() + side.slice( 1 ), value: current[ side ] || '', placeholder: '0px', onChange: function ( value ) { updateStyle( path.concat( side ), unit( value ) ); } } );
				} )
			);
		}

		function LayoutPanel() {
			var attrs = selected.attributes || {};
			return el( Fragment, null,
				Object.prototype.hasOwnProperty.call( attrs, 'align' ) && el( SelectControl, { label: __( 'Alignment', 'cresco-canvas' ), value: attrs.align || '', options: [ { label: __( 'Default', 'cresco-canvas' ), value: '' }, { label: __( 'Wide', 'cresco-canvas' ), value: 'wide' }, { label: __( 'Full width', 'cresco-canvas' ), value: 'full' }, { label: __( 'Left', 'cresco-canvas' ), value: 'left' }, { label: __( 'Center', 'cresco-canvas' ), value: 'center' }, { label: __( 'Right', 'cresco-canvas' ), value: 'right' } ], onChange: function ( value ) { update( { align: value || undefined } ); } } ),
				el( TextControl, { label: __( 'Width', 'cresco-canvas' ), value: getPath( attrs.style || {}, [ 'dimensions', 'width' ], '' ), placeholder: '100% / 640px', onChange: function ( value ) { updateStyle( [ 'dimensions', 'width' ], unit( value ) ); } } ),
				el( TextControl, { label: __( 'Minimum height', 'cresco-canvas' ), value: getPath( attrs.style || {}, [ 'dimensions', 'minHeight' ], '' ), placeholder: '320px', onChange: function ( value ) { updateStyle( [ 'dimensions', 'minHeight' ], unit( value ) ); } } ),
				SpacingControl( __( 'Margin', 'cresco-canvas' ), [ 'spacing', 'margin' ] ),
				SpacingControl( __( 'Padding', 'cresco-canvas' ), [ 'spacing', 'padding' ] ),
				el( Button, { variant: 'secondary', onClick: function () { resetStyleGroup( 'spacing' ); } }, __( 'Reset spacing', 'cresco-canvas' ) )
			);
		}

		function StylePanel() {
			var attrs = selected.attributes || {};
			return el( Fragment, null,
				el( 'label', { className: 'cc-widget-inspector__label' }, __( 'Text color', 'cresco-canvas' ) ),
				el( ColorPalette, { value: getPath( attrs.style || {}, [ 'color', 'text' ], undefined ), onChange: function ( value ) { updateStyle( [ 'color', 'text' ], value ); }, clearable: true } ),
				el( 'label', { className: 'cc-widget-inspector__label' }, __( 'Background color', 'cresco-canvas' ) ),
				el( ColorPalette, { value: getPath( attrs.style || {}, [ 'color', 'background' ], undefined ), onChange: function ( value ) { updateStyle( [ 'color', 'background' ], value ); }, clearable: true } ),
				el( TextControl, { label: __( 'Border radius', 'cresco-canvas' ), value: getPath( attrs.style || {}, [ 'border', 'radius' ], '' ), placeholder: '8px', onChange: function ( value ) { updateStyle( [ 'border', 'radius' ], unit( value ) ); } } ),
				el( TextControl, { label: __( 'Font size', 'cresco-canvas' ), value: getPath( attrs.style || {}, [ 'typography', 'fontSize' ], '' ), placeholder: '18px', onChange: function ( value ) { updateStyle( [ 'typography', 'fontSize' ], unit( value ) ); } } ),
				el( TextControl, { label: __( 'Line height', 'cresco-canvas' ), value: getPath( attrs.style || {}, [ 'typography', 'lineHeight' ], '' ), placeholder: '1.5', onChange: function ( value ) { updateStyle( [ 'typography', 'lineHeight' ], value ); } } )
			);
		}

		function ResponsivePanel() {
			var className = selected.attributes.className || '';
			function toggleClass( token, enabled ) {
				var list = className.split( /\s+/ ).filter( Boolean ).filter( function ( item ) { return item !== token; } );
				if ( enabled ) list.push( token );
				update( { className: list.join( ' ' ) } );
			}
			return el( Fragment, null,
				el( Notice, { status: 'info', isDismissible: false }, __( 'Responsive visibility is applied with Cresco utility classes and remains editable in Gutenberg.', 'cresco-canvas' ) ),
				el( ToggleControl, { label: __( 'Hide on desktop', 'cresco-canvas' ), checked: className.indexOf( 'cresco-hide-desktop' ) !== -1, onChange: function ( value ) { toggleClass( 'cresco-hide-desktop', value ); } } ),
				el( ToggleControl, { label: __( 'Hide on tablet', 'cresco-canvas' ), checked: className.indexOf( 'cresco-hide-tablet' ) !== -1, onChange: function ( value ) { toggleClass( 'cresco-hide-tablet', value ); } } ),
				el( ToggleControl, { label: __( 'Hide on mobile', 'cresco-canvas' ), checked: className.indexOf( 'cresco-hide-mobile' ) !== -1, onChange: function ( value ) { toggleClass( 'cresco-hide-mobile', value ); } } )
			);
		}

		function EffectsPanel() {
			var attrs = selected.attributes || {};
			return el( Fragment, null,
				el( RangeControl, { label: __( 'Opacity', 'cresco-canvas' ), min: 0, max: 100, value: Math.round( 100 * parseFloat( getPath( attrs.style || {}, [ 'effects', 'opacity' ], 1 ) || 1 ) ), onChange: function ( value ) { updateStyle( [ 'effects', 'opacity' ], value / 100 ); } } ),
				el( TextControl, { label: __( 'CSS transform', 'cresco-canvas' ), value: getPath( attrs.style || {}, [ 'effects', 'transform' ], '' ), placeholder: 'translateY(-4px)', onChange: function ( value ) { updateStyle( [ 'effects', 'transform' ], value ); } } ),
				el( TextControl, { label: __( 'Box shadow', 'cresco-canvas' ), value: getPath( attrs.style || {}, [ 'effects', 'boxShadow' ], '' ), placeholder: '0 8px 24px rgba(0,0,0,.12)', onChange: function ( value ) { updateStyle( [ 'effects', 'boxShadow' ], value ); } } )
			);
		}

		function AdvancedPanel() {
			var attrs = selected.attributes || {};
			return el( Fragment, null,
				el( TextControl, { label: __( 'HTML anchor', 'cresco-canvas' ), value: attrs.anchor || '', onChange: function ( value ) { update( { anchor: value.replace( /[^a-zA-Z0-9\-_:.]/g, '' ) } ); } } ),
				el( TextControl, { label: __( 'Additional CSS classes', 'cresco-canvas' ), value: attrs.className || '', onChange: function ( value ) { update( { className: value } ); } } ),
				el( SelectControl, { label: __( 'Position', 'cresco-canvas' ), value: getPath( attrs.style || {}, [ 'position', 'type' ], 'static' ), options: [ 'static', 'relative', 'absolute', 'fixed', 'sticky' ].map( function ( value ) { return { label: value.charAt( 0 ).toUpperCase() + value.slice( 1 ), value: value }; } ), onChange: function ( value ) { updateStyle( [ 'position', 'type' ], value ); } } ),
				el( TextControl, { label: __( 'Top', 'cresco-canvas' ), value: getPath( attrs.style || {}, [ 'position', 'top' ], '' ), onChange: function ( value ) { updateStyle( [ 'position', 'top' ], unit( value ) ); } } ),
				el( TextControl, { label: __( 'Right', 'cresco-canvas' ), value: getPath( attrs.style || {}, [ 'position', 'right' ], '' ), onChange: function ( value ) { updateStyle( [ 'position', 'right' ], unit( value ) ); } } ),
				el( TextControl, { label: __( 'Bottom', 'cresco-canvas' ), value: getPath( attrs.style || {}, [ 'position', 'bottom' ], '' ), onChange: function ( value ) { updateStyle( [ 'position', 'bottom' ], unit( value ) ); } } ),
				el( TextControl, { label: __( 'Left', 'cresco-canvas' ), value: getPath( attrs.style || {}, [ 'position', 'left' ], '' ), onChange: function ( value ) { updateStyle( [ 'position', 'left' ], unit( value ) ); } } ),
				el( TextControl, { label: __( 'Z-index', 'cresco-canvas' ), value: String( getPath( attrs.style || {}, [ 'position', 'zIndex' ], '' ) ), onChange: function ( value ) { updateStyle( [ 'position', 'zIndex' ], value ); } } )
			);
		}

		var title = blockType && blockType.title ? blockType.title : selected ? selected.name : __( 'Widget settings', 'cresco-canvas' );
		return el( Fragment, null,
			el( PluginSidebarMoreMenuItem, { target: 'cresco-canvas-widget-inspector' }, __( 'Widget settings', 'cresco-canvas' ) ),
			el( PluginSidebar, { name: 'cresco-canvas-widget-inspector', title: __( 'Widget settings', 'cresco-canvas' ), icon: 'admin-generic', className: 'cresco-canvas-widget-inspector cresco-canvas-sidebar' },
				selected ? el( Fragment, null,
					el( 'header', { className: 'cc-widget-inspector__header' },
						el( 'span', { className: 'cc-widget-inspector__eyebrow' }, __( 'Selected widget', 'cresco-canvas' ) ),
						el( 'h2', null, title ),
						el( 'code', null, selected.name )
					),
					el( TabPanel, { className: 'cc-widget-inspector__tabs', activeClass: 'is-active', tabs: [
						{ name: 'content', title: __( 'Content', 'cresco-canvas' ) },
						{ name: 'layout', title: __( 'Layout', 'cresco-canvas' ) },
						{ name: 'style', title: __( 'Style', 'cresco-canvas' ) },
						{ name: 'responsive', title: __( 'Responsive', 'cresco-canvas' ) },
						{ name: 'effects', title: __( 'Effects', 'cresco-canvas' ) },
						{ name: 'advanced', title: __( 'Advanced', 'cresco-canvas' ) }
					] }, function ( tab ) {
						var content = tab.name === 'content' ? ContentPanel() : tab.name === 'layout' ? LayoutPanel() : tab.name === 'style' ? StylePanel() : tab.name === 'responsive' ? ResponsivePanel() : tab.name === 'effects' ? EffectsPanel() : AdvancedPanel();
						return el( 'div', { className: 'cc-widget-inspector__panel' }, content );
					} )
				) : el( 'div', { className: 'cc-widget-inspector__empty' },
					el( 'span', { className: 'dashicons dashicons-admin-generic', 'aria-hidden': 'true' } ),
					el( 'h2', null, __( 'Select a widget', 'cresco-canvas' ) ),
					el( 'p', null, __( 'Click any block on the canvas to edit its content, layout, style, responsive behavior and advanced settings here.', 'cresco-canvas' ) )
				)
			)
		);
	}

	registerPlugin( 'cresco-canvas-widget-inspector', { icon: 'admin-generic', render: Inspector } );
} )( window.wp );
