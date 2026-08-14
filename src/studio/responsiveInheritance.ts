/**
 * Responsive inheritance resolution for Studio style properties.
 *
 * Studio stores a node's base styles on `node.style` and per-breakpoint
 * overrides on `node.responsive[ device ]`. A value cascades from wider
 * breakpoints to narrower ones: `wide` is the base, and `desktop`, `laptop`,
 * `tablet`, `mobile` each override what came before.
 *
 * The editor renders the resolved value but does not say where it came from,
 * so a control showing `24px` on Mobile looks identical whether that value was
 * set on Mobile or inherited from Desktop. Editing it then silently creates a
 * new override. This module supplies the missing provenance.
 */

export const RESPONSIVE_DEVICES = [
	'wide',
	'desktop',
	'laptop',
	'tablet',
	'mobile',
] as const;

export type ResponsiveDevice = ( typeof RESPONSIVE_DEVICES )[ number ];

export const DEVICE_LABELS: Record< ResponsiveDevice, string > = {
	wide: 'Wide',
	desktop: 'Desktop',
	laptop: 'Laptop',
	tablet: 'Tablet',
	mobile: 'Mobile',
};

export interface StyleBearingNode {
	style?: Record< string, unknown >;
	responsive?: Partial<
		Record< ResponsiveDevice, Record< string, unknown > >
	>;
}

export type InheritanceOrigin = 'own' | 'inherited' | 'unset';

export interface InheritanceResult {
	/**
	 * `own` when the property is written at the requested breakpoint,
	 * `inherited` when it resolves from a wider one, `unset` when no
	 * breakpoint defines it.
	 */
	origin: InheritanceOrigin;
	/** Breakpoint the effective value comes from, or null when unset. */
	source: ResponsiveDevice | null;
	/** Effective value after the cascade, or undefined when unset. */
	value: unknown;
}

/**
 * Whether a stored value counts as present.
 *
 * Studio deletes a key by writing an empty string, so an empty string is
 * absence rather than a value. `0` and `false` are legitimate values and must
 * survive this check.
 * @param value
 */
const isPresent = ( value: unknown ): boolean =>
	value !== undefined && value !== null && value !== '';

/**
 * Read the style bag a given breakpoint writes to.
 * @param node
 * @param device
 */
const bagFor = (
	node: StyleBearingNode,
	device: ResponsiveDevice
): Record< string, unknown > => {
	if ( device === 'wide' ) {
		return node.style ?? {};
	}
	return node.responsive?.[ device ] ?? {};
};

/**
 * Breakpoints from widest to the given one, inclusive, in cascade order.
 * @param device
 */
export const cascadeTo = ( device: ResponsiveDevice ): ResponsiveDevice[] => {
	const end = RESPONSIVE_DEVICES.indexOf( device );
	if ( end < 0 ) {
		return [ 'wide' ];
	}
	return RESPONSIVE_DEVICES.slice( 0, end + 1 );
};

/**
 * Resolve where a style property's effective value comes from.
 *
 * @param node   Node carrying `style` and `responsive`.
 * @param device Breakpoint currently being edited.
 * @param key    Style property name, for example `paddingTop`.
 */
export const resolveInheritance = (
	node: StyleBearingNode | null | undefined,
	device: ResponsiveDevice,
	key: string
): InheritanceResult => {
	if ( ! node || ! key ) {
		return { origin: 'unset', source: null, value: undefined };
	}

	const chain = cascadeTo( device );
	let source: ResponsiveDevice | null = null;
	let value: unknown;

	// Walk widest to narrowest so the last writer wins, matching how Studio
	// composes the style object it renders.
	for ( const step of chain ) {
		const candidate = bagFor( node, step )[ key ];
		if ( isPresent( candidate ) ) {
			source = step;
			value = candidate;
		}
	}

	if ( source === null ) {
		return { origin: 'unset', source: null, value: undefined };
	}

	return {
		origin: source === device ? 'own' : 'inherited',
		source,
		value,
	};
};

/**
 * Every property overridden at a breakpoint. Useful for showing an override
 * count on a breakpoint switcher.
 * @param node
 * @param device
 */
export const overriddenKeysAt = (
	node: StyleBearingNode | null | undefined,
	device: ResponsiveDevice
): string[] => {
	if ( ! node ) {
		return [];
	}
	const bag = bagFor( node, device );
	return Object.keys( bag )
		.filter( ( key ) => isPresent( bag[ key ] ) )
		.sort();
};

/**
 * Human-readable provenance for a control's help text.
 * @param result
 */
export const describeInheritance = ( result: InheritanceResult ): string => {
	if ( result.origin === 'unset' ) {
		return 'Not set at any breakpoint.';
	}
	if ( result.origin === 'own' ) {
		return 'Set at this breakpoint.';
	}
	return `Inherited from ${
		DEVICE_LABELS[ result.source as ResponsiveDevice ]
	}.`;
};
