import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { zipSync } from 'fflate';
import { collectReleaseFiles } from './release-files.mjs';

const root = process.cwd();
const outputDirectory = path.join( root, 'dist' );
const packageJson = JSON.parse( await readFile( path.join( root, 'package.json' ), 'utf8' ) );
const version = packageJson.version;
const archiveName = `cresco-canvas-${ version }.zip`;
const archivePath = path.join( outputDirectory, archiveName );
const fixedDate = new Date( '1980-01-01T00:00:00.000Z' );
const releaseOwnershipContract = [
	'docs/CRESCO_SESSION_V1.md',
	'assets/css/container-width.css',
	'assets/css/standalone-visual-editor.css',
	'assets/css/standalone-inspector-v2.css',
	'assets/css/widget-control-enhancements.css',
	'assets/css/standalone-ui-v3.css',
	'assets/css/standalone-page-settings.css',
	'assets/css/standalone-history.css',
	'assets/css/global-config-import.css',
	'assets/css/viewport-shell.css',
	'build/standalone-visual-editor.js',
	'build/standalone-visual-editor.asset.php',
	'build/standalone-inspector-v2.js',
	'build/widget-control-enhancements.js',
	'build/standalone-ui-v3.js',
	'build/standalone-page-settings.js',
	'build/standalone-history.js',
	'build/global-config-import.js',
	'build/viewport-shell.js',
	'includes/Page/PageSettings.php',
	'includes/Page/canvas-template.php',
	'includes/Session/HistoryManager.php',
	'includes/Session/SessionManager.php',
];

// Retired editor assets are listed explicitly so release packaging cannot
// silently reintroduce an obsolete runtime after a refactor or stale build.
const retiredReleaseAssets = [
	'assets/css/editor-hub.css',
	'assets/css/elements-usage-sort.css',
	'assets/css/workspace-layout.css',
	'assets/css/widget-inspector.css',
	'build/editor-hub.js',
	'build/editor-hub.asset.php',
	'build/elements-usage-sort.js',
	'build/elements-usage-sort.asset.php',
	'build/workspace-layout.js',
	'build/workspace-layout.asset.php',
	'build/widget-inspector.js',
	'build/widget-inspector.asset.php',
	'build/widget-inspector-compat.js',
	'build/widget-inspector-compat.asset.php',
	'build/standalone-content-bootstrap.js',
	'build/standalone-content-bootstrap.asset.php',
	'build/native-gutenberg-bridge.js',
	'build/native-gutenberg-bridge.asset.php',
];

const files = await collectReleaseFiles( root );
for ( const required of releaseOwnershipContract ) {
	if ( ! files.includes( required ) ) throw new Error( `Required production runtime is not in release allowlist: ${ required }` );
}
for ( const retired of retiredReleaseAssets ) {
	if ( files.includes( retired ) ) throw new Error( `Retired editor asset must not be packaged: ${ retired }` );
}
const sha256 = ( bytes ) => createHash( 'sha256' ).update( bytes ).digest( 'hex' );

function gitValue( args, fallback = 'unknown' ) {
	try {
		return execFileSync( 'git', args, { cwd: root, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ] } ).trim() || fallback;
	} catch {
		return fallback;
	}
}

await rm( outputDirectory, { recursive: true, force: true } );
await mkdir( outputDirectory, { recursive: true } );

const archiveEntries = {};
const fileChecksums = [];
for ( const file of files ) {
	const bytes = new Uint8Array( await readFile( path.join( root, file ) ) );
	const archiveNameForFile = `cresco-canvas/${ file.replaceAll( path.sep, '/' ) }`;
	archiveEntries[ archiveNameForFile ] = [ bytes, { mtime: fixedDate } ];
	fileChecksums.push( { file, sha256: sha256( bytes ) } );
}

const archive = zipSync( archiveEntries, { level: 9 } );
const checksum = sha256( archive );
await writeFile( archivePath, archive );
await writeFile( path.join( outputDirectory, 'SHA256SUMS' ), `${ checksum }  ${ archiveName }\n` );

const sourceCommit = process.env.GITHUB_SHA || gitValue( [ 'rev-parse', 'HEAD' ] );
const sourceRef = process.env.GITHUB_REF_NAME || gitValue( [ 'branch', '--show-current' ] );
const sbom = {
	spdxVersion: 'SPDX-2.3',
	dataLicense: 'CC0-1.0',
	SPDXID: 'SPDXRef-DOCUMENT',
	name: `Cresco Canvas ${ version } release file inventory`,
	documentNamespace: `urn:cresco-canvas:${ version }:${ sourceCommit }`,
	creationInfo: {
		creators: [ 'Tool: Cresco Canvas release pipeline' ],
		created: '1980-01-01T00:00:00Z',
	},
	packages: [
		{
			name: 'cresco-canvas',
			SPDXID: 'SPDXRef-Package-Cresco-Canvas',
			versionInfo: version,
			downloadLocation: 'NOASSERTION',
			filesAnalyzed: true,
			licenseConcluded: 'GPL-2.0-or-later',
			licenseDeclared: 'GPL-2.0-or-later',
			copyrightText: 'NOASSERTION',
			checksums: [ { algorithm: 'SHA256', checksumValue: checksum } ],
		},
	],
	files: fileChecksums.map( ( item, index ) => ( {
		fileName: `./cresco-canvas/${ item.file }`,
		SPDXID: `SPDXRef-File-${ index + 1 }`,
		checksums: [ { algorithm: 'SHA256', checksumValue: item.sha256 } ],
		licenseConcluded: 'NOASSERTION',
		copyrightText: 'NOASSERTION',
	} ) ),
	relationships: fileChecksums.map( ( item, index ) => ( {
		spdxElementId: 'SPDXRef-Package-Cresco-Canvas',
		relationshipType: 'CONTAINS',
		relatedSpdxElement: `SPDXRef-File-${ index + 1 }`,
	} ) ),
};
await writeFile( path.join( outputDirectory, `${ archiveName }.spdx.json` ), `${ JSON.stringify( sbom, null, 2 ) }\n` );

const provenance = {
	schema: 'cresco-release-provenance/v1',
	artifact: archiveName,
	sha256: checksum,
	version,
	source: { commit: sourceCommit, ref: sourceRef },
	builder: process.env.GITHUB_ACTIONS === 'true' ? 'github-actions' : 'local',
	node: process.version,
	signed: false,
	signingStatus: 'not-configured',
};
await writeFile( path.join( outputDirectory, `${ archiveName }.provenance.json` ), `${ JSON.stringify( provenance, null, 2 ) }\n` );

process.stdout.write( `Created dist/${ archiveName } (${ archive.length } bytes)\nSHA-256: ${ checksum }\nFiles: ${ files.length }\n` );
