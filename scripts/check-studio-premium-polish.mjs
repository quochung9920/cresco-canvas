import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const errors = [];
const exists = ( relative ) => fs.existsSync( path.join( root, relative ) );
const read = ( relative ) => fs.readFileSync( path.join( root, relative ), 'utf8' );
const expect = ( relative, token ) => {
	if ( ! exists( relative ) ) {
		errors.push( `Missing ${ relative }` );
		return;
	}
	if ( ! read( relative ).includes( token ) ) errors.push( `${ relative } missing ${ token }` );
};

const css = 'assets/css/website-builder-premium-polish.css';

for ( const token of [
	'@layer cresco.overrides',
	'--cc-premium-shadow-lg',
	'.cc-studio-stage',
	'.cc-studio-widget-grid button::after',
	'.cc-studio-empty',
	'.cc-studio-ai-preview',
	'.cc-studio-recovery-card',
	'.cc-studio-notice',
	'#cresco-canvas-standalone-editor > .cc-studio-loading::before',
	'#cresco-canvas-standalone-editor > .cc-studio-fatal',
	'@media (max-width: 1280px)',
	'@media (max-width: 960px)',
	'@media (prefers-contrast: more)',
	'@media (prefers-reduced-motion: reduce)',
	'focus-visible',
] ) expect( css, token );

for ( const token of [
	"const PREMIUM_STYLE     = 'assets/css/website-builder-premium-polish.css'",
	"'cresco-canvas-website-builder-premium-polish'",
] ) expect( 'includes/Builder/WebsiteBuilderStudio.php', token );

expect( 'includes/Builder/WebsiteBuilderModuleRegistry.php', "'file' => 'assets/css/website-builder-premium-polish.css'" );
expect( 'scripts/release-files.mjs', "'assets/css/website-builder-premium-polish.css'" );
expect( 'scripts/release-files.mjs', "'docs/STUDIO_PREMIUM_POLISH.md'" );
expect( 'package.json', '"check:studio-premium": "node scripts/check-studio-premium-polish.mjs"' );
expect( 'package.json', 'npm run check:studio-premium' );

if ( exists( css ) ) {
	const content = read( css );
	if ( content.includes( '!important' ) ) errors.push( `${ css } must not introduce !important rules.` );
	if ( ! content.includes( 'backdrop-filter' ) ) errors.push( `${ css } missing glass-surface progressive enhancement.` );
}

if ( errors.length ) {
	process.stderr.write( `${ errors.join( '\n' ) }\n` );
	process.exit( 1 );
}

process.stdout.write( '[studio-premium] Premium presentation, state, responsive, and accessibility contracts verified.\n' );
