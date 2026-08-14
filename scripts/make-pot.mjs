/**
 * Generate languages/cresco-canvas.pot from PHP and JavaScript sources.
 *
 * The plugin declares `Domain Path: /languages` and calls
 * load_plugin_textdomain(), and roughly 1,800 strings are already wrapped in
 * translation functions, but no catalogue was ever produced. Translators had
 * nothing to work from, so the declared support did not exist in practice.
 *
 * WP-CLI is the usual tool for this and is not available in this environment, so
 * this reads the same call signatures WP-CLI does. It is deliberately a lexical
 * extractor rather than a parser: it recognises literal-string arguments and
 * skips calls whose arguments are variables or concatenations, because those
 * cannot be translated anyway.
 */

import { readdir, readFile, writeFile, mkdir } from 'node:fs/promises';
import path from 'node:path';

const root = process.cwd();
const TEXT_DOMAIN = 'cresco-canvas';
const OUTPUT = 'languages/cresco-canvas.pot';

const PHP_ROOTS = [ 'includes', 'cresco-canvas.php' ];
const JS_ROOTS = [ 'src', 'runtime-src/build' ];

/**
 * Translation calls and which argument positions carry text.
 *
 * `context` is the argument index holding a gettext context, `plural` the index
 * holding the plural form. `domain` is where the text domain is expected.
 */
const FUNCTIONS = {
	__: { domain: 1 },
	_e: { domain: 1 },
	esc_html__: { domain: 1 },
	esc_html_e: { domain: 1 },
	esc_attr__: { domain: 1 },
	esc_attr_e: { domain: 1 },
	_x: { context: 1, domain: 2 },
	esc_html_x: { context: 1, domain: 2 },
	esc_attr_x: { context: 1, domain: 2 },
	_n: { plural: 1, domain: 3 },
	_nx: { plural: 1, context: 3, domain: 4 },
};

const NAMES = Object.keys( FUNCTIONS )
	.sort( ( a, b ) => b.length - a.length )
	.join( '|' );

/** Recursively collect files with the given extensions. */
const walk = async ( dir, extensions, out = [] ) => {
	let entries;
	try {
		entries = await readdir( path.join( root, dir ), { withFileTypes: true } );
	} catch {
		return out;
	}
	for ( const entry of entries ) {
		const relative = path.posix.join( dir, entry.name );
		if ( entry.isDirectory() ) {
			if ( entry.name === 'node_modules' || entry.name === 'vendor' ) continue;
			await walk( relative, extensions, out );
		} else if ( extensions.some( ( ext ) => entry.name.endsWith( ext ) ) ) {
			out.push( relative );
		}
	}
	return out;
};

/**
 * Read one quoted PHP/JS string literal starting at `from`.
 *
 * Returns null when the argument is not a literal — a variable, a concatenation,
 * or a function call — which is exactly the case an extractor must skip rather
 * than guess at.
 */
const readLiteral = ( source, from ) => {
	let i = from;
	while ( i < source.length && /\s/.test( source[ i ] ) ) i++;
	const quote = source[ i ];
	if ( quote !== "'" && quote !== '"' ) return null;

	let value = '';
	i++;
	while ( i < source.length ) {
		const char = source[ i ];
		if ( char === '\\' ) {
			const next = source[ i + 1 ];
			if ( next === 'n' ) value += '\n';
			else if ( next === 't' ) value += '\t';
			else value += next;
			i += 2;
			continue;
		}
		if ( char === quote ) return { value, end: i + 1 };
		value += char;
		i++;
	}
	return null;
};

/** Read the comma-separated literal arguments of a call. */
const readArguments = ( source, from ) => {
	const args = [];
	let i = from;
	for ( let count = 0; count < 6; count++ ) {
		const literal = readLiteral( source, i );
		if ( ! literal ) {
			args.push( null );
			// Skip to the next comma at this nesting level, or give up.
			let depth = 0;
			while ( i < source.length ) {
				const char = source[ i ];
				if ( char === '(' ) depth++;
				else if ( char === ')' ) { if ( depth === 0 ) return args; depth--; }
				else if ( char === ',' && depth === 0 ) { i++; break; }
				i++;
			}
			continue;
		}
		args.push( literal.value );
		i = literal.end;
		while ( i < source.length && /\s/.test( source[ i ] ) ) i++;
		if ( source[ i ] === ',' ) { i++; continue; }
		return args;
	}
	return args;
};

/** Extract entries from one file. */
const extract = ( source, file ) => {
	const found = [];
	const pattern = new RegExp( `(?<![\\w$])(${ NAMES })\\s*\\(`, 'g' );
	let match;

	while ( ( match = pattern.exec( source ) ) !== null ) {
		const spec = FUNCTIONS[ match[ 1 ] ];
		const args = readArguments( source, match.index + match[ 0 ].length );

		const text = args[ 0 ];
		if ( typeof text !== 'string' || text === '' ) continue;
		// Only collect strings belonging to this plugin's domain.
		if ( args[ spec.domain ] !== TEXT_DOMAIN ) continue;

		const line = source.slice( 0, match.index ).split( '\n' ).length;
		found.push( {
			msgid: text,
			msgctxt: spec.context !== undefined ? args[ spec.context ] : undefined,
			msgidPlural: spec.plural !== undefined ? args[ spec.plural ] : undefined,
			reference: `${ file }:${ line }`,
		} );
	}
	return found;
};

/** Escape a string for a PO literal. */
const poEscape = ( value ) =>
	String( value )
		.replace( /\\/g, '\\\\' )
		.replace( /"/g, '\\"' )
		.replace( /\n/g, '\\n' )
		.replace( /\t/g, '\\t' );

const phpFiles = [];
for ( const entry of PHP_ROOTS ) {
	if ( entry.endsWith( '.php' ) ) phpFiles.push( entry );
	else phpFiles.push( ...( await walk( entry, [ '.php' ] ) ) );
}
const jsFiles = [];
for ( const entry of JS_ROOTS ) {
	jsFiles.push( ...( await walk( entry, [ '.js', '.ts', '.tsx', '.jsx' ] ) ) );
}

const entries = new Map();
let scanned = 0;

for ( const file of [ ...phpFiles, ...jsFiles ] ) {
	const source = await readFile( path.join( root, file ), 'utf8' );
	scanned++;
	for ( const item of extract( source, file ) ) {
		const key = `${ item.msgctxt ?? '' }${ item.msgid }`;
		const existing = entries.get( key );
		if ( existing ) {
			// The same string can appear twice on one line; a repeated reference
			// tells a translator nothing.
			if ( ! existing.references.includes( item.reference ) ) {
				existing.references.push( item.reference );
			}
		} else {
			entries.set( key, {
				msgid: item.msgid,
				msgctxt: item.msgctxt,
				msgidPlural: item.msgidPlural,
				references: [ item.reference ],
			} );
		}
	}
}

const sorted = [ ...entries.values() ].sort( ( a, b ) =>
	a.references[ 0 ].localeCompare( b.references[ 0 ] )
);

const now = new Date().toISOString().replace( /\.\d+Z$/, '+0000' ).replace( 'T', ' ' );
const lines = [
	'# Copyright (C) Crescospec',
	'# This file is distributed under the GPL-2.0-or-later license.',
	'msgid ""',
	'msgstr ""',
	'"Project-Id-Version: Cresco Canvas\\n"',
	'"Report-Msgid-Bugs-To: https://github.com/quochung9920/cresco-canvas\\n"',
	`"POT-Creation-Date: ${ now }\\n"`,
	'"MIME-Version: 1.0\\n"',
	'"Content-Type: text/plain; charset=UTF-8\\n"',
	'"Content-Transfer-Encoding: 8bit\\n"',
	'"Plural-Forms: nplurals=2; plural=(n != 1);\\n"',
	'"X-Domain: cresco-canvas\\n"',
	'',
];

for ( const entry of sorted ) {
	for ( const reference of entry.references.slice( 0, 12 ) ) {
		lines.push( `#: ${ reference }` );
	}
	if ( entry.msgctxt ) lines.push( `msgctxt "${ poEscape( entry.msgctxt ) }"` );
	lines.push( `msgid "${ poEscape( entry.msgid ) }"` );
	if ( entry.msgidPlural ) {
		lines.push( `msgid_plural "${ poEscape( entry.msgidPlural ) }"` );
		lines.push( 'msgstr[0] ""' );
		lines.push( 'msgstr[1] ""' );
	} else {
		lines.push( 'msgstr ""' );
	}
	lines.push( '' );
}

await mkdir( path.join( root, 'languages' ), { recursive: true } );
await writeFile( path.join( root, OUTPUT ), lines.join( '\n' ), 'utf8' );

process.stdout.write(
	`[make-pot] ${ entries.size } unique string(s) from ${ scanned } file(s) -> ${ OUTPUT }\n`
);
