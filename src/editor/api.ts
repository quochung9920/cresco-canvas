import apiFetch from '@wordpress/api-fetch';

import type { ApiErrorShape } from './types';

const bootstrap = window.crescoCanvasSettings;

apiFetch.use( apiFetch.createNonceMiddleware( bootstrap.nonce ) );

export async function request< Response >(
	path: string,
	options: Record< string, unknown > = {}
): Promise< Response > {
	return apiFetch< Response >( {
		path: `${ bootstrap.restPath }${ path }`,
		...options,
	} );
}

export function normalizeApiError( error: unknown ): ApiErrorShape {
	if ( typeof error !== 'object' || error === null ) {
		return {};
	}

	const candidate = error as ApiErrorShape;

	return {
		code: typeof candidate.code === 'string' ? candidate.code : undefined,
		message:
			typeof candidate.message === 'string'
				? candidate.message
				: undefined,
		data: candidate.data,
	};
}
