import type { CanvasEditorBootstrap } from '../editor/types';

declare global {
	interface Window {
		crescoCanvasEditorSettings: CanvasEditorBootstrap;
	}
}

export {};
