( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.editor || ! wp.element || ! wp.components || ! wp.data || ! wp.blocks ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useEffect = wp.element.useEffect;
	var useMemo = wp.element.useMemo;
	var __ = wp.i18n.__;
	var registerPlugin = wp.plugins.registerPlugin;
	var PluginSidebar = wp.editor.PluginSidebar;
	var PluginSidebarMoreMenuItem = wp.editor.PluginSidebarMoreMenuItem;
	var TabPanel = wp.components.TabPanel;
	var PanelBody = wp.components.PanelBody;
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

	var INSPECTOR_SIDEBAR = 'cresco-canvas-widget-inspector/cresco-canvas-widget-inspector';
	var ELEMENTS_SIDEBAR = 'cresco-canvas-settings/cresco-canvas-settings';

	function clone( value ) {
		return value && typeof value === 'object' ? JSON.parse( JSON.stringify( value ) ) : {};
	}

	function getPath( object, path, fallback ) {
		var current = object;
		for ( var index = 0; index < path.length; index += 1 ) {
			if ( ! current || typeof current !== 'object' || ! Object.prototype.hasOwnProperty.call( current, path[ index ] ) ) {
				return fallback;
			}
			current = current[ path[ index ] ];
		}
		return current;
	}

	function setPath( object, path, value ) {
		var next = clone( object );
		var current = next;
		for ( var index = 0; index < path.length - 1; index += 1 ) {
			if ( ! current[ path[ index ] ] || typeof current[ path[ index ] ] !== 'object' ) {
				current[ path[ index ] ] = {};
			}
			current = current[ path[ index ] ];
		}
		if ( value === undefined || value === '' ) {
			delete current[ path[ path.length - 1 ] ];
		} else {
			current[ path[ path.length - 1 ] ] = value;
		}
		return next;
	}

	function unit( value ) {
		value = String( value == null ? '' : value ).trim();
		if ( ! value ) {
			return undefined;
		}
		return /^-?\d+(\.\d+)?$/.test( value ) ? value + 'px' : value;
	}

	function managedStyle( attributes ) {
		var metadata = attributes.metadata && typeof attributes.metadata === 'object' ? attributes.metadata : {};
		var managed = clone( metadata.crescoStyle || attributes.crescoStyle || {} );
		var legacy = attributes.style && typeof attributes.style === 'object' ? attributes.style : {};
		[ 'dimensions', 'spacing', 'color', 'border', 'typography', 'effects', 'position' ].forEach( function ( group ) {
			if ( ! managed[ group ] && legacy[ group ] && typeof legacy[ group ] === 'object' ) {
				managed[ group ] = clone( legacy[ group ] );
			}
		} );
		return managed;
	}

	function blockTitle( block ) {
		if ( ! block ) {
			return '';
		}
		var type = getBlockType( block.name );
		return type && type.title ? type.title : block.name;
	}

	function openGeneralSidebar( name ) {
		var stores = [ 'core/edit-post', 'core/edit-site' ];
		for ( var index = 0; index < stores.length; index += 1 ) {
			try {
				var actions = wp.data.dispatch( stores[ index ] );
				if ( actions && typeof actions.openGeneralSidebar === 'function' ) {
					actions.openGeneralSidebar( name );
					return true;
				}
			} catch ( error ) {
				// The store is not registered in this editor context.
			}
		}
		return false;
	}

	function Inspector() {
		var selection = useSelect( function ( select ) {
			var editor = select( 'core/block-editor' );
			var clientId = editor && editor.getSelectedBlockClientId ? editor.getSelectedBlockClientId() : null;
			var selected = clientId && editor.getBlock ? editor.getBlock( clientId ) : null;
			var parentIds = clientId && editor.getBlockParents ? editor.getBlockParents( clientId ) : [];
			var parents = parentIds && editor.getBlock ? parentIds.map( function ( id ) { return editor.getBlock( id ); } ).filter( Boolean ) : [];
			return {
				selected: selected,
				parents: parents,
			};
		}, [] );

		var selected = selection.selected;
		var parents = selection.parents;
		var dispatch = useDispatch( 'core/block-editor' );

		useEffect( function () {
			if ( selected ) {
				openGeneralSidebar( INSPECTOR_SIDEBAR );
			}
		}, [ selected && selected.clientId ] );

		var blockType = useMemo( function () {
			return selected ? getBlockType( selected.name ) : null;
		}, [ selected && selected.name ] );

		function update( patch ) {
			if ( selected && dispatch && dispatch.updateBlockAttributes ) {
				dispatch.updateBlockAttributes( selected.clientId, patch );
			}
		}

		function commitManagedStyle( style ) {
			var attributes = selected ? selected.attributes || {} : {};
			var metadata = clone( attributes.metadata || {} );
			metadata.crescoStyle = style;
			metadata.crescoStyleVersion = 1;
			update( {
				crescoStyle: style,
				crescoStyleVersion: 1,
				metadata: metadata,
			} );
		}

		function updateManaged( path, value ) {
			var style = setPath( managedStyle( selected.attributes || {} ), path, value );
			commitManagedStyle( style );
		}

		function resetGroups( groups ) {
			var style = managedStyle( selected.attributes || {} );
			groups.forEach( function ( group ) {
				delete style[ group ];
			} );
			commitManagedStyle( style );
		}

		function selectParent( clientId ) {
			if ( dispatch && dispatch.selectBlock ) {
				dispatch.selectBlock( clientId );
			}
		}

		function duplicateSelected() {
			if ( selected && dispatch && typeof dispatch.duplicateBlocks === 'function' ) {
				dispatch.duplicateBlocks( [ selected.clientId ] );
			}
		}

		function deleteSelected() {
			if ( selected && dispatch && typeof dispatch.removeBlock === 'function' ) {
				dispatch.removeBlock( selected.clientId, false );
			}
		}

		if ( ! selected ) {
			return el(
				'div',
				{ className: 'cc-widget-inspector cc-widget-inspector--empty' },
				el( 'span', { className: 'dashicons dashicons-admin-customizer', 'aria-hidden': 'true' } ),
				el( 'h2', null, __( 'Select a widget', 'cresco-canvas' ) ),
				el( 'p', null, __( 'Click any widget on the canvas. Its Content, Style and Advanced controls will open here.', 'cresco-canvas' ) ),
				el( Button, { variant: 'primary', onClick: function () { openGeneralSidebar( ELEMENTS_SIDEBAR ); } }, __( 'Open Elements', 'cresco-canvas' ) )
			);
		}

		var attributes = selected.attributes || {};
		var style = managedStyle( attributes );
		var title = blockType && blockType.title ? blockType.title : selected.name;

		function ContentControls() {
			var controls = [];
			if ( Object.prototype.hasOwnProperty.call( attributes, 'content' ) ) {
				controls.push( el( TextareaControl, { key: 'content', label: __( 'Content', 'cresco-canvas' ), value: attributes.content || '', onChange: function ( value ) { update( { content: value } ); } } ) );
			}
			if ( Object.prototype.hasOwnProperty.call( attributes, 'text' ) ) {
				controls.push( el( TextControl, { key: 'text', label: __( 'Text', 'cresco-canvas' ), value: attributes.text || '', onChange: function ( value ) { update( { text: value } ); } } ) );
			}
			if ( Object.prototype.hasOwnProperty.call( attributes, 'url' ) ) {
				controls.push( el( TextControl, { key: 'url', label: __( 'Link URL', 'cresco-canvas' ), value: attributes.url || '', onChange: function ( value ) { update( { url: value } ); } } ) );
			}
			if ( Object.prototype.hasOwnProperty.call( attributes, 'alt' ) ) {
				controls.push( el( TextControl, { key: 'alt', label: __( 'Alternative text', 'cresco-canvas' ), value: attributes.alt || '', onChange: function ( value ) { update( { alt: value } ); } } ) );
			}
			if ( Object.prototype.hasOwnProperty.call( attributes, 'level' ) ) {
				controls.push( el( SelectControl, { key: 'level', label: __( 'Heading level', 'cresco-canvas' ), value: String( attributes.level || 2 ), options: [ 1, 2, 3, 4, 5, 6 ].map( function ( level ) { return { label: 'H' + level, value: String( level ) }; } ), onChange: function ( value ) { update( { level: parseInt( value, 10 ) }; } } ) );
			}
			return controls.length ? controls : el( Notice, { status: 'info', isDismissible: false }, __( 'Edit this widget’s text directly on the canvas. Its design controls remain available here.', 'cresco-canvas' ) );
		}

		function LayoutControls() {
			return el(
				Fragment,
				null,
				Object.prototype.hasOwnProperty.call( attributes, 'align' ) && el( SelectControl, {
					label: __( 'Alignment', 'cresco-canvas' ),
					value: attributes.align || '',
					options: [
						{ label: __( 'Default', 'cresco-canvas' ), value: '' },
						{ label: __( 'Wide', 'cresco-canvas' ), value: 'wide' },
						{ label: __( 'Full width', 'cresco-canvas' ), value: 'full' },
						{ label: __( 'Left', 'cresco-canvas' ), value: 'left' },
						{ label: __( 'Center', 'cresco-canvas' ), value: 'center' },
						{ label: __( 'Right', 'cresco-canvas' ), value: 'right' },
					],
					onChange: function ( value ) { update( { align: value || undefined } ); },
				} ),
				el( TextControl, { label: __( 'Width', 'cresco-canvas' ), value: getPath( style, [ 'dimensions', 'width' ], '' ), placeholder: '100% / 640px', onChange: function ( value ) { updateManaged( [ 'dimensions', 'width' ], unit( value ) ); } } ),
				el( TextControl, { label: __( 'Maximum width', 'cresco-canvas' ), value: getPath( style, [ 'dimensions', 'maxWidth' ], '' ), placeholder: '1200px', onChange: function ( value ) { updateManaged( [ 'dimensions', 'maxWidth' ], unit( value ) ); } } ),
				el( TextControl, { label: __( 'Minimum height', 'cresco-canvas' ), value: getPath( style, [ 'dimensions', 'minHeight' ], '' ), placeholder: '320px', onChange: function ( value ) { updateManaged( [ 'dimensions', 'minHeight' ], unit( value ) ); } } ),
				el( Button, { className: 'cc-widget-inspector__reset', variant: 'tertiary', onClick: function () { resetGroups( [ 'dimensions' ] ); } }, __( 'Reset layout', 'cresco-canvas' ) )
			);
		}

		function SpacingControl( label, path ) {
			var current = getPath( style, path, {} ) || {};
			return el(
				'div',
				{ className: 'cc-widget-inspector__spacing' },
				el( 'strong', null, label ),
				[ 'top', 'right', 'bottom', 'left' ].map( function ( side ) {
					return el( TextControl, { key: side, label: side.charAt( 0 ).toUpperCase() + side.slice( 1 ), value: current[ side ] || '', placeholder: '0px', onChange: function ( value ) { updateManaged( path.concat( side ), unit( value ) ); } } );
				} )
			);
		}

		function ColorControls() {
			return el(
				Fragment,
				null,
				el( 'label', { className: 'cc-widget-inspector__label' }, __( 'Text color', 'cresco-canvas' ) ),
				el( ColorPalette, { value: getPath( style, [ 'color', 'text' ], undefined ), onChange: function ( value ) { updateManaged( [ 'color', 'text' ], value ); }, clearable: true } ),
				el( 'label', { className: 'cc-widget-inspector__label' }, __( 'Background color', 'cresco-canvas' ) ),
				el( ColorPalette, { value: getPath( style, [ 'color', 'background' ], undefined ), onChange: function ( value ) { updateManaged( [ 'color', 'background' ], value ); }, clearable: true } ),
				el( Button, { className: 'cc-widget-inspector__reset', variant: 'tertiary', onClick: function () { resetGroups( [ 'color' ] ); } }, __( 'Reset colors', 'cresco-canvas' ) )
			);
		}

		function TypographyControls() {
			return el(
				Fragment,
				null,
				el( TextControl, { label: __( 'Font size', 'cresco-canvas' ), value: getPath( style, [ 'typography', 'fontSize' ], '' ), placeholder: '18px', onChange: function ( value ) { updateManaged( [ 'typography', 'fontSize' ], unit( value ) ); } } ),
				el( TextControl, { label: __( 'Line height', 'cresco-canvas' ), value: getPath( style, [ 'typography', 'lineHeight' ], '' ), placeholder: '1.5', onChange: function ( value ) { updateManaged( [ 'typography', 'lineHeight' ], value ); } } ),
				el( Button, { className: 'cc-widget-inspector__reset', variant: 'tertiary', onClick: function () { resetGroups( [ 'typography' ] ); } }, __( 'Reset typography', 'cresco-canvas' ) )
			);
		}

		function BorderEffectsControls() {
			return el(
				Fragment,
				null,
				el( TextControl, { label: __( 'Border radius', 'cresco-canvas' ), value: getPath( style, [ 'border', 'radius' ], '' ), placeholder: '8px', onChange: function ( value ) { updateManaged( [ 'border', 'radius' ], unit( value ) ); } } ),
				el( RangeControl, { label: __( 'Opacity', 'cresco-canvas' ), min: 0, max: 100, value: Math.round( 100 * parseFloat( getPath( style, [ 'effects', 'opacity' ], 1 ) || 1 ) ), onChange: function ( value ) { updateManaged( [ 'effects', 'opacity' ], value / 100 ); } } ),
				el( TextControl, { label: __( 'Transform', 'cresco-canvas' ), value: getPath( style, [ 'effects', 'transform' ], '' ), placeholder: 'translateY(-4px)', onChange: function ( value ) { updateManaged( [ 'effects', 'transform' ], value ); } } ),
				el( TextControl, { label: __( 'Box shadow', 'cresco-canvas' ), value: getPath( style, [ 'effects', 'boxShadow' ], '' ), placeholder: '0 8px 24px rgba(0,0,0,.12)', onChange: function ( value ) { updateManaged( [ 'effects', 'boxShadow' ], value ); } } ),
				el( Button, { className: 'cc-widget-inspector__reset', variant: 'tertiary', onClick: function () { resetGroups( [ 'border', 'effects' ] ); } }, __( 'Reset border and effects', 'cresco-canvas' ) )
			);
		}

		function ResponsiveControls() {
			var className = attributes.className || '';
			function toggleClass( token, enabled ) {
				var list = className.split( /\s+/ ).filter( Boolean ).filter( function ( item ) { return item !== token; } );
				if ( enabled ) {
					list.push( token );
				}
				update( { className: list.join( ' ' ) } );
			}
			return el(
				Fragment,
				null,
				el( Notice, { status: 'info', isDismissible: false }, __( 'Visibility uses the Global device ranges configured in Cresco Canvas.', 'cresco-canvas' ) ),
				el( ToggleControl, { label: __( 'Hide on laptop, desktop and widescreen', 'cresco-canvas' ), checked: className.indexOf( 'cresco-hide-desktop' ) !== -1, onChange: function ( value ) { toggleClass( 'cresco-hide-desktop', value ); } } ),
				el( ToggleControl, { label: __( 'Hide on tablet', 'cresco-canvas' ), checked: className.indexOf( 'cresco-hide-tablet' ) !== -1, onChange: function ( value ) { toggleClass( 'cresco-hide-tablet', value ); } } ),
				el( ToggleControl, { label: __( 'Hide on mobile', 'cresco-canvas' ), checked: className.indexOf( 'cresco-hide-mobile' ) !== -1, onChange: function ( value ) { toggleClass( 'cresco-hide-mobile', value ); } } )
			);
		}

		function PositionControls() {
			return el(
				Fragment,
				null,
				el( SelectControl, { label: __( 'Position', 'cresco-canvas' ), value: getPath( style, [ 'position', 'type' ], 'static' ), options: [ 'static', 'relative', 'absolute', 'fixed', 'sticky' ].map( function ( value ) { return { label: value.charAt( 0 ).toUpperCase() + value.slice( 1 ), value: value }; } ), onChange: function ( value ) { updateManaged( [ 'position', 'type' ], value ); } } ),
				el( 'div', { className: 'cc-widget-inspector__offsets' }, [ 'top', 'right', 'bottom', 'left' ].map( function ( side ) { return el( TextControl, { key: side, label: side.charAt( 0 ).toUpperCase() + side.slice( 1 ), value: getPath( style, [ 'position', side ], '' ), placeholder: 'auto', onChange: function ( value ) { updateManaged( [ 'position', side ], unit( value ) ); } } ); } ) ),
				el( TextControl, { label: __( 'Z-index', 'cresco-canvas' ), value: String( getPath( style, [ 'position', 'zIndex' ], '' ) ), onChange: function ( value ) { updateManaged( [ 'position', 'zIndex' ], value ); } } ),
				el( SelectControl, { label: __( 'Overflow', 'cresco-canvas' ), value: getPath( style, [ 'position', 'overflow' ], 'visible' ), options: [ 'visible', 'hidden', 'clip', 'auto', 'scroll' ].map( function ( value ) { return { label: value.charAt( 0 ).toUpperCase() + value.slice( 1 ), value: value }; } ), onChange: function ( value ) { updateManaged( [ 'position', 'overflow' ], value ); } } ),
				el( Button, { className: 'cc-widget-inspector__reset', variant: 'tertiary', onClick: function () { resetGroups( [ 'position' ] ); } }, __( 'Reset position', 'cresco-canvas' ) )
			);
		}

		function AttributeControls() {
			return el(
				Fragment,
				null,
				el( TextControl, { label: __( 'HTML anchor', 'cresco-canvas' ), value: attributes.anchor || '', onChange: function ( value ) { update( { anchor: value.replace( /[^a-zA-Z0-9\-_:.]/g, '' ) } ); } } ),
				el( TextControl, { label: __( 'Additional CSS classes', 'cresco-canvas' ), value: attributes.className || '', onChange: function ( value ) { update( { className: value } ); } } )
			);
		}

		function ContentTab() {
			return el(
				'div',
				{ className: 'cc-widget-inspector__panel' },
				el( PanelBody, { title: __( 'Content', 'cresco-canvas' ), initialOpen: true }, el( ContentControls ) ),
				el( PanelBody, { title: __( 'Layout', 'cresco-canvas' ), initialOpen: true }, el( LayoutControls ) )
			);
		}

		function StyleTab() {
			return el(
				'div',
				{ className: 'cc-widget-inspector__panel' },
				el( PanelBody, { title: __( 'Colors', 'cresco-canvas' ), initialOpen: true }, el( ColorControls ) ),
				el( PanelBody, { title: __( 'Typography', 'cresco-canvas' ), initialOpen: false }, el( TypographyControls ) ),
				el( PanelBody, { title: __( 'Spacing', 'cresco-canvas' ), initialOpen: false }, SpacingControl( __( 'Margin', 'cresco-canvas' ), [ 'spacing', 'margin' ] ), SpacingControl( __( 'Padding', 'cresco-canvas' ), [ 'spacing', 'padding' ] ), el( Button, { className: 'cc-widget-inspector__reset', variant: 'tertiary', onClick: function () { resetGroups( [ 'spacing' ] ); } }, __( 'Reset spacing', 'cresco-canvas' ) ) ),
				el( PanelBody, { title: __( 'Border and effects', 'cresco-canvas' ), initialOpen: false }, el( BorderEffectsControls ) )
			);
		}

		function AdvancedTab() {
			return el(
				'div',
				{ className: 'cc-widget-inspector__panel' },
				el( PanelBody, { title: __( 'Responsive visibility', 'cresco-canvas' ), initialOpen: true }, el( ResponsiveControls ) ),
				el( PanelBody, { title: __( 'Positioning', 'cresco-canvas' ), initialOpen: false }, el( PositionControls ) ),
				el( PanelBody, { title: __( 'Attributes', 'cresco-canvas' ), initialOpen: false }, el( AttributeControls ) )
			);
		}

		var tabs = [
			{ name: 'content', title: __( 'Content', 'cresco-canvas' ) },
			{ name: 'style', title: __( 'Style', 'cresco-canvas' ) },
			{ name: 'advanced', title: __( 'Advanced', 'cresco-canvas' ) },
		];
		var panels = { content: ContentTab, style: StyleTab, advanced: AdvancedTab };

		return el(
			'div',
			{ className: 'cc-widget-inspector' },
			el(
				'header',
				{ className: 'cc-widget-inspector__header' },
				el( Button, { className: 'cc-widget-inspector__back', icon: 'arrow-left-alt2', label: __( 'Back to Elements', 'cresco-canvas' ), onClick: function () { openGeneralSidebar( ELEMENTS_SIDEBAR ); } } ),
				el( 'div', { className: 'cc-widget-inspector__identity' }, el( 'span', { className: 'cc-widget-inspector__eyebrow' }, __( 'Edit widget', 'cresco-canvas' ) ), el( 'h2', null, title ) ),
				el( 'div', { className: 'cc-widget-inspector__actions' },
					el( Button, { icon: 'admin-page', label: __( 'Duplicate widget', 'cresco-canvas' ), disabled: ! dispatch || typeof dispatch.duplicateBlocks !== 'function', onClick: duplicateSelected } ),
					el( Button, { className: 'is-destructive', icon: 'trash', label: __( 'Delete widget', 'cresco-canvas' ), onClick: deleteSelected } )
				)
			),
			parents.length ? el( 'nav', { className: 'cc-widget-inspector__breadcrumbs', 'aria-label': __( 'Widget hierarchy', 'cresco-canvas' ) }, parents.map( function ( parent ) { return el( Button, { key: parent.clientId, variant: 'link', onClick: function () { selectParent( parent.clientId ); } }, blockTitle( parent ) ); } ), el( 'span', { 'aria-current': 'page' }, title ) ) : null,
			el( 'div', { className: 'cc-widget-inspector__engine-status' }, el( 'span', { className: 'dashicons dashicons-yes-alt', 'aria-hidden': 'true' } ), el( 'span', null, __( 'Changes render live in the editor and frontend.', 'cresco-canvas' ) ) ),
			el( TabPanel, { className: 'cc-widget-inspector__tabs', activeClass: 'is-active', tabs: tabs }, function ( tab ) { var Panel = panels[ tab.name ]; return el( Panel ); } )
		);
	}

	registerPlugin( 'cresco-canvas-widget-inspector', {
		render: function () {
			return el(
				Fragment,
				null,
				el( PluginSidebarMoreMenuItem, { target: 'cresco-canvas-widget-inspector' }, __( 'Cresco Widget Settings', 'cresco-canvas' ) ),
				el( PluginSidebar, { className: 'cresco-canvas-widget-inspector', name: 'cresco-canvas-widget-inspector', title: __( 'Cresco Widget Settings', 'cresco-canvas' ), icon: 'admin-customizer' }, el( Inspector ) )
			);
		},
	} );
} )( window.wp );
