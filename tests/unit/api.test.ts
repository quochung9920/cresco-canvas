jest.mock( '@wordpress/api-fetch', () => {
	const apiFetch = jest.fn();
	Object.assign( apiFetch, {
		createNonceMiddleware: jest.fn( () => jest.fn() ),
		use: jest.fn(),
	} );
	return { __esModule: true, default: apiFetch };
} );

window.crescoCanvasSettings = {
	adminUrl: '/wp-admin/',
	brand: 'Cresco Canvas',
	canManageSettings: true,
	nativeEditUrl: '',
	nonce: 'nonce',
	pagesUrl: '/wp-admin/edit.php?post_type=page',
	postId: 1,
	restPath: '/cresco-canvas/v1/',
	safeModeUrl: '',
	version: '0.2.0-alpha.1',
};

describe( 'normalizeApiError', () => {
	it( 'keeps only the supported error shape', async () => {
		const { normalizeApiError } = await import( '../../src/editor/api' );
		expect(
			normalizeApiError( {
				code: 'conflict',
				message: 'Reload',
				data: { status: 409 },
			} )
		).toEqual( {
			code: 'conflict',
			data: { status: 409 },
			message: 'Reload',
		} );
	} );

	it( 'handles non-object failures safely', async () => {
		const { normalizeApiError } = await import( '../../src/editor/api' );
		expect( normalizeApiError( 'offline' ) ).toEqual( {} );
	} );
} );
