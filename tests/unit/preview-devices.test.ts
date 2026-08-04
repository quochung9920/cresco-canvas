import {
	getPreviewDeviceWidth,
	isPreviewDevice,
	persistPreviewDevice,
	PREVIEW_STORAGE_KEY,
	readPreviewDevice,
} from '../../src/shared/previewDevices';

describe( 'Cresco preview devices', () => {
	it( 'exposes the exact five supported logical widths', () => {
		expect( getPreviewDeviceWidth( '4k' ) ).toBe( 1920 );
		expect( getPreviewDeviceWidth( 'desktop' ) ).toBe( 1440 );
		expect( getPreviewDeviceWidth( 'laptop' ) ).toBe( 1024 );
		expect( getPreviewDeviceWidth( 'tablet' ) ).toBe( 768 );
		expect( getPreviewDeviceWidth( 'mobile' ) ).toBe( 390 );
	} );

	it( 'accepts only published device IDs', () => {
		expect( isPreviewDevice( 'tablet' ) ).toBe( true );
		expect( isPreviewDevice( 'watch' ) ).toBe( false );
		expect( isPreviewDevice( 390 ) ).toBe( false );
	} );

	it( 'falls back safely when storage is empty, corrupt, or unavailable', () => {
		expect( readPreviewDevice( null ) ).toBe( 'desktop' );
		expect(
			readPreviewDevice( { getItem: () => 'unknown-device' } )
		).toBe( 'desktop' );
		expect(
			readPreviewDevice( {
				getItem: () => {
					throw new Error( 'blocked' );
				},
			} )
		).toBe( 'desktop' );
	} );

	it( 'persists the selected device without surfacing storage failures', () => {
		const setItem = jest.fn();
		persistPreviewDevice( 'mobile', { setItem } );
		expect( setItem ).toHaveBeenCalledWith(
			PREVIEW_STORAGE_KEY,
			'mobile'
		);

		expect( () =>
			persistPreviewDevice( 'desktop', {
				setItem: () => {
					throw new Error( 'blocked' );
				},
			} )
		).not.toThrow();
	} );
} );
