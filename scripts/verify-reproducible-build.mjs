import { createHash } from 'node:crypto';
import { readFile, rename, unlink } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';

const digest = async ( file ) =>
	createHash( 'sha256' )
		.update( await readFile( file ) )
		.digest( 'hex' );

const first = spawnSync( process.execPath, [ 'scripts/build-release.mjs' ], {
	stdio: 'inherit',
} );
if ( first.status !== 0 ) {
	process.exit( first.status ?? 1 );
}

const firstHash = await digest( 'dist/cresco-canvas.zip' );
await rename( 'dist/cresco-canvas.zip', 'dist/cresco-canvas.first.zip' );

const second = spawnSync( process.execPath, [ 'scripts/build-release.mjs' ], {
	stdio: 'inherit',
} );
if ( second.status !== 0 ) {
	process.exit( second.status ?? 1 );
}

const secondHash = await digest( 'dist/cresco-canvas.zip' );

if ( firstHash !== secondHash ) {
	throw new Error(
		`Release ZIP is not reproducible: ${ firstHash } !== ${ secondHash }`
	);
}

await unlink( 'dist/cresco-canvas.first.zip' );
process.stdout.write( `Reproducible ZIP verified: ${ secondHash }\n` );
