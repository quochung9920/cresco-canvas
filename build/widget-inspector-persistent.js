( function ( wp, window ) {
	'use strict';

	var Cresco = window.CrescoCanvas;
	if ( ! wp || ! wp.element || ! wp.data || ! wp.components || ! wp.blocks || ! wp.i18n || ! Cresco || ! Cresco.ui || ! Cresco.responsive ) return;

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
	var __ = wp.i18n.__;

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

	function deletePath( object, path ) {
		var next = clone( object );
		var current = next;
		for ( var index = 0; index < path.length - 1; index += 1 ) {
			if ( ! current || typeof current !== 'object' ) return next;
			current = current[ path[ index ] ];
		}
		if ( current && typeof current === 'object' ) delete current[ path[ path.length - 1 ] ];
		return next;
	}

	function normalizeUnit( value ) {
		value = String( value == null ? '' : value ).trim();
		if ( ! value ) return undefined;
		return /^-?\d+(\.\d+)?$/.test( value ) ? value + 'px' : value;
	}

	function richTextToHTML( value ) {
		if ( value == null ) return '';
		if ( typeof value === 'string' ) return value;
		try {
			if ( wp.richText && typeof wp.richText.toHTMLString === 'function' ) return wp.richText.toHTMLString( { value: value } );
		} catch ( error ) {}
		if ( value && typeof value.originalHTML === 'string' ) return value.originalHTML;
		return '';
	}

	function richTextFromHTML( value ) {
		try {
			if ( wp.richText && typeof wp.richText.create === 'function' ) return wp.richText.create( { html: String( value || '' ) } );
		} catch ( error ) {}
		return String( value || '' );
	}

	function isRichTextAttribute( blockType, key, value ) {
		var schema = blockType && blockType.attributes ? blockType.attributes[ key ] : null;
		if ( schema && ( schema.type === 'rich-text' || schema.source === 'rich-text' ) ) return true;
		return Boolean( value && typeof value === 'object' && ( value.originalHTML !== undefined || value.formats !== undefined ) );
	}

	function labelForBlock( block ) {
		if ( ! block ) return __( 'Widget', 'cresco-canvas' );
		var metadata = block.attributes && block.attributes.metadata;
		if ( metadata && metadata.name ) return String( metadata.name );
		var type = getBlockType( block.name );
		return type && type.title ? String( type.title ) : String( block.name || __( 'Widget', 'cresco-canvas' ) ).replace( /^[^/]+\//, '' );
	}

	function DeviceSwitcher( props ) {
		var devices = [
			{ id: 'wide', label: __( 'Wide', 'cresco-canvas' ) },
			{ id: 'desktop', label: __( 'Desktop', 'cresco-canvas' ) },
			{ id: 'laptop', label: __( 'Laptop', 'cresco-canvas' ) },
			{ id: 'tablet', label: __( 'Tablet', 'cresco-canvas' ) },
			{ id: 'mobile', label: __( 'Mobile', 'cresco-canvas' ) }
		];
		return el( 'div', { className: 'cc-inspector-device-switcher', role: 'group', 'aria-label': __( 'Responsive device', 'cresco-canvas' ) },
			devices.map( function ( item ) {
				return el( 'button', {
					key: item.id,
					type: 'button',
					className: props.device === item.id ? 'is-active' : '',
					onClick: function () { props.onChange( item.id ); },
					'aria-pressed': props.device === item.id
				}, item.label );
			} )
		);
	}

	function PersistentInspector() {
		var tabPair = useState( 'content' );
		var activeTab = tabPair[ 0 ];
		var setActiveTab = tabPair[ 1 ];
		var uiPair = useState( Cresco.ui.getState() );
		var uiState = uiPair[ 0 ];
		var setUiState = uiPair[ 1 ];
		var editorState = useSelect( function ( select ) {
			var editor = select( 'core/block-editor' );
			var clientId = editor && editor.getSelectedBlockClientId ? editor.getSelectedBlockClientId() : null;
			return {
				block: clientId && editor.getBlock ? editor.getBlock( clientId ) : null,
				parents: clientId && editor.getBlockParents ? editor.getBlockParents( clientId ) : []
			};
		}, [] );
		var dispatch = useDispatch( 'core/block-editor' );
		var selected = editorState.block;
		var device = uiState.device || 'wide';
		var title = useMemo( function () { return labelForBlock( selected ); }, [ selected && selected.name, selected && selected.clientId ] );

		useEffect( function () { return Cresco.ui.subscribe( setUiState ); }, [] );
		useEffect( function () { if ( selected ) setActiveTab( 'content' ); }, [ selected && selected.clientId ] );

		function update( patch ) {
			if ( selected && dispatch && dispatch.updateBlockAttributes ) dispatch.updateBlockAttributes( selected.clientId, patch );
		}

		function updateManaged( path, value ) {
			if ( ! selected ) return;
			var attributes = selected.attributes || {};
			var style = Cresco.responsive.getManagedStyle( attributes );
			style = Cresco.responsive.setValue( style, device, path, value );
			var metadata = clone( attributes.metadata || {} );
			metadata.crescoStyle = style;
			metadata.crescoStyleVersion = 2;
			var patch = { metadata: metadata };
			if ( Object.prototype.hasOwnProperty.call( attributes, 'crescoStyle' ) ) {
				patch.crescoStyle = style;
				patch.crescoStyleVersion = 2;
			}
			update( patch );
		}

		function styleValue( style, path, fallback ) {
			return Cresco.responsive.getValue( style, device, path, fallback );
		}

		function resetGroup( group ) {
			if ( ! selected ) return;
			var attributes = selected.attributes || {};
			var style = Cresco.responsive.getManagedStyle( attributes );
			style = device === 'wide' ? deletePath( style, [ group ] ) : deletePath( style, [ 'responsive', device, group ] );
			var metadata = clone( attributes.metadata || {} );
			metadata.crescoStyle = style;
			metadata.crescoStyleVersion = 2;
			update( { metadata: metadata } );
		}

		function duplicateSelected() {
			if ( selected ) Cresco.adapter.duplicateBlocks( [ selected.clientId ] );
		}

		function removeSelected() {
			if ( selected ) Cresco.adapter.removeBlocks( [ selected.clientId ] );
		}

		if ( ! selected ) {
			return el( 'div', { className: 'cc-persistent-inspector__empty' },
				el( 'span', { className: 'dashicons dashicons-edit', 'aria-hidden': 'true' } ),
				el( 'strong', null, __( 'Select a widget', 'cresco-canvas' ) ),
				el( 'p', null, __( 'Click any widget on the canvas or in Structure to edit it here.', 'cresco-canvas' ) )
			);
		}

		var attributes = selected.attributes || {};
		var style = Cresco.responsive.getManagedStyle( attributes );
		var blockType = getBlockType( selected.name );
		var supports = blockType && blockType.supports ? blockType.supports : {};

		function fieldExists( key ) {
			return Object.prototype.hasOwnProperty.call( attributes, key );
		}

		function section( titleText, children, actions ) {
			return el( 'section', { className: 'cc-persistent-inspector__group' },
				el( 'div', { className: 'cc-persistent-inspector__group-heading' },
					el( 'div', { className: 'cc-persistent-inspector__group-title' }, titleText ),
					actions || null
				),
				children
			);
		}

		function spacingControl( label, path ) {
			var current = styleValue( style, path, {} ) || {};
			return section( label,
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
				),
				el( Button, { size: 'small', variant: 'tertiary', onClick: function () { resetGroup( path[ 0 ] ); } }, __( 'Reset', 'cresco-canvas' ) )
			);
		}

		function ContainerControls() {
			if ( selected.name !== 'cresco/container' ) return null;
			return section( __( 'Container layout', 'cresco-canvas' ), el( Fragment, null,
				el( SelectControl, { label: __( 'Direction', 'cresco-canvas' ), value: attributes.direction || 'column', options: [ 'column', 'row', 'column-reverse', 'row-reverse' ].map( function ( value ) { return { label: value, value: value }; } ), onChange: function ( value ) { update( { direction: value } ); } } ),
				el( SelectControl, { label: __( 'Justify content', 'cresco-canvas' ), value: attributes.justify || 'flex-start', options: [ 'flex-start', 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly' ].map( function ( value ) { return { label: value, value: value }; } ), onChange: function ( value ) { update( { justify: value } ); } } ),
				el( SelectControl, { label: __( 'Align items', 'cresco-canvas' ), value: attributes.align || 'stretch', options: [ 'stretch', 'flex-start', 'center', 'flex-end', 'baseline' ].map( function ( value ) { return { label: value, value: value }; } ), onChange: function ( value ) { update( { align: value } ); } } ),
				el( RangeControl, { label: __( 'Gap', 'cresco-canvas' ), min: 0, max: 160, value: Number( attributes.gap || 0 ), onChange: function ( value ) { update( { gap: value } ); } } ),
				el( TextControl, { label: __( 'Maximum width', 'cresco-canvas' ), value: String( attributes.maxWidth || '' ), placeholder: '1200', onChange: function ( value ) { update( { maxWidth: value === '' ? undefined : Number( value ) } ); } } )
			) );
		}

		function MediaControls() {
			if ( selected.name === 'core/image' ) {
				return section( __( 'Image', 'cresco-canvas' ), el( Fragment, null,
					fieldExists( 'alt' ) && el( TextControl, { label: __( 'Alternative text', 'cresco-canvas' ), value: String( attributes.alt || '' ), onChange: function ( value ) { update( { alt: value } ); } } ),
					fieldExists( 'caption' ) && el( TextareaControl, { label: __( 'Caption', 'cresco-canvas' ), value: richTextToHTML( attributes.caption ), onChange: function ( value ) { update( { caption: richTextFromHTML( value ) } ); } } ),
					fieldExists( 'aspectRatio' ) && el( TextControl, { label: __( 'Aspect ratio', 'cresco-canvas' ), value: String( attributes.aspectRatio || '' ), placeholder: '16/9', onChange: function ( value ) { update( { aspectRatio: value || undefined } ); } } ),
					fieldExists( 'scale' ) && el( SelectControl, { label: __( 'Scale', 'cresco-canvas' ), value: attributes.scale || 'cover', options: [ { label: __( 'Cover', 'cresco-canvas' ), value: 'cover' }, { label: __( 'Contain', 'cresco-canvas' ), value: 'contain' } ], onChange: function ( value ) { update( { scale: value } ); } } ),
					fieldExists( 'linkDestination' ) && el( SelectControl, { label: __( 'Link to', 'cresco-canvas' ), value: attributes.linkDestination || 'none', options: [ 'none', 'media', 'attachment', 'custom' ].map( function ( value ) { return { label: value, value: value }; } ), onChange: function ( value ) { update( { linkDestination: value } ); } } )
				) );
			}
			if ( selected.name === 'core/gallery' ) {
				return section( __( 'Gallery', 'cresco-canvas' ), el( Fragment, null,
					fieldExists( 'columns' ) && el( RangeControl, { label: __( 'Columns', 'cresco-canvas' ), min: 1, max: 8, value: Number( attributes.columns || 3 ), onChange: function ( value ) { update( { columns: value } ); } } ),
					fieldExists( 'imageCrop' ) && el( ToggleControl, { label: __( 'Crop images', 'cresco-canvas' ), checked: attributes.imageCrop !== false, onChange: function ( value ) { update( { imageCrop: value } ); } } ),
					fieldExists( 'linkTo' ) && el( SelectControl, { label: __( 'Link to', 'cresco-canvas' ), value: attributes.linkTo || 'none', options: [ 'none', 'media', 'attachment' ].map( function ( value ) { return { label: value, value: value }; } ), onChange: function ( value ) { update( { linkTo: value } ); } } )
				) );
			}
			return null;
		}

		function ContentTab() {
			var fields = [];
			if ( fieldExists( 'content' ) ) {
				var richContent = isRichTextAttribute( blockType, 'content', attributes.content );
				fields.push( el( TextareaControl, {
					key: 'content',
					label: __( 'Content', 'cresco-canvas' ),
					help: richContent ? __( 'Inline formatting is preserved as HTML. Direct editing on the canvas remains recommended.', 'cresco-canvas' ) : undefined,
					value: richContent ? richTextToHTML( attributes.content ) : String( attributes.content || '' ),
					onChange: function ( value ) { update( { content: richContent ? richTextFromHTML( value ) : value } ); }
				} ) );
			}
			if ( fieldExists( 'text' ) ) fields.push( el( TextControl, { key: 'text', label: __( 'Text', 'cresco-canvas' ), value: String( attributes.text || '' ), onChange: function ( value ) { update( { text: value } ); } } ) );
			if ( fieldExists( 'url' ) ) fields.push( el( TextControl, { key: 'url', label: __( 'Link URL', 'cresco-canvas' ), value: String( attributes.url || '' ), onChange: function ( value ) { update( { url: value } ); } } ) );
			if ( fieldExists( 'linkTarget' ) ) fields.push( el( ToggleControl, { key: 'linkTarget', label: __( 'Open in a new tab', 'cresco-canvas' ), checked: attributes.linkTarget === '_blank', onChange: function ( value ) { update( { linkTarget: value ? '_blank' : undefined } ); } } ) );
			if ( fieldExists( 'rel' ) ) fields.push( el( TextControl, { key: 'rel', label: __( 'Link relationship', 'cresco-canvas' ), value: String( attributes.rel || '' ), onChange: function ( value ) { update( { rel: value } ); } } ) );
			if ( fieldExists( 'level' ) ) fields.push( el( SelectControl, { key: 'level', label: __( 'Heading level', 'cresco-canvas' ), value: String( attributes.level || 2 ), options: [ 1, 2, 3, 4, 5, 6 ].map( function ( level ) { return { label: 'H' + level, value: String( level ) }; } ), onChange: function ( value ) { update( { level: parseInt( value, 10 ) } ); } } ) );
			if ( fieldExists( 'align' ) ) fields.push( el( SelectControl, { key: 'align', label: __( 'Alignment', 'cresco-canvas' ), value: attributes.align || '', options: [ { label: __( 'Default', 'cresco-canvas' ), value: '' }, { label: __( 'Wide', 'cresco-canvas' ), value: 'wide' }, { label: __( 'Full width', 'cresco-canvas' ), value: 'full' }, { label: __( 'Left', 'cresco-canvas' ), value: 'left' }, { label: __( 'Center', 'cresco-canvas' ), value: 'center' }, { label: __( 'Right', 'cresco-canvas' ), value: 'right' } ], onChange: function ( value ) { update( { align: value || undefined } ); } } ) );
			if ( selected.name === 'core/spacer' && fieldExists( 'height' ) ) fields.push( el( TextControl, { key: 'height', label: __( 'Height', 'cresco-canvas' ), value: String( attributes.height || '' ), onChange: function ( value ) { update( { height: normalizeUnit( value ) } ); } } ) );
			return el( Fragment, null,
				el( ContainerControls ),
				el( MediaControls ),
				fields.length ? section( __( 'Content', 'cresco-canvas' ), el( Fragment, null, fields ) ) : null,
				! fields.length && selected.name !== 'cresco/container' && selected.name !== 'core/image' && selected.name !== 'core/gallery' ? el( Notice, { status: 'info', isDismissible: false }, __( 'Edit this widget directly on the canvas. Style and advanced settings are available in the other tabs.', 'cresco-canvas' ) ) : null
			);
		}

		function StyleTab() {
			return el( Fragment, null,
				el( DeviceSwitcher, { device: device, onChange: function ( next ) { Cresco.ui.setState( { device: next } ); } } ),
				section( __( 'Size', 'cresco-canvas' ), el( Fragment, null,
					el( TextControl, { label: __( 'Width', 'cresco-canvas' ), value: styleValue( style, [ 'dimensions', 'width' ], '' ), placeholder: '100% or 640px', onChange: function ( value ) { updateManaged( [ 'dimensions', 'width' ], normalizeUnit( value ) ); } } ),
					el( TextControl, { label: __( 'Maximum width', 'cresco-canvas' ), value: styleValue( style, [ 'dimensions', 'maxWidth' ], '' ), placeholder: '1200px', onChange: function ( value ) { updateManaged( [ 'dimensions', 'maxWidth' ], normalizeUnit( value ) ); } } ),
					el( TextControl, { label: __( 'Minimum height', 'cresco-canvas' ), value: styleValue( style, [ 'dimensions', 'minHeight' ], '' ), placeholder: '320px', onChange: function ( value ) { updateManaged( [ 'dimensions', 'minHeight' ], normalizeUnit( value ) ); } } )
				), el( Button, { size: 'small', variant: 'tertiary', onClick: function () { resetGroup( 'dimensions' ); } }, __( 'Reset', 'cresco-canvas' ) ) ),
				spacingControl( __( 'Margin', 'cresco-canvas' ), [ 'spacing', 'margin' ] ),
				spacingControl( __( 'Padding', 'cresco-canvas' ), [ 'spacing', 'padding' ] ),
				section( __( 'Appearance', 'cresco-canvas' ), el( Fragment, null,
					el( TextControl, { label: __( 'Text color', 'cresco-canvas' ), value: styleValue( style, [ 'color', 'text' ], '' ), placeholder: '#101828', onChange: function ( value ) { updateManaged( [ 'color', 'text' ], value || undefined ); } } ),
					el( TextControl, { label: __( 'Background color', 'cresco-canvas' ), value: styleValue( style, [ 'color', 'background' ], '' ), placeholder: '#ffffff', onChange: function ( value ) { updateManaged( [ 'color', 'background' ], value || undefined ); } } ),
					el( TextControl, { label: __( 'Border radius', 'cresco-canvas' ), value: styleValue( style, [ 'border', 'radius' ], '' ), placeholder: '8px', onChange: function ( value ) { updateManaged( [ 'border', 'radius' ], normalizeUnit( value ) ); } } ),
					el( TextControl, { label: __( 'Font size', 'cresco-canvas' ), value: styleValue( style, [ 'typography', 'fontSize' ], '' ), placeholder: 'clamp(1rem, 2vw, 2rem)', onChange: function ( value ) { updateManaged( [ 'typography', 'fontSize' ], normalizeUnit( value ) ); } } ),
					el( TextControl, { label: __( 'Line height', 'cresco-canvas' ), value: styleValue( style, [ 'typography', 'lineHeight' ], '' ), placeholder: '1.5', onChange: function ( value ) { updateManaged( [ 'typography', 'lineHeight' ], value || undefined ); } } )
				), el( 'span', { className: 'cc-inspector-native-badge' }, supports.color || supports.typography || supports.spacing ? __( 'Native-first', 'cresco-canvas' ) : __( 'Cresco style', 'cresco-canvas' ) ) )
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
				el( DeviceSwitcher, { device: device, onChange: function ( next ) { Cresco.ui.setState( { device: next } ); } } ),
				section( __( 'Effects', 'cresco-canvas' ), el( Fragment, null,
					el( RangeControl, { label: __( 'Opacity', 'cresco-canvas' ), min: 0, max: 100, value: Math.round( 100 * parseFloat( styleValue( style, [ 'effects', 'opacity' ], 1 ) || 1 ) ), onChange: function ( value ) { updateManaged( [ 'effects', 'opacity' ], value / 100 ); } } ),
					el( TextControl, { label: __( 'Transform', 'cresco-canvas' ), value: styleValue( style, [ 'effects', 'transform' ], '' ), placeholder: 'translateY(-4px)', onChange: function ( value ) { updateManaged( [ 'effects', 'transform' ], value || undefined ); } } ),
					el( TextControl, { label: __( 'Box shadow', 'cresco-canvas' ), value: styleValue( style, [ 'effects', 'boxShadow' ], '' ), placeholder: '0 8px 24px rgba(0,0,0,.12)', onChange: function ( value ) { updateManaged( [ 'effects', 'boxShadow' ], value || undefined ); } } )
				), el( Button, { size: 'small', variant: 'tertiary', onClick: function () { resetGroup( 'effects' ); } }, __( 'Reset', 'cresco-canvas' ) ) ),
				section( __( 'Responsive visibility', 'cresco-canvas' ), el( Fragment, null,
					el( ToggleControl, { label: __( 'Hide on desktop and wider', 'cresco-canvas' ), checked: className.indexOf( 'cresco-hide-desktop' ) !== -1, onChange: function ( value ) { toggleClass( 'cresco-hide-desktop', value ); } } ),
					el( ToggleControl, { label: __( 'Hide on tablet', 'cresco-canvas' ), checked: className.indexOf( 'cresco-hide-tablet' ) !== -1, onChange: function ( value ) { toggleClass( 'cresco-hide-tablet', value ); } } ),
					el( ToggleControl, { label: __( 'Hide on mobile', 'cresco-canvas' ), checked: className.indexOf( 'cresco-hide-mobile' ) !== -1, onChange: function ( value ) { toggleClass( 'cresco-hide-mobile', value ); } } )
				) ),
				section( __( 'Position', 'cresco-canvas' ), el( Fragment, null,
					el( SelectControl, { label: __( 'Position', 'cresco-canvas' ), value: styleValue( style, [ 'position', 'type' ], 'static' ), options: [ 'static', 'relative', 'absolute', 'fixed', 'sticky' ].map( function ( value ) { return { label: value.charAt( 0 ).toUpperCase() + value.slice( 1 ), value: value }; } ), onChange: function ( value ) { updateManaged( [ 'position', 'type' ], value ); } } ),
					[ 'top', 'right', 'bottom', 'left' ].map( function ( side ) { return el( TextControl, { key: side, label: side.charAt( 0 ).toUpperCase() + side.slice( 1 ), value: styleValue( style, [ 'position', side ], '' ), onChange: function ( value ) { updateManaged( [ 'position', side ], normalizeUnit( value ) ); } } ); } ),
					el( TextControl, { label: __( 'Z-index', 'cresco-canvas' ), value: String( styleValue( style, [ 'position', 'zIndex' ], '' ) ), onChange: function ( value ) { updateManaged( [ 'position', 'zIndex' ], value || undefined ); } } ),
					el( SelectControl, { label: __( 'Overflow', 'cresco-canvas' ), value: styleValue( style, [ 'position', 'overflow' ], 'visible' ), options: [ 'visible', 'hidden', 'clip', 'auto', 'scroll' ].map( function ( value ) { return { label: value.charAt( 0 ).toUpperCase() + value.slice( 1 ), value: value }; } ), onChange: function ( value ) { updateManaged( [ 'position', 'overflow' ], value ); } } )
				), el( Button, { size: 'small', variant: 'tertiary', onClick: function () { resetGroup( 'position' ); } }, __( 'Reset', 'cresco-canvas' ) ) ),
				section( __( 'HTML', 'cresco-canvas' ), el( Fragment, null,
					el( TextControl, { label: __( 'HTML anchor', 'cresco-canvas' ), value: attributes.anchor || '', onChange: function ( value ) { update( { anchor: String( value ).replace( /[^a-zA-Z0-9\-_:.]/g, '' ) } ); } } ),
					el( TextControl, { label: __( 'Additional CSS classes', 'cresco-canvas' ), value: attributes.className || '', onChange: function ( value ) { update( { className: value } ); } } )
				) )
			);
		}

		var tabs = [
			{ id: 'content', label: __( 'Content', 'cresco-canvas' ) },
			{ id: 'style', label: __( 'Style', 'cresco-canvas' ) },
			{ id: 'advanced', label: __( 'Advanced', 'cresco-canvas' ) }
		];

		return el( Fragment, null,
			el( 'header', { className: 'cc-persistent-inspector__header' },
				el( 'div', { className: 'cc-persistent-inspector__title' },
					el( 'span', null, __( 'Edit widget', 'cresco-canvas' ) ),
					el( 'strong', null, title ),
					el( 'code', null, selected.name )
				),
				el( 'div', { className: 'cc-persistent-inspector__actions' },
					el( Button, { icon: 'admin-page', label: __( 'Duplicate widget', 'cresco-canvas' ), onClick: duplicateSelected } ),
					el( Button, { icon: 'trash', label: __( 'Delete widget', 'cresco-canvas' ), isDestructive: true, onClick: removeSelected } )
				)
			),
			el( 'nav', { className: 'cc-persistent-inspector__tabs', 'aria-label': __( 'Widget editing sections', 'cresco-canvas' ) },
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

	Cresco.ui.registerView( 'edit', PersistentInspector, { label: __( 'Edit', 'cresco-canvas' ), icon: 'edit' } );
} )( window.wp, window );
