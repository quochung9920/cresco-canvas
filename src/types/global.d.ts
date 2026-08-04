import type { CanvasEditorBootstrap } from '../editor/types';
import type { CanvasPreviewBootstrap } from '../preview/types';

declare global {
	interface Window {
		crescoCanvasEditorSettings: CanvasEditorBootstrap;
		crescoCanvasPreviewSettings: CanvasPreviewBootstrap;
	}
}

export {};
