import { readdirSync } from 'node:fs';
import { relative, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';
import process from 'node:process';

const root = resolve( process.cwd() );
const runtimeRoots = [
	resolve( root, 'build' ),
	resolve( root, 'runtime-src/build' ),
];

function collectJavaScriptFiles( directory ) {
	const files = [];
	let entries = [];

	try {
		entries = readdirSync( directory, { withFileTypes: true } );
	} catch ( error ) {
		return files;
	}

	for ( const entry of entries ) {
		const absolutePath = resolve( directory, entry.name );
		if ( entry.isDirectory() ) {
			files.push( ...collectJavaScriptFiles( absolutePath ) );
			continue;
		}
		if ( entry.isFile() && entry.name.toLowerCase().endsWith( '.js' ) ) {
			files.push( absolutePath );
		}
	}

	return files;
}

const runtimeFiles = runtimeRoots
	.flatMap( ( directory ) => collectJavaScriptFiles( directory ) )
	.sort( ( a, b ) => a.localeCompare( b ) );

if ( runtimeFiles.length === 0 ) {
	process.stderr.write( 'Runtime syntax check found no JavaScript runtime files. Refusing to pass an empty gate.\n' );
	process.exit( 1 );
}

const failures = [];
for ( const file of runtimeFiles ) {
	const result = spawnSync( process.execPath, [ '--check', file ], {
		cwd: root,
		encoding: 'utf8',
	} );
	if ( result.status !== 0 ) {
		failures.push( {
			file: relative( root, file ).replaceAll( '\\', '/' ),
			output: `${ result.stderr || '' }${ result.stdout || '' }`.trim(),
		} );
	}
}

if ( failures.length > 0 ) {
	process.stderr.write( `Runtime JavaScript syntax check failed in ${ failures.length } file(s):\n` );
	for ( const failure of failures ) {
		process.stderr.write( `\n--- ${ failure.file } ---\n` );
		process.stderr.write( `${ failure.output || 'node --check failed without diagnostic output.' }\n` );
	}
	process.exit( 1 );
}

process.stdout.write( `Runtime JavaScript syntax check passed for ${ runtimeFiles.length } file(s).\n` );
