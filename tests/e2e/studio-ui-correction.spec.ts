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
	await expect.poll( async () => page.evaluate( () => Boolean( ( window as typeof window & { crescoStudioUiCorrection?: unknown } ).crescoStudioUiCorrection ) ) ).toBe( true );
}

async function addWidget( page: Page, label: string ) {
	await page.locator( '.cc-studio-rail button[title="Add"]' ).click();
	const widget = page.locator( '.cc-studio-widget-grid button' ).filter( { hasText: label } ).first();
	await expect( widget ).toBeVisible();
	await widget.click();
}

test.describe( 'Studio UI correction', () => {
	test.beforeEach( async ( { page } ) => {
		await login( page );
		await openStudio( page );
	} );

	test( 'uses five visually distinct responsive device glyphs and a readable active label', async ( { page } ) => {
		const buttons = page.locator( '.cc-studio-device-toolbar button' );
		await expect( buttons ).toHaveCount( 5 );
		const ids = await buttons.evaluateAll( ( items ) => items.map( ( item ) => item.querySelector( 'svg[data-cresco-device-icon]' )?.getAttribute( 'data-cresco-device-icon' ) ) );
		expect( ids ).toEqual( [ 'wide', 'desktop', 'laptop', 'tablet', 'mobile' ] );
		for ( const id of ids ) expect( id ).toBeTruthy();

		await page.locator( '.cc-studio-device-toolbar button[data-cresco-device="laptop"]' ).click();
		await expect( page.locator( '.cc-studio-width[data-cresco-breakpoint-label="1"]' ) ).toHaveText( 'Laptop · 1366px' );
		await expect( page.locator( '.cc-studio-device-toolbar button[data-cresco-device="laptop"]' ) ).toHaveClass( /is-active/ );
	} );

	test( 'keeps Structure hierarchy readable without label and action overlap', async ( { page } ) => {
		await addWidget( page, 'Container' );
		await addWidget( page, 'Heading' );

		const rows = page.locator( '.cc-studio-tree-row[data-cresco-node-id]' );
		await expect( rows ).toHaveCount( 2 );
		const selected = page.locator( '.cc-studio-tree-row.is-selected' ).first();
		await expect( selected ).toBeVisible();
		await expect( selected.locator( '.cc-studio-tree-actions button:visible' ) ).toHaveCount( 2 );

		const metrics = await selected.evaluate( ( row ) => {
			const select = row.querySelector<HTMLElement>( '.cc-studio-tree-select' );
			const actions = row.querySelector<HTMLElement>( '.cc-studio-tree-actions' );
			const label = row.querySelector<HTMLElement>( '.cc-studio-tree-label' );
			const style = getComputedStyle( row );
			const selectRect = select?.getBoundingClientRect();
			const actionRect = actions?.getBoundingClientRect();
			return {
				display: style.display,
				height: row.getBoundingClientRect().height,
				labelWidth: label?.getBoundingClientRect().width || 0,
				separated: Boolean( selectRect && actionRect && selectRect.right <= actionRect.left + 0.5 ),
			};
		} );
		expect( metrics.display ).toBe( 'grid' );
		expect( metrics.height ).toBeGreaterThanOrEqual( 38 );
		expect( metrics.labelWidth ).toBeGreaterThan( 24 );
		expect( metrics.separated ).toBe( true );

		const nestedGuide = page.locator( '.cc-studio-tree ul' ).first();
		await expect( nestedGuide ).toBeVisible();
		const borderWidth = await nestedGuide.evaluate( ( node ) => Number.parseFloat( getComputedStyle( node ).borderLeftWidth ) || 0 );
		expect( borderWidth ).toBeGreaterThanOrEqual( 1 );
	} );

	test( 'keeps the Structure context menu fully inside the viewport', async ( { page } ) => {
		await addWidget( page, 'Heading' );
		const selected = page.locator( '.cc-studio-tree-row.is-selected' ).first();
		await selected.hover();
		const more = selected.locator( '.cc-studio-tree-actions button:last-child' );
		await expect( more ).toBeVisible();
		await more.click();

		const menu = page.locator( '.cc-studio-context-menu' );
		await expect( menu ).toBeVisible();
		await expect( menu ).toHaveAttribute( 'data-cresco-viewport-clamped', '1' );
		const bounds = await menu.evaluate( ( node ) => {
			const rect = node.getBoundingClientRect();
			return {
				left: rect.left,
				right: rect.right,
				top: rect.top,
				bottom: rect.bottom,
				viewportWidth: window.innerWidth,
				viewportHeight: window.innerHeight,
			};
		} );
		expect( bounds.left ).toBeGreaterThanOrEqual( 7 );
		expect( bounds.right ).toBeLessThanOrEqual( bounds.viewportWidth - 7 );
		expect( bounds.top ).toBeGreaterThanOrEqual( 7 );
		expect( bounds.bottom ).toBeLessThanOrEqual( bounds.viewportHeight - 7 );
	} );
} );
