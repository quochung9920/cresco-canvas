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

// A small number of generated production runtimes are authored as reviewed,
// framework-free sources rather than webpack entries. They stay in `generated`
// so the integrity gate can distinguish the checked-in development loader from
// the exact clean-build output. A clean build always replaces that loader with
// the authoritative source before packaging.
let passthroughGenerated = 0;
for ( const [ output, sourcePath ] of Object.entries( manifest.generated || {} ) ) {
	if ( ! String( sourcePath ).startsWith( 'runtime-src/build/' ) ) continue;
	const source = path.join( root, sourcePath );
	const destination = path.join( root, 'build', output );
	await mkdir( path.dirname( destination ), { recursive: true } );
	await copyFile( source, destination );
	passthroughGenerated += 1;
}

process.stdout.write( `Restored ${ manifest.reviewed.length } reviewed runtime outputs and ${ passthroughGenerated } passthrough generated output(s) from authoritative source.\n` );
