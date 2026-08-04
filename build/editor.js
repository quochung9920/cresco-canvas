(() => {
	'use strict';

	const { registerPlugin } = window.wp.plugins;
	const apiFetch = window.wp.apiFetch;
	const {
		Button,
		Notice,
		PanelBody,
		SearchControl,
		Spinner,
		TextControl,
		ToggleControl,
	} = window.wp.components;
	const { useDispatch, useSelect } = window.wp.data;
	const { PluginSidebar, PluginSidebarMoreMenuItem } = window.wp.editor;
	const {
		Fragment,
		createElement: h,
		useCallback,
		useEffect,
		useMemo,
		useState,
	} = window.wp.element;
	const { __, sprintf } = window.wp.i18n;
	const { createBlock } = window.wp.blocks;

	const ENABLED_META = '_cresco_canvas_enabled';
	const FAVORITES_KEY = 'crescoCanvas.elementFavorites';
	const RECENT_KEY = 'crescoCanvas.elementRecent';
	const DRAG_MIME = 'application/x-cresco-canvas-element';
	const MAX_RECENT = 8;

	function normalizeApiError(error) {
		if (!error || typeof error !== 'object') {
			return {};
		}
		return {
			code: typeof error.code === 'string' ? error.code : undefined,
			message: typeof error.message === 'string' ? error.message : undefined,
			data: error.data,
		};
	}

	function containsCrescoBlock(blocks) {
		return blocks.some(
			(current) =>
				current.name === 'cresco/container' ||
				containsCrescoBlock(current.innerBlocks || [])
		);
	}

	function block(name, attributes = {}, innerBlocks = []) {
		return createBlock(name, attributes, innerBlocks);
	}

	function text(content) {
		return block('core/paragraph', { content });
	}

	function heading(content, level = 2) {
		return block('core/heading', { content, level });
	}

	function button(label) {
		return block('core/buttons', {}, [
			block('core/button', { text: label, url: '#' }),
		]);
	}

	function container(innerBlocks = [], attributes = {}) {
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

	function featureCard(title, description) {
		return container([heading(title, 3), text(description)], {
			gap: 12,
			paddingTop: 24,
			paddingRight: 24,
			paddingBottom: 24,
			paddingLeft: 24,
		});
	}

	const categoryLabels = {
		layout: __('Layout', 'cresco-canvas'),
		basic: __('Basic', 'cresco-canvas'),
		media: __('Media', 'cresco-canvas'),
		marketing: __('Marketing', 'cresco-canvas'),
		navigation: __('Navigation', 'cresco-canvas'),
		blog: __('Blog & dynamic', 'cresco-canvas'),
		interactive: __('Interactive', 'cresco-canvas'),
		utility: __('Utility', 'cresco-canvas'),
	};

	const elements = [
		{
			id: 'section',
			label: __('Section', 'cresco-canvas'),
			description: __('Full-width page section.', 'cresco-canvas'),
			category: 'layout',
			keywords: ['section', 'layout', 'wrapper'],
			icon: 'align-wide',
			create: () => [
				container([], {
					maxWidth: 1440,
					paddingTop: 72,
					paddingBottom: 72,
				}),
			],
		},
		{
			id: 'container',
			label: __('Container', 'cresco-canvas'),
			description: __('Flexible nested content container.', 'cresco-canvas'),
			category: 'layout',
			keywords: ['container', 'wrapper', 'box'],
			icon: 'layout',
			create: () => [container()],
		},
		{
			id: 'row',
			label: __('Row', 'cresco-canvas'),
			description: __('Horizontal flex layout.', 'cresco-canvas'),
			category: 'layout',
			keywords: ['row', 'horizontal', 'flex'],
			icon: 'columns',
			create: () => [
				container([], {
					direction: 'row',
					align: 'center',
					paddingTop: 24,
					paddingBottom: 24,
				}),
			],
		},
		{
			id: 'grid',
			label: __('Grid', 'cresco-canvas'),
			description: __('Three-column content grid.', 'cresco-canvas'),
			category: 'layout',
			keywords: ['grid', 'columns', 'cards'],
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
							__('Feature one', 'cresco-canvas'),
							__('Describe the first feature.', 'cresco-canvas')
						),
						featureCard(
							__('Feature two', 'cresco-canvas'),
							__('Describe the second feature.', 'cresco-canvas')
						),
						featureCard(
							__('Feature three', 'cresco-canvas'),
							__('Describe the third feature.', 'cresco-canvas')
						),
					]
				),
			],
		},
		{
			id: 'stack',
			label: __('Stack', 'cresco-canvas'),
			description: __('Vertical stack with consistent spacing.', 'cresco-canvas'),
			category: 'layout',
			keywords: ['stack', 'vertical', 'group'],
			icon: 'editor-insertmore',
			create: () => [
				block(
					'core/group',
					{
						className: 'cc-elements-stack',
						layout: { type: 'flex', orientation: 'vertical' },
					},
					[
						heading(__('Stack title', 'cresco-canvas'), 3),
						text(__('Add stacked content here.', 'cresco-canvas')),
					]
				),
			],
		},
		{
			id: 'columns',
			label: __('Columns', 'cresco-canvas'),
			description: __('Two editable content columns.', 'cresco-canvas'),
			category: 'layout',
			keywords: ['columns', 'two column', 'split'],
			icon: 'columns',
			create: () => [
				block('core/columns', {}, [
					block('core/column', {}, [
						text(__('First column', 'cresco-canvas')),
					]),
					block('core/column', {}, [
						text(__('Second column', 'cresco-canvas')),
					]),
				]),
			],
		},
		{
			id: 'spacer',
			label: __('Spacer', 'cresco-canvas'),
			description: __('Adjustable vertical space.', 'cresco-canvas'),
			category: 'layout',
			keywords: ['spacer', 'space', 'gap'],
			icon: 'image-flip-vertical',
			create: () => [block('core/spacer', { height: '48px' })],
		},
		{
			id: 'divider',
			label: __('Divider', 'cresco-canvas'),
			description: __('Horizontal content separator.', 'cresco-canvas'),
			category: 'layout',
			keywords: ['divider', 'separator', 'line'],
			icon: 'minus',
			create: () => [block('core/separator')],
		},
		{
			id: 'heading',
			label: __('Heading', 'cresco-canvas'),
			description: __('Semantic editable heading.', 'cresco-canvas'),
			category: 'basic',
			keywords: ['heading', 'title', 'headline'],
			icon: 'heading',
			create: () => [heading(__('Add heading', 'cresco-canvas'))],
		},
		{
			id: 'text',
			label: __('Text', 'cresco-canvas'),
			description: __('Editable paragraph text.', 'cresco-canvas'),
			category: 'basic',
			keywords: ['text', 'paragraph', 'copy'],
			icon: 'editor-paragraph',
			create: () => [text(__('Add your text here.', 'cresco-canvas'))],
		},
		{
			id: 'button',
			label: __('Button', 'cresco-canvas'),
			description: __('Accessible call-to-action button.', 'cresco-canvas'),
			category: 'basic',
			keywords: ['button', 'link', 'cta'],
			icon: 'button',
			create: () => [button(__('Learn more', 'cresco-canvas'))],
		},
		{
			id: 'button-group',
			label: __('Button group', 'cresco-canvas'),
			description: __('Primary and secondary actions.', 'cresco-canvas'),
			category: 'basic',
			keywords: ['buttons', 'actions', 'cta'],
			icon: 'button',
			create: () => [
				block('core/buttons', {}, [
					block('core/button', {
						text: __('Primary action', 'cresco-canvas'),
						url: '#',
					}),
					block('core/button', {
						className: 'is-style-outline',
						text: __('Secondary action', 'cresco-canvas'),
						url: '#',
					}),
				]),
			],
		},
		{
			id: 'list',
			label: __('List', 'cresco-canvas'),
			description: __('Semantic bulleted list.', 'cresco-canvas'),
			category: 'basic',
			keywords: ['list', 'bullets', 'features'],
			icon: 'editor-ul',
			create: () => [
				block('core/list', {}, [
					block('core/list-item', {
						content: __('First item', 'cresco-canvas'),
					}),
					block('core/list-item', {
						content: __('Second item', 'cresco-canvas'),
					}),
					block('core/list-item', {
						content: __('Third item', 'cresco-canvas'),
					}),
				]),
			],
		},
		{
			id: 'quote',
			label: __('Quote', 'cresco-canvas'),
			description: __('Quotation with citation.', 'cresco-canvas'),
			category: 'basic',
			keywords: ['quote', 'citation', 'testimonial'],
			icon: 'format-quote',
			create: () => [
				block(
					'core/quote',
					{ citation: __('Author name', 'cresco-canvas') },
					[text(__('Add a memorable quotation.', 'cresco-canvas'))]
				),
			],
		},
		{
			id: 'table',
			label: __('Table', 'cresco-canvas'),
			description: __('Accessible data table.', 'cresco-canvas'),
			category: 'basic',
			keywords: ['table', 'data', 'comparison'],
			icon: 'editor-table',
			create: () => [block('core/table')],
		},
		{
			id: 'image',
			label: __('Image', 'cresco-canvas'),
			description: __('Responsive image from the Media Library.', 'cresco-canvas'),
			category: 'media',
			keywords: ['image', 'photo', 'media'],
			icon: 'format-image',
			create: () => [block('core/image')],
		},
		{
			id: 'gallery',
			label: __('Gallery', 'cresco-canvas'),
			description: __('Responsive image gallery.', 'cresco-canvas'),
			category: 'media',
			keywords: ['gallery', 'images', 'photos'],
			icon: 'format-gallery',
			create: () => [block('core/gallery', { columns: 3 })],
		},
		{
			id: 'video',
			label: __('Video', 'cresco-canvas'),
			description: __('Native or Media Library video.', 'cresco-canvas'),
			category: 'media',
			keywords: ['video', 'movie', 'media'],
			icon: 'format-video',
			create: () => [block('core/video')],
		},
		{
			id: 'audio',
			label: __('Audio', 'cresco-canvas'),
			description: __('Native audio player.', 'cresco-canvas'),
			category: 'media',
			keywords: ['audio', 'sound', 'podcast'],
			icon: 'format-audio',
			create: () => [block('core/audio')],
		},
		{
			id: 'file',
			label: __('File download', 'cresco-canvas'),
			description: __('Downloadable file with optional button.', 'cresco-canvas'),
			category: 'media',
			keywords: ['file', 'download', 'pdf'],
			icon: 'media-document',
			create: () => [block('core/file')],
		},
		{
			id: 'embed',
			label: __('Embed', 'cresco-canvas'),
			description: __('Embed content from a supported URL.', 'cresco-canvas'),
			category: 'media',
			keywords: ['embed', 'youtube', 'vimeo', 'url'],
			icon: 'embed-generic',
			create: () => [block('core/embed')],
		},
		{
			id: 'hero',
			label: __('Hero section', 'cresco-canvas'),
			description: __('Headline, supporting copy, and actions.', 'cresco-canvas'),
			category: 'marketing',
			keywords: ['hero', 'banner', 'landing page'],
			icon: 'cover-image',
			create: () => [
				container(
					[
						heading(__('Build something remarkable', 'cresco-canvas'), 1),
						text(
							__('Use this space to explain your strongest value proposition.', 'cresco-canvas')
						),
						block('core/buttons', {}, [
							block('core/button', {
								text: __('Get started', 'cresco-canvas'),
								url: '#',
							}),
							block('core/button', {
								className: 'is-style-outline',
								text: __('Learn more', 'cresco-canvas'),
								url: '#',
							}),
						]),
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
			label: __('Feature grid', 'cresco-canvas'),
			description: __('Three feature cards in a grid.', 'cresco-canvas'),
			category: 'marketing',
			keywords: ['features', 'cards', 'benefits'],
			icon: 'screenoptions',
			create: () => [
				container([
					heading(__('Why choose us', 'cresco-canvas')),
					block(
						'core/group',
						{
							className: 'cc-elements-grid',
							layout: { type: 'grid', columnCount: 3 },
						},
						[
							featureCard(
								__('Fast', 'cresco-canvas'),
								__('Designed for excellent performance.', 'cresco-canvas')
							),
							featureCard(
								__('Flexible', 'cresco-canvas'),
								__('Compose native WordPress blocks.', 'cresco-canvas')
							),
							featureCard(
								__('Accessible', 'cresco-canvas'),
								__('Built with inclusive interactions.', 'cresco-canvas')
							),
						]
					),
				]),
			],
		},
		{
			id: 'call-to-action',
			label: __('Call to action', 'cresco-canvas'),
			description: __('Focused conversion section.', 'cresco-canvas'),
			category: 'marketing',
			keywords: ['cta', 'call to action', 'conversion'],
			icon: 'megaphone',
			create: () => [
				container([
					heading(__('Ready to begin?', 'cresco-canvas')),
					text(__('Add a concise reason to take the next step.', 'cresco-canvas')),
					button(__('Start now', 'cresco-canvas')),
				]),
			],
		},
		{
			id: 'testimonial',
			label: __('Testimonial', 'cresco-canvas'),
			description: __('Customer quotation and attribution.', 'cresco-canvas'),
			category: 'marketing',
			keywords: ['testimonial', 'review', 'quote'],
			icon: 'testimonial',
			create: () => [
				container([
					block(
						'core/quote',
						{ citation: __('Customer name', 'cresco-canvas') },
						[
							text(
								__('Share a specific customer outcome or experience.', 'cresco-canvas')
							),
						]
					),
				]),
			],
		},
		{
			id: 'pricing-card',
			label: __('Pricing card', 'cresco-canvas'),
			description: __('Plan name, price, features, and action.', 'cresco-canvas'),
			category: 'marketing',
			keywords: ['pricing', 'plan', 'price'],
			icon: 'money-alt',
			create: () => [
				container([
					heading(__('Professional', 'cresco-canvas'), 3),
					heading(__('$49', 'cresco-canvas'), 2),
					block('core/list', {}, [
						block('core/list-item', {
							content: __('First benefit', 'cresco-canvas'),
						}),
						block('core/list-item', {
							content: __('Second benefit', 'cresco-canvas'),
						}),
						block('core/list-item', {
							content: __('Third benefit', 'cresco-canvas'),
						}),
					]),
					button(__('Choose plan', 'cresco-canvas')),
				]),
			],
		},
		{
			id: 'faq',
			label: __('FAQ', 'cresco-canvas'),
			description: __('Accessible expandable question.', 'cresco-canvas'),
			category: 'interactive',
			keywords: ['faq', 'accordion', 'details'],
			icon: 'editor-help',
			create: () => [
				block(
					'core/details',
					{ summary: __('Frequently asked question', 'cresco-canvas') },
					[text(__('Add the answer here.', 'cresco-canvas'))]
				),
			],
		},
		{
			id: 'social-links',
			label: __('Social links', 'cresco-canvas'),
			description: __('Accessible social profile links.', 'cresco-canvas'),
			category: 'navigation',
			keywords: ['social', 'icons', 'links'],
			icon: 'share',
			create: () => [block('core/social-links')],
		},
		{
			id: 'navigation',
			label: __('Navigation', 'cresco-canvas'),
			description: __('Native WordPress navigation menu.', 'cresco-canvas'),
			category: 'navigation',
			keywords: ['navigation', 'menu', 'header'],
			icon: 'menu',
			create: () => [block('core/navigation')],
		},
		{
			id: 'search',
			label: __('Search', 'cresco-canvas'),
			description: __('Accessible site search form.', 'cresco-canvas'),
			category: 'navigation',
			keywords: ['search', 'find', 'form'],
			icon: 'search',
			create: () => [
				block('core/search', { buttonUseIcon: true, showLabel: false }),
			],
		},
		{
			id: 'site-logo',
			label: __('Site logo', 'cresco-canvas'),
			description: __('Logo from WordPress site settings.', 'cresco-canvas'),
			category: 'navigation',
			keywords: ['logo', 'brand', 'site'],
			icon: 'format-image',
			create: () => [block('core/site-logo')],
		},
		{
			id: 'post-title',
			label: __('Post title', 'cresco-canvas'),
			description: __('Dynamic title for the current post.', 'cresco-canvas'),
			category: 'blog',
			keywords: ['post title', 'dynamic', 'blog'],
			icon: 'heading',
			create: () => [block('core/post-title')],
		},
		{
			id: 'post-featured-image',
			label: __('Featured image', 'cresco-canvas'),
			description: __('Dynamic current-post featured image.', 'cresco-canvas'),
			category: 'blog',
			keywords: ['featured image', 'dynamic', 'post'],
			icon: 'format-image',
			create: () => [block('core/post-featured-image')],
		},
		{
			id: 'post-excerpt',
			label: __('Post excerpt', 'cresco-canvas'),
			description: __('Dynamic current-post excerpt.', 'cresco-canvas'),
			category: 'blog',
			keywords: ['excerpt', 'dynamic', 'post'],
			icon: 'excerpt-view',
			create: () => [block('core/post-excerpt')],
		},
		{
			id: 'post-date',
			label: __('Post date', 'cresco-canvas'),
			description: __('Dynamic publication date.', 'cresco-canvas'),
			category: 'blog',
			keywords: ['date', 'dynamic', 'post'],
			icon: 'calendar-alt',
			create: () => [block('core/post-date')],
		},
		{
			id: 'latest-posts',
			label: __('Latest posts', 'cresco-canvas'),
			description: __('Native list of recent posts.', 'cresco-canvas'),
			category: 'blog',
			keywords: ['latest posts', 'blog', 'list'],
			icon: 'admin-post',
			create: () => [
				block('core/latest-posts', { displayPostDate: true }),
			],
		},
		{
			id: 'shortcode',
			label: __('Shortcode', 'cresco-canvas'),
			description: __('Render a trusted WordPress shortcode.', 'cresco-canvas'),
			category: 'utility',
			keywords: ['shortcode', 'plugin', 'integration'],
			icon: 'shortcode',
			create: () => [block('core/shortcode', { text: '[shortcode]' })],
		},
		{
			id: 'html',
			label: __('Custom HTML', 'cresco-canvas'),
			description: __('Restricted native HTML block.', 'cresco-canvas'),
			category: 'utility',
			keywords: ['html', 'code', 'custom'],
			icon: 'html',
			create: () => [
				block('core/html', { content: '<div>Custom HTML</div>' }),
			],
		},
	];

	function readStoredIds(key) {
		try {
			const stored = window.localStorage.getItem(key);
			const parsed = stored ? JSON.parse(stored) : [];
			return Array.isArray(parsed)
				? parsed.filter((value) => typeof value === 'string')
				: [];
		} catch (error) {
			return [];
		}
	}

	function writeStoredIds(key, ids) {
		try {
			window.localStorage.setItem(key, JSON.stringify(ids));
		} catch (error) {
			// Storage is optional. The library remains functional without it.
		}
	}

	function canContainElements(blockName) {
		return ['cresco/container', 'core/group', 'core/cover', 'core/column'].includes(
			blockName || ''
		);
	}

	function ElementsLibrary({ onElementInserted }) {
		const [query, setQuery] = useState('');
		const [filter, setFilter] = useState('all');
		const [favorites, setFavorites] = useState(() =>
			readStoredIds(FAVORITES_KEY)
		);
		const [recent, setRecent] = useState(() => readStoredIds(RECENT_KEY));
		const [message, setMessage] = useState('');
		const selectedClientId = useSelect(
			(select) => select('core/block-editor').getSelectedBlockClientId(),
			[]
		);
		const blockEditorSelect = useSelect(
			(select) => select('core/block-editor'),
			[]
		);
		const { insertBlocks, selectBlock } = useDispatch('core/block-editor');

		const insertDefinition = useCallback(
			(definition, targetClientId) => {
				const blocks = definition.create();
				const selected = targetClientId || selectedClientId;
				let rootClientId;
				let index;

				if (
					selected &&
					canContainElements(blockEditorSelect.getBlockName(selected))
				) {
					rootClientId = selected;
					index = blockEditorSelect.getBlockOrder(rootClientId).length;
				} else if (selected) {
					rootClientId =
						blockEditorSelect.getBlockRootClientId(selected) || undefined;
					index = blockEditorSelect.getBlockIndex(selected) + 1;
				} else {
					index = blockEditorSelect.getBlockOrder().length;
				}

				insertBlocks(blocks, index, rootClientId);
				if (blocks[0]) {
					selectBlock(blocks[0].clientId);
				}

				const nextRecent = [
					definition.id,
					...recent.filter((id) => id !== definition.id),
				].slice(0, MAX_RECENT);
				setRecent(nextRecent);
				writeStoredIds(RECENT_KEY, nextRecent);
				setMessage(
					sprintf(
						__('%s was added to the page.', 'cresco-canvas'),
						definition.label
					)
				);
				if (onElementInserted) {
					onElementInserted();
				}
			},
			[
				blockEditorSelect,
				insertBlocks,
				onElementInserted,
				recent,
				selectBlock,
				selectedClientId,
			]
		);

		const insertById = useCallback(
			(id, targetClientId) => {
				const definition = elements.find((element) => element.id === id);
				if (definition) {
					insertDefinition(definition, targetClientId);
				}
			},
			[insertDefinition]
		);

		useEffect(() => {
			let frame = 0;
			const documents = new Set();

			const onDragOver = (event) => {
				if (
					event.dataTransfer &&
					Array.from(event.dataTransfer.types).includes(DRAG_MIME)
				) {
					event.preventDefault();
					event.dataTransfer.dropEffect = 'copy';
				}
			};
			const onDrop = (event) => {
				const id = event.dataTransfer
					? event.dataTransfer.getData(DRAG_MIME)
					: '';
				if (!id) {
					return;
				}
				event.preventDefault();
				const target = event.target instanceof Element ? event.target : null;
				const blockTarget = target ? target.closest('[data-block]') : null;
				insertById(id, blockTarget ? blockTarget.dataset.block : null);
			};
			const attach = (targetDocument) => {
				if (!targetDocument || documents.has(targetDocument)) {
					return;
				}
				documents.add(targetDocument);
				targetDocument.addEventListener('dragover', onDragOver);
				targetDocument.addEventListener('drop', onDrop);
			};
			const findCanvas = () => {
				attach(document);
				const iframe = document.querySelector('iframe[name="editor-canvas"]');
				if (iframe) {
					attach(iframe.contentDocument);
				}
				frame = window.requestAnimationFrame(findCanvas);
			};
			findCanvas();

			return () => {
				window.cancelAnimationFrame(frame);
				for (const targetDocument of documents) {
					targetDocument.removeEventListener('dragover', onDragOver);
					targetDocument.removeEventListener('drop', onDrop);
				}
			};
		}, [insertById]);

		const visibleElements = useMemo(() => {
			const normalized = query.trim().toLocaleLowerCase();
			return elements.filter((element) => {
				if (filter === 'favorites' && !favorites.includes(element.id)) {
					return false;
				}
				if (filter === 'recent' && !recent.includes(element.id)) {
					return false;
				}
				if (
					!['all', 'favorites', 'recent'].includes(filter) &&
					element.category !== filter
				) {
					return false;
				}
				if (!normalized) {
					return true;
				}
				return [element.label, element.description, ...element.keywords]
					.join(' ')
					.toLocaleLowerCase()
					.includes(normalized);
			});
		}, [favorites, filter, query, recent]);

		function toggleFavorite(id) {
			const nextFavorites = favorites.includes(id)
				? favorites.filter((favoriteId) => favoriteId !== id)
				: [...favorites, id];
			setFavorites(nextFavorites);
			writeStoredIds(FAVORITES_KEY, nextFavorites);
		}

		return h(
			'div',
			{ className: 'cc-elements-library' },
			h(
				'div',
				{ className: 'cc-elements-library__intro' },
				h('strong', null, __('Cresco Elements', 'cresco-canvas')),
				h(
					'p',
					null,
					__(
						'Click an element to insert it, or drag it onto the editor canvas.',
						'cresco-canvas'
					)
				)
			),
			h(SearchControl, {
				label: __('Search elements', 'cresco-canvas'),
				onChange: setQuery,
				placeholder: __('Search elements…', 'cresco-canvas'),
				value: query,
			}),
			h(
				'div',
				{
					'aria-label': __('Element categories', 'cresco-canvas'),
					className: 'cc-elements-filters',
					role: 'group',
				},
				[
					['all', __('All', 'cresco-canvas')],
					['favorites', __('Favorites', 'cresco-canvas')],
					['recent', __('Recent', 'cresco-canvas')],
					...Object.entries(categoryLabels),
				].map(([id, label]) =>
					h(
						Button,
						{
							isPressed: filter === id,
							key: id,
							onClick: () => setFilter(id),
							variant: 'tertiary',
						},
						label
					)
				)
			),
			message
				? h(
					Notice,
					{
						isDismissible: true,
						onRemove: () => setMessage(''),
						status: 'success',
					},
					message
				)
				: null,
			h(
				'div',
				{ className: 'cc-elements-grid-list' },
				visibleElements.map((element) => {
					const isFavorite = favorites.includes(element.id);
					return h(
						'div',
						{
							className: 'cc-element-card',
							draggable: true,
							key: element.id,
							onDragStart: (event) => {
								event.dataTransfer.effectAllowed = 'copy';
								event.dataTransfer.setData(DRAG_MIME, element.id);
							},
						},
						h(
							Button,
							{
								className: 'cc-element-card__insert',
								icon: element.icon,
								onClick: () => insertDefinition(element),
								showTooltip: true,
								text: element.description,
							},
							h('span', null, element.label)
						),
						h(Button, {
							'aria-label': isFavorite
								? sprintf(
									__('Remove %s from favorites', 'cresco-canvas'),
									element.label
								)
								: sprintf(
									__('Add %s to favorites', 'cresco-canvas'),
									element.label
								),
							className: 'cc-element-card__favorite',
							icon: isFavorite ? 'star-filled' : 'star-empty',
							isPressed: isFavorite,
							onClick: () => toggleFavorite(element.id),
							size: 'small',
						})
					);
				})
			),
			visibleElements.length === 0
				? h(
					'p',
					{ className: 'cc-elements-empty' },
					__('No matching elements were found.', 'cresco-canvas')
				)
				: null
		);
	}

	function ColorField({ field, label, onChange, settings }) {
		const id = `cc-color-${field}`;
		return h(
			'label',
			{ className: 'cc-color-field', htmlFor: id },
			h('span', null, label),
			h('input', {
				id,
				type: 'color',
				value: settings[field],
				onChange: (event) =>
					onChange({ ...settings, [field]: event.target.value }),
			})
		);
	}

	function GlobalSettingsPanel({ onChange, onSave, saving, settings }) {
		return h(
			Fragment,
			null,
			h(
				PanelBody,
				{
					initialOpen: false,
					title: __('Global design', 'cresco-canvas'),
				},
				h(
					'div',
					{ className: 'cc-settings-grid' },
					h(ColorField, {
						field: 'primary',
						label: __('Primary color', 'cresco-canvas'),
						onChange,
						settings,
					}),
					h(ColorField, {
						field: 'text',
						label: __('Text color', 'cresco-canvas'),
						onChange,
						settings,
					}),
					h(ColorField, {
						field: 'background',
						label: __('Page background', 'cresco-canvas'),
						onChange,
						settings,
					}),
					h(TextControl, {
						label: __('Global radius', 'cresco-canvas'),
						max: 80,
						min: 0,
						onChange: (value) =>
							onChange({ ...settings, radius: Number(value) }),
						type: 'number',
						value: settings.radius,
					}),
					h(TextControl, {
						label: __('Font family stack', 'cresco-canvas'),
						onChange: (value) =>
							onChange({ ...settings, fontFamily: value }),
						value: settings.fontFamily,
					}),
					h(
						Button,
						{
							disabled: saving,
							isBusy: saving,
							onClick: onSave,
							variant: 'primary',
						},
						saving
							? __('Saving…', 'cresco-canvas')
							: __('Save global design', 'cresco-canvas')
					)
				)
			),
			h(
				PanelBody,
				{
					initialOpen: false,
					title: __('Data and uninstall', 'cresco-canvas'),
				},
				h(ToggleControl, {
					checked: settings.removeDataOnUninstall,
					help: __(
						'Page content is never deleted. This removes only Cresco settings and metadata during uninstall.',
						'cresco-canvas'
					),
					label: __('Remove plugin data on uninstall', 'cresco-canvas'),
					onChange: (enabled) =>
						onChange({ ...settings, removeDataOnUninstall: enabled }),
				})
			)
		);
	}

	function SettingsSidebar() {
		const bootstrap = window.crescoCanvasEditorSettings;
		const [globalSettings, setGlobalSettings] = useState(null);
		const [loading, setLoading] = useState(bootstrap.canManageSettings);
		const [saving, setSaving] = useState(false);
		const [notice, setNotice] = useState('');
		const [noticeStatus, setNoticeStatus] = useState('success');
		const pageMeta = useSelect((select) => {
			const value = select('core/editor').getEditedPostAttribute('meta');
			return value && typeof value === 'object' ? value : {};
		}, []);
		const { editPost } = useDispatch('core/editor');
		const hasCanvasBlock = useSelect(
			(select) => containsCrescoBlock(select('core/block-editor').getBlocks()),
			[]
		);
		const pageUsesCanvas = Boolean(pageMeta[ENABLED_META]) || hasCanvasBlock;

		const enablePageStyles = useCallback(() => {
			if (pageMeta[ENABLED_META]) {
				return;
			}
			editPost({ meta: { ...pageMeta, [ENABLED_META]: true } });
		}, [editPost, pageMeta]);

		const loadSettings = useCallback(async () => {
			if (!bootstrap.canManageSettings) {
				return;
			}
			setLoading(true);
			setNotice('');
			try {
				const result = await apiFetch({
					path: `${bootstrap.restPath}settings`,
				});
				setGlobalSettings(result);
			} catch (error) {
				const normalized = normalizeApiError(error);
				setNotice(
					normalized.message ||
						__(
							'Global design settings could not be loaded.',
							'cresco-canvas'
						)
				);
				setNoticeStatus('error');
			} finally {
				setLoading(false);
			}
		}, [bootstrap.canManageSettings, bootstrap.restPath]);

		useEffect(() => {
			loadSettings();
		}, [loadSettings]);

		useEffect(() => {
			let animationFrame = 0;
			let attempts = 0;
			let iframe = null;
			const update = () => {
				const documents = [document, iframe ? iframe.contentDocument : null];
				for (const targetDocument of documents) {
					const wrapper = targetDocument
						? targetDocument.querySelector('.editor-styles-wrapper')
						: null;
					if (wrapper) {
						wrapper.classList.toggle(
							'cresco-canvas-editor-scope',
							pageUsesCanvas
						);
						if (globalSettings) {
							const tokens = {
								'--cc-background': globalSettings.background,
								'--cc-container-max': `${globalSettings.containerMax}px`,
								'--cc-content-max': `${globalSettings.contentMax}px`,
								'--cc-font': globalSettings.fontFamily,
								'--cc-muted': globalSettings.muted,
								'--cc-primary': globalSettings.primary,
								'--cc-radius': `${globalSettings.radius}px`,
								'--cc-text': globalSettings.text,
							};
							for (const [property, value] of Object.entries(tokens)) {
								wrapper.style.setProperty(property, value);
							}
						}
					}
				}
			};
			const connectIframe = () => {
				const candidate = document.querySelector('iframe[name="editor-canvas"]');
				if (candidate) {
					iframe = candidate;
					iframe.addEventListener('load', update);
					update();
					return;
				}
				if (attempts < 120) {
					attempts += 1;
					animationFrame = window.requestAnimationFrame(connectIframe);
				}
			};
			update();
			connectIframe();
			return () => {
				window.cancelAnimationFrame(animationFrame);
				if (iframe) {
					iframe.removeEventListener('load', update);
				}
			};
		}, [globalSettings, pageUsesCanvas]);

		async function saveGlobalSettings() {
			if (!globalSettings || saving) {
				return;
			}
			setSaving(true);
			setNotice('');
			try {
				const result = await apiFetch({
					data: globalSettings,
					method: 'POST',
					path: `${bootstrap.restPath}settings`,
				});
				setGlobalSettings(result);
				setNotice(__('Global design saved.', 'cresco-canvas'));
				setNoticeStatus('success');
			} catch (error) {
				const normalized = normalizeApiError(error);
				setNotice(
					normalized.message ||
						__('Global design could not be saved.', 'cresco-canvas')
				);
				setNoticeStatus('error');
			} finally {
				setSaving(false);
			}
		}

		return h(
			Fragment,
			null,
			h(
				PluginSidebarMoreMenuItem,
				{ target: 'cresco-canvas-settings' },
				__('Cresco Canvas', 'cresco-canvas')
			),
			h(
				PluginSidebar,
				{
					className: 'cresco-canvas-sidebar',
					icon: 'layout',
					name: 'cresco-canvas-settings',
					title: __('Cresco Canvas', 'cresco-canvas'),
				},
				notice
					? h(
						Notice,
						{
							isDismissible: true,
							onRemove: () => setNotice(''),
							status: noticeStatus,
						},
						notice
					)
					: null,
				h(
					PanelBody,
					{
						className: 'cc-elements-panel',
						initialOpen: true,
						title: __('Elements', 'cresco-canvas'),
					},
					h(ElementsLibrary, { onElementInserted: enablePageStyles })
				),
				h(
					PanelBody,
					{
						initialOpen: false,
						title: __('Page styling', 'cresco-canvas'),
					},
					h(ToggleControl, {
						checked: Boolean(pageMeta[ENABLED_META]),
						help: __(
							'Applies Cresco global colors, typography, and spacing tokens on this Page. Pages containing a Cresco block are detected automatically.',
							'cresco-canvas'
						),
						label: __('Enable Cresco page styles', 'cresco-canvas'),
						onChange: (enabled) =>
							editPost({
								meta: { ...pageMeta, [ENABLED_META]: enabled },
							}),
					}),
					h(
						'p',
						{ className: 'cc-native-note' },
						__(
							'This setting is saved by the normal Gutenberg Save or Update button.',
							'cresco-canvas'
						)
					)
				),
				bootstrap.canManageSettings && loading
					? h(
						'div',
						{
							'aria-label': __('Loading global design', 'cresco-canvas'),
							className: 'cc-sidebar-loading',
						},
						h(Spinner)
					)
					: null,
				bootstrap.canManageSettings && globalSettings
					? h(GlobalSettingsPanel, {
						onChange: setGlobalSettings,
						onSave: saveGlobalSettings,
						saving,
						settings: globalSettings,
					})
					: null,
				bootstrap.canManageSettings && !loading && !globalSettings
					? h(
						'div',
						{ className: 'cc-sidebar-retry' },
						h(
							Button,
							{ onClick: loadSettings, variant: 'secondary' },
							__('Retry loading settings', 'cresco-canvas')
						)
					)
					: null
			)
		);
	}

	registerPlugin('cresco-canvas', {
		icon: 'layout',
		render: SettingsSidebar,
	});
})();
