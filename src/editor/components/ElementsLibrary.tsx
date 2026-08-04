import type { BlockInstance } from '@wordpress/blocks';
import { Button, Notice, SearchControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import {
	useCallback,
	useEffect,
	useMemo,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import {
	crescoElements,
	elementCategoryLabels,
	findCrescoElement,
	type CrescoElementDefinition,
	type ElementCategory,
} from '../elements';

const FAVORITES_KEY = 'crescoCanvas.elementFavorites';
const RECENT_KEY = 'crescoCanvas.elementRecent';
const DRAG_MIME = 'application/x-cresco-canvas-element';
const MAX_RECENT = 8;

type LibraryFilter = 'all' | 'favorites' | 'recent' | ElementCategory;

interface BlockEditorSelect {
	getBlockIndex: ( clientId: string ) => number;
	getBlockName: ( clientId: string ) => string | null;
	getBlockOrder: ( rootClientId?: string ) => string[];
	getBlockRootClientId: ( clientId: string ) => string | null;
	getSelectedBlockClientId: () => string | null;
}

interface BlockEditorDispatch {
	insertBlocks: (
		blocks: BlockInstance | BlockInstance[],
		index?: number,
		rootClientId?: string
	) => void;
	selectBlock: ( clientId: string ) => void;
}

interface ElementsLibraryProps {
	onElementInserted?: () => void;
}

function readStoredIds( key: string ): string[] {
	try {
		const stored = window.localStorage.getItem( key );
		const parsed: unknown = stored ? JSON.parse( stored ) : [];
		return Array.isArray( parsed )
			? parsed.filter( ( value ): value is string => typeof value === 'string' )
			: [];
	} catch {
		return [];
	}
}

function writeStoredIds( key: string, ids: string[] ) {
	try {
		window.localStorage.setItem( key, JSON.stringify( ids ) );
	} catch {
		// Storage may be unavailable in privacy modes. The library remains usable.
	}
}

function canContainElements( blockName: string | null ): boolean {
	return [
		'cresco/container',
		'core/group',
		'core/cover',
		'core/column',
	].includes( blockName ?? '' );
}

export function ElementsLibrary( {
	onElementInserted,
}: ElementsLibraryProps ) {
	const [ query, setQuery ] = useState( '' );
	const [ filter, setFilter ] = useState< LibraryFilter >( 'all' );
	const [ favorites, setFavorites ] = useState< string[] >( () =>
		readStoredIds( FAVORITES_KEY )
	);
	const [ recent, setRecent ] = useState< string[] >( () =>
		readStoredIds( RECENT_KEY )
	);
	const [ message, setMessage ] = useState( '' );

	const selection = useSelect( ( select ) => {
		const blockEditor = select(
			'core/block-editor'
		) as unknown as BlockEditorSelect;
		return {
			selectedClientId: blockEditor.getSelectedBlockClientId(),
		};
	}, [] );
	const blockEditorSelect = useSelect(
		( select ) =>
			select( 'core/block-editor' ) as unknown as BlockEditorSelect,
		[]
	);
	const { insertBlocks, selectBlock } = useDispatch(
		'core/block-editor'
	) as unknown as BlockEditorDispatch;

	const insertDefinition = useCallback(
		( definition: CrescoElementDefinition, targetClientId?: string | null ) => {
			const blocks = definition.create();
			const selectedClientId = targetClientId ?? selection.selectedClientId;
			let rootClientId: string | undefined;
			let index: number | undefined;

			if (
				selectedClientId &&
				canContainElements( blockEditorSelect.getBlockName( selectedClientId ) )
			) {
				rootClientId = selectedClientId;
				index = blockEditorSelect.getBlockOrder( rootClientId ).length;
			} else if ( selectedClientId ) {
				rootClientId =
					blockEditorSelect.getBlockRootClientId( selectedClientId ) ??
					undefined;
				index = blockEditorSelect.getBlockIndex( selectedClientId ) + 1;
			} else {
				index = blockEditorSelect.getBlockOrder().length;
			}

			insertBlocks( blocks, index, rootClientId );

			const firstBlock = blocks[ 0 ];
			if ( firstBlock ) {
				selectBlock( firstBlock.clientId );
			}

			const nextRecent = [
				definition.id,
				...recent.filter( ( id ) => id !== definition.id ),
			].slice( 0, MAX_RECENT );
			setRecent( nextRecent );
			writeStoredIds( RECENT_KEY, nextRecent );
			setMessage(
				sprintf(
					/* translators: %s is an element name. */
					__( '%s was added to the page.', 'cresco-canvas' ),
					definition.label
				)
			);
			onElementInserted?.();
		},
		[
			blockEditorSelect,
			insertBlocks,
			onElementInserted,
			recent,
			selectBlock,
			selection.selectedClientId,
		]
	);

	const insertById = useCallback(
		( id: string, targetClientId?: string | null ) => {
			const definition = findCrescoElement( id );
			if ( definition ) {
				insertDefinition( definition, targetClientId );
			}
		},
		[ insertDefinition ]
	);

	useEffect( () => {
		let frame = 0;
		let iframe: HTMLIFrameElement | null = null;
		const documents = new Set< Document >();

		const onDragOver = ( event: DragEvent ) => {
			if ( event.dataTransfer?.types.includes( DRAG_MIME ) ) {
				event.preventDefault();
				if ( event.dataTransfer ) {
					event.dataTransfer.dropEffect = 'copy';
				}
			}
		};
		const onDrop = ( event: DragEvent ) => {
			const id = event.dataTransfer?.getData( DRAG_MIME );
			if ( ! id ) {
				return;
			}

			event.preventDefault();
			const target = event.target instanceof Element ? event.target : null;
			const targetClientId = target
				?.closest< HTMLElement >( '[data-block]' )
				?.dataset.block;
			insertById( id, targetClientId ?? null );
		};
		const attach = ( targetDocument: Document | null | undefined ) => {
			if ( ! targetDocument || documents.has( targetDocument ) ) {
				return;
			}
			documents.add( targetDocument );
			targetDocument.addEventListener( 'dragover', onDragOver );
			targetDocument.addEventListener( 'drop', onDrop );
		};
		const findCanvas = () => {
			attach( document );
			iframe = document.querySelector< HTMLIFrameElement >(
				'iframe[name="editor-canvas"]'
			);
			attach( iframe?.contentDocument );
			frame = window.requestAnimationFrame( findCanvas );
		};

		findCanvas();

		return () => {
			window.cancelAnimationFrame( frame );
			for ( const targetDocument of documents ) {
				targetDocument.removeEventListener( 'dragover', onDragOver );
				targetDocument.removeEventListener( 'drop', onDrop );
			}
		};
	}, [ insertById ] );

	const visibleElements = useMemo( () => {
		const normalizedQuery = query.trim().toLocaleLowerCase();
		return crescoElements.filter( ( element ) => {
			if ( filter === 'favorites' && ! favorites.includes( element.id ) ) {
				return false;
			}
			if ( filter === 'recent' && ! recent.includes( element.id ) ) {
				return false;
			}
			if (
				filter !== 'all' &&
				filter !== 'favorites' &&
				filter !== 'recent' &&
				element.category !== filter
			) {
				return false;
			}
			if ( ! normalizedQuery ) {
				return true;
			}

			return [
				element.label,
				element.description,
				...element.keywords,
			]
				.join( ' ' )
				.toLocaleLowerCase()
				.includes( normalizedQuery );
		} );
	}, [ favorites, filter, query, recent ] );

	function toggleFavorite( id: string ) {
		const nextFavorites = favorites.includes( id )
			? favorites.filter( ( favoriteId ) => favoriteId !== id )
			: [ ...favorites, id ];
		setFavorites( nextFavorites );
		writeStoredIds( FAVORITES_KEY, nextFavorites );
	}

	return (
		<div className="cc-elements-library">
			<div className="cc-elements-library__intro">
				<strong>{ __( 'Cresco Elements', 'cresco-canvas' ) }</strong>
				<p>
					{ __(
						'Click an element to insert it, or drag it onto the editor canvas.',
						'cresco-canvas'
					) }
				</p>
			</div>
			<SearchControl
				label={ __( 'Search elements', 'cresco-canvas' ) }
				onChange={ setQuery }
				placeholder={ __( 'Search elements…', 'cresco-canvas' ) }
				value={ query }
			/>
			<div
				aria-label={ __( 'Element categories', 'cresco-canvas' ) }
				className="cc-elements-filters"
				role="group"
			>
				<Button
					isPressed={ filter === 'all' }
					onClick={ () => setFilter( 'all' ) }
					variant="tertiary"
				>
					{ __( 'All', 'cresco-canvas' ) }
				</Button>
				<Button
					isPressed={ filter === 'favorites' }
					onClick={ () => setFilter( 'favorites' ) }
					variant="tertiary"
				>
					{ __( 'Favorites', 'cresco-canvas' ) }
				</Button>
				<Button
					isPressed={ filter === 'recent' }
					onClick={ () => setFilter( 'recent' ) }
					variant="tertiary"
				>
					{ __( 'Recent', 'cresco-canvas' ) }
				</Button>
				{ (
					Object.entries( elementCategoryLabels ) as [
						ElementCategory,
						string,
					][]
				).map( ( [ category, label ] ) => (
					<Button
						isPressed={ filter === category }
						key={ category }
						onClick={ () => setFilter( category ) }
						variant="tertiary"
					>
						{ label }
					</Button>
				) ) }
			</div>
			{ message && (
				<Notice
					isDismissible
					onRemove={ () => setMessage( '' ) }
					status="success"
				>
					{ message }
				</Notice>
			) }
			<div className="cc-elements-grid-list">
				{ visibleElements.map( ( element ) => {
					const isFavorite = favorites.includes( element.id );
					return (
						<div
							className="cc-element-card"
							draggable
							key={ element.id }
							onDragStart={ ( event ) => {
								event.dataTransfer.effectAllowed = 'copy';
								event.dataTransfer.setData( DRAG_MIME, element.id );
							} }
						>
							<Button
								className="cc-element-card__insert"
								icon={ element.icon }
								onClick={ () => insertDefinition( element ) }
								showTooltip
								text={ element.description }
							>
								<span>{ element.label }</span>
							</Button>
							<Button
								aria-label={
									isFavorite
										? sprintf(
											/* translators: %s is an element name. */
											__( 'Remove %s from favorites', 'cresco-canvas' ),
											element.label
										)
										: sprintf(
											/* translators: %s is an element name. */
											__( 'Add %s to favorites', 'cresco-canvas' ),
											element.label
										)
								}
								className="cc-element-card__favorite"
								icon={ isFavorite ? 'star-filled' : 'star-empty' }
								isPressed={ isFavorite }
								onClick={ () => toggleFavorite( element.id ) }
								size="small"
							/>
						</div>
					);
				} ) }
			</div>
			{ visibleElements.length === 0 && (
				<p className="cc-elements-empty">
					{ __( 'No matching elements were found.', 'cresco-canvas' ) }
				</p>
			) }
		</div>
	);
}
