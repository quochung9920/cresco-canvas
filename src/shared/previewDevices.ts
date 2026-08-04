export const PREVIEW_DEVICE_EVENT = 'cresco-canvas-preview-device-change';
export const PREVIEW_STORAGE_KEY = 'crescoCanvas.previewDevice';

export const PREVIEW_DEVICES = [
	{ id: '4k', width: 1920 },
	{ id: 'desktop', width: 1440 },
	{ id: 'laptop', width: 1024 },
	{ id: 'tablet', width: 768 },
	{ id: 'mobile', width: 390 },
] as const;

export type PreviewDevice = ( typeof PREVIEW_DEVICES )[ number ][ 'id' ];

export interface PreviewDeviceEventDetail {
	device: PreviewDevice;
}

export function isPreviewDevice( value: unknown ): value is PreviewDevice {
	return PREVIEW_DEVICES.some( ( device ) => device.id === value );
}

export function getPreviewDeviceWidth( device: PreviewDevice ): number {
	return (
		PREVIEW_DEVICES.find( ( candidate ) => candidate.id === device )
			?.width ?? 1440
	);
}

export function readPreviewDevice(
	storage: Pick< Storage, 'getItem' > | null =
		typeof window === 'undefined' ? null : window.localStorage
): PreviewDevice {
	if ( ! storage ) {
		return 'desktop';
	}

	try {
		const value = storage.getItem( PREVIEW_STORAGE_KEY );
		return isPreviewDevice( value ) ? value : 'desktop';
	} catch {
		return 'desktop';
	}
}

export function persistPreviewDevice(
	device: PreviewDevice,
	storage: Pick< Storage, 'setItem' > | null =
		typeof window === 'undefined' ? null : window.localStorage
): void {
	if ( ! storage ) {
		return;
	}

	try {
		storage.setItem( PREVIEW_STORAGE_KEY, device );
	} catch {
		// Storage can be unavailable in hardened or private browser contexts.
	}
}
