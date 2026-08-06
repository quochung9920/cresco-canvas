( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.data || ! wp.plugins ) return;

	var editPost = wp.editPost || {};
	var editor = wp.editor || {};
	var TARGETS = {
		widgets: 'cresco-canvas/cresco-canvas-settings',
		edit: 'cresco-canvas-widget-inspector/cresco-canvas-widget-inspector',
		global: 'cresco-canvas-design-system/cresco-canvas-design-system'
	};
	var INSPECTOR_PLUGIN = 'cresco-canvas-widget-inspector';
	var LEGACY_STORES = [ 'core/edit-post', 'core/edit-site' ];
	var INTERFACE_SCOPES = [ 'core/edit-post', 'core/edit-site' ];
	var attachedDocuments = [];
	var retryTimer = null;
	var retryCount = 0;
	var pendingTarget = '';
	var pendingClientId = null;
	var lastSelectedClientId = null;
	var MAX_RETRIES = 60;
	var RETRY_DELAY = 100;

	if ( ! wp.editor ) wp.editor = editor;
	if ( ! editor.PluginSidebar && editPost.PluginSidebar ) editor.PluginSidebar = editPost.PluginSidebar;
	if ( ! editor.PluginSidebarMoreMenuItem && editPost.PluginSidebarMoreMenuItem ) editor.PluginSidebarMoreMenuItem = editPost.PluginSidebarMoreMenuItem;

	function selectedClientId() {
		try {
			var store = wp.data.select( 'core/block-editor' );
			return store && typeof store.getSelectedBlockClientId === 'function' ? store.getSelectedBlockClientId() : null;
		} catch ( error ) {
			return null;
		}
	}

	function pluginReady( pluginName ) {
		try {
			if ( typeof wp.plugins.getPlugin === 'function' ) return Boolean( wp.plugins.getPlugin( pluginName ) );
			if ( typeof wp.plugins.getPlugins === 'function' ) {
				return wp.plugins.getPlugins().some( function ( plugin ) {
					return plugin && plugin.name === pluginName;
				} );
			}
		} catch ( error ) {}
		return false;
	}

	function activeSidebar() {
		try {
			var interfaceStore = wp.data.select( 'core/interface' );
			if ( interfaceStore && typeof interfaceStore.getActiveComplementaryArea === 'function' ) {
				for ( var scopeIndex = 0; scopeIndex < INTERFACE_SCOPES.length; scopeIndex += 1 ) {
					var area = interfaceStore.getActiveComplementaryArea( INTERFACE_SCOPES[ scopeIndex ] );
					if ( area ) return area;
				}
			}
		} catch ( error ) {}

		for ( var index = 0; index < LEGACY_STORES.length; index += 1 ) {
			try {
				var store = wp.data.select( LEGACY_STORES[ index ] );
				if ( store && typeof store.getActiveGeneralSidebarName === 'function' ) {
					var general = store.getActiveGeneralSidebarName();
					if ( general ) return general;
				}
			} catch ( error ) {}
		}
		return '';
	}

	function targetPluginName( target ) {
		return String( target || '' ).split( '/' )[ 0 ];
	}

	function targetIsActive( target ) {
		var active = String( activeSidebar() );
		return active === target || active.indexOf( targetPluginName( target ) ) !== -1;
	}

	function requestTarget( target ) {
		var requested = false;

		try {
			var interfaceActions = wp.data.dispatch( 'core/interface' );
			if ( interfaceActions && typeof interfaceActions.enableComplementaryArea === 'function' ) {
				for ( var scopeIndex = 0; scopeIndex < INTERFACE_SCOPES.length; scopeIndex += 1 ) {
					try {
						interfaceActions.enableComplementaryArea( INTERFACE_SCOPES[ scopeIndex ], target );
						requested = true;
					} catch ( error ) {}
				}
			}
		} catch ( error ) {}

		for ( var index = 0; index < LEGACY_STORES.length; index += 1 ) {
			try {
				var actions = wp.data.dispatch( LEGACY_STORES[ index ] );
				if ( actions && typeof actions.openGeneralSidebar === 'function' ) {
					actions.openGeneralSidebar( target );
					requested = true;
				}
			} catch ( error ) {}
		}

		return requested;
	}

	function clearRetry() {
		if ( retryTimer ) window.clearTimeout( retryTimer );
		retryTimer = null;
		retryCount = 0;
		pendingTarget = '';
		pendingClientId = null;
	}

	function modeFromActiveSidebar() {
		var active = String( activeSidebar() );
		if ( active.indexOf( 'widget-inspector' ) !== -1 ) return 'edit';
		if ( active.indexOf( 'design-system' ) !== -1 ) return 'global';
		return 'widgets';
	}

	function syncModeNavigation() {
		var activeMode = modeFromActiveSidebar();
		document.querySelectorAll( '[data-cc-builder-mode]' ).forEach( function ( button ) {
			var active = button.getAttribute( 'data-cc-builder-mode' ) === activeMode;
			button.classList.toggle( 'is-active', active );
			button.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
		} );
		if ( document.body ) {
			document.body.classList.toggle( 'cresco-native-inspector-active', activeMode === 'edit' );
		}
	}

	function confirmTarget() {
		if ( ! pendingTarget ) return;
		if ( pendingClientId && selectedClientId() !== pendingClientId ) {
			clearRetry();
			return;
		}

		if ( targetIsActive( pendingTarget ) ) {
			clearRetry();
			syncModeNavigation();
			return;
		}

		var pluginName = targetPluginName( pendingTarget );
		if ( pluginName !== INSPECTOR_PLUGIN || pluginReady( pluginName ) ) requestTarget( pendingTarget );

		retryCount += 1;
		if ( retryCount >= MAX_RETRIES ) {
			clearRetry();
			syncModeNavigation();
			return;
		}

		retryTimer = window.setTimeout( confirmTarget, RETRY_DELAY );
	}

	function openTarget( target, clientId ) {
		clearRetry();
		pendingTarget = target;
		pendingClientId = clientId || null;
		confirmTarget();
	}

	function openInspectorForCurrentSelection() {
		var clientId = selectedClientId();
		if ( ! clientId ) return;
		openTarget( TARGETS.edit, clientId );
	}

	function handleModeClick( event ) {
		var button = event.target && event.target.closest ? event.target.closest( '[data-cc-builder-mode]' ) : null;
		if ( ! button ) return;
		var mode = button.getAttribute( 'data-cc-builder-mode' );
		var target = TARGETS[ mode ];
		if ( ! target ) return;

		event.preventDefault();
		event.stopPropagation();
		if ( typeof event.stopImmediatePropagation === 'function' ) event.stopImmediatePropagation();
		openTarget( target, mode === 'edit' ? selectedClientId() : null );
	}

	function handleCanvasPointer( event ) {
		var block = event.target && event.target.closest ? event.target.closest( '[data-block]' ) : null;
		if ( ! block ) return;
		window.setTimeout( openInspectorForCurrentSelection, 0 );
	}

	function attachCanvasDocument( targetDocument ) {
		if ( ! targetDocument || attachedDocuments.indexOf( targetDocument ) !== -1 ) return;
		attachedDocuments.push( targetDocument );
		targetDocument.addEventListener( 'pointerup', handleCanvasPointer, true );
	}

	function scanCanvasDocuments() {
		attachCanvasDocument( document );
		document.querySelectorAll( 'iframe[name="editor-canvas"]' ).forEach( function ( iframe ) {
			try {
				if ( iframe.contentDocument ) attachCanvasDocument( iframe.contentDocument );
			} catch ( error ) {}
		} );
	}

	function handleDataChange() {
		var clientId = selectedClientId();
		if ( clientId !== lastSelectedClientId ) {
			lastSelectedClientId = clientId;
			if ( clientId ) openTarget( TARGETS.edit, clientId );
			else clearRetry();
		}
		syncModeNavigation();
	}

	function start() {
		document.addEventListener( 'click', handleModeClick, true );
		scanCanvasDocuments();
		var observer = new MutationObserver( function () {
			scanCanvasDocuments();
			syncModeNavigation();
		} );
		observer.observe( document.body, { childList: true, subtree: true } );
		if ( typeof wp.data.subscribe === 'function' ) wp.data.subscribe( handleDataChange );
		handleDataChange();
	}

	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	else start();
} )( window.wp );
