import type { ApiErrorShape } from './types';

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
