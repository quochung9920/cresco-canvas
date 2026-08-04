import { execFileSync } from 'node:child_process';

function wp( args: string[] ): string {
	return execFileSync( 'npx', [ 'wp-env', 'run', 'cli', 'wp', ...args ], {
		encoding: 'utf8',
		stdio: [ 'ignore', 'pipe', 'inherit' ],
	} ).trim();
}

export default function globalSetup() {
	wp( [ 'plugin', 'activate', 'cresco-canvas' ] );
	const fixtureIds = findFixtureIds();
	if ( fixtureIds.length > 0 ) {
		wp( [ 'post', 'delete', ...fixtureIds, '--force' ] );
	}
	wp( [
		'post',
		'create',
		'--post_type=page',
		'--post_status=publish',
		'--post_title=Cresco E2E Plain',
		'--post_name=cresco-e2e-plain',
		'--post_content=Plain Core content',
		'--porcelain',
	] );
	wp( [
		'post',
		'create',
		'--post_type=page',
		'--post_status=publish',
		'--post_title=Cresco E2E Canvas',
		'--post_name=cresco-e2e-canvas',
		'--post_content=<!-- wp:cresco/container --><div class="wp-block-cresco-container cc-container"><p>Canvas fixture</p></div><!-- /wp:cresco/container -->',
		'--porcelain',
	] );
}

function findFixtureIds(): string[] {
	return [ 'cresco-e2e-plain', 'cresco-e2e-canvas' ].flatMap( ( slug ) => {
		const output = wp( [
			'post',
			'list',
			'--post_type=page',
			`--name=${ slug }`,
			'--field=ID',
		] );
		return output ? output.split( /\s+/ ) : [];
	} );
}
