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
