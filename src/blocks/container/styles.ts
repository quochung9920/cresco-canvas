import type { CSSProperties } from 'react';

import type { ContainerAttributes } from './types';

export function styleFromAttributes(
	attributes: ContainerAttributes
): CSSProperties {
	return {
		alignItems:
			attributes.layoutMode === 'block' ? undefined : attributes.align,
		background: attributes.background || undefined,
		display: attributes.layoutMode,
		flexDirection:
			attributes.layoutMode === 'flex' ? attributes.direction : undefined,
		gap:
			attributes.layoutMode === 'block'
				? undefined
				: `${ attributes.gap }px`,
		justifyContent:
			attributes.layoutMode === 'block' ? undefined : attributes.justify,
		marginLeft: 'auto',
		marginRight: 'auto',
		maxWidth: `${ attributes.maxWidth }px`,
		padding: `${ attributes.paddingTop }px ${ attributes.paddingRight }px ${ attributes.paddingBottom }px ${ attributes.paddingLeft }px`,
	};
}
