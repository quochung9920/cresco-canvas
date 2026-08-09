import { readdirSync } from 'node:fs';
import { relative, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';

const root = resolve( process.cwd() );
const phpBinary = process.env.PHP_BINARY || 'php';
const excludedDirectories = new Set( [
	'.git',
	'node_modules',
	'vendor',
	'coverage',
	'artifacts',
	'test-results',
	'playwright-report',
] );

function collectPhpFiles( directory ) {
	const files = [];

	for ( const entry of readdirSync( directory, { withFileTypes: true } ) ) {
		if ( entry.isDirectory() && excludedDirectories.has( entry.name ) ) {
			continue;
		}

		const absolutePath = resolve( directory, entry.name );

		if ( entry.isDirectory() ) {
			files.push( ...collectPhpFiles( absolutePath ) );
			continue;
		}

		if ( entry.isFile() && entry.name.toLowerCase().endsWith( '.php' ) ) {
			files.push( absolutePath );
		}
	}

	return files;
}

const probe = spawnSync( phpBinary, [ '-v' ], { encoding: 'utf8' } );
if ( probe.error || probe.status !== 0 ) {
	process.stderr.write( `PHP syntax check requires a working PHP CLI. Tried: ${ phpBinary }\n` );
	if ( probe.error ) {
		process.stderr.write( `${ probe.error.message }\n` );
	} else if ( probe.stderr ) {
		process.stderr.write( `${ probe.stderr.trim() }\n` );
	}
	process.exit( 1 );
}

const phpFiles = collectPhpFiles( root ).sort( ( a, b ) => a.localeCompare( b ) );
if ( phpFiles.length === 0 ) {
	process.stderr.write( 'PHP syntax check found no PHP files. Refusing to pass an empty gate.\n' );
	process.exit( 1 );
}

const failures = [];
for ( const file of phpFiles ) {
	const result = spawnSync( phpBinary, [ '-l', file ], { encoding: 'utf8' } );
	if ( result.status !== 0 ) {
		failures.push( {
			file: relative( root, file ).replaceAll( '\\', '/' ),
			output: `${ result.stdout || '' }${ result.stderr || '' }`.trim(),
		} );
	}
}

if ( failures.length > 0 ) {
	process.stderr.write( `PHP syntax check failed in ${ failures.length } file(s):\n` );
	for ( const failure of failures ) {
		process.stderr.write( `\n--- ${ failure.file } ---\n` );
		process.stderr.write( `${ failure.output || 'php -l failed without diagnostic output.' }\n` );
	}
	process.exit( 1 );
}

process.stdout.write( `PHP syntax check passed for ${ phpFiles.length } file(s).\n` );
