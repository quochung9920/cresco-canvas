import type { PreviewDevice } from '../../shared/previewDevices';
import type {
	ContainerAttributes,
	ContainerResponsiveOverrides,
	ResolvedContainerLayout,
	ResponsivePreviewDevice,
} from './types';

const ALIGNMENTS = [ 'center', 'flex-end', 'flex-start', 'stretch' ] as const;
const DIRECTIONS = [ 'column', 'row' ] as const;
const JUSTIFICATIONS = [
	'center',
	'flex-end',
	'flex-start',
	'space-between',
] as const;
const LAYOUT_MODES = [ 'block', 'flex', 'grid' ] as const;
const WRAPS = [ 'nowrap', 'wrap' ] as const;

const DEFAULT_LAYOUT: ResolvedContainerLayout = {
	align: 'stretch',
	columns: 3,
	direction: 'column',
	gap: 24,
	justify: 'flex-start',
	layoutMode: 'flex',
	maxWidth: 1200,
	paddingBottom: 40,
	paddingLeft: 24,
	paddingRight: 24,
	paddingTop: 40,
	wrap: 'nowrap',
};

function enumValue< Value extends string >(
	value: unknown,
	allowed: readonly Value[],
	fallback: Value
): Value {
	return allowed.includes( value as Value ) ? ( value as Value ) : fallback;
}

function numberValue(
	value: unknown,
	minimum: number,
	maximum: number,
	fallback: number
): number {
	if ( typeof value !== 'number' || ! Number.isFinite( value ) ) {
		return fallback;
	}
	return Math.min( maximum, Math.max( minimum, Math.round( value ) ) );
}

function mergeLayout(
	base: ResolvedContainerLayout,
	override: Partial< ResolvedContainerLayout > | undefined
): ResolvedContainerLayout {
	if ( ! override ) {
		return base;
	}

	return normalizeContainerLayout( { ...base, ...override }, base );
}

export function normalizeContainerLayout(
	value: Partial< ResolvedContainerLayout > | ContainerAttributes,
	fallback: ResolvedContainerLayout = DEFAULT_LAYOUT
): ResolvedContainerLayout {
	return {
		align: enumValue( value.align, ALIGNMENTS, fallback.align ),
		columns: numberValue( value.columns, 1, 12, fallback.columns ),
		direction: enumValue( value.direction, DIRECTIONS, fallback.direction ),
		gap: numberValue( value.gap, 0, 240, fallback.gap ),
		justify: enumValue(
			value.justify,
			JUSTIFICATIONS,
			fallback.justify
		),
		layoutMode: enumValue(
			value.layoutMode,
			LAYOUT_MODES,
			fallback.layoutMode
		),
		maxWidth: numberValue( value.maxWidth, 320, 3840, fallback.maxWidth ),
		paddingBottom: numberValue(
			value.paddingBottom,
			0,
			400,
			fallback.paddingBottom
		),
		paddingLeft: numberValue(
			value.paddingLeft,
			0,
			400,
			fallback.paddingLeft
		),
		paddingRight: numberValue(
			value.paddingRight,
			0,
			400,
			fallback.paddingRight
		),
		paddingTop: numberValue(
			value.paddingTop,
			0,
			400,
			fallback.paddingTop
		),
		wrap: enumValue( value.wrap, WRAPS, fallback.wrap ),
	};
}

export function resolveContainerLayout(
	attributes: ContainerAttributes,
	device: PreviewDevice
): ResolvedContainerLayout {
	const desktop = normalizeContainerLayout( attributes );
	const responsive = attributes.responsive || {};

	if ( device === 'desktop' ) {
		return desktop;
	}
	if ( device === '4k' ) {
		return mergeLayout( desktop, responsive[ '4k' ] );
	}

	const laptop = mergeLayout( desktop, responsive.laptop );
	if ( device === 'laptop' ) {
		return laptop;
	}

	const tablet = mergeLayout( laptop, responsive.tablet );
	if ( device === 'tablet' ) {
		return tablet;
	}

	return mergeLayout( tablet, responsive.mobile );
}

export function updateResponsiveOverride<
	Key extends keyof ResolvedContainerLayout,
>(
	responsive: ContainerResponsiveOverrides | undefined,
	device: ResponsivePreviewDevice,
	key: Key,
	value: ResolvedContainerLayout[ Key ]
): ContainerResponsiveOverrides {
	return {
		...( responsive || {} ),
		[ device ]: {
			...( responsive?.[ device ] || {} ),
			[ key ]: value,
		},
	};
}

export function resetResponsiveDevice(
	responsive: ContainerResponsiveOverrides | undefined,
	device: ResponsivePreviewDevice
): ContainerResponsiveOverrides | undefined {
	if ( ! responsive?.[ device ] ) {
		return responsive;
	}

	const next = { ...responsive };
	delete next[ device ];
	return Object.keys( next ).length > 0 ? next : undefined;
}

export function hasResponsiveEnhancements(
	attributes: ContainerAttributes
): boolean {
	return (
		typeof attributes.columns === 'number' ||
		typeof attributes.wrap === 'string' ||
		Boolean(
			attributes.responsive &&
				Object.values( attributes.responsive ).some(
					( override ) =>
						override && Object.keys( override ).length > 0
				)
		)
	);
}
