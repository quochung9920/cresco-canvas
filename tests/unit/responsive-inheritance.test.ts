import {
	cascadeTo,
	describeInheritance,
	overriddenKeysAt,
	resolveInheritance,
	type StyleBearingNode,
} from '../../src/studio/responsiveInheritance';

const node: StyleBearingNode = {
	style: { paddingTop: '32px', color: '#000', gap: '0' },
	responsive: {
		laptop: { paddingTop: '24px' },
		mobile: { paddingTop: '', gap: '8px' },
	},
};

describe( 'cascadeTo', () => {
	it( 'returns the widest-to-narrowest chain including the device', () => {
		expect( cascadeTo( 'tablet' ) ).toEqual( [
			'wide',
			'desktop',
			'laptop',
			'tablet',
		] );
	} );

	it( 'returns only the base for the widest breakpoint', () => {
		expect( cascadeTo( 'wide' ) ).toEqual( [ 'wide' ] );
	} );

	it( 'falls back to the base for an unknown device', () => {
		expect(
			cascadeTo( 'watch' as unknown as 'mobile' )
		).toEqual( [ 'wide' ] );
	} );
} );

describe( 'resolveInheritance', () => {
	it( 'reports a base value as owned at the widest breakpoint', () => {
		expect( resolveInheritance( node, 'wide', 'paddingTop' ) ).toEqual( {
			origin: 'own',
			source: 'wide',
			value: '32px',
		} );
	} );

	it( 'reports inheritance from the base on an untouched breakpoint', () => {
		expect( resolveInheritance( node, 'desktop', 'paddingTop' ) ).toEqual( {
			origin: 'inherited',
			source: 'wide',
			value: '32px',
		} );
	} );

	it( 'reports ownership where the override is written', () => {
		expect( resolveInheritance( node, 'laptop', 'paddingTop' ) ).toEqual( {
			origin: 'own',
			source: 'laptop',
			value: '24px',
		} );
	} );

	it( 'inherits the nearest wider override, not the base', () => {
		expect( resolveInheritance( node, 'tablet', 'paddingTop' ) ).toEqual( {
			origin: 'inherited',
			source: 'laptop',
			value: '24px',
		} );
	} );

	it( 'treats an empty string as absence rather than a value', () => {
		// Studio clears a key by writing '', so Mobile must keep inheriting.
		expect( resolveInheritance( node, 'mobile', 'paddingTop' ) ).toEqual( {
			origin: 'inherited',
			source: 'laptop',
			value: '24px',
		} );
	} );

	it( 'preserves a falsy but meaningful value such as "0"', () => {
		expect( resolveInheritance( node, 'desktop', 'gap' ) ).toEqual( {
			origin: 'inherited',
			source: 'wide',
			value: '0',
		} );
	} );

	it( 'reports unset for a property no breakpoint defines', () => {
		expect( resolveInheritance( node, 'mobile', 'borderRadius' ) ).toEqual( {
			origin: 'unset',
			source: null,
			value: undefined,
		} );
	} );

	it( 'handles a missing node and an empty key', () => {
		expect( resolveInheritance( null, 'mobile', 'gap' ).origin ).toBe(
			'unset'
		);
		expect( resolveInheritance( node, 'mobile', '' ).origin ).toBe(
			'unset'
		);
	} );

	it( 'handles a node with no style bags at all', () => {
		expect( resolveInheritance( {}, 'tablet', 'gap' ).origin ).toBe(
			'unset'
		);
	} );
} );

describe( 'overriddenKeysAt', () => {
	it( 'lists only keys actually written at the breakpoint', () => {
		expect( overriddenKeysAt( node, 'mobile' ) ).toEqual( [ 'gap' ] );
	} );

	it( 'returns base keys for the widest breakpoint', () => {
		expect( overriddenKeysAt( node, 'wide' ) ).toEqual( [
			'color',
			'gap',
			'paddingTop',
		] );
	} );

	it( 'returns nothing for an untouched breakpoint', () => {
		expect( overriddenKeysAt( node, 'tablet' ) ).toEqual( [] );
		expect( overriddenKeysAt( null, 'tablet' ) ).toEqual( [] );
	} );
} );

describe( 'describeInheritance', () => {
	it( 'names the breakpoint a value was inherited from', () => {
		expect(
			describeInheritance( resolveInheritance( node, 'tablet', 'paddingTop' ) )
		).toBe( 'Inherited from Laptop.' );
	} );

	it( 'distinguishes owned and unset properties', () => {
		expect(
			describeInheritance( resolveInheritance( node, 'laptop', 'paddingTop' ) )
		).toBe( 'Set at this breakpoint.' );
		expect(
			describeInheritance( resolveInheritance( node, 'mobile', 'zIndex' ) )
		).toBe( 'Not set at any breakpoint.' );
	} );
} );
