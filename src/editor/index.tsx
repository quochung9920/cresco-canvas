import { createRoot } from '@wordpress/element';

import '../styles/admin.scss';
import { App } from './App';
import { ErrorBoundary } from './components/ErrorBoundary';

const container = document.getElementById( 'cresco-canvas-app' );

if ( container ) {
	const bootstrap = window.crescoCanvasSettings;
	createRoot( container ).render(
		<ErrorBoundary
			nativeEditUrl={ bootstrap.nativeEditUrl }
			safeModeUrl={ bootstrap.safeModeUrl }
		>
			<App />
		</ErrorBoundary>
	);
}
