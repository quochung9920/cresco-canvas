import { execFileSync } from 'node:child_process';

function wp( args: string[] ): string {
	const config = process.env.WP_ENV_CONFIG;
	const wpEnvArgs = [ 'wp-env' ];
	if ( config ) wpEnvArgs.push( `--config=${ config }` );
	wpEnvArgs.push( 'run', 'cli', 'wp', ...args );
	return execFileSync( 'npx', wpEnvArgs, {
		encoding: 'utf8',
		stdio: [ 'ignore', 'pipe', 'inherit' ],
		env: process.env,
	} ).trim();
}

export default function globalSetup() {
	wp( [ 'plugin', 'activate', 'cresco-canvas' ] );
	const fixtureIds = findFixtureIds();
	if ( fixtureIds.length > 0 ) wp( [ 'post', 'delete', ...fixtureIds, '--force' ] );
	for ( const fixture of [
		[ 'Cresco E2E Plain', 'cresco-e2e-plain', 'Plain Core content' ],
		[ 'Cresco E2E Canvas', 'cresco-e2e-canvas', '<!-- wp:cresco/container --><div class="wp-block-cresco-container cc-container"><p>Canvas fixture</p></div><!-- /wp:cresco/container -->' ],
		[ 'Cresco E2E Session', 'cresco-e2e-session', 'Session fallback content' ],
		[ 'Cresco E2E Foundation Session', 'cresco-e2e-foundation-session', 'Foundation fallback content' ],
	] ) {
		wp( [
			'post', 'create', '--post_type=page', '--post_status=publish',
			`--post_title=${ fixture[ 0 ] }`, `--post_name=${ fixture[ 1 ] }`, `--post_content=${ fixture[ 2 ] }`, '--porcelain',
		] );
	}
}

function findFixtureIds(): string[] {
	return [ 'cresco-e2e-plain', 'cresco-e2e-canvas', 'cresco-e2e-session', 'cresco-e2e-foundation-session' ].flatMap( ( slug ) => {
		const output = wp( [ 'post', 'list', '--post_type=page', `--name=${ slug }`, '--field=ID' ] );
		return output ? output.split( /\s+/ ) : [];
	} );
}
