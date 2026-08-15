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

async function openStudio( page: Page ) {
	await page.goto( '/wp-admin/edit.php?post_type=page' );
	const row = page.locator( 'tr' ).filter( { hasText: 'Cresco E2E Session' } ).first();
	await expect( row ).toBeVisible();
	await row.hover();
	await row.getByRole( 'link', { name: 'Edit with Cresco Canvas' } ).click();
	await expect( page.locator( '.cc-studio-app' ) ).toBeVisible();
}

// axe reports `impact` as `ImpactValue | undefined`, not `string | null`, so the
// parameter has to admit undefined or every call site fails to type.
function blocking( results: { violations: Array< { impact?: string | null } > } ) {
	return results.violations.filter( ( violation ) => [ 'critical', 'serious' ].includes( violation.impact || '' ) );
}

test( 'Studio shell, Structure, Inspector and Page Settings have no serious or critical axe violations', async ( { page } ) => {
	await login( page );
	await openStudio( page );
	let results = await new AxeBuilder( { page } ).include( '#cresco-canvas-standalone-editor' ).analyze();
	expect( blocking( results ) ).toEqual( [] );

	await page.locator( '.cc-studio-rail button[title="Add"]' ).click();
	await page.locator( '.cc-studio-widget-grid button' ).filter( { hasText: 'Heading' } ).first().click();
	await page.locator( '.cc-studio-rail button[title="Edit"]' ).click();
	await expect( page.locator( '.cc-studio-left' ) ).toContainText( 'Edit Heading' );
	results = await new AxeBuilder( { page } ).include( '.cc-studio-left' ).analyze();
	expect( blocking( results ) ).toEqual( [] );

	await page.locator( '.cc-studio-rail button[title="Page"]' ).click();
	await expect( page.locator( '.cc-studio-left' ) ).toContainText( 'Page Settings 2.0' );
	results = await new AxeBuilder( { page } ).include( '.cc-studio-left' ).analyze();
	expect( blocking( results ) ).toEqual( [] );
} );

test( 'Cresco frontend output has no serious or critical axe violations', async ( { page } ) => {
	await login( page );
	await openStudio( page );
	await page.locator( '.cc-studio-rail button[title="Add"]' ).click();
	await page.locator( '.cc-studio-widget-grid button' ).filter( { hasText: 'Heading' } ).first().click();
	await page.locator( '.cc-studio-rail button[title="Edit"]' ).click();
	const text = page.locator( '.cc-studio-field' ).filter( { hasText: /^Text/ } ).locator( 'textarea,input' ).first();
	await text.fill( 'Accessibility preview' );
	await page.getByRole( 'button', { name: 'Update' } ).click();
	await expect( page.getByRole( 'button', { name: 'Saved' } ) ).toBeDisabled();
	const previewHref = await page.getByRole( 'link', { name: 'Preview' } ).getAttribute( 'href' );
	expect( previewHref ).toBeTruthy();
	await page.goto( previewHref! );
	await expect( page.locator( '.cresco-session-root' ) ).toBeVisible();
	const results = await new AxeBuilder( { page } ).include( '.cresco-session-root' ).analyze();
	expect( blocking( results ) ).toEqual( [] );
} );
