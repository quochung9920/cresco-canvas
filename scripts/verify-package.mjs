import { createHash } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { unzipSync } from 'fflate';
import { collectReleaseFiles, forbiddenPackageFragments } from './release-files.mjs';

const root = process.cwd();
const packageJson = JSON.parse( await readFile( path.join( root, 'package.json' ), 'utf8' ) );
const version = packageJson.version;
const archiveName = `cresco-canvas-${ version }.zip`;
const archivePath = path.join( root, 'dist', archiveName );
const archive = await readFile( archivePath );
const checksum = createHash( 'sha256' ).update( archive ).digest( 'hex' );
const expectedFiles = ( await collectReleaseFiles( root ) ).map( ( file ) => `cresco-canvas/${ file }` ).sort();
const entries = Object.keys( unzipSync( new Uint8Array( archive ) ) ).filter( ( entry ) => ! entry.endsWith( '/' ) ).sort();
const errors = [];

if ( JSON.stringify( entries ) !== JSON.stringify( expectedFiles ) ) {
	const actual = new Set( entries );
	const expected = new Set( expectedFiles );
	for ( const file of expectedFiles ) if ( ! actual.has( file ) ) errors.push( `Missing release file: ${ file }` );
	for ( const file of entries ) if ( ! expected.has( file ) ) errors.push( `Unexpected release file: ${ file }` );
}

for ( const entry of entries ) {
	const normalized = `/${ entry }`;
	for ( const fragment of forbiddenPackageFragments ) {
		if ( normalized.includes( fragment ) ) errors.push( `Forbidden release path: ${ entry }` );
	}
}

const checksums = await readFile( path.join( root, 'dist', 'SHA256SUMS' ), 'utf8' );
if ( checksums.trim() !== `${ checksum }  ${ archiveName }` ) errors.push( 'SHA256SUMS does not match the exact release ZIP.' );

const sbom = JSON.parse( await readFile( path.join( root, 'dist', `${ archiveName }.spdx.json` ), 'utf8' ) );
if ( sbom.spdxVersion !== 'SPDX-2.3' ) errors.push( 'SBOM is not SPDX 2.3.' );
if ( ! Array.isArray( sbom.files ) || sbom.files.length !== expectedFiles.length ) errors.push( 'SBOM file inventory does not match release inventory.' );

const provenance = JSON.parse( await readFile( path.join( root, 'dist', `${ archiveName }.provenance.json` ), 'utf8' ) );
if ( provenance.sha256 !== checksum || provenance.artifact !== archiveName || provenance.version !== version ) {
	errors.push( 'Provenance metadata does not identify the exact release ZIP.' );
}
if ( provenance.signed !== false || provenance.signingStatus !== 'not-configured' ) errors.push( 'Provenance must not claim a signature that does not exist.' );

if ( errors.length ) {
	process.stderr.write( `${ errors.join( '\n' ) }\n` );
	process.exit( 1 );
}

process.stdout.write( `Release package verified: ${ archiveName}\nSHA-256: ${ checksum }\nEntries: ${ entries.length }\n` );
