( function ( wp, window, document ) {
	'use strict';

	var Cresco = window.CrescoCanvas;
	if ( ! wp || ! wp.element || ! wp.data || ! wp.components || ! wp.i18n || ! Cresco || ! Cresco.ui ) return;

	var el = wp.element.createElement;
	var Component = wp.element.Component;
	var useEffect = wp.element.useEffect;
	var useMemo = wp.element.useMemo;
	var useState = wp.element.useState;
	var useSelect = wp.data.useSelect;
	var Button = wp.components.Button;
	var Notice = wp.components.Notice;
	var __ = wp.i18n.__;
	var HOST_ID = 'cresco-canvas-app-shell';
	var ROOT_ID = 'cresco-canvas-app-shell-root';
	var READY_CLASS = 'cresco-app-shell-ready';
	var FAILED_CLASS = 'cresco-app-shell-failed';
	var BOOT_TIMEOUT = 8000;
	var mountedRoot = null;
	var bootTimer = null;

	function report( code, error ) {
		if ( Cresco.diagnostics && typeof Cresco.diagnostics.report === 'function' ) {
			Cresco.diagnostics.report( 'error', code, error && error.message ? error.message : String( error || code ), { stack: error && error.stack ? error.stack : '' } );
		}
	}

	function markReady() {
		if ( bootTimer ) {
			window.clearTimeout( bootTimer );
			bootTimer = null;
		}
		if ( ! document.body ) return;
		document.body.classList.add( READY_CLASS );
		document.body.classList.remove( FAILED_CLASS );
	}

	function failOpen( error ) {
		report( 'app-shell-bootstrap', error );
		if ( bootTimer ) {
			window.clearTimeout( bootTimer );
			bootTimer = null;
		}
		if ( document.body ) {
			document.body.classList.remove( READY_CLASS, 'cresco-app-shell-open', 'cresco-app-shell-closed', 'cresco-canvas-three-pane' );
			document.body.classList.add( FAILED_CLASS );
		}
		document.documentElement.style.removeProperty( '--cc-app-shell-width' );
		var host = document.getElementById( HOST_ID );
		if ( host && host.parentNode ) host.parentNode.removeChild( host );
		try {
			Cresco.ui.setState( { open: false, visualMode: false } );
		} catch ( stateError ) {
			report( 'app-shell-fallback-state', stateError );
		}
	}

	class ViewBoundary extends Component {
		constructor( props ) {
			super( props );
			this.state = { error: null };
		}

		static getDerivedStateFromError( error ) {
			return { error: error };
		}

		componentDidCatch( error, info ) {
			if ( Cresco.diagnostics && typeof Cresco.diagnostics.report === 'function' ) {
				Cresco.diagnostics.report(
					'error',
					'view-' + this.props.view,
					error && error.message ? error.message : String( error ),
					{ componentStack: info && info.componentStack ? info.componentStack : '' }
				);
			}
		}

		componentDidUpdate( previousProps ) {
			if ( previousProps.view !== this.props.view && this.state.error ) this.setState( { error: null } );
		}

		render() {
			if ( this.state.error ) {
				return el( 'div', { className: 'cc-app-shell__error' },
					el( Notice, { status: 'error', isDismissible: false }, __( 'This editor panel encountered an error. Native WordPress controls are still available.', 'cresco-canvas' ) ),
					el( Button, { variant: 'secondary', onClick: function () { window.location.reload(); } }, __( 'Reload editor', 'cresco-canvas' ) )
				);
			}
			return this.props.children;
		}
	}

	function AppShell() {
		var statePair = useState( Cresco.ui.getState() );
		var state = statePair[ 0 ];
		var setState = statePair[ 1 ];
		var viewRevisionPair = useState( 0 );
		var viewRevision = viewRevisionPair[ 0 ];
		var setViewRevision = viewRevisionPair[ 1 ];
		var selectedClientId = useSelect( function ( select ) {
			var editor = select( 'core/block-editor' );
			return editor && editor.getSelectedBlockClientId ? editor.getSelectedBlockClientId() : null;
		}, [] );

		useEffect( function () {
			markReady();
			return Cresco.ui.subscribe( setState );
		}, [] );
		useEffect( function () {
			function refreshViews() { setViewRevision( function ( value ) { return value + 1; } ); }
			window.addEventListener( 'cresco-canvas:views', refreshViews );
			return function () { window.removeEventListener( 'cresco-canvas:views', refreshViews ); };
		}, [] );
		useEffect( function () {
			if ( selectedClientId ) Cresco.ui.open( 'edit' );
		}, [ selectedClientId ] );
		useEffect( function () {
			if ( ! document.body ) return;
			document.body.classList.toggle( 'cresco-app-shell-open', state.open );
			document.body.classList.toggle( 'cresco-app-shell-closed', ! state.open );
			document.documentElement.style.setProperty( '--cc-app-shell-width', state.width + 'px' );
		}, [ state.open, state.width ] );

		var view = useMemo( function () { return Cresco.ui.getView( state.activeView ); }, [ state.activeView, viewRevision ] );
		var ViewComponent = view && view.component;
		var tabs = [
			{ id: 'widgets', icon: 'screenoptions', label: __( 'Widgets', 'cresco-canvas' ) },
			{ id: 'edit', icon: 'edit', label: __( 'Edit', 'cresco-canvas' ) },
			{ id: 'global', icon: 'admin-appearance', label: __( 'Global', 'cresco-canvas' ) }
		];

		function beginResize( event ) {
			if ( event.button !== 0 ) return;
			event.preventDefault();
			var startX = event.clientX;
			var startWidth = state.width;
			function move( moveEvent ) { Cresco.ui.setState( { width: startWidth + moveEvent.clientX - startX } ); }
			function end() {
				window.removeEventListener( 'pointermove', move );
				window.removeEventListener( 'pointerup', end );
				window.removeEventListener( 'pointercancel', end );
			}
			window.addEventListener( 'pointermove', move );
			window.addEventListener( 'pointerup', end );
			window.addEventListener( 'pointercancel', end );
		}

		if ( ! state.open ) {
			return el( Button, {
				className: 'cc-app-shell-launcher',
				icon: 'layout',
				label: __( 'Open Cresco Canvas', 'cresco-canvas' ),
				onClick: function () { Cresco.ui.setState( { open: true } ); }
			} );
		}

		return el( 'aside', { className: 'cc-app-shell', 'aria-label': __( 'Cresco Canvas editor', 'cresco-canvas' ) },
			el( 'header', { className: 'cc-app-shell__header' },
				el( 'div', { className: 'cc-app-shell__brand' },
					el( 'span', { className: 'dashicons dashicons-layout', 'aria-hidden': 'true' } ),
					el( 'div', null,
						el( 'strong', null, __( 'Cresco Canvas', 'cresco-canvas' ) ),
						el( 'span', null, __( 'Visual website builder', 'cresco-canvas' ) )
					)
				),
				el( Button, { icon: 'no-alt', label: __( 'Close Cresco panel', 'cresco-canvas' ), onClick: function () { Cresco.ui.setState( { open: false } ); } } )
			),
			el( 'nav', { className: 'cc-app-shell__tabs', 'aria-label': __( 'Cresco editor sections', 'cresco-canvas' ) },
				tabs.map( function ( tab ) {
					var disabled = tab.id === 'edit' && ! selectedClientId;
					return el( 'button', {
						key: tab.id,
						type: 'button',
						className: state.activeView === tab.id ? 'is-active' : '',
						disabled: disabled,
						onClick: function () { Cresco.ui.open( tab.id ); },
						'aria-current': state.activeView === tab.id ? 'page' : undefined
					},
						el( 'span', { className: 'dashicons dashicons-' + tab.icon, 'aria-hidden': 'true' } ),
						el( 'span', null, tab.label )
					);
				} )
			),
			el( 'div', { className: 'cc-app-shell__content', key: state.activeView + '-' + viewRevision },
				ViewComponent ? el( ViewBoundary, { view: state.activeView }, el( ViewComponent ) ) : el( Notice, { status: 'info', isDismissible: false }, state.activeView === 'edit' ? __( 'Select a widget on the canvas to edit it.', 'cresco-canvas' ) : __( 'This Cresco workspace is loading.', 'cresco-canvas' ) )
			),
			el( 'footer', { className: 'cc-app-shell__footer' },
				el( 'button', { type: 'button', onClick: function () { Cresco.ui.setState( { visualMode: ! state.visualMode } ); } },
					el( 'span', { className: 'dashicons ' + ( state.visualMode ? 'dashicons-visibility' : 'dashicons-editor-code' ), 'aria-hidden': 'true' } ),
					state.visualMode ? __( 'Cresco canvas', 'cresco-canvas' ) : __( 'Native controls', 'cresco-canvas' )
				),
				el( 'span', null, state.device )
			),
			el( 'div', { className: 'cc-app-shell__resize', role: 'separator', 'aria-label': __( 'Resize Cresco panel', 'cresco-canvas' ), onPointerDown: beginResize } )
		);
	}

	function ensureHost() {
		var existing = document.getElementById( HOST_ID );
		if ( existing ) return existing;
		var shellBody = document.querySelector( '.interface-interface-skeleton__body' );
		var content = document.querySelector( '.interface-interface-skeleton__content' );
		if ( ! shellBody || ! content ) return null;
		var host = document.createElement( 'div' );
		host.id = HOST_ID;
		var root = document.createElement( 'div' );
		root.id = ROOT_ID;
		host.appendChild( root );
		shellBody.insertBefore( host, content );
		return host;
	}

	function verifyMounted() {
		window.requestAnimationFrame( function () {
			window.requestAnimationFrame( function () {
				var rootNode = document.getElementById( ROOT_ID );
				if ( rootNode && rootNode.querySelector( '.cc-app-shell, .cc-app-shell-launcher' ) ) markReady();
				else failOpen( new Error( 'Cresco App Shell did not render.' ) );
			} );
		} );
	}

	function mount() {
		try {
			var host = ensureHost();
			if ( ! host ) return false;
			if ( mountedRoot ) return true;
			var rootNode = document.getElementById( ROOT_ID );
			if ( ! rootNode ) return false;
			if ( typeof wp.element.createRoot === 'function' ) {
				mountedRoot = wp.element.createRoot( rootNode );
				mountedRoot.render( el( AppShell ) );
			} else if ( typeof wp.element.render === 'function' ) {
				wp.element.render( el( AppShell ), rootNode );
				mountedRoot = true;
			} else {
				throw new Error( 'WordPress element renderer is unavailable.' );
			}
			verifyMounted();
			return true;
		} catch ( error ) {
			failOpen( error );
			return false;
		}
	}

	function start() {
		if ( ! document.body ) return;
		bootTimer = window.setTimeout( function () {
			if ( ! document.body.classList.contains( READY_CLASS ) ) failOpen( new Error( 'Cresco App Shell timed out while waiting for the editor.' ) );
		}, BOOT_TIMEOUT );
		if ( mount() ) return;
		var observer = new MutationObserver( function () {
			if ( document.body.classList.contains( FAILED_CLASS ) ) {
				observer.disconnect();
				return;
			}
			if ( mount() ) observer.disconnect();
		} );
		observer.observe( document.documentElement, { childList: true, subtree: true } );
	}

	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', start, { once: true } );
	else start();
} )( window.wp, window, document );
