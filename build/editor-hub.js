( function ( wp ) {
	'use strict';
	if ( ! wp || ! wp.plugins || ! wp.editor || ! wp.element || ! wp.components || ! wp.data ) return;

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useEffect = wp.element.useEffect;
	var useMemo = wp.element.useMemo;
	var useState = wp.element.useState;
	var __ = wp.i18n.__;
	var registerPlugin = wp.plugins.registerPlugin;
	var getPlugins = wp.plugins.getPlugins;
	var PluginSidebar = wp.editor.PluginSidebar;
	var PluginSidebarMoreMenuItem = wp.editor.PluginSidebarMoreMenuItem;
	var Button = wp.components.Button;
	var Notice = wp.components.Notice;
	var SearchControl = wp.components.SearchControl;

	var preferred = [
		{ key: 'design', label: __( 'Design System', 'cresco-canvas' ), hint: __( 'Colors, typography, spacing and global tokens.', 'cresco-canvas' ), words: [ 'design-system', 'global' ] },
		{ key: 'templates', label: __( 'Templates & Components', 'cresco-canvas' ), hint: __( 'Patterns, synced components and site kits.', 'cresco-canvas' ), words: [ 'templates', 'template' ] },
		{ key: 'theme', label: __( 'Theme Builder', 'cresco-canvas' ), hint: __( 'Headers, footers, templates and display conditions.', 'cresco-canvas' ), words: [ 'theme-builder', 'theme' ] },
		{ key: 'dynamic', label: __( 'Dynamic Data', 'cresco-canvas' ), hint: __( 'Dynamic fields, loops, queries and filters.', 'cresco-canvas' ), words: [ 'dynamic' ] },
		{ key: 'interactions', label: __( 'Interactions & Forms', 'cresco-canvas' ), hint: __( 'Tabs, popups, sliders, forms and diagnostics.', 'cresco-canvas' ), words: [ 'interaction', 'forms', 'form' ] }
	];

	function titleFromName( name ) {
		return String( name || '' ).replace( /^cresco-canvas-/, '' ).replace( /-/g, ' ' ).replace( /\b\w/g, function ( c ) { return c.toUpperCase(); } );
	}

	function discoverModules() {
		var plugins = typeof getPlugins === 'function' ? getPlugins() : [];
		return plugins.filter( function ( plugin ) {
			return plugin && plugin.name && plugin.name.indexOf( 'cresco-canvas-' ) === 0 && plugin.name !== 'cresco-canvas-hub';
		} ).map( function ( plugin ) {
			var match = preferred.find( function ( item ) { return item.words.some( function ( word ) { return plugin.name.indexOf( word ) !== -1; } ); } );
			return {
				name: plugin.name,
				target: plugin.name + '/' + plugin.name,
				label: match ? match.label : titleFromName( plugin.name ),
				hint: match ? match.hint : __( 'Open this Cresco Canvas workspace.', 'cresco-canvas' )
			};
		} ).filter( function ( item, index, list ) {
			return list.findIndex( function ( other ) { return other.name === item.name; } ) === index;
		} );
	}

	function hideExtraToolbarEntries() {
		var selectors = [ '.edit-post-header', '.editor-header', '.interface-interface-skeleton__header' ];
		selectors.forEach( function ( selector ) {
			document.querySelectorAll( selector + ' button, ' + selector + ' [role="button"]' ).forEach( function ( node ) {
				var label = ( node.getAttribute( 'aria-label' ) || node.getAttribute( 'title' ) || node.textContent || '' ).trim();
				if ( /cresco/i.test( label ) && ! /cresco canvas$/i.test( label ) && ! node.closest( '.cresco-canvas-hub' ) ) {
					node.classList.add( 'cresco-canvas-module-entry' );
				}
			} );
	}

	function Hub() {
		var moduleState = useState( [] );
		var modules = moduleState[ 0 ];
		var setModules = moduleState[ 1 ];
		var queryState = useState( '' );
		var query = queryState[ 0 ];
		var setQuery = queryState[ 1 ];

		useEffect( function () {
			var refresh = function () { setModules( discoverModules() ); hideExtraToolbarEntries(); };
			refresh();
			var timer = window.setInterval( refresh, 1200 );
			var observer = new MutationObserver( hideExtraToolbarEntries );
			observer.observe( document.body, { childList: true, subtree: true } );
			return function () { window.clearInterval( timer ); observer.disconnect(); };
		}, [] );

		var filtered = useMemo( function () {
			var needle = query.trim().toLowerCase();
			return ! needle ? modules : modules.filter( function ( item ) { return ( item.label + ' ' + item.hint ).toLowerCase().indexOf( needle ) !== -1; } );
		}, [ modules, query ] );

		function openModule( module ) {
			var dispatch = wp.data.dispatch( 'core/edit-post' );
			if ( dispatch && dispatch.openGeneralSidebar ) dispatch.openGeneralSidebar( module.target );
		}

		return el( Fragment, null,
			el( PluginSidebarMoreMenuItem, { target: 'cresco-canvas-hub' }, __( 'Cresco Canvas', 'cresco-canvas' ) ),
			el( PluginSidebar, { name: 'cresco-canvas-hub', title: __( 'Cresco Canvas', 'cresco-canvas' ), icon: 'art', className: 'cresco-canvas-hub' },
				el( 'div', { className: 'cresco-canvas-hub__intro' },
					el( 'strong', null, __( 'One workspace for the whole site', 'cresco-canvas' ) ),
					el( 'p', null, __( 'Choose a Cresco tool without crowding the WordPress toolbar.', 'cresco-canvas' ) )
				),
				el( SearchControl, { label: __( 'Search Cresco tools', 'cresco-canvas' ), value: query, onChange: setQuery } ),
				filtered.length ? el( 'div', { className: 'cresco-canvas-hub__grid' }, filtered.map( function ( module ) {
					return el( Button, { key: module.name, className: 'cresco-canvas-hub__card', onClick: function () { openModule( module ); } },
						el( 'span', { className: 'cresco-canvas-hub__card-title' }, module.label ),
						el( 'span', { className: 'cresco-canvas-hub__card-hint' }, module.hint )
					);
				} ) ) : el( Notice, { status: 'info', isDismissible: false }, __( 'No matching Cresco tools were found.', 'cresco-canvas' ) ),
				el( 'div', { className: 'cresco-canvas-hub__footer' }, __( 'Cresco Canvas 1.0 workspace', 'cresco-canvas' ) )
			)
		);
	}

	registerPlugin( 'cresco-canvas-hub', { icon: 'art', render: Hub } );
} )( window.wp );
