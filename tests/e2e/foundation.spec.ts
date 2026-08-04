import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

interface WordPressBrowserApi {
	blocks: {
		createBlock: (
			name: string,
			attributes?: Record< string, unknown >
		) => unknown;
	};
	data: {
		dispatch: ( store: string ) => {
			autosave?: () => Promise< unknown >;
			insertBlocks?: ( blocks: unknown ) => void;
			redo?: () => void;
			savePost?: () => Promise< unknown >;
			undo?: () => void;
		};
		select: ( store: string ) => {
			getBlockCount?: () => number;
			getCurrentPostId?: () => number;
			getEditedPostAttribute?: ( attribute: string ) => unknown;
			isEditedPostDirty?: () => boolean;
		};
	};
}

async function login( page: Page ) {
	await page.goto( '/wp-login.php' );
	await page.getByLabel( 'Username or Email Address' ).fill( 'admin' );
	await page.getByLabel( 'Password', { exact: true } ).fill( 'password' );
	await page.getByRole( 'button', { name: 'Log In' } ).click();
}

async function openCanvasFixtureInGutenberg( page: Page ) {
	await page.goto( '/wp-admin/edit.php?post_type=page' );
	const row = page.locator( 'tr', { hasText: 'Cresco E2E Canvas' } );

	await expect(
		row.getByRole( 'link', { name: 'Edit in Canvas' } )
	).toHaveCount( 0 );
	await expect(
		row.getByRole( 'link', { name: 'WordPress Editor' } )
	).toHaveCount( 0 );
	await row.locator( 'a.row-title' ).click();
	await expect( page.locator( 'body' ) ).toHaveClass( /block-editor-page/ );
	await expect(
		page.getByRole( 'button', { name: 'Cresco Canvas' } ).first()
	).toBeVisible();
}

async function saveNativePost( page: Page ) {
	await page.evaluate( async () => {
		const wordpress = ( window as unknown as { wp: WordPressBrowserApi } )
			.wp;
		const result = wordpress.data.dispatch( 'core/editor' ).savePost?.();
		await result;
	} );

	await expect
		.poll( () =>
			page.evaluate( () => {
				const wordpress = (
					window as unknown as { wp: WordPressBrowserApi }
				 ).wp;
				return Boolean(
					wordpress.data.select( 'core/editor' ).isEditedPostDirty?.()
				);
			} )
		)
		.toBe( false );
}

test( 'non-Cresco Pages do not receive frontend assets or scope', async ( {
	page,
} ) => {
	await page.goto( '/?pagename=cresco-e2e-plain' );
	await expect( page.locator( 'body' ) ).not.toHaveClass(
		/cresco-canvas-page/
	);
	await expect( page.locator( '#cresco-canvas-frontend-css' ) ).toHaveCount(
		0
	);
} );

test( 'legacy Cresco block Pages retain scoped frontend output', async ( {
	page,
} ) => {
	await page.goto( '/?pagename=cresco-e2e-canvas' );
	await expect( page.locator( 'body' ) ).toHaveClass( /cresco-canvas-page/ );
	await expect( page.locator( '#cresco-canvas-frontend-css' ) ).toHaveCount(
		1
	);
	await expect( page.getByText( 'Canvas fixture' ) ).toBeVisible();
} );

test( 'normal Edit opens Gutenberg with Cresco integrated directly', async ( {
	page,
} ) => {
	await login( page );
	await openCanvasFixtureInGutenberg( page );

	await page.getByRole( 'button', { name: 'Cresco Canvas' } ).first().click();
	await expect( page.locator( '.cresco-canvas-sidebar' ) ).toBeVisible();
	await expect(
		page.getByRole( 'checkbox', { name: 'Enable Cresco page styles' } )
	).toBeVisible();
} );

test( 'native Gutenberg document and history services remain available', async ( {
	page,
} ) => {
	await login( page );
	await openCanvasFixtureInGutenberg( page );

	const result = await page.evaluate( async () => {
		const wordpress = ( window as unknown as { wp: WordPressBrowserApi } )
			.wp;
		const editorDispatch = wordpress.data.dispatch( 'core/editor' );
		const editorSelect = wordpress.data.select( 'core/editor' );
		const blockDispatch = wordpress.data.dispatch( 'core/block-editor' );
		const blockSelect = wordpress.data.select( 'core/block-editor' );
		const initialCount = blockSelect.getBlockCount?.() ?? -1;

		blockDispatch.insertBlocks?.(
			wordpress.blocks.createBlock( 'core/paragraph', {
				content: 'Native history probe',
			} )
		);
		await new Promise( ( resolve ) => requestAnimationFrame( resolve ) );
		const insertedCount = blockSelect.getBlockCount?.() ?? -1;
		editorDispatch.undo?.();
		await new Promise( ( resolve ) => requestAnimationFrame( resolve ) );
		const undoneCount = blockSelect.getBlockCount?.() ?? -1;
		editorDispatch.redo?.();
		await new Promise( ( resolve ) => requestAnimationFrame( resolve ) );
		const redoneCount = blockSelect.getBlockCount?.() ?? -1;
		editorDispatch.undo?.();

		return {
			autosave: typeof editorDispatch.autosave === 'function',
			currentPostId: editorSelect.getCurrentPostId?.() ?? 0,
			initialCount,
			insertedCount,
			redoneCount,
			savePost: typeof editorDispatch.savePost === 'function',
			status: editorSelect.getEditedPostAttribute?.( 'status' ),
			undoneCount,
		};
	} );

	expect( result ).toMatchObject( {
		autosave: true,
		insertedCount: result.initialCount + 1,
		redoneCount: result.initialCount + 1,
		savePost: true,
		undoneCount: result.initialCount,
	} );
	expect( result.currentPostId ).toBeGreaterThan( 0 );
	expect( typeof result.status ).toBe( 'string' );
} );

test( 'Gutenberg owns Page edits and persists Cresco metadata', async ( {
	page,
} ) => {
	await login( page );
	await openCanvasFixtureInGutenberg( page );

	await page.evaluate( () => {
		const wordpress = ( window as unknown as { wp: WordPressBrowserApi } )
			.wp;
		const paragraph = wordpress.blocks.createBlock( 'core/paragraph', {
			content: 'Native Gutenberg save verified',
		} );
		wordpress.data
			.dispatch( 'core/block-editor' )
			.insertBlocks?.( paragraph );
	} );

	await expect
		.poll( () =>
			page.evaluate( () => {
				const wordpress = (
					window as unknown as { wp: WordPressBrowserApi }
				 ).wp;
				return Boolean(
					wordpress.data.select( 'core/editor' ).isEditedPostDirty?.()
				);
			} )
		)
		.toBe( true );

	await page.getByRole( 'button', { name: 'Cresco Canvas' } ).first().click();
	await page
		.getByRole( 'checkbox', { name: 'Enable Cresco page styles' } )
		.check();
	await saveNativePost( page );

	await page.goto( '/?pagename=cresco-e2e-canvas' );
	await expect(
		page.getByText( 'Native Gutenberg save verified' ).first()
	).toBeVisible();
	await expect( page.locator( 'body' ) ).toHaveClass( /cresco-canvas-page/ );
} );

test( 'the retired custom Page REST route is no longer exposed', async ( {
	page,
} ) => {
	await login( page );
	const response = await page.request.get(
		'/wp-json/cresco-canvas/v1/pages'
	);
	expect( response.status() ).toBe( 404 );
	expect( await response.json() ).toMatchObject( { code: 'rest_no_route' } );
} );

test( 'Cresco Gutenberg sidebar has no serious automated accessibility violations', async ( {
	page,
} ) => {
	await login( page );
	await openCanvasFixtureInGutenberg( page );
	await page.getByRole( 'button', { name: 'Cresco Canvas' } ).first().click();
	await expect( page.locator( '.cresco-canvas-sidebar' ) ).toBeVisible();

	const results = await new AxeBuilder( { page } )
		.include( '.cresco-canvas-sidebar' )
		.analyze();
	const releaseBlocking = results.violations.filter( ( violation ) =>
		[ 'critical', 'serious' ].includes( violation.impact || '' )
	);
	expect( releaseBlocking ).toEqual( [] );
} );
