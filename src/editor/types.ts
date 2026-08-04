export type Device = '4k' | 'desktop' | 'laptop' | 'tablet' | 'mobile';

export type EditorPreference = 'canvas' | 'wordpress' | 'remember';

export interface CanvasBootstrap {
	adminUrl: string;
	brand: string;
	canManageSettings: boolean;
	nativeEditUrl: string;
	nonce: string;
	pagesUrl: string;
	postId: number;
	restPath: string;
	safeModeUrl: string;
	version: string;
}

export interface PageRecord {
	content: string;
	id: number;
	modifiedGmt: string;
	preview: string;
	revision: string;
	status: 'draft' | 'publish' | 'pending' | 'private' | 'future';
	title: string;
}

export interface SaveResponse {
	id: number;
	modifiedGmt: string;
	preview: string;
	revision: string;
	saved: boolean;
}

export interface GlobalSettings {
	background: string;
	containerMax: number;
	contentMax: number;
	editorPreference: EditorPreference;
	fontFamily: string;
	muted: string;
	primary: string;
	radius: number;
	removeDataOnUninstall: boolean;
	schemaVersion: number;
	text: string;
}

export interface ApiErrorShape {
	code?: string;
	message?: string;
	data?: {
		status?: number;
	};
}
