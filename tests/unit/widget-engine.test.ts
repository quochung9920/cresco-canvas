import {
	CORE_WIDGET_CONTRACTS,
	UNIVERSAL_CONTROL_GROUPS,
	getControlDefinition,
	getWidgetContract,
} from '../../src/engine/widgets/registry';

describe( 'Cresco widget engine registry', () => {
	it( 'publishes contracts for every Session v1 core widget', () => {
		expect( Object.keys( CORE_WIDGET_CONTRACTS ).sort() ).toEqual(
			[
				'button',
				'columns',
				'container',
				'divider',
				'heading',
				'image',
				'list',
				'spacer',
				'text',
			].sort()
		);
	} );

	it( 'publishes stable inner-part selectors for complex widgets', () => {
		// `parts` is a Record, and noUncheckedIndexedAccess makes each lookup
		// possibly undefined; the assertion still fails if a part goes missing.
		expect( getWidgetContract( 'button' )?.parts.text?.selector ).toBe(
			'& [data-cresco-part="text"]'
		);
		expect( getWidgetContract( 'image' )?.parts.media?.selector ).toBe(
			'& [data-cresco-part="media"]'
		);
		expect( getWidgetContract( 'image' )?.parts.caption?.selector ).toBe(
			'& [data-cresco-part="caption"]'
		);
	} );

	it( 'marks universal controls as responsive and token-aware', () => {
		expect( getControlDefinition( 'paddingTop' )?.responsive ).toBe( true );
		expect( getControlDefinition( 'paddingTop' )?.tokenPresets?.length ).toBeGreaterThan( 0 );
		expect( getControlDefinition( 'fontSize' )?.tokenPresets?.some( ( token ) => token.value === '{typography.sizes.h1}' ) ).toBe( true );
		expect( getControlDefinition( 'width' )?.units ).toContain( 'rem' );
	} );

	it( 'keeps control group identifiers unique', () => {
		const ids = UNIVERSAL_CONTROL_GROUPS.map( ( group ) => group.id );
		expect( new Set( ids ).size ).toBe( ids.length );
	} );
} );
