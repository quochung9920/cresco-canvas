import { createServer } from 'node:http';
import { spawn } from 'node:child_process';
import { mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';

const root = process.cwd();
const packageJson = JSON.parse( await readFile( 'package.json', 'utf8' ) );
const candidateName = `cresco-canvas-${ packageJson.version }.zip`;
const candidatePath = path.join( root, 'dist', candidateName );
const historicalSha = process.env.CRESCO_UPGRADE_FROM_SHA || 'e23103c98a7fce013313fd96263cb469aff211f7';
const historicalName = 'cresco-canvas-0.9.0-rc.1-fixture.zip';
const tempRoot = path.join( root, 'dist', '.upgrade-smoke' );
const historicalPath = path.join( tempRoot, historicalName );
const configPath = path.join( tempRoot, 'wp-env.json' );
const npx = process.platform === 'win32' ? 'npx.cmd' : 'npx';
const wpPort = process.env.CRESCO_UPGRADE_PORT || '8891';
const commonEnv = {
	WP_ENV_PORT: wpPort,
	WP_ENV_HOME: path.join( tempRoot, 'wp-env-home' ),
};

function run( command, args, options = {} ) {
	return new Promise( ( resolve, reject ) => {
		const child = spawn( command, args, {
			cwd: root,
			stdio: options.capture ? [ 'ignore', 'pipe', 'inherit' ] : 'inherit',
			env: { ...process.env, ...commonEnv, ...( options.env || {} ) },
		} );
		let output = '';
		if ( options.capture && child.stdout ) child.stdout.on( 'data', ( chunk ) => { output += chunk.toString(); } );
		child.on( 'error', reject );
		child.on( 'close', ( code ) => code === 0 ? resolve( output.trim() ) : reject( new Error( `${ command } ${ args.join( ' ' ) } exited ${ code }` ) ) );
	} );
}

async function writeConfig( pluginUrl ) {
	const config = {
		core: process.env.CRESCO_RELEASE_WP_CORE || 'WordPress/WordPress#7.0.2',
		phpVersion: process.env.CRESCO_RELEASE_PHP || '8.3',
		plugins: [ pluginUrl ],
		config: { WP_DEBUG: true, WP_DEBUG_LOG: true },
	};
	await writeFile( configPath, `${ JSON.stringify( config, null, 2 ) }\n` );
}

await readFile( candidatePath );
await rm( tempRoot, { recursive: true, force: true } );
await mkdir( tempRoot, { recursive: true } );
await run( 'git', [ 'archive', '--format=zip', '--prefix=cresco-canvas/', `--output=${ historicalPath }`, historicalSha ] );
const historical = await readFile( historicalPath );
const candidate = await readFile( candidatePath );
const payloads = new Map( [ [ `/${ historicalName }`, historical ], [ `/${ candidateName }`, candidate ] ] );
const server = createServer( ( request, response ) => {
	const payload = payloads.get( request.url || '' );
	if ( ! payload ) {
		response.writeHead( 404 );
		response.end( 'Not found' );
		return;
	}
	response.writeHead( 200, { 'content-type': 'application/zip', 'content-length': payload.length } );
	response.end( payload );
} );
await new Promise( ( resolve, reject ) => {
	server.once( 'error', reject );
	server.listen( 0, '127.0.0.1', resolve );
} );
const address = server.address();
if ( ! address || typeof address === 'string' ) throw new Error( 'Could not allocate upgrade fixture server port.' );
const configArg = `--config=${ configPath }`;
const wpArgs = ( args ) => [ 'wp-env', configArg, 'run', 'cli', 'wp', ...args ];

try {
	await writeConfig( `http://127.0.0.1:${ address.port }/${ historicalName }` );
	await run( npx, [ 'wp-env', configArg, 'start', '--update' ] );
	await run( npx, wpArgs( [ 'plugin', 'status', 'cresco-canvas' ] ) );
	const seed = [
		'$settings = array("primary" => "#123456", "text" => "#112233", "containerMax" => 1440);',
		'update_option("cresco_canvas_settings", $settings, false);',
		'update_option("cresco_canvas_db_version", 1, false);',
		'$id = wp_insert_post(array("post_type" => "page", "post_status" => "publish", "post_title" => "Cresco Upgrade Fixture"));',
		'$session = array("schema" => "cresco-session/v1", "version" => 1, "documentId" => "upgrade-fixture", "nodes" => array());',
		'update_post_meta($id, "_cresco_canvas_document", wp_json_encode($session));',
		'update_post_meta($id, "_cresco_canvas_enabled", true);',
		'echo $id;',
	].join( ' ' );
	const pageId = await run( npx, wpArgs( [ 'eval', seed ] ), { capture: true } );
	if ( ! /^\d+$/.test( pageId ) ) throw new Error( `Could not create upgrade fixture page: ${ pageId }` );

	await writeConfig( `http://127.0.0.1:${ address.port }/${ candidateName }` );
	await run( npx, [ 'wp-env', configArg, 'start', '--update' ] );
	await run( npx, wpArgs( [ 'plugin', 'status', 'cresco-canvas' ] ) );
	const verify = [
		'if (class_exists("CrescoCanvas\\Migration\\Migrator")) { CrescoCanvas\\Migration\\Migrator::maybe_run(); }',
		'$settings = get_option("cresco_canvas_settings", array());',
		'if (!is_array($settings) || ($settings["primary"] ?? "") !== "#123456") { fwrite(STDERR, "settings not preserved\\n"); exit(21); }',
		`$session = get_post_meta(${ pageId }, "_cresco_canvas_document", true);`,
		'if (strpos((string) $session, "upgrade-fixture") === false) { fwrite(STDERR, "session not preserved\\n"); exit(22); }',
		'if ((int) get_option("cresco_canvas_db_version", 0) !== (int) CRESCO_CANVAS_SCHEMA_VERSION) { fwrite(STDERR, "migration version incomplete\\n"); exit(23); }',
		'echo CRESCO_CANVAS_VERSION;',
	].join( ' ' );
	const upgradedVersion = await run( npx, wpArgs( [ 'eval', verify ] ), { capture: true } );
	if ( upgradedVersion !== packageJson.version ) throw new Error( `Candidate version mismatch after upgrade: ${ upgradedVersion }` );
	process.stdout.write( `Upgrade smoke passed: ${ historicalSha } (0.9.0-rc.1) -> ${ packageJson.version }; fixture page ${ pageId } preserved.\n` );
} finally {
	try {
		await run( npx, [ 'wp-env', configArg, 'destroy' ] );
	} catch ( error ) {
		process.stderr.write( `wp-env cleanup warning: ${ error.message }\n` );
	}
	await new Promise( ( resolve ) => server.close( resolve ) );
}
