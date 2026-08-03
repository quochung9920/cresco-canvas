import { __ } from '@wordpress/i18n';

import type { Device } from './types';

export const DEVICES: Device[] = [
	'4k',
	'desktop',
	'laptop',
	'tablet',
	'mobile',
];

export const ELEMENTS = [
	{
		category: __( 'Layout', 'cresco-canvas' ),
		label: __( 'Container', 'cresco-canvas' ),
		name: 'cresco/container',
	},
	{
		category: __( 'Text', 'cresco-canvas' ),
		label: __( 'Heading', 'cresco-canvas' ),
		name: 'core/heading',
	},
	{
		category: __( 'Text', 'cresco-canvas' ),
		label: __( 'Paragraph', 'cresco-canvas' ),
		name: 'core/paragraph',
	},
	{
		category: __( 'Design', 'cresco-canvas' ),
		label: __( 'Buttons', 'cresco-canvas' ),
		name: 'core/buttons',
	},
	{
		category: __( 'Media', 'cresco-canvas' ),
		label: __( 'Image', 'cresco-canvas' ),
		name: 'core/image',
	},
	{
		category: __( 'Media', 'cresco-canvas' ),
		label: __( 'Video', 'cresco-canvas' ),
		name: 'core/video',
	},
	{
		category: __( 'Text', 'cresco-canvas' ),
		label: __( 'List', 'cresco-canvas' ),
		name: 'core/list',
	},
	{
		category: __( 'Design', 'cresco-canvas' ),
		label: __( 'Spacer', 'cresco-canvas' ),
		name: 'core/spacer',
	},
	{
		category: __( 'Design', 'cresco-canvas' ),
		label: __( 'Divider', 'cresco-canvas' ),
		name: 'core/separator',
	},
	{
		category: __( 'Layout', 'cresco-canvas' ),
		label: __( 'Columns', 'cresco-canvas' ),
		name: 'core/columns',
	},
] as const;
