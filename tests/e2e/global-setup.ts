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
	const pageSlugs = [ 'cresco-e2e-plain', 'cresco-e2e-canvas', 'cresco-e2e-session', 'cresco-e2e-foundation-session' ];
	const pageIds = findFixtureIds( 'page', pageSlugs );
	if ( pageIds.length > 0 ) wp( [ 'post', 'delete', ...pageIds, '--force' ] );
	const themeIds = findFixtureIds( 'cresco_template', [ 'cresco-e2e-theme-header' ] );
	if ( themeIds.length > 0 ) wp( [ 'post', 'delete', ...themeIds, '--force' ] );

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

	const themeId = wp( [
		'post', 'create', '--post_type=cresco_template', '--post_status=publish',
		'--post_title=Cresco E2E Theme Header', '--post_name=cresco-e2e-theme-header', '--porcelain',
	] );
	wp( [ 'post', 'meta', 'update', themeId, '_cresco_template_type', 'header' ] );
}

function findFixtureIds( postType: string, slugs: string[] ): string[] {
	return slugs.flatMap( ( slug ) => {
		const output = wp( [ 'post', 'list', `--post_type=${ postType }`, `--name=${ slug }`, '--field=ID' ] );
		return output ? output.split( /\s+/ ) : [];
	} );
}
