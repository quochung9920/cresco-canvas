import { Button, Modal, Notice, PanelBody } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import {
	Fragment,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';

import {
	getPreviewDeviceWidth,
	persistPreviewDevice,
	PREVIEW_DEVICE_EVENT,
	PREVIEW_DEVICES,
	readPreviewDevice,
	type PreviewDevice,
	type PreviewDeviceEventDetail,
} from '../shared/previewDevices';

interface EditorSelect {
	getEditedPostPreviewLink?: () => string | undefined;
	isAutosavingPost?: () => boolean;
	isSavingPost?: () => boolean;
}

const DEVICE_LABELS: Record< PreviewDevice, string > = {
	'4k': __( '4K', 'cresco-canvas' ),
	desktop: __( 'Desktop', 'cresco-canvas' ),
	laptop: __( 'Laptop', 'cresco-canvas' ),
	tablet: __( 'Tablet', 'cresco-canvas' ),
	mobile: __( 'Mobile', 'cresco-canvas' ),
};

function applyDeviceToDocument(
	targetDocument: Document | null,
	device: PreviewDevice
): void {
	if ( targetDocument ) {
		targetDocument.documentElement.dataset.ccPreviewDevice = device;
	}
}

function appendRefreshToken( url: string, refreshKey: number ): string {
	if ( ! url ) {
		return '';
	}
	try {
		const parsed = new URL( url, window.location.href );
		parsed.searchParams.set( 'cresco_canvas_refresh', String( refreshKey ) );
		return parsed.toString();
	} catch {
		return url;
	}
}

function PreviewSidebar() {
	const bootstrap = window.crescoCanvasPreviewSettings;
	const [ device, setDevice ] = useState< PreviewDevice >( readPreviewDevice );
	const [ previewOpen, setPreviewOpen ] = useState( false );
	const [ refreshKey, setRefreshKey ] = useState( 0 );
	const wasSaving = useRef( false );
	const editorState = useSelect( ( select ) => {
		const editor = select( 'core/editor' ) as unknown as EditorSelect;
		return {
			previewUrl:
				editor.getEditedPostPreviewLink?.() || bootstrap.previewUrl,
			saving: Boolean(
				editor.isSavingPost?.() || editor.isAutosavingPost?.()
			),
		};
	}, [ bootstrap.previewUrl ] );
	const previewSrc = useMemo(
		() => appendRefreshToken( editorState.previewUrl, refreshKey ),
		[ editorState.previewUrl, refreshKey ]
	);

	useEffect( () => {
		persistPreviewDevice( device );
		window.dispatchEvent(
			new CustomEvent< PreviewDeviceEventDetail >( PREVIEW_DEVICE_EVENT, {
				detail: { device },
			} )
		);

		const iframeListeners = new Map< HTMLIFrameElement, () => void >();
		const attachIframe = ( iframe: HTMLIFrameElement ) => {
			if ( iframeListeners.has( iframe ) ) {
				return;
			}
			const onLoad = () =>
				applyDeviceToDocument( iframe.contentDocument, device );
			iframeListeners.set( iframe, onLoad );
			iframe.addEventListener( 'load', onLoad );
			onLoad();
		};
		const scan = () => {
			applyDeviceToDocument( document, device );
			document
				.querySelectorAll< HTMLIFrameElement >(
					'iframe[name="editor-canvas"]'
				)
				.forEach( attachIframe );
		};

		scan();
		const observer = new MutationObserver( scan );
		observer.observe( document.documentElement, {
			childList: true,
			subtree: true,
		} );

		return () => {
			observer.disconnect();
			for ( const [ iframe, onLoad ] of iframeListeners ) {
				iframe.removeEventListener( 'load', onLoad );
			}
		};
	}, [ device ] );

	useEffect( () => {
		if ( wasSaving.current && ! editorState.saving && previewOpen ) {
			setRefreshKey( ( value ) => value + 1 );
		}
		wasSaving.current = editorState.saving;
	}, [ editorState.saving, previewOpen ] );

	return (
		<Fragment>
			<PluginSidebarMoreMenuItem target="cresco-canvas-preview">
				{ __( 'Cresco Preview', 'cresco-canvas' ) }
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				className="cresco-canvas-preview-sidebar"
				icon="visibility"
				name="cresco-canvas-preview"
				title={ __( 'Cresco Preview', 'cresco-canvas' ) }
			>
				<PanelBody
					initialOpen
					title={ __( 'Responsive viewport', 'cresco-canvas' ) }
				>
					<div
						aria-label={ __( 'Preview device', 'cresco-canvas' ) }
						className="cc-preview-device-grid"
						role="group"
					>
						{ PREVIEW_DEVICES.map( ( candidate ) => (
							<Button
								aria-pressed={ device === candidate.id }
								key={ candidate.id }
								onClick={ () => setDevice( candidate.id ) }
								variant={
									device === candidate.id
										? 'primary'
										: 'secondary'
								}
							>
								{ DEVICE_LABELS[ candidate.id ] }
							</Button>
						) ) }
					</div>
					<p className="cc-preview-note">
						{ sprintf(
							/* translators: 1: device name, 2: logical viewport width. */
							__( '%1$s uses a %2$dpx logical viewport.', 'cresco-canvas' ),
							DEVICE_LABELS[ device ],
							getPreviewDeviceWidth( device )
						) }
					</p>
				</PanelBody>
				<PanelBody
					initialOpen
					title={ __( 'Live frontend preview', 'cresco-canvas' ) }
				>
					{ editorState.previewUrl ? (
						<>
							<p className="cc-preview-note">
								{ __(
									'The iframe shows WordPress frontend output and refreshes after a save or autosave finishes.',
									'cresco-canvas'
								) }
							</p>
							<div className="cc-preview-actions">
								<Button
									onClick={ () => setPreviewOpen( true ) }
									variant="primary"
								>
									{ __( 'Open frontend preview', 'cresco-canvas' ) }
								</Button>
								<Button
									href={ editorState.previewUrl }
									target="_blank"
									variant="secondary"
								>
									{ __( 'Open in new tab', 'cresco-canvas' ) }
								</Button>
							</div>
						</>
					) : (
						<Notice isDismissible={ false } status="warning">
							{ __(
								'A frontend preview URL is not available for this Page yet.',
								'cresco-canvas'
							) }
						</Notice>
					) }
				</PanelBody>
			</PluginSidebar>
			{ previewOpen && previewSrc && (
				<Modal
					className="cc-frontend-preview-modal"
					onRequestClose={ () => setPreviewOpen( false ) }
					title={ __( 'Live frontend preview', 'cresco-canvas' ) }
				>
					<div className="cc-frontend-preview-toolbar">
						<span aria-live="polite">
							{ editorState.saving
								? __( 'Waiting for WordPress to finish saving…', 'cresco-canvas' )
								: __( 'Showing the latest saved preview.', 'cresco-canvas' ) }
						</span>
						<Button
							onClick={ () => setRefreshKey( ( value ) => value + 1 ) }
							variant="secondary"
						>
							{ __( 'Refresh', 'cresco-canvas' ) }
						</Button>
					</div>
					<div className="cc-frontend-preview-stage">
						<iframe
							className="cc-frontend-preview-frame"
							key={ `${ device }-${ refreshKey }` }
							src={ previewSrc }
							style={ { inlineSize: getPreviewDeviceWidth( device ) } }
							title={ __( 'Cresco frontend Page preview', 'cresco-canvas' ) }
						/>
					</div>
				</Modal>
			) }
		</Fragment>
	);
}

registerPlugin( 'cresco-canvas-preview', {
	icon: 'visibility',
	render: PreviewSidebar,
} );
