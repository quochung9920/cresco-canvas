import { createHash } from 'node:crypto';
import { mkdir, readFile, readdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { zipSync } from 'fflate';

const root = process.cwd();
const outputDirectory = path.join( root, 'dist' );
const archivePath = path.join( outputDirectory, 'cresco-canvas.zip' );
const fixedDate = new Date( '1980-01-01T00:00:00.000Z' );

const requiredFiles = [
	'cresco-canvas.php',
	'uninstall.php',
	'README.md',
	'CHANGELOG.md',
	'LICENSE',
	'assets/css/design-system.css',
	'assets/css/dynamic.css',
	'assets/css/dynamic-advanced.css',
	'assets/css/dynamic-alpha4.css',
	'assets/css/preview.css',
	'assets/css/templates.css',
	'assets/css/theme-builder.css',
	'build/design-system.js',
	'build/design-system.asset.php',
	'build/dynamic.js',
	'build/dynamic.asset.php',
	'build/dynamic-advanced.js',
	'build/dynamic-advanced.asset.php',
	'build/dynamic-alpha4.js',
	'build/dynamic-alpha4.asset.php',
	'build/editor.js',
	'build/editor.asset.php',
	'build/editor.css',
	'build/container.js',
	'build/container.asset.php',
	'build/preview.js',
	'build/preview.asset.php',
	'build/templates.js',
	'build/templates.asset.php',
	'build/theme-builder.js',
	'build/theme-builder.asset.php',
	'includes/Theme/renderer.php',
	'vendor/autoload.php',
];

const allowedRoots = [
	'assets/css/design-system.css',
	'assets/css/dynamic.css',
	'assets/css/dynamic-advanced.css',
	'assets/css/dynamic-alpha4.css',
	'assets/css/frontend.css',
	'assets/css/preview.css',
	'assets/css/templates.css',
	'assets/css/theme-builder.css',
	'blocks',
	'build',
	'docs',
	'includes',
	'vendor',
];

async function walk( relativePath ) {
	const absolutePath = path.join( root, relativePath );
	const entries = await readdir( absolutePath, { withFileTypes: true } );
	const files = [];

	for ( const entry of entries.sort( ( left, right ) =>
		left.name.localeCompare( right.name )
	) ) {
		const child = path.join( relativePath, entry.name );
		if ( entry.isDirectory() ) {
			files.push( ...( await walk( child ) ) );
		} else if ( entry.isFile() ) {
			files.push( child );
		}
	}

	return files;
}

for ( const file of requiredFiles ) {
	await readFile( path.join( root, file ) );
}

const files = [
	'cresco-canvas.php',
	'uninstall.php',
	'README.md',
	'CHANGELOG.md',
	'LICENSE',
];

for ( const allowedRoot of allowedRoots ) {
	if ( path.extname( allowedRoot ) ) {
		files.push( allowedRoot );
	} else {
		files.push( ...( await walk( allowedRoot ) ) );
	}
}

const archiveEntries = {};

for ( const file of [ ...new Set( files ) ].sort() ) {
	if ( file.endsWith( '.map' ) || file.includes( '/tests/' ) ) {
		continue;
	}

	const archiveName = `cresco-canvas/${ file.replaceAll( path.sep, '/' ) }`;
	archiveEntries[ archiveName ] = [
		new Uint8Array( await readFile( path.join( root, file ) ) ),
		{ mtime: fixedDate },
	];
}

const archive = zipSync( archiveEntries, { level: 9 } );
const checksum = createHash( 'sha256' ).update( archive ).digest( 'hex' );

await mkdir( outputDirectory, { recursive: true } );
await writeFile( archivePath, archive );
await writeFile(
	`${ archivePath }.sha256`,
	`${ checksum }  cresco-canvas.zip\n`
);

process.stdout.write(
	`Created dist/cresco-canvas.zip (${ archive.length } bytes)\nSHA-256: ${ checksum }\n`
);
