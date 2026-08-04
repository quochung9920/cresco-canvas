export interface CanvasEditorBootstrap {
	canManageSettings: boolean;
	restPath: string;
	version: string;
}

export interface GlobalSettings {
	background: string;
	containerMax: number;
	contentMax: number;
	fontFamily: string;
	muted: string;
	primary: string;
	radius: number;
	removeDataOnUninstall: boolean;
	schemaVersion: number;
	text: string;
}

export interface PageMeta extends Record< string, unknown > {
	_cresco_canvas_enabled?: boolean;
}

export interface ApiErrorShape {
	code?: string;
	message?: string;
	data?: {
		status?: number;
	};
}
