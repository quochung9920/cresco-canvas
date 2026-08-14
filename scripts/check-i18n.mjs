/**
 * Verify the translation catalogue is present and current.
 *
 * The plugin advertises translation support in its header (`Domain Path:
 * /languages`) and loads a text domain at runtime. This gate keeps that promise
 * honest: it fails when the catalogue is missing, when it is not parseable, or
 * when sources have gained strings the catalogue does not carry.
 */

import { readFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';

const root = process.cwd();
const POT = 'languages/cresco-canvas.pot';
const errors = [];

if ( ! existsSync( path.join( root, POT ) ) ) {
	errors.push( `Missing translation catalogue: ${ POT }. Run \`npm run make:pot\`.` );
} else {
	const contents = await readFile( path.join( root, POT ), 'utf8' );

	for ( const header of [ 'X-Domain: cresco-canvas', 'Content-Type: text/plain; charset=UTF-8' ] ) {
		if ( ! contents.includes( header ) ) errors.push( `${ POT } is missing header: ${ header }` );
	}

	const committed = ( contents.match( /^msgid "/gm ) || [] ).length - 1;
	if ( committed < 1 ) errors.push( `${ POT } carries no translatable strings.` );

	// Regenerate into a scratch comparison by counting what the extractor finds
	// now. A drift means someone added user-facing strings without refreshing the
	// catalogue, which silently ships untranslatable UI.
	let reported = 0;
	try {
		const output = execFileSync( process.execPath, [ 'scripts/make-pot.mjs' ], {
			cwd: root,
			encoding: 'utf8',
		} );
		reported = Number( ( output.match( /\[make-pot\] (\d+) unique/ ) || [] )[ 1 ] || 0 );
	} catch ( error ) {
		errors.push( `Could not run the extractor: ${ error.message }` );
	}

	if ( reported && Math.abs( reported - committed ) > 0 ) {
		errors.push(
			`${ POT } is stale: sources yield ${ reported } string(s), catalogue has ${ committed }. Run \`npm run make:pot\`.`
		);
	}
}

if ( errors.length ) {
	process.stderr.write( `${ errors.join( '\n' ) }\n` );
	process.exit( 1 );
}
process.stdout.write( '[i18n] Translation catalogue present and current.\n' );
