import { mkdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import { chromium } from '@playwright/test';

const BREAKPOINTS = [
	{ id: 'wide', width: 1920, height: 1080 },
	{ id: 'desktop', width: 1440, height: 900 },
	{ id: 'laptop', width: 1366, height: 768 },
	{ id: 'tablet', width: 768, height: 1024 },
	{ id: 'mobile', width: 390, height: 844 },
];

function argsFrom( argv ) {
	const args = {};
	for ( let index = 0; index < argv.length; index += 1 ) {
		const value = argv[ index ];
		if ( ! value.startsWith( '--' ) ) continue;
		const key = value.slice( 2 );
		const next = argv[ index + 1 ];
		if ( next && ! next.startsWith( '--' ) ) {
			args[ key ] = next;
			index += 1;
		} else {
			args[ key ] = true;
		}
	}
	return args;
}

function isLocalHostname( hostname ) {
	const value = String( hostname || '' ).toLowerCase();
	return value === 'localhost' || value === '127.0.0.1' || value === '::1' || value.endsWith( '.localhost' );
}

function assertLocalUrl( value, label ) {
	let url;
	try {
		url = new URL( value );
	} catch {
		throw new Error( `${ label } must be a valid URL.` );
	}
	if ( ! [ 'http:', 'https:' ].includes( url.protocol ) || ! isLocalHostname( url.hostname ) ) {
		throw new Error( `${ label } must point to localhost. External network targets are intentionally rejected.` );
	}
	return url;
}

function safeName( value ) {
	return String( value || 'cresco-visual' )
		.toLowerCase()
		.replace( /[^a-z0-9._-]+/g, '-' )
		.replace( /^-+|-+$/g, '' ) || 'cresco-visual';
}

async function loadVisualDocument( args ) {
	if ( args.html ) {
		return {
			document: await readFile( path.resolve( String( args.html ) ), 'utf8' ),
			baseUrl: null,
			name: path.basename( String( args.html ), path.extname( String( args.html ) ) ),
		};
	}

	if ( ! args.url ) {
		throw new Error( 'Provide --url <localhost visual REST endpoint> or --html <standalone HTML file>.' );
	}

	const endpoint = assertLocalUrl( String( args.url ), '--url' );
	const headers = { 'Content-Type': 'application/json' };
	const nonce = String( args.nonce || process.env.CRESCO_WP_NONCE || '' ).trim();
	const cookie = String( args.cookie || process.env.CRESCO_WP_COOKIE || '' ).trim();
	if ( nonce ) headers[ 'X-WP-Nonce' ] = nonce;
	if ( cookie ) headers.Cookie = cookie;

	const payload = { scope: String( args.scope || 'page' ) };
	if ( args.target ) {
		try {
			payload.target = JSON.parse( String( args.target ) );
		} catch {
			throw new Error( '--target must be valid JSON.' );
		}
	}

	const response = await fetch( endpoint, {
		method: 'POST',
		headers,
		body: JSON.stringify( payload ),
		redirect: 'error',
	} );
	if ( ! response.ok ) {
		const body = await response.text();
		throw new Error( `Visual export failed with HTTP ${ response.status }: ${ body.slice( 0, 500 ) }` );
	}
	const result = await response.json();
	if ( result?.schema !== 'cresco-ai-visual/v1' || typeof result?.document !== 'string' ) {
		throw new Error( 'Visual endpoint did not return cresco-ai-visual/v1 with a document string.' );
	}

	return {
		document: result.document,
		baseUrl: endpoint.origin,
		name: safeName( result.filename || `cresco-${ payload.scope }` ).replace( /\.html$/i, '' ),
	};
}

function requestIsAllowed( requestUrl, baseUrl ) {
	let parsed;
	try {
		parsed = new URL( requestUrl );
	} catch {
		return false;
	}
	if ( [ 'data:', 'blob:', 'about:' ].includes( parsed.protocol ) ) return true;
	if ( ! [ 'http:', 'https:' ].includes( parsed.protocol ) ) return false;
	if ( ! isLocalHostname( parsed.hostname ) ) return false;
	if ( ! baseUrl ) return true;
	return parsed.origin === baseUrl;
}

async function main() {
	const args = argsFrom( process.argv.slice( 2 ) );
	const visual = await loadVisualDocument( args );
	const outputDir = path.resolve( String( args.out || 'artifacts/visual-breakpoints' ) );
	await mkdir( outputDir, { recursive: true } );

	const browser = await chromium.launch( { headless: true } );
	const blocked = new Set();
	try {
		for ( const breakpoint of BREAKPOINTS ) {
			const context = await browser.newContext( {
				viewport: { width: breakpoint.width, height: breakpoint.height },
				deviceScaleFactor: 1,
			} );
			await context.route( '**/*', async ( route ) => {
				const requestUrl = route.request().url();
				if ( requestIsAllowed( requestUrl, visual.baseUrl ) ) {
					await route.continue();
					return;
				}
				blocked.add( requestUrl );
				await route.abort( 'blockedbyclient' );
			} );

			const page = await context.newPage();
			await page.setContent( visual.document, { waitUntil: 'domcontentloaded' } );
			await page.evaluate( async () => {
				if ( document.fonts?.ready ) await document.fonts.ready;
			} );
			await page.screenshot( {
				path: path.join( outputDir, `${ safeName( visual.name ) }-${ breakpoint.id }-${ breakpoint.width }.png` ),
				fullPage: true,
				animations: 'disabled',
			} );
			await context.close();
		}
	} finally {
		await browser.close();
	}

	process.stdout.write(
		`Captured ${ BREAKPOINTS.length } local-only breakpoint screenshots in ${ outputDir }.\n`
	);
	if ( blocked.size ) {
		process.stdout.write( `Blocked ${ blocked.size } external/non-local request(s); screenshots never fetched them.\n` );
	}
}

main().catch( ( error ) => {
	process.stderr.write( `${ error instanceof Error ? error.message : String( error ) }\n` );
	process.exitCode = 1;
} );
