import { createHash } from 'node:crypto';
import { access, readFile } from 'node:fs/promises';
import path from 'node:path';
import { buildFiles } from './release-files.mjs';

const root = process.cwd();
const manifest = JSON.parse( await readFile( path.join( root, 'runtime-src/manifest.json' ), 'utf8' ) );
const errors = [];
const hash = ( bytes ) => createHash( 'sha256' ).update( bytes ).digest( 'hex' );

for ( const file of manifest.reviewed ) {
	try {
		const source = await readFile( path.join( root, 'runtime-src/build', file ) );
		const output = await readFile( path.join( root, 'build', file ) );
		if ( hash( source ) !== hash( output ) ) errors.push( `Reviewed runtime differs from source: build/${ file }` );
		if ( file.endsWith( '.js' ) ) {
			const sourceText = source.toString( 'utf8' );
			if ( sourceText.includes( '//# sourceMappingURL=' ) || sourceText.includes( '/*# sourceMappingURL=' ) ) {
				errors.push( `Reviewed runtime must not reference source maps: ${ file }` );
			}
		}
	} catch ( error ) {
		errors.push( `Missing reviewed runtime source/output for ${ file }: ${ error.message }` );
	}
}

for ( const [ output, source ] of Object.entries( manifest.generated ) ) {
	try {
		await access( path.join( root, source ) );
		await access( path.join( root, 'build', output ) );
	} catch ( error ) {
		errors.push( `Generated runtime ownership is incomplete for build/${ output } <- ${ source }: ${ error.message }` );
		continue;
	}
	// A generated entry sourced from `runtime-src/build/` is copied verbatim by
	// scripts/build-runtime.mjs, so it needs the same parity as a reviewed entry.
	// Existence alone let build/standalone-ai-bridge.js be edited directly and
	// drift a full feature ahead of its source; the next `npm run build` would
	// have silently reverted the shipped runtime. Entries owned by webpack are
	// compiled, not copied, so they are exempt.
	if ( ! source.startsWith( 'runtime-src/build/' ) ) continue;
	const sourceBytes = await readFile( path.join( root, source ) );
	const outputBytes = await readFile( path.join( root, 'build', output ) );
	if ( hash( sourceBytes ) !== hash( outputBytes ) ) {
		errors.push( `Generated runtime differs from its copied source: build/${ output } != ${ source }` );
	}
}

const owned = new Set( [
	...manifest.reviewed.map( ( file ) => `build/${ file }` ),
	...Object.keys( manifest.generated ).map( ( file ) => `build/${ file }` ),
] );
for ( const file of buildFiles ) {
	if ( ! owned.has( file ) ) errors.push( `Production runtime has no authoritative source owner: ${ file }` );
}

for ( const file of owned ) {
	if ( ! buildFiles.includes( file ) ) errors.push( `Runtime manifest owns a build output that is not in the production allowlist: ${ file }` );
}

if ( errors.length ) {
	process.stderr.write( `${ errors.join( '\n' ) }\n` );
	process.exit( 1 );
}

process.stdout.write( `Build integrity verified: ${ manifest.reviewed.length } reviewed outputs match source and ${ Object.keys( manifest.generated ).length } webpack outputs have authoritative entries.\n` );
