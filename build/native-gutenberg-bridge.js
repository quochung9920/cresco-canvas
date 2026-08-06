( function ( wp ) {
	'use strict';
	if ( ! wp || ! wp.hooks || ! wp.compose || ! wp.element || ! wp.blocks ) return;

	var addFilter = wp.hooks.addFilter;
	var createHigherOrderComponent = wp.compose.createHigherOrderComponent;
	var createElement = wp.element.createElement;
	var useEffect = wp.element.useEffect;
	var getBlockSupport = wp.blocks.getBlockSupport;
	var INSPECTOR_SIDEBAR = 'cresco-canvas-widget-inspector/cresco-canvas-widget-inspector';
	var EDITOR_STORES = [ 'core/edit-post', 'core/edit-site' ];
	var MAX_INSPECTOR_RETRIES = 30;
	var INSPECTOR_RETRY_DELAY = 75;
	var lastSelectedClientId = null;
	var scheduledClientId = null;
	var retryAttempts = 0;
	var retryTimer = null;

	function clone( value ) {
		return value && typeof value === 'object' ? JSON.parse( JSON.stringify( value ) ) : {};
	}

	function getPath( object, path ) {
		var current = object;
		for ( var i = 0; i < path.length; i++ ) {
			if ( ! current || typeof current !== 'object' || ! Object.prototype.hasOwnProperty.call( current, path[ i ] ) ) return undefined;
			current = current[ path[ i ] ];
		}
		return current;
	}

	function setPath( object, path, value ) {
		var next = clone( object );
		var cursor = next;
		for ( var i = 0; i < path.length - 1; i++ ) {
			if ( ! cursor[ path[ i ] ] || typeof cursor[ path[ i ] ] !== 'object' ) cursor[ path[ i ] ] = {};
			cursor = cursor[ path[ i ] ];
		}
		cursor[ path[ path.length - 1 ] ] = value;
		return next;
	}

	function equal( left, right ) {
		return JSON.stringify( left ) === JSON.stringify( right );
	}

	var mappings = [
		{ support: 'spacing.margin', managed: [ 'spacing', 'margin' ], native: [ 'spacing', 'margin' ] },
		{ support: 'spacing.padding', managed: [ 'spacing', 'padding' ], native: [ 'spacing', 'padding' ] },
		{ support: 'color.text', managed: [ 'color', 'text' ], native: [ 'color', 'text' ] },
		{ support: 'color.background', managed: [ 'color', 'background' ], native: [ 'color', 'background' ] },
		{ support: 'typography.fontSize', managed: [ 'typography', 'fontSize' ], native: [ 'typography', 'fontSize' ] },
		{ support: 'typography.lineHeight', managed: [ 'typography', 'lineHeight' ], native: [ 'typography', 'lineHeight' ] },
		{ support: 'border.radius', managed: [ 'border', 'radius' ], native: [ 'border', 'radius' ] },
		{ support: 'dimensions.minHeight', managed: [ 'dimensions', 'minHeight' ], native: [ 'dimensions', 'minHeight' ] }
	];

	function supports( blockName, path ) {
		var parts = path.split( '.' );
		var root = getBlockSupport( blockName, parts[ 0 ], false );
		if ( parts.length === 1 ) return !! root;
		for ( var i = 1; i < parts.length; i++ ) {
			if ( root === true ) return true;
			if ( ! root || typeof root !== 'object' ) return false;
			root = root[ parts[ i ] ];
		}
		return !! root;
	}

	addFilter( 'editor.BlockEdit', 'cresco-canvas/native-capability-bridge', createHigherOrderComponent( function ( BlockEdit ) {
		return function ( props ) {
			var attributes = props.attributes || {};
			var metadata = clone( attributes.metadata || {} );
			var managed = clone( metadata.crescoStyle || attributes.crescoStyle || {} );
			var nativeStyle = clone( attributes.style || {} );

			useEffect( function () {
				if ( ! props.isSelected || ! props.setAttributes ) return;
				var nextNative = clone( nativeStyle );
				var nextManaged = clone( managed );
				var nativeChanged = false;
				var managedChanged = false;

				mappings.forEach( function ( item ) {
					if ( ! supports( props.name, item.support ) ) return;
					var managedValue = getPath( nextManaged, item.managed );
					var nativeValue = getPath( nextNative, item.native );
					if ( managedValue !== undefined && ! equal( managedValue, nativeValue ) ) {
						nextNative = setPath( nextNative, item.native, managedValue );
						nativeChanged = true;
					}
					if ( managedValue === undefined && nativeValue !== undefined ) {
						nextManaged = setPath( nextManaged, item.managed, nativeValue );
						managedChanged = true;
					}
				} );

				var patch = {};
				if ( nativeChanged ) patch.style = nextNative;
				if ( managedChanged ) {
					metadata.crescoStyle = nextManaged;
					metadata.crescoStyleVersion = 1;
					patch.metadata = metadata;
				}
				if ( Object.keys( patch ).length ) props.setAttributes( patch );
			}, [ props.isSelected, props.name, JSON.stringify( managed ), JSON.stringify( nativeStyle ) ] );

			return createElement( BlockEdit, props );
		};
	}, 'withCrescoNativeCapabilityBridge' ) );

	function getSelectedClientId() {
		if ( ! wp.data || ! wp.data.select ) return null;
		try {
			var blockEditor = wp.data.select( 'core/block-editor' );
			return blockEditor && typeof blockEditor.getSelectedBlockClientId === 'function' ? blockEditor.getSelectedBlockClientId() : null;
		} catch ( error ) {
			return null;
		}
	}

	function getActiveSidebarName() {
		for ( var i = 0; i < EDITOR_STORES.length; i++ ) {
			try {
				var editor = wp.data.select( EDITOR_STORES[ i ] );
				if ( editor && typeof editor.getActiveGeneralSidebarName === 'function' ) {
					return editor.getActiveGeneralSidebarName() || '';
				}
				if ( editor && typeof editor.getActiveComplementaryArea === 'function' ) {
					return editor.getActiveComplementaryArea() || '';
				}
			} catch ( error ) {
				// This store is not registered in the current editor.
			}
		}
		return '';
	}

	function inspectorIsActive() {
		return String( getActiveSidebarName() ).indexOf( 'cresco-canvas-widget-inspector' ) !== -1;
	}

	function openInspector() {
		for ( var i = 0; i < EDITOR_STORES.length; i++ ) {
			try {
				var actions = wp.data.dispatch( EDITOR_STORES[ i ] );
				if ( actions && typeof actions.openGeneralSidebar === 'function' ) {
					actions.openGeneralSidebar( INSPECTOR_SIDEBAR );
					return true;
				}
				if ( actions && typeof actions.enableComplementaryArea === 'function' ) {
					actions.enableComplementaryArea( 'core', INSPECTOR_SIDEBAR );
					return true;
				}
			} catch ( error ) {
				// This store is not registered in the current editor.
			}
		}
		return false;
	}

	function clearInspectorRetry() {
		if ( retryTimer ) window.clearTimeout( retryTimer );
		retryTimer = null;
		scheduledClientId = null;
		retryAttempts = 0;
	}

	function confirmInspectorForSelection( clientId ) {
		if ( getSelectedClientId() !== clientId ) {
			clearInspectorRetry();
			return;
		}

		if ( inspectorIsActive() ) {
			lastSelectedClientId = clientId;
			clearInspectorRetry();
			markEditorState();
			return;
		}

		openInspector();
		retryAttempts += 1;
		if ( retryAttempts >= MAX_INSPECTOR_RETRIES ) {
			clearInspectorRetry();
			return;
		}

		retryTimer = window.setTimeout( function () {
			confirmInspectorForSelection( clientId );
		}, INSPECTOR_RETRY_DELAY );
	}

	function openInspectorForSelection() {
		var clientId = getSelectedClientId();
		if ( ! clientId ) {
			lastSelectedClientId = null;
			clearInspectorRetry();
			return;
		}

		if ( clientId === lastSelectedClientId ) return;
		if ( clientId === scheduledClientId && retryTimer ) return;

		clearInspectorRetry();
		scheduledClientId = clientId;
		confirmInspectorForSelection( clientId );
	}

	function markEditorState() {
		var body = document.body;
		if ( ! body ) return;
		body.classList.toggle( 'cresco-native-inspector-active', inspectorIsActive() );
	}

	function handleEditorChange() {
		markEditorState();
		openInspectorForSelection();
	}

	if ( wp.data && wp.data.subscribe ) wp.data.subscribe( handleEditorChange );
	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', handleEditorChange );
	else handleEditorChange();
} )( window.wp );