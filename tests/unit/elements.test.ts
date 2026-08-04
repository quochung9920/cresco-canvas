jest.mock( '@wordpress/i18n', () => ( {
	__: ( value: string ) => value,
} ) );

jest.mock( '@wordpress/blocks', () => ( {
	createBlock: (
		name: string,
		attributes: Record< string, unknown > = {},
		innerBlocks: unknown[] = []
	) => ( {
		attributes,
		clientId: `${ name }-${ Math.random() }`,
		innerBlocks,
		isValid: true,
		name,
	} ),
} ) );

import {
	crescoElements,
	elementCategoryLabels,
	findCrescoElement,
} from '../../src/editor/elements';

describe( 'Cresco Elements registry', () => {
	it( 'uses unique stable IDs and known categories', () => {
		const ids = crescoElements.map( ( element ) => element.id );
		const categories = new Set( Object.keys( elementCategoryLabels ) );

		expect( new Set( ids ).size ).toBe( ids.length );
		expect( ids.length ).toBeGreaterThanOrEqual( 30 );

		for ( const element of crescoElements ) {
			expect( element.id ).toMatch( /^[a-z0-9]+(?:-[a-z0-9]+)*$/ );
			expect( element.label.trim() ).not.toBe( '' );
			expect( element.description.trim() ).not.toBe( '' );
			expect( element.keywords.length ).toBeGreaterThan( 0 );
			expect( categories.has( element.category ) ).toBe( true );
			expect( findCrescoElement( element.id ) ).toBe( element );
		}
	} );

	it( 'creates at least one valid native block for every element', () => {
		for ( const element of crescoElements ) {
			const blocks = element.create();

			expect( blocks.length ).toBeGreaterThan( 0 );
			for ( const block of blocks ) {
				expect( block.name ).toMatch( /^(?:core|cresco)\// );
				expect( block.clientId ).toBeTruthy();
			}
		}
	} );

	it( 'returns undefined for an unknown element ID', () => {
		expect( findCrescoElement( 'not-a-real-element' ) ).toBeUndefined();
	} );
} );
