import { createHash } from 'node:crypto';
import { readFile, rename, unlink } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';

const packageJson = JSON.parse( await readFile( 'package.json', 'utf8' ) );
const archiveName = `cresco-canvas-${ packageJson.version }.zip`;
const archivePath = `dist/${ archiveName }`;
const digest = async ( file ) => createHash( 'sha256' ).update( await readFile( file ) ).digest( 'hex' );

function build() {
	const result = spawnSync( process.execPath, [ 'scripts/build-release.mjs' ], { stdio: 'inherit' } );
	if ( result.status !== 0 ) process.exit( result.status ?? 1 );
}

build();
const firstHash = await digest( archivePath );
const firstPath = `dist/${ archiveName }.first`;
await rename( archivePath, firstPath );
build();
const secondHash = await digest( archivePath );

if ( firstHash !== secondHash ) throw new Error( `Release ZIP is not reproducible in one clean workspace: ${ firstHash } !== ${ secondHash }` );
await unlink( firstPath );
process.stdout.write( `Deterministic package verified in one workspace: ${ secondHash }\n` );
