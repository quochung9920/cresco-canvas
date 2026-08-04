import type { GlobalSettings } from './types';

export interface EditorBlockTree {
	innerBlocks?: EditorBlockTree[];
	name?: string;
}

export function containsCrescoBlock( blocks: EditorBlockTree[] ): boolean {
	return blocks.some(
		( block ) =>
			block.name === 'cresco/container' ||
			containsCrescoBlock( block.innerBlocks ?? [] )
	);
}

export function cssVariablesFromSettings(
	settings: GlobalSettings
): Record< string, string > {
	return {
		'--cc-background': settings.background,
		'--cc-container-max': `${ settings.containerMax }px`,
		'--cc-content-max': `${ settings.contentMax }px`,
		'--cc-font': settings.fontFamily,
		'--cc-muted': settings.muted,
		'--cc-primary': settings.primary,
		'--cc-radius': `${ settings.radius }px`,
		'--cc-text': settings.text,
	};
}

export function applyPreviewTokens(
	settings: GlobalSettings,
	documents: Array< Document | null | undefined >
): void {
	const variables = cssVariablesFromSettings( settings );

	for ( const documentObject of documents ) {
		const editor = documentObject?.querySelector< HTMLElement >(
			'.editor-styles-wrapper'
		);

		if ( ! editor ) {
			continue;
		}

		for ( const [ name, value ] of Object.entries( variables ) ) {
			editor.style.setProperty( name, value );
		}
	}
}

export function togglePreviewScope(
	documents: Array< Document | null | undefined >,
	enabled: boolean
): void {
	for ( const documentObject of documents ) {
		documentObject
			?.querySelector( '.editor-styles-wrapper' )
			?.classList.toggle( 'cresco-canvas-editor-scope', enabled );
	}
}
