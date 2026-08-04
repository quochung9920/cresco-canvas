import {
	hasResponsiveEnhancements,
	normalizeContainerLayout,
	resetResponsiveDevice,
	resolveContainerLayout,
	updateResponsiveOverride,
} from '../../src/blocks/container/responsive';
import { styleFromAttributes } from '../../src/blocks/container/styles';
import type { ContainerAttributes } from '../../src/blocks/container/types';

const base: ContainerAttributes = {
	align: 'stretch',
	background: '#ffffff',
	direction: 'column',
	gap: 24,
	justify: 'flex-start',
	layoutMode: 'flex',
	maxWidth: 1200,
	paddingBottom: 40,
	paddingLeft: 24,
	paddingRight: 24,
	paddingTop: 40,
};

describe( 'responsive Container layout', () => {
	it( 'normalizes invalid values into bounded safe layout values', () => {
		expect(
			normalizeContainerLayout( {
				align: 'invalid' as never,
				columns: 99,
				gap: -8,
				layoutMode: 'grid',
				maxWidth: Number.POSITIVE_INFINITY,
				paddingTop: 999,
			} )
		).toMatchObject( {
			align: 'stretch',
			columns: 12,
			gap: 0,
			layoutMode: 'grid',
			maxWidth: 1200,
			paddingTop: 400,
		} );
	} );

	it( 'inherits desktop to laptop, laptop to tablet, and tablet to mobile', () => {
		const attributes: ContainerAttributes = {
			...base,
			responsive: {
				laptop: { gap: 20 },
				tablet: { columns: 2, layoutMode: 'grid' },
				mobile: { columns: 1 },
			},
		};

		expect( resolveContainerLayout( attributes, 'laptop' ).gap ).toBe( 20 );
		expect( resolveContainerLayout( attributes, 'tablet' ) ).toMatchObject( {
			columns: 2,
			gap: 20,
			layoutMode: 'grid',
		} );
		expect( resolveContainerLayout( attributes, 'mobile' ) ).toMatchObject( {
			columns: 1,
			gap: 20,
			layoutMode: 'grid',
		} );
	} );

	it( 'keeps 4K as an independent desktop-derived override', () => {
		const attributes: ContainerAttributes = {
			...base,
			responsive: {
				'4k': { maxWidth: 1800 },
				laptop: { maxWidth: 960 },
			},
		};

		expect( resolveContainerLayout( attributes, '4k' ).maxWidth ).toBe( 1800 );
		expect( resolveContainerLayout( attributes, '4k' ).gap ).toBe( 24 );
	} );

	it( 'updates and resets only the requested device override', () => {
		const responsive = updateResponsiveOverride(
			{ laptop: { gap: 20 } },
			'tablet',
			'columns',
			2
		);

		expect( responsive ).toEqual( {
			laptop: { gap: 20 },
			tablet: { columns: 2 },
		} );
		expect( resetResponsiveDevice( responsive, 'tablet' ) ).toEqual( {
			laptop: { gap: 20 },
		} );
	} );

	it( 'preserves legacy inline output until responsive controls are used', () => {
		expect( hasResponsiveEnhancements( base ) ).toBe( false );
		expect( styleFromAttributes( base ) ).toMatchObject( {
			display: 'flex',
			maxWidth: '1200px',
			padding: '40px 24px 40px 24px',
		} );
	} );

	it( 'emits resolved variables and rejects unsafe background values', () => {
		const style = styleFromAttributes( {
			...base,
			background: 'url(javascript:alert(1))',
			columns: 3,
			responsive: { mobile: { columns: 1, gap: 12 } },
		} ) as Record< string, string | number | undefined >;

		expect( style.backgroundColor ).toBeUndefined();
		expect( style[ '--cc-columns-desktop' ] ).toBe( '3' );
		expect( style[ '--cc-columns-mobile' ] ).toBe( '1' );
		expect( style[ '--cc-gap-mobile' ] ).toBe( '12px' );
	} );
} );
