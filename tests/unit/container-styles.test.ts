import { styleFromAttributes } from '../../src/blocks/container/styles';
import type { ContainerAttributes } from '../../src/blocks/container/types';

const attributes: ContainerAttributes = {
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

describe( 'styleFromAttributes', () => {
	it( 'preserves the serialized 0.1.x container style contract', () => {
		expect( styleFromAttributes( attributes ) ).toEqual( {
			alignItems: 'stretch',
			background: '#ffffff',
			display: 'flex',
			flexDirection: 'column',
			gap: '24px',
			justifyContent: 'flex-start',
			marginLeft: 'auto',
			marginRight: 'auto',
			maxWidth: '1200px',
			padding: '40px 24px 40px 24px',
		} );
	} );

	it( 'omits flex-only properties for block layout', () => {
		const result = styleFromAttributes( {
			...attributes,
			layoutMode: 'block',
		} );
		expect( result.alignItems ).toBeUndefined();
		expect( result.flexDirection ).toBeUndefined();
		expect( result.gap ).toBeUndefined();
		expect( result.justifyContent ).toBeUndefined();
	} );
} );
