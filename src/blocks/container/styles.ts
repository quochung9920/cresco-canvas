import type { CSSProperties } from 'react';

import { PREVIEW_DEVICES } from '../../shared/previewDevices';
import {
	hasResponsiveEnhancements,
	resolveContainerLayout,
} from './responsive';
import type { ContainerAttributes, ResolvedContainerLayout } from './types';

function safeColor( value: string ): string | undefined {
	const color = value.trim();
	if ( ! color ) {
		return undefined;
	}

	if (
		/^#[0-9a-f]{3,8}$/i.test( color ) ||
		/^(?:rgb|hsl)a?\([0-9.,%+\-\s/]+\)$/i.test( color ) ||
		/^var\(--[a-z0-9_-]+\)$/i.test( color ) ||
		/^(?:currentcolor|transparent)$/i.test( color )
	) {
		return color;
	}

	return undefined;
}

function legacyStyle( attributes: ContainerAttributes ): CSSProperties {
	return {
		alignItems:
			attributes.layoutMode === 'block' ? undefined : attributes.align,
		backgroundColor: safeColor( attributes.background ),
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

function writeDeviceVariables(
	style: Record< string, string | number | undefined >,
	device: string,
	layout: ResolvedContainerLayout
): void {
	style[ `--cc-display-${ device }` ] = layout.layoutMode;
	style[ `--cc-direction-${ device }` ] = layout.direction;
	style[ `--cc-justify-${ device }` ] = layout.justify;
	style[ `--cc-align-${ device }` ] = layout.align;
	style[ `--cc-wrap-${ device }` ] = layout.wrap;
	style[ `--cc-columns-${ device }` ] = String( layout.columns );
	style[ `--cc-gap-${ device }` ] = `${ layout.gap }px`;
	style[ `--cc-padding-top-${ device }` ] = `${ layout.paddingTop }px`;
	style[ `--cc-padding-right-${ device }` ] = `${ layout.paddingRight }px`;
	style[ `--cc-padding-bottom-${ device }` ] = `${ layout.paddingBottom }px`;
	style[ `--cc-padding-left-${ device }` ] = `${ layout.paddingLeft }px`;
	style[ `--cc-max-width-${ device }` ] = `${ layout.maxWidth }px`;
}

export function styleFromAttributes(
	attributes: ContainerAttributes
): CSSProperties {
	if ( ! hasResponsiveEnhancements( attributes ) ) {
		return legacyStyle( attributes );
	}

	const style: Record< string, string | number | undefined > = {
		alignItems: 'var(--cc-active-align)',
		backgroundColor: safeColor( attributes.background ),
		display: 'var(--cc-active-display)',
		flexDirection: 'var(--cc-active-direction)',
		flexWrap: 'var(--cc-active-wrap)',
		gap: 'var(--cc-active-gap)',
		gridTemplateColumns:
			'repeat(var(--cc-active-columns), minmax(0, 1fr))',
		justifyContent: 'var(--cc-active-justify)',
		marginLeft: 'auto',
		marginRight: 'auto',
		maxWidth: 'var(--cc-active-max-width)',
		paddingBottom: 'var(--cc-active-padding-bottom)',
		paddingLeft: 'var(--cc-active-padding-left)',
		paddingRight: 'var(--cc-active-padding-right)',
		paddingTop: 'var(--cc-active-padding-top)',
	};

	for ( const device of PREVIEW_DEVICES ) {
		writeDeviceVariables(
			style,
			device.id,
			resolveContainerLayout( attributes, device.id )
		);
	}

	return style as CSSProperties;
}
