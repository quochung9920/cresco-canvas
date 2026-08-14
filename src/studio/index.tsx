/**
 * Studio: responsive inheritance inspector section.
 *
 * Registers through the documented `window.CrescoStudioSDK` extension point
 * rather than reaching into Studio's DOM, so it carries no coupling to the
 * editor's internal markup.
 *
 * This is the first Studio runtime module built from `src/`. It is typechecked
 * by `tsc`, linted, unit tested, and bundled by `wp-scripts`, unlike the
 * hand-maintained files under `runtime-src/build/`. New Studio behaviour should
 * follow this shape; see docs/AUDIT_2026-08.md for the consolidation plan.
 */

import { createElement, Fragment } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import {
	DEVICE_LABELS,
	overriddenKeysAt,
	resolveInheritance,
	type ResponsiveDevice,
	type StyleBearingNode,
} from './responsiveInheritance';
import { registerVisualExport } from './visualExport';

interface InspectorRenderArgs {
	node?: StyleBearingNode | null;
	device?: ResponsiveDevice;
}

interface InspectorWhenArgs extends InspectorRenderArgs {
	tab?: string;
}

interface StudioSdk {
	registerInspectorSection: ( section: {
		id: string;
		label?: string;
		when?: ( args: InspectorWhenArgs ) => boolean;
		render?: ( args: InspectorRenderArgs ) => unknown;
	} ) => () => void;
	registerCommand: ( command: {
		id: string;
		label: string;
		description?: string;
		run: ( args: { session?: unknown } ) => void;
	} ) => () => void;
}

declare global {
	interface Window {
		CrescoStudioSDK?: StudioSdk;
	}
}

const SECTION_ID = 'cresco/responsive-inheritance';

/**
 * Turn `paddingTop` into `Padding top` for display.
 * @param key
 */
const humanize = ( key: string ): string => {
	const spaced = key.replace( /([A-Z])/g, ' $1' ).trim();
	return spaced.charAt( 0 ).toUpperCase() + spaced.slice( 1 ).toLowerCase();
};

const renderSection = ( { node, device }: InspectorRenderArgs ) => {
	const active: ResponsiveDevice = device ?? 'wide';
	const own = overriddenKeysAt( node, active );

	// Properties that render at this breakpoint but were written at a wider one.
	const inherited = ( () => {
		if ( ! node ) {
			return [] as Array< { key: string; from: ResponsiveDevice } >;
		}
		const seen = new Set< string >();
		const rows: Array< { key: string; from: ResponsiveDevice } > = [];
		const bags = [
			node.style ?? {},
			...Object.values( node.responsive ?? {} ),
		];
		for ( const bag of bags ) {
			for ( const key of Object.keys( bag ) ) {
				if ( seen.has( key ) ) {
					continue;
				}
				seen.add( key );
				const result = resolveInheritance( node, active, key );
				if ( result.origin === 'inherited' && result.source ) {
					rows.push( { key, from: result.source } );
				}
			}
		}
		return rows.sort( ( a, b ) => a.key.localeCompare( b.key ) );
	} )();

	if ( ! own.length && ! inherited.length ) {
		return createElement(
			'p',
			{ className: 'cc-studio-help' },
			__(
				'No styles set yet. Values you add here apply from this breakpoint down.',
				'cresco-canvas'
			)
		);
	}

	return createElement(
		Fragment,
		null,
		createElement(
			'p',
			{ className: 'cc-studio-help' },
			sprintf(
				/* translators: %s: breakpoint name, for example Tablet. */
				__(
					'Editing %s. Narrower breakpoints inherit from here.',
					'cresco-canvas'
				),
				DEVICE_LABELS[ active ]
			)
		),
		own.length
			? createElement(
					'div',
					{ className: 'cc-inheritance-group' },
					createElement(
						'h4',
						{ className: 'cc-inheritance-heading' },
						sprintf(
							/* translators: %d: number of overridden properties. */
							__( 'Set here (%d)', 'cresco-canvas' ),
							own.length
						)
					),
					createElement(
						'ul',
						{ className: 'cc-inheritance-list' },
						own.map( ( key ) =>
							createElement(
								'li',
								{
									key,
									className: 'cc-inheritance-item is-own',
								},
								humanize( key )
							)
						)
					)
			  )
			: null,
		inherited.length
			? createElement(
					'div',
					{ className: 'cc-inheritance-group' },
					createElement(
						'h4',
						{ className: 'cc-inheritance-heading' },
						sprintf(
							/* translators: %d: number of inherited properties. */
							__( 'Inherited (%d)', 'cresco-canvas' ),
							inherited.length
						)
					),
					createElement(
						'ul',
						{ className: 'cc-inheritance-list' },
						inherited.map( ( row ) =>
							createElement(
								'li',
								{
									key: row.key,
									className:
										'cc-inheritance-item is-inherited',
								},
								humanize( row.key ),
								createElement(
									'span',
									{ className: 'cc-inheritance-source' },
									DEVICE_LABELS[ row.from ]
								)
							)
						)
					)
			  )
			: null
	);
};

const register = ( sdk: StudioSdk ): void => {
	sdk.registerInspectorSection( {
		id: SECTION_ID,
		label: __( 'Responsive inheritance', 'cresco-canvas' ),
		when: ( { node } ) => Boolean( node ),
		render: renderSection,
	} );
	registerVisualExport( sdk );
};

/**
 * The SDK is created by the Studio bundle, which may load after this module.
 * Poll briefly rather than assume an ordering the enqueue cannot guarantee, and
 * give up quietly so a Studio that never mounts costs nothing.
 * @param attemptsLeft
 */
const whenSdkReady = ( attemptsLeft = 40 ): void => {
	if ( window.CrescoStudioSDK?.registerInspectorSection ) {
		register( window.CrescoStudioSDK );
		return;
	}
	if ( attemptsLeft <= 0 ) {
		return;
	}
	window.setTimeout( () => whenSdkReady( attemptsLeft - 1 ), 125 );
};

whenSdkReady();
