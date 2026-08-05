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
	var TabPanel = wp.components.TabPanel;

	var STORAGE_RECENT = 'crescoCanvasRecentTools';
	var STORAGE_FAVORITES = 'crescoCanvasFavoriteTools';

	var catalog = [
		{
			key: 'design',
			label: __( 'Design System', 'cresco-canvas' ),
			hint: __( 'Manage global colors, typography, spacing and reusable design tokens.', 'cresco-canvas' ),
			group: __( 'Design', 'cresco-canvas' ),
			icon: 'admin-appearance',
			words: [ 'design-system', 'global', 'style', 'color', 'typography', 'spacing', 'token' ]
		},
		{
			key: 'templates',
			label: __( 'Templates & Components', 'cresco-canvas' ),
			hint: __( 'Create reusable sections, synced components, patterns and complete site kits.', 'cresco-canvas' ),
			group: __( 'Build', 'cresco-canvas' ),
			icon: 'screenoptions',
			words: [ 'templates', 'template', 'component', 'pattern', 'kit' ]
		},
		{
			key: 'theme',
			label: __( 'Theme Builder', 'cresco-canvas' ),
			hint: __( 'Design headers, footers and conditional templates for the whole website.', 'cresco-canvas' ),
			group: __( 'Build', 'cresco-canvas' ),
			icon: 'layout',
			words: [ 'theme-builder', 'theme', 'header', 'footer', 'archive', 'single', 'condition' ]
		},
		{
			key: 'dynamic',
			label: __( 'Dynamic Data', 'cresco-canvas' ),
			hint: __( 'Connect fields, queries, loops, filters, ACF and WooCommerce data.', 'cresco-canvas' ),
			group: __( 'Data', 'cresco-canvas' ),
			icon: 'database',
			words: [ 'dynamic', 'data', 'query', 'loop', 'filter', 'acf', 'woocommerce' ]
		},
		{
			key: 'interactions',
			label: __( 'Interactions & Forms', 'cresco-canvas' ),
			hint: __( 'Build tabs, sliders, popups and validated forms with submissions.', 'cresco-canvas' ),
			group: __( 'Engage', 'cresco-canvas' ),
			icon: 'feedback',
			words: [ 'interaction', 'forms', 'form', 'tabs', 'slider', 'popup', 'submission' ]
		}
	];

	function readList( key ) {
		try {
			var value = JSON.parse( window.localStorage.getItem( key ) || '[]' );
			return Array.isArray( value ) ? value.filter( function ( item ) { return typeof item === 'string'; } ).slice( 0, 8 ) : [];
		} catch ( error ) {
			return [];
		}
	}

	function writeList( key, value ) {
		try { window.localStorage.setItem( key, JSON.stringify( value.slice( 0, 8 ) ) ); } catch ( error ) {}
	}

	function titleFromName( name ) {
		return String( name || '' ).replace( /^cresco-canvas-/, '' ).replace( /-/g, ' ' ).replace( /\b\w/g, function ( c ) { return c.toUpperCase(); } );
	}

	function discoverModules() {
		var plugins = typeof getPlugins === 'function' ? getPlugins() : [];
		return plugins.filter( function ( plugin ) {
			return plugin && plugin.name && plugin.name.indexOf( 'cresco-canvas-' ) === 0 && plugin.name !== 'cresco-canvas-hub';
		} ).map( function ( plugin ) {
			var match = catalog.find( function ( item ) {
				return item.words.some( function ( word ) { return plugin.name.indexOf( word ) !== -1; } );
			} );
			return {
				name: plugin.name,
				target: plugin.name + '/' + plugin.name,
				key: match ? match.key : plugin.name,
				label: match ? match.label : titleFromName( plugin.name ),
				hint: match ? match.hint : __( 'Open this Cresco Canvas workspace.', 'cresco-canvas' ),
				group: match ? match.group : __( 'More', 'cresco-canvas' ),
				icon: match ? match.icon : 'admin-tools',
				keywords: match ? match.words.join( ' ' ) : plugin.name,
				status: __( 'Ready', 'cresco-canvas' )
			};
		} ).filter( function ( item, index, list ) {
			return list.findIndex( function ( other ) { return other.key === item.key; } ) === index;
		} );
	}

	function hideExtraToolbarEntries() {
		var selectors = [ '.edit-post-header', '.editor-header', '.interface-interface-skeleton__header' ];
		selectors.forEach( function ( selector ) {
			document.querySelectorAll( selector + ' button, ' + selector + ' [role="button"]' ).forEach( function ( node ) {
				var label = ( node.getAttribute( 'aria-label' ) || node.getAttribute( 'title' ) || node.textContent || '' ).trim();
				if ( /cresco/i.test( label ) && ! /^cresco canvas$/i.test( label ) && ! node.closest( '.cresco-canvas-hub' ) ) {
					node.classList.add( 'cresco-canvas-module-entry' );
				}
			} );
		} );
	}

	function Hub() {
		var moduleState = useState( [] );
		var modules = moduleState[ 0 ];
		var setModules = moduleState[ 1 ];
		var queryState = useState( '' );
		var query = queryState[ 0 ];
		var setQuery = queryState[ 1 ];
		var recentState = useState( readList( STORAGE_RECENT ) );
		var recent = recentState[ 0 ];
		var setRecent = recentState[ 1 ];
		var favoriteState = useState( readList( STORAGE_FAVORITES ) );
		var favorites = favoriteState[ 0 ];
		var setFavorites = favoriteState[ 1 ];

		useEffect( function () {
			var refresh = function () { setModules( discoverModules() ); hideExtraToolbarEntries(); };
			refresh();
			var timer = window.setInterval( refresh, 1500 );
			var observer = new MutationObserver( hideExtraToolbarEntries );
			observer.observe( document.body, { childList: true, subtree: true } );
			return function () { window.clearInterval( timer ); observer.disconnect(); };
		}, [] );

		var filtered = useMemo( function () {
			var needle = query.trim().toLowerCase();
			return ! needle ? modules : modules.filter( function ( item ) {
				return ( item.label + ' ' + item.hint + ' ' + item.group + ' ' + item.keywords ).toLowerCase().indexOf( needle ) !== -1;
			} );
		}, [ modules, query ] );

		function openModule( module ) {
			var dispatch = wp.data.dispatch( 'core/edit-post' );
			if ( dispatch && dispatch.openGeneralSidebar ) dispatch.openGeneralSidebar( module.target );
			var next = [ module.key ].concat( recent.filter( function ( key ) { return key !== module.key; } ) ).slice( 0, 5 );
			setRecent( next );
			writeList( STORAGE_RECENT, next );
		}

		function toggleFavorite( module, event ) {
			event.stopPropagation();
			var next = favorites.indexOf( module.key ) === -1 ? favorites.concat( module.key ) : favorites.filter( function ( key ) { return key !== module.key; } );
			setFavorites( next );
			writeList( STORAGE_FAVORITES, next );
		}

		function orderedByKeys( keys ) {
			return keys.map( function ( key ) { return modules.find( function ( module ) { return module.key === key; } ); } ).filter( Boolean );
		}

		function ToolCard( module, compact ) {
			var selected = favorites.indexOf( module.key ) !== -1;
			return el( 'article', { key: module.name, className: 'cresco-canvas-hub__card' + ( compact ? ' is-compact' : '' ) },
				el( Button, { className: 'cresco-canvas-hub__card-main', onClick: function () { openModule( module ); } },
					el( 'span', { className: 'dashicons dashicons-' + module.icon, 'aria-hidden': 'true' } ),
					el( 'span', { className: 'cresco-canvas-hub__card-copy' },
						el( 'span', { className: 'cresco-canvas-hub__card-title' }, module.label ),
						! compact && el( 'span', { className: 'cresco-canvas-hub__card-hint' }, module.hint ),
						el( 'span', { className: 'cresco-canvas-hub__card-meta' },
							el( 'span', { className: 'cresco-canvas-hub__status-dot', 'aria-hidden': 'true' } ),
							module.status,
							el( 'span', { 'aria-hidden': 'true' }, ' · ' ),
							module.group
						)
					)
				),
				el( Button, {
					className: 'cresco-canvas-hub__favorite',
					icon: selected ? 'star-filled' : 'star-empty',
					label: selected ? __( 'Remove from favorites', 'cresco-canvas' ) : __( 'Add to favorites', 'cresco-canvas' ),
					onClick: function ( event ) { toggleFavorite( module, event ); }
				} )
			);
		}

		function ToolGrid( list, compact ) {
			return list.length ? el( 'div', { className: 'cresco-canvas-hub__grid' }, list.map( function ( module ) { return ToolCard( module, compact ); } ) ) : el( Notice, { status: 'info', isDismissible: false }, __( 'No matching Cresco tools were found.', 'cresco-canvas' ) );
		}

		var recentModules = orderedByKeys( recent );
		var favoriteModules = orderedByKeys( favorites );

		return el( Fragment, null,
			el( PluginSidebarMoreMenuItem, { target: 'cresco-canvas-hub' }, __( 'Cresco Canvas', 'cresco-canvas' ) ),
			el( PluginSidebar, { name: 'cresco-canvas-hub', title: __( 'Cresco Canvas', 'cresco-canvas' ), icon: 'art', className: 'cresco-canvas-hub' },
				el( 'div', { className: 'cresco-canvas-hub__hero' },
					el( 'div', { className: 'cresco-canvas-hub__brand' },
						el( 'span', { className: 'dashicons dashicons-art', 'aria-hidden': 'true' } ),
						el( 'div', null,
							el( 'strong', null, __( 'Build your website from one workspace', 'cresco-canvas' ) ),
							el( 'p', null, __( 'Choose what you want to do. Cresco opens the right tool without adding more toolbar buttons.', 'cresco-canvas' ) )
						)
					),
					el( 'div', { className: 'cresco-canvas-hub__summary', role: 'status' },
						el( 'span', null, modules.length ),
						__( ' tools ready', 'cresco-canvas' )
					)
				),
				el( 'div', { className: 'cresco-canvas-hub__search' },
					el( SearchControl, { label: __( 'Search by task or tool', 'cresco-canvas' ), placeholder: __( 'Try “header”, “form”, “colors”…', 'cresco-canvas' ), value: query, onChange: setQuery } )
				),
				query ? ToolGrid( filtered, false ) : el( TabPanel, {
					className: 'cresco-canvas-hub__tabs',
					activeClass: 'is-active',
					tabs: [
						{ name: 'start', title: __( 'Start', 'cresco-canvas' ) },
						{ name: 'all', title: __( 'All tools', 'cresco-canvas' ) },
						{ name: 'saved', title: __( 'Saved', 'cresco-canvas' ) }
					]
				}, function ( tab ) {
					if ( tab.name === 'all' ) return ToolGrid( modules, false );
					if ( tab.name === 'saved' ) {
						return el( Fragment, null,
							el( 'h3', { className: 'cresco-canvas-hub__section-title' }, __( 'Favorites', 'cresco-canvas' ) ),
							favoriteModules.length ? ToolGrid( favoriteModules, true ) : el( 'p', { className: 'cresco-canvas-hub__empty' }, __( 'Star a tool to keep it here.', 'cresco-canvas' ) ),
							el( 'h3', { className: 'cresco-canvas-hub__section-title' }, __( 'Recently used', 'cresco-canvas' ) ),
							recentModules.length ? ToolGrid( recentModules, true ) : el( 'p', { className: 'cresco-canvas-hub__empty' }, __( 'Your recently opened tools will appear here.', 'cresco-canvas' ) )
						);
					}
					return el( Fragment, null,
						el( 'div', { className: 'cresco-canvas-hub__workflow' },
							el( 'span', { className: 'cresco-canvas-hub__eyebrow' }, __( 'Recommended workflow', 'cresco-canvas' ) ),
							el( 'h3', null, __( 'Design → Build → Connect → Engage', 'cresco-canvas' ) ),
							el( 'p', null, __( 'Start with global styles, create reusable layouts, connect dynamic content, then add interactions and forms.', 'cresco-canvas' ) )
						),
						ToolGrid( modules.slice( 0, 5 ), false )
					);
				} ),
				el( 'div', { className: 'cresco-canvas-hub__footer' },
					el( 'span', null, __( 'Cresco Canvas 1.0 workspace', 'cresco-canvas' ) ),
					el( 'span', { className: 'cresco-canvas-hub__footer-status' }, __( 'All systems ready', 'cresco-canvas' ) )
				)
			)
		);
	}

	registerPlugin( 'cresco-canvas-hub', { icon: 'art', render: Hub } );
} )( window.wp );
