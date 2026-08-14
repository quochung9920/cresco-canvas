/**
 * Studio command: download the rendered appearance of the current design.
 *
 * The AI context envelope describes a document semantically, which is the right
 * shape for editing it but says nothing about how it looks. This command saves a
 * self-contained HTML file that opens in any browser with no WordPress and no
 * network access, so the design can be inspected visually or handed to a reader
 * that looks at pages rather than reading JSON.
 *
 * Nothing is transmitted anywhere. The file is produced by the site's own
 * renderer and saved locally; carrying it further is the operator's choice.
 */

import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

interface StudioSettings {
	studio?: { aiVisualExport?: string };
}

interface CommandRunArgs {
	session?: unknown;
}

interface StudioSdkCommands {
	registerCommand: ( command: {
		id: string;
		label: string;
		description?: string;
		run: ( args: CommandRunArgs ) => void;
	} ) => () => void;
}

declare global {
	interface Window {
		crescoWebsiteBuilderSettings?: StudioSettings;
	}
}

interface VisualResponse {
	document?: string;
	filename?: string;
}

const COMMAND_ID = 'cresco/export-rendered-html';

/**
 * Hand a generated file to the browser.
 *
 * Revoking on the next frame rather than immediately gives Safari time to start
 * the download before the object URL disappears.
 *
 * @param filename Suggested name for the saved file.
 * @param contents File body.
 */
const saveFile = ( filename: string, contents: string ): void => {
	const blob = new Blob( [ contents ], { type: 'text/html;charset=utf-8' } );
	const url = URL.createObjectURL( blob );
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = filename;
	document.body.appendChild( link );
	link.click();
	link.remove();
	window.setTimeout( () => URL.revokeObjectURL( url ), 0 );
};

/**
 * Request the rendered document and save it.
 *
 * @param session Session to render, including unsaved editor state.
 */
const exportVisual = async ( session: unknown ): Promise< void > => {
	const path = window.crescoWebsiteBuilderSettings?.studio?.aiVisualExport;
	if ( ! path ) {
		return;
	}

	try {
		const result = ( await apiFetch( {
			path,
			method: 'POST',
			data: { session, scope: 'page' },
		} ) ) as VisualResponse;

		if ( ! result?.document ) {
			return;
		}
		saveFile( result.filename || 'cresco-export.html', result.document );
	} catch ( error ) {
		// Surfacing this through Studio's notice system would need a mutation
		// channel the SDK does not expose yet, so log rather than fail silently.
		// eslint-disable-next-line no-console
		console.error( 'Cresco: rendered HTML export failed.', error );
	}
};

/**
 * Register the command so it appears in the command palette.
 *
 * @param sdk Studio SDK instance.
 */
export const registerVisualExport = ( sdk: StudioSdkCommands ): void => {
	sdk.registerCommand( {
		id: COMMAND_ID,
		label: __( 'Export rendered HTML', 'cresco-canvas' ),
		description: __(
			'Save a self-contained HTML file of this page for visual review',
			'cresco-canvas'
		),
		run: ( { session } ) => {
			void exportVisual( session );
		},
	} );
};
