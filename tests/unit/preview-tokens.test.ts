import {
	applyPreviewTokens,
	containsCrescoBlock,
	cssVariablesFromSettings,
	togglePreviewScope,
} from '../../src/editor/previewTokens';
import type { GlobalSettings } from '../../src/editor/types';

const settings: GlobalSettings = {
	background: '#ffffff',
	containerMax: 1440,
	contentMax: 1200,
	fontFamily: 'system-ui, sans-serif',
	muted: '#6b7280',
	primary: '#635bff',
	radius: 12,
	removeDataOnUninstall: false,
	schemaVersion: 2,
	text: '#111827',
};

describe( 'native Gutenberg preview tokens', () => {
	it( 'projects validated settings into stable CSS variables', () => {
		expect( cssVariablesFromSettings( settings ) ).toEqual( {
			'--cc-background': '#ffffff',
			'--cc-container-max': '1440px',
			'--cc-content-max': '1200px',
			'--cc-font': 'system-ui, sans-serif',
			'--cc-muted': '#6b7280',
			'--cc-primary': '#635bff',
			'--cc-radius': '12px',
			'--cc-text': '#111827',
		} );
	} );

	it( 'updates an editor canvas without touching unrelated elements', () => {
		document.body.innerHTML =
			'<main class="editor-styles-wrapper"></main><aside id="other"></aside>';

		applyPreviewTokens( settings, [ document ] );

		const editor = document.querySelector< HTMLElement >(
			'.editor-styles-wrapper'
		);
		const other = document.querySelector< HTMLElement >( '#other' );
		expect( editor?.style.getPropertyValue( '--cc-primary' ) ).toBe(
			'#635bff'
		);
		expect( other?.getAttribute( 'style' ) ).toBeNull();
	} );

	it( 'detects nested Cresco blocks and scopes only the editor canvas', () => {
		document.body.innerHTML =
			'<main class="editor-styles-wrapper"></main><aside id="other"></aside>';
		expect(
			containsCrescoBlock( [
				{
					name: 'core/group',
					innerBlocks: [ { name: 'cresco/container' } ],
				},
			] )
		).toBe( true );
		expect( containsCrescoBlock( [ { name: 'core/paragraph' } ] ) ).toBe(
			false
		);

		togglePreviewScope( [ document ], true );
		expect(
			document
				.querySelector( '.editor-styles-wrapper' )
				?.classList.contains( 'cresco-canvas-editor-scope' )
		).toBe( true );
		expect( document.querySelector( '#other' )?.className ).toBe( '' );
	} );
} );
