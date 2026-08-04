import { BlockCanvas, BlockEditorProvider } from '@wordpress/block-editor';
import { parse, serialize, type Block } from '@wordpress/blocks';
import { Button, Notice, Spinner } from '@wordpress/components';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { normalizeApiError, request } from './api';
import { createElementBlock } from './blockFactory';
import { ElementPanel } from './components/ElementPanel';
import { InspectorPanel } from './components/InspectorPanel';
import { TopBar } from './components/TopBar';
import type { Device, GlobalSettings, PageRecord, SaveResponse } from './types';

interface SavedSnapshot {
	content: string;
	title: string;
}

type NoticeStatus = 'error' | 'info' | 'success' | 'warning';

export function App() {
	const bootstrap = window.crescoCanvasSettings;
	const [ page, setPage ] = useState< PageRecord | null >( null );
	const [ blocks, setBlocks ] = useState< Block[] >( [] );
	const [ saved, setSaved ] = useState< SavedSnapshot >( {
		content: '',
		title: '',
	} );
	const [ device, setDevice ] = useState< Device >( 'desktop' );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( '' );
	const [ noticeStatus, setNoticeStatus ] =
		useState< NoticeStatus >( 'info' );
	const [ settingsOpen, setSettingsOpen ] = useState( false );
	const [ globalSettings, setGlobalSettings ] =
		useState< GlobalSettings | null >( null );

	const serializedContent = useMemo( () => serialize( blocks ), [ blocks ] );
	const dirty =
		Boolean( page ) &&
		( serializedContent !== saved.content || page?.title !== saved.title );

	const showError = useCallback( ( error: unknown ) => {
		const normalized = normalizeApiError( error );
		setNotice(
			normalized.message ||
				__(
					'Cresco Canvas encountered an unexpected error.',
					'cresco-canvas'
				)
		);
		setNoticeStatus( 'error' );
		setSaving( false );
	}, [] );

	useEffect( () => {
		let active = true;
		let settingsFailed = false;

		async function load() {
			try {
				const settingsRequest = bootstrap.canManageSettings
					? request< GlobalSettings >( 'settings' ).catch( () => {
							settingsFailed = true;
							return null;
					  } )
					: Promise.resolve( null );
				const pageRequest = bootstrap.postId
					? request< PageRecord >( `pages/${ bootstrap.postId }` )
					: Promise.resolve( null );
				const [ loadedSettings, loadedPage ] = await Promise.all( [
					settingsRequest,
					pageRequest,
				] );

				if ( ! active ) {
					return;
				}

				setGlobalSettings( loadedSettings );

				if ( loadedPage ) {
					setPage( loadedPage );
					setBlocks( parse( loadedPage.content || '' ) );
					setSaved( {
						content: loadedPage.content || '',
						title: loadedPage.title || '',
					} );
				}

				if ( settingsFailed ) {
					setNotice(
						__(
							'The Page loaded, but Global Settings are temporarily unavailable.',
							'cresco-canvas'
						)
					);
					setNoticeStatus( 'warning' );
				}
			} catch ( error ) {
				if ( active ) {
					showError( error );
				}
			} finally {
				if ( active ) {
					setLoading( false );
				}
			}
		}

		void load();

		return () => {
			active = false;
		};
	}, [ bootstrap.canManageSettings, bootstrap.postId, showError ] );

	useEffect( () => {
		if ( ! dirty ) {
			return undefined;
		}

		const warnBeforeUnload = ( event: BeforeUnloadEvent ) => {
			event.preventDefault();
			event.returnValue = '';
		};

		window.addEventListener( 'beforeunload', warnBeforeUnload );
		return () =>
			window.removeEventListener( 'beforeunload', warnBeforeUnload );
	}, [ dirty ] );

	async function savePage() {
		if ( ! page || saving ) {
			return;
		}

		setSaving( true );
		setNotice( '' );

		try {
			const result = await request< SaveResponse >(
				`pages/${ page.id }`,
				{
					data: {
						content: serializedContent,
						revision: page.revision,
						status: page.status,
						title: page.title,
					},
					method: 'POST',
				}
			);

			setPage( {
				...page,
				modifiedGmt: result.modifiedGmt,
				preview: result.preview,
				revision: result.revision,
			} );
			setSaved( { content: serializedContent, title: page.title } );
			setNotice( __( 'Page saved successfully.', 'cresco-canvas' ) );
			setNoticeStatus( 'success' );
		} catch ( error ) {
			showError( error );
		} finally {
			setSaving( false );
		}
	}

	async function saveGlobalSettings() {
		if ( ! globalSettings ) {
			return;
		}

		try {
			const result = await request< GlobalSettings >( 'settings', {
				data: globalSettings,
				method: 'POST',
			} );
			setGlobalSettings( result );
			setNotice( __( 'Global Settings saved.', 'cresco-canvas' ) );
			setNoticeStatus( 'success' );
		} catch ( error ) {
			showError( error );
		}
	}

	function addElement( name: string ) {
		setBlocks( ( current ) => [ ...current, createElementBlock( name ) ] );
	}

	const editorSettings = useMemo(
		() => ( {
			canLockBlocks: true,
			focusMode: false,
			hasFixedToolbar: false,
			templateLock: false as const,
		} ),
		[]
	);
	let canvasContent;

	if ( loading ) {
		canvasContent = (
			<div
				aria-label={ __( 'Loading Page', 'cresco-canvas' ) }
				className="cc-empty"
			>
				<Spinner />
			</div>
		);
	} else if ( page ) {
		canvasContent = (
			<div className="cc-editor editor-styles-wrapper">
				<BlockCanvas height="100%" />
			</div>
		);
	} else {
		canvasContent = (
			<div className="cc-empty">
				<strong>{ __( 'No Page selected.', 'cresco-canvas' ) }</strong>
				<p>
					{ __(
						'Open Pages and choose Edit in Canvas for the Page you want to build.',
						'cresco-canvas'
					) }
				</p>
				<Button href={ bootstrap.pagesUrl } variant="primary">
					{ __( 'Open Pages', 'cresco-canvas' ) }
				</Button>
			</div>
		);
	}

	const canvas = (
		<section className="cc-canvas-wrap">
			<div className="cc-canvas" data-device={ device }>
				{ canvasContent }
			</div>
		</section>
	);

	const shell = (
		<main className="cc-shell">
			<ElementPanel disabled={ ! page } onAdd={ addElement } />
			{ canvas }
			{ page ? (
				<InspectorPanel
					globalSettings={ globalSettings }
					onGlobalChange={ setGlobalSettings }
					onGlobalSave={ saveGlobalSettings }
					settingsOpen={ settingsOpen }
				/>
			) : (
				<aside className="cc-panel cc-panel-right">
					<div className="cc-panel-header">
						{ __( 'Block Settings', 'cresco-canvas' ) }
					</div>
					<div className="cc-panel-body">
						{ __(
							'Select a Page to begin editing.',
							'cresco-canvas'
						) }
					</div>
				</aside>
			) }
		</main>
	);

	return (
		<div className="cc-app">
			<TopBar
				brand={ bootstrap.brand || 'Cresco Canvas' }
				device={ device }
				dirty={ dirty }
				nativeEditUrl={ bootstrap.nativeEditUrl }
				notice={ notice }
				onDeviceChange={ setDevice }
				onGlobalSettings={ () =>
					setSettingsOpen( ( value ) => ! value )
				}
				onSave={ savePage }
				onTitleChange={ ( title ) =>
					setPage( ( current ) =>
						current ? { ...current, title } : current
					)
				}
				page={ page }
				pagesUrl={ bootstrap.pagesUrl }
				saving={ saving }
				showGlobalSettings={ bootstrap.canManageSettings }
			/>
			{ notice && (
				<Notice
					className="cc-notice"
					isDismissible
					onRemove={ () => setNotice( '' ) }
					status={ noticeStatus }
				>
					{ notice }
				</Notice>
			) }
			{ page ? (
				<BlockEditorProvider
					onChange={ setBlocks }
					onInput={ setBlocks }
					settings={ editorSettings }
					value={ blocks }
				>
					{ shell }
				</BlockEditorProvider>
			) : (
				shell
			) }
		</div>
	);
}
