import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';
import path from 'node:path';
import process from 'node:process';

const root = process.cwd();
const npm = process.platform === 'win32' ? 'npm.cmd' : 'npm';
const packageJson = JSON.parse( await readFile( path.join( root, 'package.json' ), 'utf8' ) );
const report = {
	schema: 'cresco-commercial-readiness-evidence/v1',
	startedAt: new Date().toISOString(),
	platform: process.platform,
	node: process.version,
	git: {},
	commands: [],
	builds: [],
	tests: {},
};

function run( command, args, label, options = {} ) {
	const started = Date.now();
	const result = spawnSync( command, args, {
		cwd: root,
		encoding: 'utf8',
		stdio: options.inherit ? 'inherit' : [ 'ignore', 'pipe', 'pipe' ],
		env: process.env,
	} );
	const item = {
		label,
		command: [ command, ...args ].join( ' ' ),
		exitCode: result.status ?? 1,
		durationMs: Date.now() - started,
		stdout: options.inherit ? '' : String( result.stdout || '' ),
		stderr: options.inherit ? '' : String( result.stderr || '' ),
		error: result.error ? result.error.message : '',
	};
	report.commands.push( item );
	return item;
}

function git( args ) {
	const result = spawnSync( 'git', args, { cwd: root, encoding: 'utf8' } );
	return result.status === 0 ? String( result.stdout || '' ).trim() : '';
}

function parseJestCount( output ) {
	const match = output.match( /Tests:\s+(?:(\d+) failed,\s*)?(?:(\d+) skipped,\s*)?(?:(\d+) passed,\s*)?(\d+) total/i );
	return match ? { failed: Number( match[ 1 ] || 0 ), skipped: Number( match[ 2 ] || 0 ), passed: Number( match[ 3 ] || 0 ), total: Number( match[ 4 ] || 0 ) } : null;
}

function parsePhpunitCount( output ) {
	const tests = output.match( /Tests:\s*(\d+)/i );
	const assertions = output.match( /Assertions:\s*(\d+)/i );
	return tests ? { total: Number( tests[ 1 ] ), assertions: Number( assertions?.[ 1 ] || 0 ) } : null;
}

function buildStatus() {
	return git( [ 'status', '--porcelain', '--', 'build/' ] );
}

report.git.sha = git( [ 'rev-parse', 'HEAD' ] );
report.git.branch = git( [ 'branch', '--show-current' ] );
report.git.initialStatus = git( [ 'status', '--porcelain' ] );

const checkScripts = Object.keys( packageJson.scripts || {} )
	.filter( ( name ) => name.startsWith( 'check:' ) && name !== 'check:quality' )
	.sort();

for ( const name of checkScripts ) {
	run( npm, [ 'run', name ], name );
}

const cssLint = process.platform === 'win32'
	? run( 'bash', [ '-lc', "npx wp-scripts lint-style 'assets/css/**/*.css' 'blocks/**/*.css' 'src/**/*.scss'" ], 'lint:css (bash)' )
	: run( npm, [ 'run', 'lint:css' ], 'lint:css' );
void cssLint;

const jsTests = run( npm, [ 'run', 'test:unit' ], 'test:unit' );
report.tests.javascript = parseJestCount( `${ jsTests.stdout }\n${ jsTests.stderr }` );

const phpTests = run( npm, [ 'run', 'test:php' ], 'test:php' );
report.tests.php = parsePhpunitCount( `${ phpTests.stdout }\n${ phpTests.stderr }` );

for ( let pass = 1; pass <= 2; pass += 1 ) {
	const before = buildStatus();
	const build = run( npm, [ 'run', 'build' ], `build:${ pass }` );
	const after = buildStatus();
	report.builds.push( {
		pass,
		exitCode: build.exitCode,
		buildStatusBefore: before,
		buildStatusAfter: after,
		clean: after === '',
	} );
}

run( npm, [ 'run', 'check:quality' ], 'check:quality' );

report.finishedAt = new Date().toISOString();
report.git.finalStatus = git( [ 'status', '--porcelain' ] );
report.success = report.commands.every( ( item ) => item.exitCode === 0 ) && report.builds.every( ( item ) => item.clean );

const stamp = report.startedAt.replace( /[:.]/g, '-' );
const outputDir = path.join( root, 'artifacts', 'commercial-readiness' );
await mkdir( outputDir, { recursive: true } );
const jsonPath = path.join( outputDir, `${ stamp }.json` );
const markdownPath = path.join( outputDir, `${ stamp }.md` );
await writeFile( jsonPath, `${ JSON.stringify( report, null, 2 ) }\n` );

const rows = report.commands
	.map( ( item ) => `| \`${ item.label }\` | ${ item.exitCode } | ${ item.durationMs } |` )
	.join( '\n' );
const buildRows = report.builds
	.map( ( item ) => `| ${ item.pass } | ${ item.exitCode } | ${ item.clean ? 'yes' : 'NO' } | \`${ item.buildStatusAfter.replaceAll( '\n', '<br>' ) }\` |` )
	.join( '\n' );
const markdown = `# Cresco commercial-readiness evidence\n\n- Started: ${ report.startedAt }\n- Finished: ${ report.finishedAt }\n- Branch: \`${ report.git.branch }\`\n- Commit: \`${ report.git.sha }\`\n- Overall: **${ report.success ? 'PASS' : 'FAIL' }**\n\n## Gates and tests\n\n| Command | Exit | ms |\n| --- | ---: | ---: |\n${ rows }\n\n## Reproducible build check\n\n| Pass | Exit | build/ clean | build/ status |\n| ---: | ---: | --- | --- |\n${ buildRows }\n\n## Test counts\n\n- JavaScript: ${ JSON.stringify( report.tests.javascript ) }\n- PHP: ${ JSON.stringify( report.tests.php ) }\n`;
await writeFile( markdownPath, markdown );

process.stdout.write( `${ markdown }\nEvidence: ${ path.relative( root, markdownPath ) }\n` );
process.exitCode = report.success ? 0 : 1;
