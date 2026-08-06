( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.data || ! wp.plugins ) return;

	var editPost = wp.editPost || {};
	var editor = wp.editor || {};
	var TARGET = 'cresco-canvas-widget-inspector/cresco-canvas-widget-inspector';
	var PLUGIN_NAME = 'cresco-canvas-widget-inspector';
	var STORE_NAMES = [ 'core/edit-post', 'core/edit-site' ];
	var retryTimer = null;
	var retryCount = 0;
	var MAX_RETRIES = 40;
	var RETRY_DELAY = 100;
	var lastConfirmedClientId = null;

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

	function pluginReady() {
		try {
			if ( typeof wp.plugins.getPlugin === 'function' ) return Boolean( wp.plugins.getPlugin( PLUGIN_NAME ) );
			if ( typeof wp.plugins.getPlugins === 'function' ) {
				return wp.plugins.getPlugins().some( function ( plugin ) { return plugin && plugin.name === PLUGIN_NAME; } );
			}
		} catch ( error ) {}
		return false;
	}

	function activeSidebar() {
		for ( var index = 0; index < STORE_NAMES.length; index += 1 ) {
			try {
				var store = wp.data.select( STORE_NAMES[ index ] );
				if ( store && typeof store.getActiveGeneralSidebarName === 'function' ) {
					var general = store.getActiveGeneralSidebarName();
					if ( general ) return general;
				}
				if ( store && typeof store.getActiveComplementaryArea === 'function' ) {
					var complementary = store.getActiveComplementaryArea();
					if ( complementary ) return complementary;
				}
			} catch ( error ) {}
		}
		return '';
	}

	function inspectorActive() {
		return String( activeSidebar() ).indexOf( PLUGIN_NAME ) !== -1;
	}

	function openInspector() {
		var requested = false;
		for ( var index = 0; index < STORE_NAMES.length; index += 1 ) {
			try {
				var actions = wp.data.dispatch( STORE_NAMES[ index ] );
				if ( actions && typeof actions.openGeneralSidebar === 'function' ) {
					actions.openGeneralSidebar( TARGET );
					requested = true;
				}
				if ( actions && typeof actions.enableComplementaryArea === 'function' ) {
					actions.enableComplementaryArea( 'core', TARGET );
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
	}

	function markState() {
		if ( document.body ) document.body.classList.toggle( 'cresco-native-inspector-active', inspectorActive() );
	}

	function confirmForSelection( clientId ) {
		if ( selectedClientId() !== clientId ) {
			clearRetry();
			return;
		}

		if ( inspectorActive() ) {
			lastConfirmedClientId = clientId;
			clearRetry();
			markState();
			return;
		}

		if ( pluginReady() ) openInspector();
		retryCount += 1;
		if ( retryCount >= MAX_RETRIES ) {
			clearRetry();
			return;
		}

		retryTimer = window.setTimeout( function () {
			confirmForSelection( clientId );
		}, RETRY_DELAY );
	}

	function syncInspector() {
		markState();
		var clientId = selectedClientId();
		if ( ! clientId ) {
			lastConfirmedClientId = null;
			clearRetry();
			return;
		}
		if ( clientId === lastConfirmedClientId && inspectorActive() ) return;
		if ( retryTimer ) return;
		retryCount = 0;
		confirmForSelection( clientId );
	}

	if ( typeof wp.data.subscribe === 'function' ) wp.data.subscribe( syncInspector );
	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', syncInspector, { once: true } );
	else syncInspector();
} )( window.wp );
