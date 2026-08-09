import { expect, test, type Page } from '@playwright/test';

async function login( page: Page ) {
	await page.goto( '/wp-login.php' );
	if ( await page.locator( '#user_login' ).isVisible().catch( () => false ) ) {
		await page.locator( '#user_login' ).fill( 'admin' );
		await page.locator( '#user_pass' ).fill( 'password' );
		await page.locator( '#wp-submit' ).click();
	}
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
}

async function openStandaloneEditor( page: Page ) {
	await page.goto( '/wp-admin/edit.php?post_type=page' );
	const row = page.locator( 'tr' ).filter( { hasText: 'Cresco E2E Session' } ).first();
	await expect( row ).toBeVisible();
	await row.hover();
	await row.getByRole( 'link', { name: 'Edit with Cresco Canvas' } ).click();
	await expect( page.locator( '.cc-standalone-app.cc-ui-v3-ready' ) ).toBeVisible();
}

async function openPanel( page: Page, label: 'Widgets' | 'Edit' | 'Settings' | 'AI' ) {
	const button = page.locator( '.cc-standalone-tabs button' ).filter( { hasText: label } ).first();
	await button.click();
	await expect( button ).toHaveClass( /is-active/ );
}

function sessionWithNodes( count: number ) {
	return {
		schema: 'cresco-session/v1',
		version: 1,
		documentId: `performance-${ count }`,
		nodes: Array.from( { length: count }, ( _, index ) => ( {
			id: `performance-heading-${ index }`,
			type: 'heading',
			props: { text: `Heading ${ index + 1 }`, level: 2 },
			style: { fontSize: '{typography.sizes.h4}' },
			responsive: {},
			customCSS: {},
			children: [],
		} ) ),
	};
}

test( 'keeps critical editor interactions bounded at 50, 200, and 500 nodes', async ( { page } ) => {
	test.slow();
	await page.setViewportSize( { width: 1440, height: 900 } );
	await login( page );
	await openStandaloneEditor( page );

	for ( const count of [ 50, 200, 500 ] ) {
		await openPanel( page, 'AI' );
		await page.locator( '.cc-ai-card textarea' ).fill( JSON.stringify( sessionWithNodes( count ) ) );
		await page.getByRole( 'button', { name: 'Validate import' } ).click();
		await expect( page.locator( '.cc-ai-import-summary' ) ).toContainText( 'Ready to apply' );
		await page.getByRole( 'button', { name: 'Apply to Cresco Editor' } ).click();
		await expect( page.locator( '.cc-standalone-structure-item' ) ).toHaveCount( count );

		const selectStarted = Date.now();
		await page.locator( '.cc-standalone-structure-item' ).nth( count - 1 ).click();
		await expect( page.locator( '.cc-inspector' ) ).toBeVisible();
		expect( Date.now() - selectStarted ).toBeLessThan( 4000 );

		const settingsStarted = Date.now();
		await openPanel( page, 'Settings' );
		await expect( page.getByRole( 'region', { name: 'Settings Center' } ) ).toBeVisible();
		expect( Date.now() - settingsStarted ).toBeLessThan( 4000 );
	}
} );
