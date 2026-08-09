import { createServer } from 'node:http';
import { spawn } from 'node:child_process';
import { mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';

const root = process.cwd();
const packageJson = JSON.parse( await readFile( 'package.json', 'utf8' ) );
const archiveName = `cresco-canvas-${ packageJson.version }.zip`;
const archivePath = path.join( root, 'dist', archiveName );
const archive = await readFile( archivePath );
const tempRoot = path.join( root, 'dist', '.zip-install-smoke' );
const configPath = path.join( tempRoot, 'wp-env.json' );
const npx = process.platform === 'win32' ? 'npx.cmd' : 'npx';
const wpCore = process.env.CRESCO_RELEASE_WP_CORE || 'WordPress/WordPress#7.0.2';
const phpVersion = process.env.CRESCO_RELEASE_PHP || '8.3';
const wpPort = process.env.CRESCO_RELEASE_PORT || '8890';

function run( command, args, env = {} ) {
	return new Promise( ( resolve, reject ) => {
		const child = spawn( command, args, {
			cwd: root,
			stdio: 'inherit',
			env: { ...process.env, ...env },
		} );
		child.on( 'error', reject );
		child.on( 'close', ( code ) => code === 0 ? resolve() : reject( new Error( `${ command } ${ args.join( ' ' ) } exited ${ code }` ) ) );
	} );
}

await rm( tempRoot, { recursive: true, force: true } );
await mkdir( tempRoot, { recursive: true } );
const server = createServer( ( request, response ) => {
	if ( request.url !== `/${ archiveName }` ) {
		response.writeHead( 404 );
		response.end( 'Not found' );
		return;
	}
	response.writeHead( 200, { 'content-type': 'application/zip', 'content-length': archive.length } );
	response.end( archive );
} );
await new Promise( ( resolve, reject ) => {
	server.once( 'error', reject );
	server.listen( 0, '127.0.0.1', resolve );
} );
const address = server.address();
if ( ! address || typeof address === 'string' ) throw new Error( 'Could not allocate local ZIP server port.' );

const config = {
	core: wpCore,
	phpVersion,
	plugins: [ `http://127.0.0.1:${ address.port }/${ archiveName }` ],
	config: { WP_DEBUG: true, WP_DEBUG_LOG: true, SCRIPT_DEBUG: false },
};
await writeFile( configPath, `${ JSON.stringify( config, null, 2 ) }\n` );
const commonEnv = {
	WP_ENV_PORT: wpPort,
	WP_ENV_HOME: path.join( tempRoot, 'wp-env-home' ),
};
const configArg = `--config=${ configPath }`;

try {
	await run( npx, [ 'wp-env', configArg, 'start', '--update' ], commonEnv );
	await run( npx, [ 'wp-env', configArg, 'run', 'cli', 'wp', 'plugin', 'status', 'cresco-canvas' ], commonEnv );
	await run( npx, [ 'playwright', 'test', 'tests/e2e/release-zip.spec.ts', '--project=chromium' ], {
		...commonEnv,
		WP_BASE_URL: `http://127.0.0.1:${ wpPort }`,
		WP_ENV_CONFIG: configPath,
		CRESCO_RELEASE_ZIP_SMOKE: '1',
	} );
	process.stdout.write( `Exact ZIP install smoke passed for ${ archiveName }.\n` );
} finally {
	try {
		await run( npx, [ 'wp-env', configArg, 'destroy' ], commonEnv );
	} catch ( error ) {
		process.stderr.write( `wp-env cleanup warning: ${ error.message }\n` );
	}
	await new Promise( ( resolve ) => server.close( resolve ) );
}
