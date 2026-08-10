import { createHash } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';
import process from 'node:process';

const errors = [];
const read = ( file ) => readFile( file, 'utf8' );
const hash = ( value ) => createHash( 'sha256' ).update( value ).digest( 'hex' );
const contractFiles = [
	'contracts/document/v1.schema.json',
	'contracts/scope/v1.schema.json',
	'contracts/command/v1.schema.json',
	'contracts/ai-context/v2.schema.json',
];
for ( const file of contractFiles ) {
	try { JSON.parse( await read( file ) ); } catch ( error ) { errors.push( `${ file }: ${ error.message }` ); }
}
const phpFiles = [
	'includes/Core/Document/Document.php',
	'includes/Core/Scope/ScopeEngine.php',
	'includes/Core/Context/ContextEngine.php',
	'includes/Core/Command/CommandBus.php',
	'includes/Core/UI/UiRegistry.php',
	'includes/Core/Widget/WidgetRegistry.php',
	'includes/Core/Storage/DocumentRepository.php',
	'includes/Core/Module/ModuleRegistry.php',
	'includes/Infrastructure/WordPress/Storage/WordPressDocumentRepository.php',
	'includes/Rendering/RenderEngine.php',
	'includes/Application/BuilderArchitecture.php',
];
for ( const file of phpFiles ) {
	const result = spawnSync( 'php', [ '-l', file ], { encoding: 'utf8' } );
	if ( result.status !== 0 ) errors.push( `${ file }: ${ result.stderr || result.stdout || 'PHP syntax check failed' }` );
}
const source = await read( 'runtime-src/build/website-builder-architecture.js' );
const build = await read( 'build/website-builder-architecture.js' );
for ( const file of [ 'runtime-src/build/website-builder-architecture.js', 'build/website-builder-architecture.js', 'scripts/check-core-architecture.mjs' ] ) {
	const result = spawnSync( process.execPath, [ '--check', file ], { encoding: 'utf8' } );
	if ( result.status !== 0 ) errors.push( `${ file }: ${ result.stderr || result.stdout || 'JavaScript syntax check failed' }` );
}
if ( hash( source ) !== hash( build ) ) errors.push( 'Architecture runtime source/build mismatch.' );
const phpCorpus = ( await Promise.all( phpFiles.map( read ) ) ).join( '\n' );
for ( const token of [ 'cresco-ai-context/v2', 'ScopeEngine', 'CommandBus', 'RenderEngine', 'UiRegistry', 'WidgetRegistry', 'DocumentRepository', 'ModuleRegistry', 'cresco-command/v1', 'cresco_command_scope_mismatch' ] ) {
	if ( ! phpCorpus.includes( token ) ) errors.push( `Architecture PHP missing ${ token }` );
}
for ( const token of [ 'metaKey||e.ctrlKey', 'crescoBuilderArchitecture', 'addCommand', 'data-cresco-zone', 'Scoped AI', 'Authoritative Renderer Preview', 'Cresco Documents', 'selection' ] ) {
	if ( ! source.includes( token ) ) errors.push( `Architecture runtime missing ${ token }` );
}
const plugin = await read( 'includes/Plugin.php' );
if ( ! plugin.includes( 'BuilderArchitecture' ) || ! plugin.includes( '( new BuilderArchitecture() )->register();' ) ) errors.push( 'Plugin does not register BuilderArchitecture.' );
const release = await read( 'scripts/release-files.mjs' );
for ( const token of [ "'docs/CORE_ARCHITECTURE.md'", "'assets/css/website-builder-architecture.css'", "'build/website-builder-architecture.js'", "walkFiles( root, 'contracts'" ] ) if ( ! release.includes( token ) ) errors.push( `Release inventory missing ${ token }` );
const manifest = JSON.parse( await read( 'runtime-src/manifest.json' ) );
if ( ! manifest.reviewed.includes( 'website-builder-architecture.js' ) ) errors.push( 'Runtime manifest does not review website-builder-architecture.js.' );
const packageJson = JSON.parse( await read( 'package.json' ) );
if ( packageJson.scripts?.[ 'check:architecture' ] !== 'node scripts/check-core-architecture.mjs' ) errors.push( 'package.json is missing check:architecture.' );
if ( ! String( packageJson.scripts?.[ 'check:quality' ] || '' ).includes( 'check:architecture' ) ) errors.push( 'check:quality does not include check:architecture.' );
const docs = await read( 'docs/CORE_ARCHITECTURE.md' );
for ( const token of [ 'One document model', 'One mutation path', 'Scoped AI', 'Editor UX shell', 'Compatibility policy' ] ) if ( ! docs.includes( token ) ) errors.push( `Architecture documentation missing ${ token }` );
if ( /ComprehensiveV4|ProfessionalUxV4|WebsiteBuilderV4/.test( source + docs ) ) errors.push( 'Architecture consolidation must not introduce a V4 builder layer.' );
if ( errors.length ) {
	process.stderr.write( `${ errors.join( '\n' ) }\n` );
	process.exit( 1 );
}
process.stdout.write( 'Cresco Core contracts, scoped AI, command bus, UI registry, unified render boundary, release ownership, and architecture runtime verified.\n' );
