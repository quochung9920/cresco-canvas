import { getBlockType, type BlockInstance } from '@wordpress/blocks';
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
import {
	findUnavailableBlockNames,
	matchesElementQuery,
	MAX_RECENT_ELEMENTS,
	prependRecentElement,
	resolveInsertionPoint,
	sanitizeElementIds,
} from '../elementLibraryState';

const FAVORITES_KEY = 'crescoCanvas.elementFavorites';
const RECENT_KEY = 'crescoCanvas.elementRecent';
const DRAG_MIME = 'application/x-cresco-canvas-element';
const VALID_ELEMENT_IDS = new Set(
	crescoElements.map( ( element ) => element.id )
);

type LibraryFilter = 'all' | 'favorites' | 'recent' | ElementCategory;
type LibraryNoticeStatus = 'success' | 'warning' | 'error';

interface LibraryNotice {
	message: string;
	status: LibraryNoticeStatus;
}

interface BlockEditorSelect {
	canInsertBlockType?: ( blockName: string, rootClientId?: string ) => boolean;
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

function readStoredIds( key: string, limit = Number.POSITIVE_INFINITY ): string[] {
	try {
		const stored = window.localStorage.getItem( key );
		const parsed: unknown = stored ? JSON.parse( stored ) : [];
		return sanitizeElementIds( parsed, VALID_ELEMENT_IDS, limit );
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
		readStoredIds( RECENT_KEY, MAX_RECENT_ELEMENTS )
	);
	const [ notice, setNotice ] = useState< LibraryNotice | null >( null );

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
			setNotice( null );

			try {
				const blocks = definition.create();
				if ( blocks.length === 0 ) {
					throw new Error( 'Element factory returned no blocks.' );
				}

				const unavailableBlockNames = findUnavailableBlockNames(
					blocks,
					( blockName ) => Boolean( getBlockType( blockName ) )
				);
				if ( unavailableBlockNames.length > 0 ) {
					setNotice( {
						message: sprintf(
							/* translators: 1: element name, 2: comma-separated block names. */
							__(
								'%1$s cannot be added because these WordPress blocks are unavailable: %2$s.',
								'cresco-canvas'
							),
							definition.label,
							unavailableBlockNames.join( ', ' )
						),
						status: 'error',
					} );
					return;
				}

				const selectedClientId =
					targetClientId ?? selection.selectedClientId;
				const insertionPoint = resolveInsertionPoint(
					selectedClientId,
					blockEditorSelect,
					canContainElements
				);
				const blockedRootNames =
					typeof blockEditorSelect.canInsertBlockType === 'function'
						? [
								...new Set(
									blocks
										.map( ( current ) => current.name )
										.filter(
											( blockName ) =>
												! blockEditorSelect.canInsertBlockType?.(
													blockName,
													insertionPoint.rootClientId
												)
										)
								),
							]
						: [];

				if ( blockedRootNames.length > 0 ) {
					setNotice( {
						message: sprintf(
							/* translators: 1: element name, 2: comma-separated block names. */
							__(
								'%1$s cannot be inserted at the selected location. Restricted blocks: %2$s.',
								'cresco-canvas'
							),
							definition.label,
							blockedRootNames.join( ', ' )
						),
						status: 'warning',
					} );
					return;
				}

				insertBlocks(
					blocks,
					insertionPoint.index,
					insertionPoint.rootClientId
				);

				const firstBlock = blocks[ 0 ];
				if ( firstBlock ) {
					selectBlock( firstBlock.clientId );
				}

				setRecent( ( current ) => {
					const nextRecent = prependRecentElement(
						current,
						definition.id,
						VALID_ELEMENT_IDS,
						MAX_RECENT_ELEMENTS
					);
					writeStoredIds( RECENT_KEY, nextRecent );
					return nextRecent;
				} );
				setNotice( {
					message: sprintf(
						/* translators: %s is an element name. */
						__( '%s was added to the page.', 'cresco-canvas' ),
						definition.label
					),
					status: 'success',
				} );
				onElementInserted?.();
			} catch {
				setNotice( {
					message: sprintf(
						/* translators: %s is an element name. */
						__(
							'%s could not be added. Reload the editor and try again.',
							'cresco-canvas'
						),
						definition.label
					),
					status: 'error',
				} );
			}
		},
		[
			blockEditorSelect,
			insertBlocks,
			onElementInserted,
			selectBlock,
			selection.selectedClientId,
		]
	);

	const insertById = useCallback(
		( id: string, targetClientId?: string | null ) => {
			const definition = findCrescoElement( id );
			if ( ! definition ) {
				setNotice( {
					message: __(
						'This dragged element is no longer available.',
						'cresco-canvas'
					),
					status: 'error',
				} );
				return;
			}

			insertDefinition( definition, targetClientId );
		},
		[ insertDefinition ]
	);

	useEffect( () => {
		const documents = new Set< Document >();
		const iframeListeners = new Map< HTMLIFrameElement, () => void >();

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
			const target = event.target as Element | null;
			const targetClientId =
				target && typeof target.closest === 'function'
					? target.closest< HTMLElement >( '[data-block]' )?.dataset.block
					: undefined;
			insertById( id, targetClientId ?? null );
		};
		const attachDocument = ( targetDocument: Document | null | undefined ) => {
			if ( ! targetDocument || documents.has( targetDocument ) ) {
				return;
			}
			documents.add( targetDocument );
			targetDocument.addEventListener( 'dragover', onDragOver );
			targetDocument.addEventListener( 'drop', onDrop );
		};
		const attachIframe = ( iframe: HTMLIFrameElement ) => {
			if ( iframeListeners.has( iframe ) ) {
				return;
			}

			const onLoad = () => attachDocument( iframe.contentDocument );
			iframeListeners.set( iframe, onLoad );
			iframe.addEventListener( 'load', onLoad );
			onLoad();
		};
		const scanForCanvas = () => {
			document
				.querySelectorAll< HTMLIFrameElement >(
					'iframe[name="editor-canvas"]'
				)
				.forEach( attachIframe );
		};

		attachDocument( document );
		scanForCanvas();

		const observer = new MutationObserver( scanForCanvas );
		observer.observe( document.documentElement, {
			childList: true,
			subtree: true,
		} );

		return () => {
			observer.disconnect();
			for ( const [ iframe, onLoad ] of iframeListeners ) {
				iframe.removeEventListener( 'load', onLoad );
			}
			for ( const targetDocument of documents ) {
				targetDocument.removeEventListener( 'dragover', onDragOver );
				targetDocument.removeEventListener( 'drop', onDrop );
			}
		};
	}, [ insertById ] );

	const visibleElements = useMemo( () => {
		const matches = crescoElements.filter( ( element ) => {
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

			return matchesElementQuery( element, query );
		} );

		return filter === 'recent'
			? matches.sort(
					( first, second ) =>
						recent.indexOf( first.id ) - recent.indexOf( second.id )
			  )
			: matches;
	}, [ favorites, filter, query, recent ] );

	function toggleFavorite( id: string ) {
		setFavorites( ( current ) => {
			const nextFavorites = current.includes( id )
				? current.filter( ( favoriteId ) => favoriteId !== id )
				: [ ...current, id ];
			const sanitized = sanitizeElementIds(
				nextFavorites,
				VALID_ELEMENT_IDS
			);
			writeStoredIds( FAVORITES_KEY, sanitized );
			return sanitized;
		} );
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
			{ notice && (
				<Notice
					isDismissible
					onRemove={ () => setNotice( null ) }
					status={ notice.status }
				>
					{ notice.message }
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
