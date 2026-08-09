import { expect, test, type Page, type TestInfo } from '@playwright/test';

type Metric = {
	nodes: number;
	editorLoadMs: number;
	selectionMs: number;
	inspectorTabMs: number;
	settingsTabMs: number;
	saveMs: number;
};

async function login( page: Page ) {
	await page.goto( '/wp-login.php' );
	if ( await page.locator( '#user_login' ).isVisible().catch( () => false ) ) {
		await page.locator( '#user_login' ).fill( 'admin' );
		await page.locator( '#user_pass' ).fill( 'password' );
		await page.locator( '#wp-submit' ).click();
	}
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
}

async function editorHref( page: Page ) {
	await page.goto( '/wp-admin/edit.php?post_type=page' );
	const row = page.locator( 'tr' ).filter( { hasText: 'Cresco E2E Session' } ).first();
	await row.hover();
	const href = await row.getByRole( 'link', { name: 'Edit with Cresco Canvas' } ).getAttribute( 'href' );
	expect( href ).toBeTruthy();
	return href!;
}

function session( count: number ) {
	return {
		schema: 'cresco-session/v1',
		version: 1,
		documentId: `performance-${ count }`,
		nodes: Array.from( { length: count }, ( _, index ) => ( {
			id: `perf-${ count }-${ index }`,
			type: 'heading',
			props: { text: `Performance node ${ index + 1 }`, level: 2 },
			style: {},
			responsive: {},
			customCSS: {},
			children: [],
		} ) ),
	};
}

async function record( testInfo: TestInfo, metric: Metric ) {
	const json = JSON.stringify( metric );
	console.log( `CRESCO_PERF_METRIC ${ json }` );
	await testInfo.attach( `performance-${ metric.nodes }.json`, {
		body: Buffer.from( `${ JSON.stringify( metric, null, 2 ) }\n` ),
		contentType: 'application/json',
	} );
}

for ( const count of [ 50, 200, 500 ] ) {
	test( `${ count } node editor benchmark`, async ( { page }, testInfo ) => {
		test.setTimeout( 120_000 );
		await login( page );
		const href = await editorHref( page );
		await page.goto( href );
		await expect( page.locator( '.cc-standalone-app.cc-ui-v3-ready' ) ).toBeVisible();
		await page.evaluate( async ( payload ) => {
			const runtime = window as unknown as {
				wp: { apiFetch: ( options: { path: string; method: string; data: unknown } ) => Promise< unknown > };
				crescoCanvasStandaloneSettings: { sessionPath: string };
			};
			await runtime.wp.apiFetch( { path: runtime.crescoCanvasStandaloneSettings.sessionPath, method: 'POST', data: { session: payload } } );
		}, session( count ) );

		const loadStart = Date.now();
		await page.goto( href );
		await expect( page.locator( '.cc-standalone-app.cc-ui-v3-ready' ) ).toBeVisible();
		await expect( page.locator( '.cc-canvas-widget-heading' ) ).toHaveCount( count );
		const editorLoadMs = Date.now() - loadStart;

		const target = page.locator( '.cc-canvas-widget-heading' ).nth( Math.min( count - 1, 20 ) );
		const selectionStart = Date.now();
		await target.click();
		await expect( page.locator( '.cc-inspector' ) ).toBeVisible();
		const selectionMs = Date.now() - selectionStart;

		const style = page.locator( '.cc-inspector-v2-tab[data-tab="style"]' );
		const inspectorStart = Date.now();
		await style.click();
		await expect( style ).toHaveClass( /is-active/ );
		const inspectorTabMs = Date.now() - inspectorStart;

		const settings = page.locator( '.cc-standalone-tabs button' ).filter( { hasText: 'Settings' } ).first();
		const settingsStart = Date.now();
		await settings.click();
		await expect( page.getByRole( 'region', { name: 'Settings Center' } ) ).toBeVisible();
		const settingsTabMs = Date.now() - settingsStart;

		await page.locator( '.cc-standalone-tabs button' ).filter( { hasText: 'Edit' } ).first().click();
		await target.click();
		await page.getByLabel( 'Text', { exact: true } ).fill( `Performance node updated ${ count }` );
		const saveStart = Date.now();
		await page.getByRole( 'button', { name: 'Update' } ).click();
		await expect( page.getByRole( 'button', { name: 'Saved' } ) ).toBeDisabled();
		const saveMs = Date.now() - saveStart;

		const metric = { nodes: count, editorLoadMs, selectionMs, inspectorTabMs, settingsTabMs, saveMs };
		await record( testInfo, metric );

		// Anti-freeze ceilings only. Release regression budgets are established from recorded baseline evidence.
		expect( editorLoadMs ).toBeLessThan( 30_000 );
		expect( selectionMs ).toBeLessThan( 5_000 );
		expect( inspectorTabMs ).toBeLessThan( 5_000 );
		expect( settingsTabMs ).toBeLessThan( 5_000 );
		expect( saveMs ).toBeLessThan( 10_000 );
	} );
}
