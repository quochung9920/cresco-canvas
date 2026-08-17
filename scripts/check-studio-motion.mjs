import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const errors = [];
const read = ( relative ) => fs.readFileSync( path.join( root, relative ), 'utf8' );
const expect = ( relative, token ) => {
	const file = path.join( root, relative );
	if ( ! fs.existsSync( file ) ) {
		errors.push( `Missing ${ relative }` );
		return;
	}
	if ( ! read( relative ).includes( token ) ) errors.push( `${ relative } missing ${ token }` );
};

const foundation = 'assets/css/cresco-foundation.css';
const stateTabs = 'assets/css/studio-widget-state-tabs.css';

for ( const token of [
	'cresco.overrides, cresco.motion',
	'--cc-motion-instant: 90ms',
	'--cc-motion-fast: 140ms',
	'--cc-motion-base: 200ms',
	'--cc-motion-slow: 280ms',
	'--cc-motion-ease-standard',
	'--cc-motion-ease-enter',
	'--cc-motion-ease-exit',
	'--cc-motion-ease-emphasized',
	'--cc-motion-press-scale: 0.98',
	'@layer cresco.motion',
	':not(.cc-studio-canvas *)',
	'scale: var(--cc-motion-press-scale)',
	'cc-motion-popover-in',
	'cc-motion-notice-in',
	'cc-motion-reveal',
	'@media (prefers-reduced-motion: reduce)',
] ) expect( foundation, token );

for ( const token of [
	'button[aria-selected="true"]',
	'--cc-color-accent-wash',
	'--cc-color-accent-hover',
	'font-weight:700!important',
] ) expect( stateTabs, token );

if ( fs.existsSync( path.join( root, foundation ) ) ) {
	const content = read( foundation );
	if ( /transition\s*:\s*all\b/i.test( content ) ) {
		errors.push( `${ foundation } must not use transition: all.` );
	}
	if ( ! content.includes( 'transition-property: background-color, border-color, color, box-shadow, opacity, filter, scale' ) ) {
		errors.push( `${ foundation } must keep explicit chrome transition properties.` );
	}
}

if ( fs.existsSync( path.join( root, stateTabs ) ) ) {
	const content = read( stateTabs );
	if ( /button\.is-active[^}]*background:var\(--cc-color-accent-soft/.test( content ) ) {
		errors.push( `${ stateTabs } active state must use accent-wash, not accent-soft, to preserve label contrast.` );
	}
}

expect( 'package.json', '"check:studio-motion": "node scripts/check-studio-motion.mjs"' );
expect( 'package.json', 'npm run check:studio-motion' );

if ( errors.length ) {
	process.stderr.write( `${ errors.join( '\n' ) }\n` );
	process.exit( 1 );
}

process.stdout.write( '[studio-motion] Motion tokens, chrome scoping, state-tab contrast, and reduced-motion contracts verified.\n' );
