import { readFile } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';
import process from 'node:process';

const runtimeFiles = [
	'build/editor-foundation.js',
	'build/editor.js',
	'build/widget-inspector-persistent.js',
	'build/editor-app-shell.js',
	'build/visual-canvas.js',
	'build/structure-navigator.js',
	'build/preview-foundation-bridge.js',
	'build/style-engine-editor.js',
];

const errors = [];

for ( const file of runtimeFiles ) {
	const result = spawnSync( process.execPath, [ '--check', file ], {
		cwd: process.cwd(),
		encoding: 'utf8',
	} );
	if ( result.status !== 0 ) {
		errors.push( `${ file }: ${ result.stderr || result.stdout || 'syntax check failed' }` );
	}
}

const integration = await readFile( 'includes/Admin/EditorIntegration.php', 'utf8' );
const hub = await readFile( 'includes/Admin/EditorHub.php', 'utf8' );
const packaging = await readFile( 'scripts/build-release.mjs', 'utf8' );

const requiredIntegrationTokens = [
	"'cresco-canvas-editor-foundation'",
	"'cresco-canvas-editor'",
	'build/editor-foundation.js',
	'build/editor.js',
];

const requiredHubTokens = [
	"'cresco-canvas-widget-inspector-persistent'",
	"'cresco-canvas-editor-app-shell'",
	"'cresco-canvas-visual-canvas'",
	"'cresco-canvas-structure-navigator'",
	"'cresco-canvas-preview-foundation-bridge'",
];

const requiredPackageFiles = [
	'build/editor-foundation.js',
	'build/editor-app-shell.js',
	'build/widget-inspector-persistent.js',
	'build/visual-canvas.js',
	'build/structure-navigator.js',
	'build/preview-foundation-bridge.js',
	'assets/css/editor-app-shell.css',
	'assets/css/structure-navigator-actions.css',
];

for ( const token of requiredIntegrationTokens ) {
	if ( ! integration.includes( token ) ) errors.push( `EditorIntegration is missing ${ token }` );
}
for ( const token of requiredHubTokens ) {
	if ( ! hub.includes( token ) ) errors.push( `EditorHub is missing ${ token }` );
}
for ( const file of requiredPackageFiles ) {
	if ( ! packaging.includes( `'${ file }'` ) ) errors.push( `Release package does not require ${ file }` );
}

const forbiddenEnqueueTokens = [
	"'cresco-canvas-editor-hub'",
	"'cresco-canvas-workspace-layout'",
	"'cresco-canvas-widget-inspector'",
	"'cresco-canvas-widget-inspector-compat'",
];
for ( const token of forbiddenEnqueueTokens ) {
	if ( hub.includes( token ) ) errors.push( `Legacy runtime is still enqueued: ${ token }` );
}

const excludedLegacyFiles = [
	'build/editor-hub.js',
	'build/workspace-layout.js',
	'build/widget-inspector.js',
	'build/widget-inspector-compat.js',
];
for ( const file of excludedLegacyFiles ) {
	if ( ! packaging.includes( `'${ file }'` ) ) errors.push( `Legacy package exclusion is missing ${ file }` );
}

if ( errors.length ) {
	process.stderr.write( `${ errors.join( '\n' ) }\n` );
	process.exit( 1 );
}

process.stdout.write( `Checked ${ runtimeFiles.length } editor runtimes and verified integration/package gates.\n` );
