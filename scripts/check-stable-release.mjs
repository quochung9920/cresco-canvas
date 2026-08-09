import { readFile } from 'node:fs/promises';

const packageJson = JSON.parse( await readFile( 'package.json', 'utf8' ) );
const version = packageJson.version;

if ( version.includes( '-' ) ) {
	process.stdout.write( `Stable release evidence gate not applicable to prerelease version ${ version }.\n` );
	process.exit( 0 );
}

const evidencePath = `release-evidence/${ version }.json`;
let evidence;
try {
	evidence = JSON.parse( await readFile( evidencePath, 'utf8' ) );
} catch ( error ) {
	throw new Error( `Stable version ${ version } requires ${ evidencePath }: ${ error.message }` );
}

const requiredP0 = [
	'build',
	'unit',
	'phpunit',
	'lint',
	'e2e',
	'package',
	'wordpressMatrix',
	'phpMatrix',
	'browserMatrix',
	'accessibilityAutomated',
	'accessibilityManual',
	'performance',
	'upgrade',
	'cleanZipInstall',
	'securityDependencies',
	'documentation',
];
const failures = [];
for ( const gate of requiredP0 ) {
	if ( evidence.gates?.[ gate ]?.status !== 'pass' ) failures.push( `${ gate } is not pass` );
}
if ( evidence.version !== version ) failures.push( 'evidence version does not match package version' );
if ( ! /^[0-9a-f]{40}$/i.test( evidence.sourceCommit || '' ) ) failures.push( 'sourceCommit is missing or invalid' );
if ( ! /^[0-9a-f]{64}$/i.test( evidence.artifact?.sha256 || '' ) ) failures.push( 'exact release artifact SHA-256 is missing or invalid' );
if ( evidence.manualVerificationConfirmed !== true ) failures.push( 'manual verification is not explicitly confirmed' );

if ( failures.length ) throw new Error( `Stable release evidence is incomplete:\n${ failures.join( '\n' ) }` );
process.stdout.write( `Stable release evidence verified for ${ version }.\n` );
