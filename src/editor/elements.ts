import { createBlock, type BlockInstance } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

export type ElementCategory =
	| 'layout'
	| 'basic'
	| 'media'
	| 'marketing'
	| 'navigation'
	| 'blog'
	| 'interactive'
	| 'utility';

export interface CrescoElementDefinition {
	id: string;
	label: string;
	description: string;
	category: ElementCategory;
	keywords: string[];
	icon: string;
	create: () => BlockInstance[];
}

export const elementCategoryLabels: Record< ElementCategory, string > = {
	layout: __( 'Layout', 'cresco-canvas' ),
	basic: __( 'Basic', 'cresco-canvas' ),
	media: __( 'Media', 'cresco-canvas' ),
	marketing: __( 'Marketing', 'cresco-canvas' ),
	navigation: __( 'Navigation', 'cresco-canvas' ),
	blog: __( 'Blog & dynamic', 'cresco-canvas' ),
	interactive: __( 'Interactive', 'cresco-canvas' ),
	utility: __( 'Utility', 'cresco-canvas' ),
};

function block(
	name: string,
	attributes: Record< string, unknown > = {},
	innerBlocks: BlockInstance[] = []
): BlockInstance {
	return createBlock( name, attributes, innerBlocks );
}

function text( content: string ): BlockInstance {
	return block( 'core/paragraph', { content } );
}

function heading( content: string, level = 2 ): BlockInstance {
	return block( 'core/heading', { content, level } );
}

function button( label: string ): BlockInstance {
	return block( 'core/buttons', {}, [
		block( 'core/button', { text: label, url: '#' } ),
	] );
}

function container(
	innerBlocks: BlockInstance[] = [],
	attributes: Record< string, unknown > = {}
): BlockInstance {
	return block(
		'cresco/container',
		{
			layoutMode: 'flex',
			direction: 'column',
			justify: 'flex-start',
			align: 'stretch',
			gap: 24,
			paddingTop: 48,
			paddingRight: 32,
			paddingBottom: 48,
			paddingLeft: 32,
			maxWidth: 1200,
			...attributes,
		},
		innerBlocks
	);
}

function featureCard( title: string, description: string ): BlockInstance {
	return container(
		[ heading( title, 3 ), text( description ) ],
		{
			gap: 12,
			paddingTop: 24,
			paddingRight: 24,
			paddingBottom: 24,
			paddingLeft: 24,
			maxWidth: 1200,
		}
	);
}

export const crescoElements: CrescoElementDefinition[] = [
	{
		id: 'section',
		label: __( 'Section', 'cresco-canvas' ),
		description: __( 'Full-width semantic page section.', 'cresco-canvas' ),
		category: 'layout',
		keywords: [ 'section', 'layout', 'wrapper', 'full width' ],
		icon: 'align-wide',
		create: () => [
			container( [], {
				maxWidth: 1440,
				paddingTop: 72,
				paddingBottom: 72,
			} ),
		],
	},
	{
		id: 'container',
		label: __( 'Container', 'cresco-canvas' ),
		description: __( 'Flexible nested content container.', 'cresco-canvas' ),
		category: 'layout',
		keywords: [ 'container', 'wrapper', 'box' ],
		icon: 'layout',
		create: () => [ container() ],
	},
	{
		id: 'row',
		label: __( 'Row', 'cresco-canvas' ),
		description: __( 'Horizontal flex layout.', 'cresco-canvas' ),
		category: 'layout',
		keywords: [ 'row', 'horizontal', 'flex' ],
		icon: 'columns',
		create: () => [
			container( [], {
				direction: 'row',
				align: 'center',
				paddingTop: 24,
				paddingBottom: 24,
			} ),
		],
	},
	{
		id: 'grid',
		label: __( 'Grid', 'cresco-canvas' ),
		description: __( 'Three-column responsive content grid.', 'cresco-canvas' ),
		category: 'layout',
		keywords: [ 'grid', 'columns', 'cards' ],
		icon: 'grid-view',
		create: () => [
			block(
				'core/group',
				{
					className: 'cc-elements-grid',
					layout: { type: 'grid', columnCount: 3 },
				},
				[
					featureCard(
						__( 'Feature one', 'cresco-canvas' ),
						__( 'Describe the first feature.', 'cresco-canvas' )
					),
					featureCard(
						__( 'Feature two', 'cresco-canvas' ),
						__( 'Describe the second feature.', 'cresco-canvas' )
					),
					featureCard(
						__( 'Feature three', 'cresco-canvas' ),
						__( 'Describe the third feature.', 'cresco-canvas' )
					),
				]
			),
		],
	},
	{
		id: 'stack',
		label: __( 'Stack', 'cresco-canvas' ),
		description: __( 'Vertical stack with consistent spacing.', 'cresco-canvas' ),
		category: 'layout',
		keywords: [ 'stack', 'vertical', 'group' ],
		icon: 'editor-insertmore',
		create: () => [
			block(
				'core/group',
				{
					className: 'cc-elements-stack',
					layout: { type: 'flex', orientation: 'vertical' },
				},
				[ heading( __( 'Stack title', 'cresco-canvas' ), 3 ), text( __( 'Add stacked content here.', 'cresco-canvas' ) ) ]
			),
		],
	},
	{
		id: 'columns',
		label: __( 'Columns', 'cresco-canvas' ),
		description: __( 'Two editable content columns.', 'cresco-canvas' ),
		category: 'layout',
		keywords: [ 'columns', 'two column', 'split' ],
		icon: 'columns',
		create: () => [
			block( 'core/columns', {}, [
				block( 'core/column', {}, [ text( __( 'First column', 'cresco-canvas' ) ) ] ),
				block( 'core/column', {}, [ text( __( 'Second column', 'cresco-canvas' ) ) ] ),
			] ),
		],
	},
	{
		id: 'spacer',
		label: __( 'Spacer', 'cresco-canvas' ),
		description: __( 'Adjustable vertical space.', 'cresco-canvas' ),
		category: 'layout',
		keywords: [ 'spacer', 'space', 'gap' ],
		icon: 'image-flip-vertical',
		create: () => [ block( 'core/spacer', { height: '48px' } ) ],
	},
	{
		id: 'divider',
		label: __( 'Divider', 'cresco-canvas' ),
		description: __( 'Horizontal content separator.', 'cresco-canvas' ),
		category: 'layout',
		keywords: [ 'divider', 'separator', 'line' ],
		icon: 'minus',
		create: () => [ block( 'core/separator' ) ],
	},
	{
		id: 'heading',
		label: __( 'Heading', 'cresco-canvas' ),
		description: __( 'Semantic editable heading.', 'cresco-canvas' ),
		category: 'basic',
		keywords: [ 'heading', 'title', 'headline' ],
		icon: 'heading',
		create: () => [ heading( __( 'Add heading', 'cresco-canvas' ) ) ],
	},
	{
		id: 'text',
		label: __( 'Text', 'cresco-canvas' ),
		description: __( 'Editable paragraph text.', 'cresco-canvas' ),
		category: 'basic',
		keywords: [ 'text', 'paragraph', 'copy' ],
		icon: 'editor-paragraph',
		create: () => [ text( __( 'Add your text here.', 'cresco-canvas' ) ) ],
	},
	{
		id: 'button',
		label: __( 'Button', 'cresco-canvas' ),
		description: __( 'Accessible call-to-action button.', 'cresco-canvas' ),
		category: 'basic',
		keywords: [ 'button', 'link', 'cta' ],
		icon: 'button',
		create: () => [ button( __( 'Learn more', 'cresco-canvas' ) ) ],
	},
	{
		id: 'button-group',
		label: __( 'Button group', 'cresco-canvas' ),
		description: __( 'Primary and secondary actions.', 'cresco-canvas' ),
		category: 'basic',
		keywords: [ 'buttons', 'actions', 'cta' ],
		icon: 'button',
		create: () => [
			block( 'core/buttons', {}, [
				block( 'core/button', { text: __( 'Primary action', 'cresco-canvas' ), url: '#' } ),
				block( 'core/button', {
					className: 'is-style-outline',
					text: __( 'Secondary action', 'cresco-canvas' ),
					url: '#',
				} ),
			] ),
		],
	},
	{
		id: 'list',
		label: __( 'List', 'cresco-canvas' ),
		description: __( 'Semantic bulleted list.', 'cresco-canvas' ),
		category: 'basic',
		keywords: [ 'list', 'bullets', 'features' ],
		icon: 'editor-ul',
		create: () => [
			block( 'core/list', {}, [
				block( 'core/list-item', { content: __( 'First item', 'cresco-canvas' ) } ),
				block( 'core/list-item', { content: __( 'Second item', 'cresco-canvas' ) } ),
				block( 'core/list-item', { content: __( 'Third item', 'cresco-canvas' ) } ),
			] ),
		],
	},
	{
		id: 'quote',
		label: __( 'Quote', 'cresco-canvas' ),
		description: __( 'Quotation with citation.', 'cresco-canvas' ),
		category: 'basic',
		keywords: [ 'quote', 'citation', 'testimonial' ],
		icon: 'format-quote',
		create: () => [
			block( 'core/quote', { citation: __( 'Author name', 'cresco-canvas' ) }, [
				text( __( 'Add a memorable quotation.', 'cresco-canvas' ) ),
			] ),
		],
	},
	{
		id: 'table',
		label: __( 'Table', 'cresco-canvas' ),
		description: __( 'Accessible data table.', 'cresco-canvas' ),
		category: 'basic',
		keywords: [ 'table', 'data', 'comparison' ],
		icon: 'editor-table',
		create: () => [ block( 'core/table' ) ],
	},
	{
		id: 'image',
		label: __( 'Image', 'cresco-canvas' ),
		description: __( 'Responsive image from the Media Library.', 'cresco-canvas' ),
		category: 'media',
		keywords: [ 'image', 'photo', 'media' ],
		icon: 'format-image',
		create: () => [ block( 'core/image' ) ],
	},
	{
		id: 'gallery',
		label: __( 'Gallery', 'cresco-canvas' ),
		description: __( 'Responsive image gallery.', 'cresco-canvas' ),
		category: 'media',
		keywords: [ 'gallery', 'images', 'photos' ],
		icon: 'format-gallery',
		create: () => [ block( 'core/gallery', { columns: 3 } ) ],
	},
	{
		id: 'video',
		label: __( 'Video', 'cresco-canvas' ),
		description: __( 'Native video or Media Library video.', 'cresco-canvas' ),
		category: 'media',
		keywords: [ 'video', 'movie', 'media' ],
		icon: 'format-video',
		create: () => [ block( 'core/video' ) ],
	},
	{
		id: 'audio',
		label: __( 'Audio', 'cresco-canvas' ),
		description: __( 'Native audio player.', 'cresco-canvas' ),
		category: 'media',
		keywords: [ 'audio', 'sound', 'podcast' ],
		icon: 'format-audio',
		create: () => [ block( 'core/audio' ) ],
	},
	{
		id: 'file',
		label: __( 'File download', 'cresco-canvas' ),
		description: __( 'Downloadable file with optional button.', 'cresco-canvas' ),
		category: 'media',
		keywords: [ 'file', 'download', 'pdf' ],
		icon: 'media-document',
		create: () => [ block( 'core/file' ) ],
	},
	{
		id: 'embed',
		label: __( 'Embed', 'cresco-canvas' ),
		description: __( 'Embed content from a supported URL.', 'cresco-canvas' ),
		category: 'media',
		keywords: [ 'embed', 'youtube', 'vimeo', 'url' ],
		icon: 'embed-generic',
		create: () => [ block( 'core/embed' ) ],
	},
	{
		id: 'hero',
		label: __( 'Hero section', 'cresco-canvas' ),
		description: __( 'Headline, supporting copy, and actions.', 'cresco-canvas' ),
		category: 'marketing',
		keywords: [ 'hero', 'banner', 'landing page' ],
		icon: 'cover-image',
		create: () => [
			container(
				[
					heading( __( 'Build something remarkable', 'cresco-canvas' ), 1 ),
					text( __( 'Use this space to explain your strongest value proposition.', 'cresco-canvas' ) ),
					block( 'core/buttons', {}, [
						block( 'core/button', { text: __( 'Get started', 'cresco-canvas' ), url: '#' } ),
						block( 'core/button', {
							className: 'is-style-outline',
							text: __( 'Learn more', 'cresco-canvas' ),
							url: '#',
						} ),
					] ),
				],
				{
					align: 'flex-start',
					gap: 20,
					paddingTop: 96,
					paddingBottom: 96,
					maxWidth: 1440,
				}
			),
		],
	},
	{
		id: 'feature-grid',
		label: __( 'Feature grid', 'cresco-canvas' ),
		description: __( 'Three feature cards in a grid.', 'cresco-canvas' ),
		category: 'marketing',
		keywords: [ 'features', 'cards', 'benefits' ],
		icon: 'screenoptions',
		create: () => [
			container( [
				heading( __( 'Why choose us', 'cresco-canvas' ) ),
				block(
					'core/group',
					{ className: 'cc-elements-grid', layout: { type: 'grid', columnCount: 3 } },
					[
						featureCard( __( 'Fast', 'cresco-canvas' ), __( 'Designed for excellent performance.', 'cresco-canvas' ) ),
						featureCard( __( 'Flexible', 'cresco-canvas' ), __( 'Compose native WordPress blocks.', 'cresco-canvas' ) ),
						featureCard( __( 'Accessible', 'cresco-canvas' ), __( 'Built with inclusive interactions.', 'cresco-canvas' ) ),
					]
				),
			] ),
		],
	},
	{
		id: 'call-to-action',
		label: __( 'Call to action', 'cresco-canvas' ),
		description: __( 'Focused conversion section.', 'cresco-canvas' ),
		category: 'marketing',
		keywords: [ 'cta', 'call to action', 'conversion' ],
		icon: 'megaphone',
		create: () => [
			container( [
				heading( __( 'Ready to begin?', 'cresco-canvas' ) ),
				text( __( 'Add a concise reason to take the next step.', 'cresco-canvas' ) ),
				button( __( 'Start now', 'cresco-canvas' ) ),
			] ),
		],
	},
	{
		id: 'testimonial',
		label: __( 'Testimonial', 'cresco-canvas' ),
		description: __( 'Customer quotation and attribution.', 'cresco-canvas' ),
		category: 'marketing',
		keywords: [ 'testimonial', 'review', 'quote' ],
		icon: 'testimonial',
		create: () => [
			container( [
				block( 'core/quote', { citation: __( 'Customer name', 'cresco-canvas' ) }, [
					text( __( 'Share a specific customer outcome or experience.', 'cresco-canvas' ) ),
				] ),
			] ),
		],
	},
	{
		id: 'pricing-card',
		label: __( 'Pricing card', 'cresco-canvas' ),
		description: __( 'Plan name, price, features, and action.', 'cresco-canvas' ),
		category: 'marketing',
		keywords: [ 'pricing', 'plan', 'price' ],
		icon: 'money-alt',
		create: () => [
			container( [
				heading( __( 'Professional', 'cresco-canvas' ), 3 ),
				heading( __( '$49', 'cresco-canvas' ), 2 ),
				block( 'core/list', {}, [
					block( 'core/list-item', { content: __( 'First benefit', 'cresco-canvas' ) } ),
					block( 'core/list-item', { content: __( 'Second benefit', 'cresco-canvas' ) } ),
					block( 'core/list-item', { content: __( 'Third benefit', 'cresco-canvas' ) } ),
				] ),
				button( __( 'Choose plan', 'cresco-canvas' ) ),
			] ),
		],
	},
	{
		id: 'faq',
		label: __( 'FAQ', 'cresco-canvas' ),
		description: __( 'Accessible expandable question.', 'cresco-canvas' ),
		category: 'interactive',
		keywords: [ 'faq', 'accordion', 'details' ],
		icon: 'editor-help',
		create: () => [
			block( 'core/details', { summary: __( 'Frequently asked question', 'cresco-canvas' ) }, [
				text( __( 'Add the answer here.', 'cresco-canvas' ) ),
			] ),
		],
	},
	{
		id: 'social-links',
		label: __( 'Social links', 'cresco-canvas' ),
		description: __( 'Accessible social profile links.', 'cresco-canvas' ),
		category: 'navigation',
		keywords: [ 'social', 'icons', 'links' ],
		icon: 'share',
		create: () => [ block( 'core/social-links' ) ],
	},
	{
		id: 'navigation',
		label: __( 'Navigation', 'cresco-canvas' ),
		description: __( 'Native WordPress navigation menu.', 'cresco-canvas' ),
		category: 'navigation',
		keywords: [ 'navigation', 'menu', 'header' ],
		icon: 'menu',
		create: () => [ block( 'core/navigation' ) ],
	},
	{
		id: 'search',
		label: __( 'Search', 'cresco-canvas' ),
		description: __( 'Accessible site search form.', 'cresco-canvas' ),
		category: 'navigation',
		keywords: [ 'search', 'find', 'form' ],
		icon: 'search',
		create: () => [ block( 'core/search', { buttonUseIcon: true, showLabel: false } ) ],
	},
	{
		id: 'site-logo',
		label: __( 'Site logo', 'cresco-canvas' ),
		description: __( 'Logo from WordPress site settings.', 'cresco-canvas' ),
		category: 'navigation',
		keywords: [ 'logo', 'brand', 'site' ],
		icon: 'format-image',
		create: () => [ block( 'core/site-logo' ) ],
	},
	{
		id: 'post-title',
		label: __( 'Post title', 'cresco-canvas' ),
		description: __( 'Dynamic title for the current post.', 'cresco-canvas' ),
		category: 'blog',
		keywords: [ 'post title', 'dynamic', 'blog' ],
		icon: 'heading',
		create: () => [ block( 'core/post-title' ) ],
	},
	{
		id: 'post-featured-image',
		label: __( 'Featured image', 'cresco-canvas' ),
		description: __( 'Dynamic current-post featured image.', 'cresco-canvas' ),
		category: 'blog',
		keywords: [ 'featured image', 'dynamic', 'post' ],
		icon: 'format-image',
		create: () => [ block( 'core/post-featured-image' ) ],
	},
	{
		id: 'post-excerpt',
		label: __( 'Post excerpt', 'cresco-canvas' ),
		description: __( 'Dynamic current-post excerpt.', 'cresco-canvas' ),
		category: 'blog',
		keywords: [ 'excerpt', 'dynamic', 'post' ],
		icon: 'excerpt-view',
		create: () => [ block( 'core/post-excerpt' ) ],
	},
	{
		id: 'post-date',
		label: __( 'Post date', 'cresco-canvas' ),
		description: __( 'Dynamic publication date.', 'cresco-canvas' ),
		category: 'blog',
		keywords: [ 'date', 'dynamic', 'post' ],
		icon: 'calendar-alt',
		create: () => [ block( 'core/post-date' ) ],
	},
	{
		id: 'latest-posts',
		label: __( 'Latest posts', 'cresco-canvas' ),
		description: __( 'Native list of recent posts.', 'cresco-canvas' ),
		category: 'blog',
		keywords: [ 'latest posts', 'blog', 'list' ],
		icon: 'admin-post',
		create: () => [ block( 'core/latest-posts', { displayPostDate: true } ) ],
	},
	{
		id: 'shortcode',
		label: __( 'Shortcode', 'cresco-canvas' ),
		description: __( 'Render a trusted WordPress shortcode.', 'cresco-canvas' ),
		category: 'utility',
		keywords: [ 'shortcode', 'plugin', 'integration' ],
		icon: 'shortcode',
		create: () => [ block( 'core/shortcode', { text: '[shortcode]' } ) ],
	},
	{
		id: 'html',
		label: __( 'Custom HTML', 'cresco-canvas' ),
		description: __( 'Restricted native HTML block.', 'cresco-canvas' ),
		category: 'utility',
		keywords: [ 'html', 'code', 'custom' ],
		icon: 'html',
		create: () => [ block( 'core/html', { content: '<div>Custom HTML</div>' } ) ],
	},
];

export function findCrescoElement( id: string ): CrescoElementDefinition | undefined {
	return crescoElements.find( ( element ) => element.id === id );
}
