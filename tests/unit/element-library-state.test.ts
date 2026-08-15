import type { Block as BlockInstance } from '@wordpress/blocks';

import {
	collectBlockNames,
	findUnavailableBlockNames,
	matchesElementQuery,
	prependRecentElement,
	resolveInsertionPoint,
	sanitizeElementIds,
} from '../../src/editor/elementLibraryState';

const validIds = new Set( [ 'heading', 'text', 'image' ] );

function block(
	name: string,
	innerBlocks: BlockInstance[] = []
): BlockInstance {
	return {
		attributes: {},
		clientId: `${ name }-${ innerBlocks.length }`,
		innerBlocks,
		isValid: true,
		name,
		originalContent: '',
		validationIssues: [],
	} as BlockInstance;
}

describe( 'element library state', () => {
	it( 'sanitizes persisted IDs, removes duplicates, and applies limits', () => {
		expect(
			sanitizeElementIds(
				[ 'heading', 'unknown', 7, 'text', 'heading', 'image' ],
				validIds,
				2
			)
		).toEqual( [ 'heading', 'text' ] );
		expect( sanitizeElementIds( { heading: true }, validIds ) ).toEqual( [] );
	} );

	it( 'prepends recent elements without duplicates', () => {
		expect(
			prependRecentElement(
				[ 'text', 'heading', 'image' ],
				'heading',
				validIds,
				2
			)
		).toEqual( [ 'heading', 'text' ] );
	} );

	it( 'matches labels, descriptions, and keywords case-insensitively', () => {
		const element = {
			description: 'Semantic editable heading.',
			keywords: [ 'headline', 'title' ],
			label: 'Heading',
		};

		expect( matchesElementQuery( element, 'HEAD' ) ).toBe( true );
		expect( matchesElementQuery( element, 'semantic' ) ).toBe( true );
		expect( matchesElementQuery( element, 'title' ) ).toBe( true );
		expect( matchesElementQuery( element, 'gallery' ) ).toBe( false );
	} );

	it( 'collects nested block names and reports unavailable block types once', () => {
		const blocks = [
			block( 'core/group', [
				block( 'core/heading' ),
				block( 'missing/widget' ),
				block( 'missing/widget' ),
			] ),
		];

		expect( collectBlockNames( blocks ) ).toEqual( [
			'core/group',
			'core/heading',
			'missing/widget',
			'missing/widget',
		] );
		expect(
			findUnavailableBlockNames(
				blocks,
				( name ) => name.startsWith( 'core/' )
			)
		).toEqual( [ 'missing/widget' ] );
	} );

	it( 'inserts inside containers, after selected siblings, or at document end', () => {
		const reader = {
			getBlockIndex: ( clientId: string ) =>
				clientId === 'paragraph' ? 2 : -1,
			getBlockName: ( clientId: string ) =>
				clientId === 'container' ? 'core/group' : 'core/paragraph',
			getBlockOrder: ( rootClientId?: string ) =>
				rootClientId === 'container'
					? [ 'one', 'two' ]
					: [ 'a', 'b', 'c', 'd' ],
			getBlockRootClientId: ( clientId: string ) =>
				clientId === 'paragraph' ? 'parent' : null,
		};
		const canContain = ( name: string | null ) => name === 'core/group';

		expect(
			resolveInsertionPoint( 'container', reader, canContain )
		).toEqual( { index: 2, rootClientId: 'container' } );
		expect(
			resolveInsertionPoint( 'paragraph', reader, canContain )
		).toEqual( { index: 3, rootClientId: 'parent' } );
		expect( resolveInsertionPoint( null, reader, canContain ) ).toEqual( {
			index: 4,
		} );
	} );
} );
