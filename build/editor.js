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
	const { createBlock, getBlockType } = window.wp.blocks;

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

	function definition(id, label, description, category, keywords, icon, create) {
		return { id, label, description, category, keywords, icon, create };
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
		definition('section', __('Section', 'cresco-canvas'), __('Full-width page section.', 'cresco-canvas'), 'layout', ['section', 'layout', 'wrapper'], 'align-wide', () => [container([], { maxWidth: 1440, paddingTop: 72, paddingBottom: 72 })]),
		definition('container', __('Container', 'cresco-canvas'), __('Flexible nested content container.', 'cresco-canvas'), 'layout', ['container', 'wrapper', 'box'], 'layout', () => [container()]),
		definition('row', __('Row', 'cresco-canvas'), __('Horizontal flex layout.', 'cresco-canvas'), 'layout', ['row', 'horizontal', 'flex'], 'columns', () => [container([], { direction: 'row', align: 'center', paddingTop: 24, paddingBottom: 24 })]),
		definition('grid', __('Grid', 'cresco-canvas'), __('Three-column content grid.', 'cresco-canvas'), 'layout', ['grid', 'columns', 'cards'], 'grid-view', () => [block('core/group', { className: 'cc-elements-grid', layout: { type: 'grid', columnCount: 3 } }, [featureCard(__('Feature one', 'cresco-canvas'), __('Describe the first feature.', 'cresco-canvas')), featureCard(__('Feature two', 'cresco-canvas'), __('Describe the second feature.', 'cresco-canvas')), featureCard(__('Feature three', 'cresco-canvas'), __('Describe the third feature.', 'cresco-canvas'))])]),
		definition('stack', __('Stack', 'cresco-canvas'), __('Vertical stack with consistent spacing.', 'cresco-canvas'), 'layout', ['stack', 'vertical', 'group'], 'editor-insertmore', () => [block('core/group', { className: 'cc-elements-stack', layout: { type: 'flex', orientation: 'vertical' } }, [heading(__('Stack title', 'cresco-canvas'), 3), text(__('Add stacked content here.', 'cresco-canvas'))])]),
		definition('columns', __('Columns', 'cresco-canvas'), __('Two editable content columns.', 'cresco-canvas'), 'layout', ['columns', 'two column', 'split'], 'columns', () => [block('core/columns', {}, [block('core/column', {}, [text(__('First column', 'cresco-canvas'))]), block('core/column', {}, [text(__('Second column', 'cresco-canvas'))])])]),
		definition('spacer', __('Spacer', 'cresco-canvas'), __('Adjustable vertical space.', 'cresco-canvas'), 'layout', ['spacer', 'space', 'gap'], 'image-flip-vertical', () => [block('core/spacer', { height: '48px' })]),
		definition('divider', __('Divider', 'cresco-canvas'), __('Horizontal content separator.', 'cresco-canvas'), 'layout', ['divider', 'separator', 'line'], 'minus', () => [block('core/separator')]),
		definition('heading', __('Heading', 'cresco-canvas'), __('Semantic editable heading.', 'cresco-canvas'), 'basic', ['heading', 'title', 'headline'], 'heading', () => [heading(__('Add heading', 'cresco-canvas'))]),
		definition('text', __('Text', 'cresco-canvas'), __('Editable paragraph text.', 'cresco-canvas'), 'basic', ['text', 'paragraph', 'copy'], 'editor-paragraph', () => [text(__('Add your text here.', 'cresco-canvas'))]),
		definition('button', __('Button', 'cresco-canvas'), __('Accessible call-to-action button.', 'cresco-canvas'), 'basic', ['button', 'link', 'cta'], 'button', () => [button(__('Learn more', 'cresco-canvas'))]),
		definition('button-group', __('Button group', 'cresco-canvas'), __('Primary and secondary actions.', 'cresco-canvas'), 'basic', ['buttons', 'actions', 'cta'], 'button', () => [block('core/buttons', {}, [block('core/button', { text: __('Primary action', 'cresco-canvas'), url: '#' }), block('core/button', { className: 'is-style-outline', text: __('Secondary action', 'cresco-canvas'), url: '#' })])]),
		definition('list', __('List', 'cresco-canvas'), __('Semantic bulleted list.', 'cresco-canvas'), 'basic', ['list', 'bullets', 'features'], 'editor-ul', () => [block('core/list', {}, [block('core/list-item', { content: __('First item', 'cresco-canvas') }), block('core/list-item', { content: __('Second item', 'cresco-canvas') }), block('core/list-item', { content: __('Third item', 'cresco-canvas') })])]),
		definition('quote', __('Quote', 'cresco-canvas'), __('Quotation with citation.', 'cresco-canvas'), 'basic', ['quote', 'citation', 'testimonial'], 'format-quote', () => [block('core/quote', { citation: __('Author name', 'cresco-canvas') }, [text(__('Add a memorable quotation.', 'cresco-canvas'))])]),
		definition('table', __('Table', 'cresco-canvas'), __('Accessible data table.', 'cresco-canvas'), 'basic', ['table', 'data', 'comparison'], 'editor-table', () => [block('core/table')]),
		definition('image', __('Image', 'cresco-canvas'), __('Responsive image from the Media Library.', 'cresco-canvas'), 'media', ['image', 'photo', 'media'], 'format-image', () => [block('core/image')]),
		definition('gallery', __('Gallery', 'cresco-canvas'), __('Responsive image gallery.', 'cresco-canvas'), 'media', ['gallery', 'images', 'photos'], 'format-gallery', () => [block('core/gallery', { columns: 3 })]),
		definition('video', __('Video', 'cresco-canvas'), __('Native or Media Library video.', 'cresco-canvas'), 'media', ['video', 'movie', 'media'], 'format-video', () => [block('core/video')]),
		definition('audio', __('Audio', 'cresco-canvas'), __('Native audio player.', 'cresco-canvas'), 'media', ['audio', 'sound', 'podcast'], 'format-audio', () => [block('core/audio')]),
		definition('file', __('File download', 'cresco-canvas'), __('Downloadable file with optional button.', 'cresco-canvas'), 'media', ['file', 'download', 'pdf'], 'media-document', () => [block('core/file')]),
		definition('embed', __('Embed', 'cresco-canvas'), __('Embed content from a supported URL.', 'cresco-canvas'), 'media', ['embed', 'youtube', 'vimeo', 'url'], 'embed-generic', () => [block('core/embed')]),
		definition('hero', __('Hero section', 'cresco-canvas'), __('Headline, supporting copy, and actions.', 'cresco-canvas'), 'marketing', ['hero', 'banner', 'landing page'], 'cover-image', () => [container([heading(__('Build something remarkable', 'cresco-canvas'), 1), text(__('Use this space to explain your strongest value proposition.', 'cresco-canvas')), block('core/buttons', {}, [block('core/button', { text: __('Get started', 'cresco-canvas'), url: '#' }), block('core/button', { className: 'is-style-outline', text: __('Learn more', 'cresco-canvas'), url: '#' })])], { align: 'flex-start', gap: 20, paddingTop: 96, paddingBottom: 96, maxWidth: 1440 })]),
		definition('feature-grid', __('Feature grid', 'cresco-canvas'), __('Three feature cards in a grid.', 'cresco-canvas'), 'marketing', ['features', 'cards', 'benefits'], 'screenoptions', () => [container([heading(__('Why choose us', 'cresco-canvas')), block('core/group', { className: 'cc-elements-grid', layout: { type: 'grid', columnCount: 3 } }, [featureCard(__('Fast', 'cresco-canvas'), __('Designed for excellent performance.', 'cresco-canvas')), featureCard(__('Flexible', 'cresco-canvas'), __('Compose native WordPress blocks.', 'cresco-canvas')), featureCard(__('Accessible', 'cresco-canvas'), __('Built with inclusive interactions.', 'cresco-canvas'))])])]),
		definition('call-to-action', __('Call to action', 'cresco-canvas'), __('Focused conversion section.', 'cresco-canvas'), 'marketing', ['cta', 'call to action', 'conversion'], 'megaphone', () => [container([heading(__('Ready to begin?', 'cresco-canvas')), text(__('Add a concise reason to take the next step.', 'cresco-canvas')), button(__('Start now', 'cresco-canvas'))])]),
		definition('testimonial', __('Testimonial', 'cresco-canvas'), __('Customer quotation and attribution.', 'cresco-canvas'), 'marketing', ['testimonial', 'review', 'quote'], 'testimonial', () => [container([block('core/quote', { citation: __('Customer name', 'cresco-canvas') }, [text(__('Share a specific customer outcome or experience.', 'cresco-canvas'))])])]),
		definition('pricing-card', __('Pricing card', 'cresco-canvas'), __('Plan name, price, features, and action.', 'cresco-canvas'), 'marketing', ['pricing', 'plan', 'price'], 'money-alt', () => [container([heading(__('Professional', 'cresco-canvas'), 3), heading(__('$49', 'cresco-canvas'), 2), block('core/list', {}, [block('core/list-item', { content: __('First benefit', 'cresco-canvas') }), block('core/list-item', { content: __('Second benefit', 'cresco-canvas') }), block('core/list-item', { content: __('Third benefit', 'cresco-canvas') })]), button(__('Choose plan', 'cresco-canvas'))])]),
		definition('faq', __('FAQ', 'cresco-canvas'), __('Accessible expandable question.', 'cresco-canvas'), 'interactive', ['faq', 'accordion', 'details'], 'editor-help', () => [block('core/details', { summary: __('Frequently asked question', 'cresco-canvas') }, [text(__('Add the answer here.', 'cresco-canvas'))])]),
		definition('social-links', __('Social links', 'cresco-canvas'), __('Accessible social profile links.', 'cresco-canvas'), 'navigation', ['social', 'icons', 'links'], 'share', () => [block('core/social-links')]),
		definition('navigation', __('Navigation', 'cresco-canvas'), __('Native WordPress navigation menu.', 'cresco-canvas'), 'navigation', ['navigation', 'menu', 'header'], 'menu', () => [block('core/navigation')]),
		definition('search', __('Search', 'cresco-canvas'), __('Accessible site search form.', 'cresco-canvas'), 'navigation', ['search', 'find', 'form'], 'search', () => [block('core/search', { buttonUseIcon: true, showLabel: false })]),
		definition('site-logo', __('Site logo', 'cresco-canvas'), __('Logo from WordPress site settings.', 'cresco-canvas'), 'navigation', ['logo', 'brand', 'site'], 'format-image', () => [block('core/site-logo')]),
		definition('post-title', __('Post title', 'cresco-canvas'), __('Dynamic title for the current post.', 'cresco-canvas'), 'blog', ['post title', 'dynamic', 'blog'], 'heading', () => [block('core/post-title')]),
		definition('post-featured-image', __('Featured image', 'cresco-canvas'), __('Dynamic current-post featured image.', 'cresco-canvas'), 'blog', ['featured image', 'dynamic', 'post'], 'format-image', () => [block('core/post-featured-image')]),
		definition('post-excerpt', __('Post excerpt', 'cresco-canvas'), __('Dynamic current-post excerpt.', 'cresco-canvas'), 'blog', ['excerpt', 'dynamic', 'post'], 'excerpt-view', () => [block('core/post-excerpt')]),
		definition('post-date', __('Post date', 'cresco-canvas'), __('Dynamic publication date.', 'cresco-canvas'), 'blog', ['date', 'dynamic', 'post'], 'calendar-alt', () => [block('core/post-date')]),
		definition('latest-posts', __('Latest posts', 'cresco-canvas'), __('Native list of recent posts.', 'cresco-canvas'), 'blog', ['latest posts', 'blog', 'list'], 'admin-post', () => [block('core/latest-posts', { displayPostDate: true })]),
		definition('shortcode', __('Shortcode', 'cresco-canvas'), __('Render a trusted WordPress shortcode.', 'cresco-canvas'), 'utility', ['shortcode', 'plugin', 'integration'], 'shortcode', () => [block('core/shortcode', { text: '[shortcode]' })]),
		definition('html', __('Custom HTML', 'cresco-canvas'), __('Restricted native HTML block.', 'cresco-canvas'), 'utility', ['html', 'code', 'custom'], 'html', () => [block('core/html', { content: '<div>Custom HTML</div>' })]),
	];

	const validElementIds = new Set(elements.map((element) => element.id));

	function sanitizeElementIds(value, limit = Number.POSITIVE_INFINITY) {
		if (!Array.isArray(value) || limit <= 0) {
			return [];
		}
		const result = [];
		const seen = new Set();
		for (const candidate of value) {
			if (typeof candidate !== 'string' || !validElementIds.has(candidate) || seen.has(candidate)) {
				continue;
			}
			seen.add(candidate);
			result.push(candidate);
			if (result.length >= limit) {
				break;
			}
		}
		return result;
	}

	function prependRecentElement(current, id) {
		return sanitizeElementIds([id, ...current], MAX_RECENT);
	}

	function matchesElementQuery(element, query) {
		const normalized = query.trim().toLocaleLowerCase();
		return !normalized || [element.label, element.description, ...element.keywords].join(' ').toLocaleLowerCase().includes(normalized);
	}

	function collectBlockNames(blocks) {
		const names = [];
		for (const current of blocks) {
			names.push(current.name);
			if (current.innerBlocks && current.innerBlocks.length > 0) {
				names.push(...collectBlockNames(current.innerBlocks));
			}
		}
		return names;
	}

	function findUnavailableBlockNames(blocks) {
		return [...new Set(collectBlockNames(blocks).filter((name) => typeof getBlockType !== 'function' || !getBlockType(name)))];
	}

	function readStoredIds(key, limit = Number.POSITIVE_INFINITY) {
		try {
			const stored = window.localStorage.getItem(key);
			return sanitizeElementIds(stored ? JSON.parse(stored) : [], limit);
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
		return ['cresco/container', 'core/group', 'core/cover', 'core/column'].includes(blockName || '');
	}

	function resolveInsertionPoint(selectedClientId, blockEditorSelect) {
		if (selectedClientId && canContainElements(blockEditorSelect.getBlockName(selectedClientId))) {
			return { index: blockEditorSelect.getBlockOrder(selectedClientId).length, rootClientId: selectedClientId };
		}
		if (selectedClientId) {
			return { index: Math.max(0, blockEditorSelect.getBlockIndex(selectedClientId) + 1), rootClientId: blockEditorSelect.getBlockRootClientId(selectedClientId) || undefined };
		}
		return { index: blockEditorSelect.getBlockOrder().length };
	}

	function ElementsLibrary({ onElementInserted }) {
		const [query, setQuery] = useState('');
		const [filter, setFilter] = useState('all');
		const [favorites, setFavorites] = useState(() => readStoredIds(FAVORITES_KEY));
		const [recent, setRecent] = useState(() => readStoredIds(RECENT_KEY, MAX_RECENT));
		const [libraryNotice, setLibraryNotice] = useState(null);
		const selectedClientId = useSelect((select) => select('core/block-editor').getSelectedBlockClientId(), []);
		const blockEditorSelect = useSelect((select) => select('core/block-editor'), []);
		const { insertBlocks, selectBlock } = useDispatch('core/block-editor');

		const insertDefinition = useCallback((definitionToInsert, targetClientId) => {
			setLibraryNotice(null);
			try {
				const blocks = definitionToInsert.create();
				if (!blocks.length) {
					throw new Error('Element factory returned no blocks.');
				}
				const unavailableBlockNames = findUnavailableBlockNames(blocks);
				if (unavailableBlockNames.length > 0) {
					setLibraryNotice({ message: sprintf(__('%1$s cannot be added because these WordPress blocks are unavailable: %2$s.', 'cresco-canvas'), definitionToInsert.label, unavailableBlockNames.join(', ')), status: 'error' });
					return;
				}
				const insertionPoint = resolveInsertionPoint(targetClientId ?? selectedClientId, blockEditorSelect);
				const blockedRootNames = typeof blockEditorSelect.canInsertBlockType === 'function' ? [...new Set(blocks.map((current) => current.name).filter((blockName) => !blockEditorSelect.canInsertBlockType(blockName, insertionPoint.rootClientId)))] : [];
				if (blockedRootNames.length > 0) {
					setLibraryNotice({ message: sprintf(__('%1$s cannot be inserted at the selected location. Restricted blocks: %2$s.', 'cresco-canvas'), definitionToInsert.label, blockedRootNames.join(', ')), status: 'warning' });
					return;
				}
				insertBlocks(blocks, insertionPoint.index, insertionPoint.rootClientId);
				if (blocks[0]) {
					selectBlock(blocks[0].clientId);
				}
				setRecent((current) => {
					const nextRecent = prependRecentElement(current, definitionToInsert.id);
					writeStoredIds(RECENT_KEY, nextRecent);
					return nextRecent;
				});
				setLibraryNotice({ message: sprintf(__('%s was added to the page.', 'cresco-canvas'), definitionToInsert.label), status: 'success' });
				if (onElementInserted) {
					onElementInserted();
				}
			} catch (error) {
				setLibraryNotice({ message: sprintf(__('%s could not be added. Reload the editor and try again.', 'cresco-canvas'), definitionToInsert.label), status: 'error' });
			}
		}, [blockEditorSelect, insertBlocks, onElementInserted, selectBlock, selectedClientId]);

		const insertById = useCallback((id, targetClientId) => {
			const found = elements.find((element) => element.id === id);
			if (!found) {
				setLibraryNotice({ message: __('This dragged element is no longer available.', 'cresco-canvas'), status: 'error' });
				return;
			}
			insertDefinition(found, targetClientId);
		}, [insertDefinition]);

		useEffect(() => {
			const documents = new Set();
			const iframeListeners = new Map();
			const onDragOver = (event) => {
				if (event.dataTransfer && Array.from(event.dataTransfer.types).includes(DRAG_MIME)) {
					event.preventDefault();
					event.dataTransfer.dropEffect = 'copy';
				}
			};
			const onDrop = (event) => {
				const id = event.dataTransfer ? event.dataTransfer.getData(DRAG_MIME) : '';
				if (!id) {
					return;
				}
				event.preventDefault();
				const target = event.target;
				const blockTarget = target && typeof target.closest === 'function' ? target.closest('[data-block]') : null;
				insertById(id, blockTarget ? blockTarget.dataset.block : null);
			};
			const attachDocument = (targetDocument) => {
				if (!targetDocument || documents.has(targetDocument)) {
					return;
				}
				documents.add(targetDocument);
				targetDocument.addEventListener('dragover', onDragOver);
				targetDocument.addEventListener('drop', onDrop);
			};
			const attachIframe = (iframe) => {
				if (iframeListeners.has(iframe)) {
					return;
				}
				const onLoad = () => attachDocument(iframe.contentDocument);
				iframeListeners.set(iframe, onLoad);
				iframe.addEventListener('load', onLoad);
				onLoad();
			};
			const scanForCanvas = () => document.querySelectorAll('iframe[name="editor-canvas"]').forEach(attachIframe);
			attachDocument(document);
			scanForCanvas();
			const observer = new MutationObserver(scanForCanvas);
			observer.observe(document.documentElement, { childList: true, subtree: true });
			return () => {
				observer.disconnect();
				for (const [iframe, onLoad] of iframeListeners) {
					iframe.removeEventListener('load', onLoad);
				}
				for (const targetDocument of documents) {
					targetDocument.removeEventListener('dragover', onDragOver);
					targetDocument.removeEventListener('drop', onDrop);
				}
			};
		}, [insertById]);

		const visibleElements = useMemo(() => {
			const matches = elements.filter((element) => {
				if (filter === 'favorites' && !favorites.includes(element.id)) {
					return false;
				}
				if (filter === 'recent' && !recent.includes(element.id)) {
					return false;
				}
				if (!['all', 'favorites', 'recent'].includes(filter) && element.category !== filter) {
					return false;
				}
				return matchesElementQuery(element, query);
			});
			return filter === 'recent' ? matches.sort((first, second) => recent.indexOf(first.id) - recent.indexOf(second.id)) : matches;
		}, [favorites, filter, query, recent]);

		function toggleFavorite(id) {
			setFavorites((current) => {
				const sanitized = sanitizeElementIds(current.includes(id) ? current.filter((favoriteId) => favoriteId !== id) : [...current, id]);
				writeStoredIds(FAVORITES_KEY, sanitized);
				return sanitized;
			});
		}

		return h('div', { className: 'cc-elements-library' },
			h('div', { className: 'cc-elements-library__intro' }, h('strong', null, __('Cresco Elements', 'cresco-canvas')), h('p', null, __('Click an element to insert it, or drag it onto the editor canvas.', 'cresco-canvas'))),
			h(SearchControl, { label: __('Search elements', 'cresco-canvas'), onChange: setQuery, placeholder: __('Search elements…', 'cresco-canvas'), value: query }),
			h('div', { 'aria-label': __('Element categories', 'cresco-canvas'), className: 'cc-elements-filters', role: 'group' }, [['all', __('All', 'cresco-canvas')], ['favorites', __('Favorites', 'cresco-canvas')], ['recent', __('Recent', 'cresco-canvas')], ...Object.entries(categoryLabels)].map(([id, label]) => h(Button, { isPressed: filter === id, key: id, onClick: () => setFilter(id), variant: 'tertiary' }, label))),
			libraryNotice ? h(Notice, { isDismissible: true, onRemove: () => setLibraryNotice(null), status: libraryNotice.status }, libraryNotice.message) : null,
			h('div', { className: 'cc-elements-grid-list' }, visibleElements.map((element) => {
				const isFavorite = favorites.includes(element.id);
				return h('div', { className: 'cc-element-card', draggable: true, key: element.id, onDragStart: (event) => { event.dataTransfer.effectAllowed = 'copy'; event.dataTransfer.setData(DRAG_MIME, element.id); } }, h(Button, { className: 'cc-element-card__insert', icon: element.icon, onClick: () => insertDefinition(element), showTooltip: true, text: element.description }, h('span', null, element.label)), h(Button, { 'aria-label': isFavorite ? sprintf(__('Remove %s from favorites', 'cresco-canvas'), element.label) : sprintf(__('Add %s to favorites', 'cresco-canvas'), element.label), className: 'cc-element-card__favorite', icon: isFavorite ? 'star-filled' : 'star-empty', isPressed: isFavorite, onClick: () => toggleFavorite(element.id), size: 'small' }));
			})),
			visibleElements.length === 0 ? h('p', { className: 'cc-elements-empty' }, __('No matching elements were found.', 'cresco-canvas')) : null
		);
	}

	function ColorField({ field, label, onChange, settings }) {
		const id = `cc-color-${field}`;
		return h('label', { className: 'cc-color-field', htmlFor: id }, h('span', null, label), h('input', { id, type: 'color', value: settings[field], onChange: (event) => onChange({ ...settings, [field]: event.target.value }) }));
	}

	function GlobalSettingsPanel({ onChange, onSave, saving, settings }) {
		return h(Fragment, null,
			h(PanelBody, { initialOpen: false, title: __('Global design', 'cresco-canvas') }, h('div', { className: 'cc-settings-grid' },
				h(ColorField, { field: 'primary', label: __('Primary color', 'cresco-canvas'), onChange, settings }),
				h(ColorField, { field: 'text', label: __('Text color', 'cresco-canvas'), onChange, settings }),
				h(ColorField, { field: 'background', label: __('Page background', 'cresco-canvas'), onChange, settings }),
				h(TextControl, { label: __('Global radius', 'cresco-canvas'), max: 80, min: 0, onChange: (value) => onChange({ ...settings, radius: Number(value) }), type: 'number', value: settings.radius }),
				h(TextControl, { label: __('Font family stack', 'cresco-canvas'), onChange: (value) => onChange({ ...settings, fontFamily: value }), value: settings.fontFamily }),
				h(Button, { disabled: saving, isBusy: saving, onClick: onSave, variant: 'primary' }, saving ? __('Saving…', 'cresco-canvas') : __('Save global design', 'cresco-canvas'))
			)),
			h(PanelBody, { initialOpen: false, title: __('Data and uninstall', 'cresco-canvas') }, h(ToggleControl, { checked: settings.removeDataOnUninstall, help: __('Page content is never deleted. This removes only Cresco settings and metadata during uninstall.', 'cresco-canvas'), label: __('Remove plugin data on uninstall', 'cresco-canvas'), onChange: (enabled) => onChange({ ...settings, removeDataOnUninstall: enabled }) }))
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
		const hasCanvasBlock = useSelect((select) => containsCrescoBlock(select('core/block-editor').getBlocks()), []);
		const pageUsesCanvas = Boolean(pageMeta[ENABLED_META]) || hasCanvasBlock;

		const enablePageStyles = useCallback(() => {
			if (!pageMeta[ENABLED_META]) {
				editPost({ meta: { ...pageMeta, [ENABLED_META]: true } });
			}
		}, [editPost, pageMeta]);

		const loadSettings = useCallback(async () => {
			if (!bootstrap.canManageSettings) {
				return;
			}
			setLoading(true);
			setNotice('');
			try {
				setGlobalSettings(await apiFetch({ path: `${bootstrap.restPath}settings` }));
			} catch (error) {
				const normalized = normalizeApiError(error);
				setNotice(normalized.message || __('Global design settings could not be loaded.', 'cresco-canvas'));
				setNoticeStatus('error');
			} finally {
				setLoading(false);
			}
		}, [bootstrap.canManageSettings, bootstrap.restPath]);

		useEffect(() => {
			loadSettings();
		}, [loadSettings]);

		useEffect(() => {
			const iframeListeners = new Map();
			const updateDocument = (targetDocument) => {
				if (!targetDocument) {
					return;
				}
				const wrapper = targetDocument.querySelector('.editor-styles-wrapper');
				if (!wrapper) {
					return;
				}
				wrapper.classList.toggle('cresco-canvas-editor-scope', pageUsesCanvas);
				if (globalSettings) {
					const tokens = { '--cc-background': globalSettings.background, '--cc-container-max': `${globalSettings.containerMax}px`, '--cc-content-max': `${globalSettings.contentMax}px`, '--cc-font': globalSettings.fontFamily, '--cc-muted': globalSettings.muted, '--cc-primary': globalSettings.primary, '--cc-radius': `${globalSettings.radius}px`, '--cc-text': globalSettings.text };
					for (const [property, value] of Object.entries(tokens)) {
						wrapper.style.setProperty(property, value);
					}
				}
			};
			const attachIframe = (iframe) => {
				if (iframeListeners.has(iframe)) {
					return;
				}
				const onLoad = () => updateDocument(iframe.contentDocument);
				iframeListeners.set(iframe, onLoad);
				iframe.addEventListener('load', onLoad);
				onLoad();
			};
			const scan = () => {
				updateDocument(document);
				document.querySelectorAll('iframe[name="editor-canvas"]').forEach(attachIframe);
			};
			scan();
			const observer = new MutationObserver(scan);
			observer.observe(document.documentElement, { childList: true, subtree: true });
			return () => {
				observer.disconnect();
				for (const [iframe, onLoad] of iframeListeners) {
					iframe.removeEventListener('load', onLoad);
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
				const result = await apiFetch({ data: globalSettings, method: 'POST', path: `${bootstrap.restPath}settings` });
				setGlobalSettings(result);
				setNotice(__('Global design saved.', 'cresco-canvas'));
				setNoticeStatus('success');
			} catch (error) {
				const normalized = normalizeApiError(error);
				setNotice(normalized.message || __('Global design could not be saved.', 'cresco-canvas'));
				setNoticeStatus('error');
			} finally {
				setSaving(false);
			}
		}

		return h(Fragment, null,
			h(PluginSidebarMoreMenuItem, { target: 'cresco-canvas-settings' }, __('Cresco Canvas', 'cresco-canvas')),
			h(PluginSidebar, { className: 'cresco-canvas-sidebar', icon: 'layout', name: 'cresco-canvas-settings', title: __('Cresco Canvas', 'cresco-canvas') },
				notice ? h(Notice, { isDismissible: true, onRemove: () => setNotice(''), status: noticeStatus }, notice) : null,
				h(PanelBody, { className: 'cc-elements-panel', initialOpen: true, title: __('Elements', 'cresco-canvas') }, h(ElementsLibrary, { onElementInserted: enablePageStyles })),
				h(PanelBody, { initialOpen: false, title: __('Page styling', 'cresco-canvas') }, h(ToggleControl, { checked: Boolean(pageMeta[ENABLED_META]), help: __('Applies Cresco global colors, typography, and spacing tokens on this Page. Pages containing a Cresco block are detected automatically.', 'cresco-canvas'), label: __('Enable Cresco page styles', 'cresco-canvas'), onChange: (enabled) => editPost({ meta: { ...pageMeta, [ENABLED_META]: enabled } }) }), h('p', { className: 'cc-native-note' }, __('This setting is saved by the normal Gutenberg Save or Update button.', 'cresco-canvas'))),
				bootstrap.canManageSettings && loading ? h('div', { 'aria-label': __('Loading global design', 'cresco-canvas'), className: 'cc-sidebar-loading' }, h(Spinner)) : null,
				bootstrap.canManageSettings && globalSettings ? h(GlobalSettingsPanel, { onChange: setGlobalSettings, onSave: saveGlobalSettings, saving, settings: globalSettings }) : null,
				bootstrap.canManageSettings && !loading && !globalSettings ? h('div', { className: 'cc-sidebar-retry' }, h(Button, { onClick: loadSettings, variant: 'secondary' }, __('Retry loading settings', 'cresco-canvas'))) : null
			)
		);
	}

	registerPlugin('cresco-canvas', {
		icon: 'layout',
		render: SettingsSidebar,
	});
})();
