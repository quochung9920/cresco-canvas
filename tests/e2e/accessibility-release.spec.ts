import AxeBuilder from '@axe-core/playwright';
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

async function openEditor( page: Page ) {
	await page.goto( '/wp-admin/edit.php?post_type=page' );
	const row = page.locator( 'tr' ).filter( { hasText: 'Cresco E2E Session' } ).first();
	await row.hover();
	await row.getByRole( 'link', { name: 'Edit with Cresco Canvas' } ).click();
	await expect( page.locator( '.cc-standalone-app.cc-ui-v3-ready' ) ).toBeVisible();
}

function blocking( results: { violations: Array< { impact: string | null } > } ) {
	return results.violations.filter( ( violation ) => [ 'critical', 'serious' ].includes( violation.impact || '' ) );
}

test( 'critical editor and Settings Center have no serious or critical axe violations', async ( { page } ) => {
	await login( page );
	await openEditor( page );
	let results = await new AxeBuilder( { page } ).include( '#cresco-canvas-standalone-editor' ).analyze();
	expect( blocking( results ) ).toEqual( [] );

	await page.locator( '.cc-standalone-tabs button' ).filter( { hasText: 'Settings' } ).first().click();
	await expect( page.getByRole( 'region', { name: 'Settings Center' } ) ).toBeVisible();
	results = await new AxeBuilder( { page } ).include( '.cc-settings-center-host' ).analyze();
	expect( blocking( results ) ).toEqual( [] );
} );

test( 'Cresco frontend output has no serious or critical axe violations', async ( { page } ) => {
	await login( page );
	await openEditor( page );
	await page.locator( '.cc-standalone-tabs button' ).filter( { hasText: 'Widgets' } ).first().click();
	await page.locator( '.cc-standalone-widget' ).filter( { hasText: 'Heading' } ).click();
	await page.getByLabel( 'Text', { exact: true } ).fill( 'Accessibility preview' );
	await page.getByRole( 'button', { name: 'Update' } ).click();
	await expect( page.getByRole( 'button', { name: 'Saved' } ) ).toBeDisabled();
	const previewHref = await page.getByRole( 'link', { name: 'Preview' } ).getAttribute( 'href' );
	expect( previewHref ).toBeTruthy();
	await page.goto( previewHref! );
	await expect( page.locator( '.cresco-session-root' ) ).toBeVisible();
	const results = await new AxeBuilder( { page } ).include( '.cresco-session-root' ).analyze();
	expect( blocking( results ) ).toEqual( [] );
} );
