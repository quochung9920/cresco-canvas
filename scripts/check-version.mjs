import { readFile } from 'node:fs/promises';

const packageJson = JSON.parse( await readFile( 'package.json', 'utf8' ) );
const plugin = await readFile( 'cresco-canvas.php', 'utf8' );
const block = JSON.parse(
	await readFile( 'blocks/container/block.json', 'utf8' )
);
const changelog = await readFile( 'CHANGELOG.md', 'utf8' );
const version = packageJson.version;

const failures = [];

if ( ! plugin.includes( `Version:           ${ version }` ) ) {
	failures.push( 'Plugin header version differs from package.json.' );
}

if ( ! plugin.includes( `CRESCO_CANVAS_VERSION', '${ version }'` ) ) {
	failures.push( 'CRESCO_CANVAS_VERSION differs from package.json.' );
}

if ( block.version !== version ) {
	failures.push( 'Container block version differs from package.json.' );
}

if ( ! changelog.includes( `## [${ version }]` ) ) {
	failures.push( 'CHANGELOG.md has no entry for the package version.' );
}

if ( failures.length > 0 ) {
	throw new Error( failures.join( '\n' ) );
}

process.stdout.write( `Version consistency verified: ${ version }\n` );
