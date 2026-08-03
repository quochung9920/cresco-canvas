import type { CanvasBootstrap } from '../editor/types';

declare global {
	interface Window {
		crescoCanvasSettings: CanvasBootstrap;
	}
}

export {};
