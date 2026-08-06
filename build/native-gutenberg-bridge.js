( function ( wp ) {
	'use strict';
	if ( ! wp || ! wp.hooks || ! wp.compose || ! wp.element || ! wp.blocks ) return;

	var addFilter = wp.hooks.addFilter;
	var createHigherOrderComponent = wp.compose.createHigherOrderComponent;
	var createElement = wp.element.createElement;
	var useEffect = wp.element.useEffect;
	var getBlockSupport = wp.blocks.getBlockSupport;

	function clone( value ) {
		return value && typeof value === 'object' ? JSON.parse( JSON.stringify( value ) ) : {};
	}

	function getPath( object, path ) {
		var current = object;
		for ( var index = 0; index < path.length; index += 1 ) {
			if ( ! current || typeof current !== 'object' || ! Object.prototype.hasOwnProperty.call( current, path[ index ] ) ) return undefined;
			current = current[ path[ index ] ];
		}
		return current;
	}

	function setPath( object, path, value ) {
		var next = clone( object );
		var cursor = next;
		for ( var index = 0; index < path.length - 1; index += 1 ) {
			if ( ! cursor[ path[ index ] ] || typeof cursor[ path[ index ] ] !== 'object' ) cursor[ path[ index ] ] = {};
			cursor = cursor[ path[ index ] ];
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
		if ( parts.length === 1 ) return Boolean( root );
		for ( var index = 1; index < parts.length; index += 1 ) {
			if ( root === true ) return true;
			if ( ! root || typeof root !== 'object' ) return false;
			root = root[ parts[ index ] ];
		}
		return Boolean( root );
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
					metadata.crescoStyleVersion = 2;
					patch.metadata = metadata;
				}
				if ( Object.keys( patch ).length ) props.setAttributes( patch );
			}, [ props.isSelected, props.name, JSON.stringify( managed ), JSON.stringify( nativeStyle ) ] );

			return createElement( BlockEdit, props );
		};
	}, 'withCrescoNativeCapabilityBridge' ) );
} )( window.wp );
