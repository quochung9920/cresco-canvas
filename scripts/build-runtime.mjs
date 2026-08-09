import { copyFile, mkdir, readFile, rm } from 'node:fs/promises';
import path from 'node:path';

const root = process.cwd();
const manifest = JSON.parse( await readFile( path.join( root, 'runtime-src/manifest.json' ), 'utf8' ) );

// @wordpress/scripts emits an asset manifest for every webpack entry. This runtime
// is enqueued with explicit dependencies and intentionally does not ship that manifest.
await rm( path.join( root, 'build', 'widget-control-enhancements.asset.php' ), { force: true } );

for ( const file of manifest.reviewed ) {
	const source = path.join( root, 'runtime-src/build', file );
	const destination = path.join( root, 'build', file );
	await mkdir( path.dirname( destination ), { recursive: true } );
	await copyFile( source, destination );
}

process.stdout.write( `Restored ${ manifest.reviewed.length } reviewed runtime outputs from authoritative source.\n` );
