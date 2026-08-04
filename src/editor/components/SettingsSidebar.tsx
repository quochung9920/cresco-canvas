import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Notice,
	PanelBody,
	Spinner,
	ToggleControl,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { normalizeApiError } from '../api';
import {
	applyPreviewTokens,
	containsCrescoBlock,
	togglePreviewScope,
	type EditorBlockTree,
} from '../previewTokens';
import type { GlobalSettings, PageMeta } from '../types';
import { GlobalSettingsPanel } from './GlobalSettingsPanel';

const ENABLED_META = '_cresco_canvas_enabled';

interface EditorSelect {
	getEditedPostAttribute: ( attribute: string ) => unknown;
}

interface EditorDispatch {
	editPost: ( edits: { meta: PageMeta } ) => void;
}

interface BlockEditorSelect {
	getBlocks: () => EditorBlockTree[];
}

type NoticeStatus = 'error' | 'success';

export function SettingsSidebar() {
	const bootstrap = window.crescoCanvasEditorSettings;
	const [ globalSettings, setGlobalSettings ] =
		useState< GlobalSettings | null >( null );
	const [ loading, setLoading ] = useState( bootstrap.canManageSettings );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( '' );
	const [ noticeStatus, setNoticeStatus ] =
		useState< NoticeStatus >( 'success' );

	const pageMeta = useSelect( ( select ) => {
		const editor = select( 'core/editor' ) as unknown as EditorSelect;
		const value = editor.getEditedPostAttribute( 'meta' );
		return value && typeof value === 'object' ? ( value as PageMeta ) : {};
	}, [] );
	const { editPost } = useDispatch(
		'core/editor'
	) as unknown as EditorDispatch;
	const hasCanvasBlock = useSelect( ( select ) => {
		const blockEditor = select(
			'core/block-editor'
		) as unknown as BlockEditorSelect;
		return containsCrescoBlock( blockEditor.getBlocks() );
	}, [] );
	const pageUsesCanvas =
		Boolean( pageMeta[ ENABLED_META ] ) || hasCanvasBlock;

	const loadSettings = useCallback( async () => {
		if ( ! bootstrap.canManageSettings ) {
			return;
		}

		setLoading( true );
		setNotice( '' );

		try {
			const result = await apiFetch< GlobalSettings >( {
				path: `${ bootstrap.restPath }settings`,
			} );
			setGlobalSettings( result );
		} catch ( error ) {
			const normalized = normalizeApiError( error );
			setNotice(
				normalized.message ||
					__(
						'Global design settings could not be loaded.',
						'cresco-canvas'
					)
			);
			setNoticeStatus( 'error' );
		} finally {
			setLoading( false );
		}
	}, [ bootstrap.canManageSettings, bootstrap.restPath ] );

	useEffect( () => {
		void loadSettings();
	}, [ loadSettings ] );

	useEffect( () => {
		let animationFrame = 0;
		let attempts = 0;
		let iframe: HTMLIFrameElement | null = null;

		const update = () => {
			const documents = [ document, iframe?.contentDocument ];
			togglePreviewScope( documents, pageUsesCanvas );

			if ( globalSettings ) {
				applyPreviewTokens( globalSettings, documents );
			}
		};
		const connectIframe = () => {
			const candidate = document.querySelector< HTMLIFrameElement >(
				'iframe[name="editor-canvas"]'
			);

			if ( candidate ) {
				iframe = candidate;
				iframe.addEventListener( 'load', update );
				update();
				return;
			}

			if ( attempts < 120 ) {
				attempts += 1;
				animationFrame = requestAnimationFrame( connectIframe );
			}
		};

		update();
		connectIframe();

		return () => {
			cancelAnimationFrame( animationFrame );
			iframe?.removeEventListener( 'load', update );
		};
	}, [ globalSettings, pageUsesCanvas ] );

	async function saveGlobalSettings() {
		if ( ! globalSettings || saving ) {
			return;
		}

		setSaving( true );
		setNotice( '' );

		try {
			const result = await apiFetch< GlobalSettings >( {
				data: globalSettings,
				method: 'POST',
				path: `${ bootstrap.restPath }settings`,
			} );
			setGlobalSettings( result );
			setNotice( __( 'Global design saved.', 'cresco-canvas' ) );
			setNoticeStatus( 'success' );
		} catch ( error ) {
			const normalized = normalizeApiError( error );
			setNotice(
				normalized.message ||
					__( 'Global design could not be saved.', 'cresco-canvas' )
			);
			setNoticeStatus( 'error' );
		} finally {
			setSaving( false );
		}
	}

	return (
		<>
			<PluginSidebarMoreMenuItem target="cresco-canvas-settings">
				{ __( 'Cresco Canvas', 'cresco-canvas' ) }
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				className="cresco-canvas-sidebar"
				icon="layout"
				name="cresco-canvas-settings"
				title={ __( 'Cresco Canvas', 'cresco-canvas' ) }
			>
				{ notice && (
					<Notice
						isDismissible
						onRemove={ () => setNotice( '' ) }
						status={ noticeStatus }
					>
						{ notice }
					</Notice>
				) }
				<PanelBody
					initialOpen
					title={ __( 'Page styling', 'cresco-canvas' ) }
				>
					<ToggleControl
						checked={ Boolean( pageMeta[ ENABLED_META ] ) }
						help={ __(
							'Applies Cresco global colors, typography, and spacing tokens on this Page. Pages containing a Cresco block are detected automatically.',
							'cresco-canvas'
						) }
						label={ __(
							'Enable Cresco page styles',
							'cresco-canvas'
						) }
						onChange={ ( enabled ) =>
							editPost( {
								meta: {
									...pageMeta,
									[ ENABLED_META ]: enabled,
								},
							} )
						}
					/>
					<p className="cc-native-note">
						{ __(
							'This setting is saved by the normal Gutenberg Save or Update button.',
							'cresco-canvas'
						) }
					</p>
				</PanelBody>
				{ bootstrap.canManageSettings && loading && (
					<div
						aria-label={ __(
							'Loading global design',
							'cresco-canvas'
						) }
						className="cc-sidebar-loading"
					>
						<Spinner />
					</div>
				) }
				{ bootstrap.canManageSettings && globalSettings && (
					<GlobalSettingsPanel
						onChange={ setGlobalSettings }
						onSave={ saveGlobalSettings }
						saving={ saving }
						settings={ globalSettings }
					/>
				) }
				{ bootstrap.canManageSettings &&
					! loading &&
					! globalSettings && (
						<div className="cc-sidebar-retry">
							<Button
								onClick={ loadSettings }
								variant="secondary"
							>
								{ __(
									'Retry loading settings',
									'cresco-canvas'
								) }
							</Button>
						</div>
					) }
			</PluginSidebar>
		</>
	);
}
