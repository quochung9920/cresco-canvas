import type { Block as BlockInstance } from '@wordpress/blocks';

import type { CrescoElementDefinition } from './elements';

export const MAX_RECENT_ELEMENTS = 8;

export interface InsertionPoint {
	index: number;
	rootClientId?: string;
}

interface BlockLocationReader {
	getBlockIndex: ( clientId: string ) => number;
	getBlockName: ( clientId: string ) => string | null;
	getBlockOrder: ( rootClientId?: string ) => string[];
	getBlockRootClientId: ( clientId: string ) => string | null;
}

export function sanitizeElementIds(
	value: unknown,
	validIds: ReadonlySet< string >,
	limit = Number.POSITIVE_INFINITY
): string[] {
	if ( ! Array.isArray( value ) || limit <= 0 ) {
		return [];
	}

	const result: string[] = [];
	const seen = new Set< string >();

	for ( const candidate of value ) {
		if (
			typeof candidate !== 'string' ||
			! validIds.has( candidate ) ||
			seen.has( candidate )
		) {
			continue;
		}

		seen.add( candidate );
		result.push( candidate );

		if ( result.length >= limit ) {
			break;
		}
	}

	return result;
}

export function prependRecentElement(
	current: readonly string[],
	id: string,
	validIds: ReadonlySet< string >,
	limit = MAX_RECENT_ELEMENTS
): string[] {
	return sanitizeElementIds( [ id, ...current ], validIds, limit );
}

export function matchesElementQuery(
	element: Pick<
		CrescoElementDefinition,
		'label' | 'description' | 'keywords'
	>,
	query: string
): boolean {
	const normalizedQuery = query.trim().toLocaleLowerCase();
	if ( ! normalizedQuery ) {
		return true;
	}

	return [ element.label, element.description, ...element.keywords ]
		.join( ' ' )
		.toLocaleLowerCase()
		.includes( normalizedQuery );
}

export function collectBlockNames(
	blocks: readonly BlockInstance[]
): string[] {
	const names: string[] = [];

	for ( const current of blocks ) {
		names.push( current.name );
		if ( current.innerBlocks.length > 0 ) {
			names.push( ...collectBlockNames( current.innerBlocks ) );
		}
	}

	return names;
}

export function findUnavailableBlockNames(
	blocks: readonly BlockInstance[],
	isRegistered: ( name: string ) => boolean
): string[] {
	return [ ...new Set( collectBlockNames( blocks ).filter( ( name ) => ! isRegistered( name ) ) ) ];
}

export function resolveInsertionPoint(
	selectedClientId: string | null | undefined,
	reader: BlockLocationReader,
	canContainElements: ( blockName: string | null ) => boolean
): InsertionPoint {
	if (
		selectedClientId &&
		canContainElements( reader.getBlockName( selectedClientId ) )
	) {
		return {
			index: reader.getBlockOrder( selectedClientId ).length,
			rootClientId: selectedClientId,
		};
	}

	if ( selectedClientId ) {
		const rootClientId =
			reader.getBlockRootClientId( selectedClientId ) ?? undefined;
		return {
			index: Math.max( 0, reader.getBlockIndex( selectedClientId ) + 1 ),
			rootClientId,
		};
	}

	return {
		index: reader.getBlockOrder().length,
	};
}
