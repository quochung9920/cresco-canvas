export type CrescoResponsiveDevice =
	| 'wide'
	| 'desktop'
	| 'laptop'
	| 'tablet'
	| 'mobile';

export type CrescoWidgetState =
	| 'normal'
	| 'hover'
	| 'focus'
	| 'active'
	| 'disabled';

export type CrescoStyleCapability =
	| 'dimensions'
	| 'spacing'
	| 'layout'
	| 'typography'
	| 'background'
	| 'border'
	| 'effects'
	| 'position'
	| 'responsive'
	| 'states'
	| 'tokens'
	| 'custom-css';

export interface CrescoWidgetPartDefinition {
	selector: string;
	capabilities?: CrescoStyleCapability[];
	states?: CrescoWidgetState[];
}

export interface CrescoWidgetContract {
	allowsChildren: boolean;
	capabilities: CrescoStyleCapability[];
	category: 'layout' | 'basic' | 'media';
	label: string;
	parts: Record< string, CrescoWidgetPartDefinition >;
	type: string;
	version: number;
}

export interface CrescoControlTokenPreset {
	label: string;
	value: string;
}

export interface CrescoControlDefinition {
	id: string;
	label: string;
	responsive: boolean;
	tokenPresets?: CrescoControlTokenPreset[];
	units?: string[];
}

export interface CrescoControlGroupDefinition {
	controls: CrescoControlDefinition[];
	id: CrescoStyleCapability;
	label: string;
}

const universalCapabilities: CrescoStyleCapability[] = [
	'dimensions',
	'spacing',
	'typography',
	'background',
	'border',
	'effects',
	'position',
	'responsive',
	'tokens',
	'custom-css',
];

const rootPart: CrescoWidgetPartDefinition = {
	selector: '&',
	capabilities: universalCapabilities,
	states: [ 'normal', 'hover', 'focus' ],
};

export const CORE_WIDGET_CONTRACTS: Record< string, CrescoWidgetContract > = {
	container: { type: 'container', version: 1, label: 'Container', category: 'layout', allowsChildren: true, capabilities: [ ...universalCapabilities, 'layout' ], parts: { root: rootPart } },
	columns: { type: 'columns', version: 1, label: 'Columns', category: 'layout', allowsChildren: true, capabilities: [ ...universalCapabilities, 'layout' ], parts: { root: rootPart } },
	heading: { type: 'heading', version: 1, label: 'Heading', category: 'basic', allowsChildren: false, capabilities: universalCapabilities, parts: { root: rootPart } },
	text: { type: 'text', version: 1, label: 'Text', category: 'basic', allowsChildren: false, capabilities: universalCapabilities, parts: { root: rootPart } },
	button: {
		type: 'button', version: 1, label: 'Button', category: 'basic', allowsChildren: false,
		capabilities: [ ...universalCapabilities, 'states' ],
		parts: {
			root: rootPart,
			text: { selector: '& [data-cresco-part="text"]', capabilities: [ 'typography', 'responsive', 'tokens', 'states' ], states: [ 'normal', 'hover', 'focus', 'active', 'disabled' ] },
		},
	},
	image: {
		type: 'image', version: 1, label: 'Image', category: 'media', allowsChildren: false, capabilities: universalCapabilities,
		parts: {
			root: rootPart,
			media: { selector: '& [data-cresco-part="media"]', capabilities: [ 'dimensions', 'border', 'effects', 'responsive' ] },
			caption: { selector: '& [data-cresco-part="caption"]', capabilities: [ 'spacing', 'typography', 'responsive', 'tokens' ] },
		},
	},
	list: {
		type: 'list', version: 1, label: 'List', category: 'basic', allowsChildren: false, capabilities: universalCapabilities,
		parts: { root: rootPart, item: { selector: '& [data-cresco-part="item"]', capabilities: [ 'spacing', 'typography', 'responsive', 'tokens' ] } },
	},
	divider: { type: 'divider', version: 1, label: 'Divider', category: 'basic', allowsChildren: false, capabilities: universalCapabilities, parts: { root: rootPart } },
	spacer: { type: 'spacer', version: 1, label: 'Spacer', category: 'layout', allowsChildren: false, capabilities: [ 'dimensions', 'responsive', 'tokens', 'custom-css' ], parts: { root: rootPart } },
};

const spacingTokens: CrescoControlTokenPreset[] = [
	{ label: '2XS', value: '{spacing.2xs}' }, { label: 'XS', value: '{spacing.xs}' }, { label: 'SM', value: '{spacing.sm}' },
	{ label: 'MD', value: '{spacing.md}' }, { label: 'LG', value: '{spacing.lg}' }, { label: 'XL', value: '{spacing.xl}' },
	{ label: '2XL', value: '{spacing.2xl}' }, { label: '3XL', value: '{spacing.3xl}' },
];

const typographyTokens: CrescoControlTokenPreset[] = [
	{ label: 'Body', value: '{typography.sizes.base}' }, { label: 'Large', value: '{typography.sizes.lg}' }, { label: 'XL', value: '{typography.sizes.xl}' },
	{ label: 'H1', value: '{typography.sizes.h1}' }, { label: 'H2', value: '{typography.sizes.h2}' }, { label: 'H3', value: '{typography.sizes.h3}' },
	{ label: 'H4', value: '{typography.sizes.h4}' }, { label: 'H5', value: '{typography.sizes.h5}' }, { label: 'H6', value: '{typography.sizes.h6}' },
];

const lengthUnits = [ 'px', '%', 'rem', 'em', 'vw', 'vh', 'ch' ];

export const UNIVERSAL_CONTROL_GROUPS: CrescoControlGroupDefinition[] = [
	{
		id: 'dimensions', label: 'Dimensions', controls: [
			{ id: 'width', label: 'Width', responsive: true, units: [ ...lengthUnits, 'auto' ], tokenPresets: [ { label: 'Content max', value: '{layout.contentMax}' }, { label: 'Container max', value: '{layout.containerMax}' } ] },
			{ id: 'maxWidth', label: 'Maximum width', responsive: true, units: [ ...lengthUnits, 'auto' ] },
			{ id: 'minHeight', label: 'Minimum height', responsive: true, units: lengthUnits },
		],
	},
	{
		id: 'spacing', label: 'Spacing', controls: [
			...[ 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'marginTop', 'marginRight', 'marginBottom', 'marginLeft', 'gap' ].map( ( id ) => ( {
				id, label: id, responsive: true, units: [ ...lengthUnits, ...( id.startsWith( 'margin' ) ? [ 'auto' ] : [] ) ], tokenPresets: spacingTokens,
			} ) ),
		],
	},
	{
		id: 'typography', label: 'Typography', controls: [
			{ id: 'fontSize', label: 'Font size', responsive: true, units: [ 'px', 'rem', 'em', 'vw' ], tokenPresets: typographyTokens },
			{ id: 'fontWeight', label: 'Font weight', responsive: true }, { id: 'lineHeight', label: 'Line height', responsive: true },
			{ id: 'letterSpacing', label: 'Letter spacing', responsive: true, units: [ 'px', 'rem', 'em' ] },
		],
	},
	{ id: 'background', label: 'Colors & background', controls: [ { id: 'color', label: 'Text color', responsive: true }, { id: 'background', label: 'Background', responsive: true } ] },
	{ id: 'border', label: 'Border & shadow', controls: [ { id: 'borderRadius', label: 'Border radius', responsive: true, units: [ 'px', '%', 'rem', 'em' ] }, { id: 'boxShadow', label: 'Box shadow', responsive: true } ] },
	{ id: 'position', label: 'Visibility & position', controls: [
		{ id: 'opacity', label: 'Opacity', responsive: true }, { id: 'position', label: 'Position', responsive: true },
		{ id: 'top', label: 'Top', responsive: true, units: lengthUnits }, { id: 'right', label: 'Right', responsive: true, units: lengthUnits },
		{ id: 'bottom', label: 'Bottom', responsive: true, units: lengthUnits }, { id: 'left', label: 'Left', responsive: true, units: lengthUnits },
		{ id: 'zIndex', label: 'Z-index', responsive: true }, { id: 'overflow', label: 'Overflow', responsive: true },
	] },
];

export function getWidgetContract( type: string ): CrescoWidgetContract | undefined {
	return CORE_WIDGET_CONTRACTS[ type ];
}

export function getControlDefinition( id: string ): CrescoControlDefinition | undefined {
	for ( const group of UNIVERSAL_CONTROL_GROUPS ) {
		const control = group.controls.find( ( candidate ) => candidate.id === id );
		if ( control ) return control;
	}
	return undefined;
}
