(function (wp) {
	'use strict';
	if (!wp || !wp.plugins || !wp.editor || !wp.element || !wp.components || !wp.apiFetch || !wp.blocks || !wp.data) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useEffect = wp.element.useEffect;
	var useMemo = wp.element.useMemo;
	var useState = wp.element.useState;
	var __ = wp.i18n.__;
	var PluginSidebar = wp.editor.PluginSidebar;
	var PluginSidebarMoreMenuItem = wp.editor.PluginSidebarMoreMenuItem;
	var Button = wp.components.Button;
	var Notice = wp.components.Notice;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var Spinner = wp.components.Spinner;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;

	function request(path, options) {
		return wp.apiFetch(Object.assign({ path: '/cresco-canvas/v1/' + path }, options || {}));
	}

	function TemplatesSidebar() {
		var _useState = useState(null), catalog = _useState[0], setCatalog = _useState[1];
		var _useState2 = useState([]), components = _useState2[0], setComponents = _useState2[1];
		var _useState3 = useState(''), search = _useState3[0], setSearch = _useState3[1];
		var _useState4 = useState('all'), category = _useState4[0], setCategory = _useState4[1];
		var _useState5 = useState(''), notice = _useState5[0], setNotice = _useState5[1];
		var _useState6 = useState('success'), noticeStatus = _useState6[0], setNoticeStatus = _useState6[1];
		var _useState7 = useState(true), loading = _useState7[0], setLoading = _useState7[1];
		var _useState8 = useState(''), componentTitle = _useState8[0], setComponentTitle = _useState8[1];
		var _useState9 = useState(''), siteKitJson = _useState9[0], setSiteKitJson = _useState9[1];

		function show(message, status) {
			setNotice(message);
			setNoticeStatus(status || 'success');
		}

		function load() {
			setLoading(true);
			Promise.all([request('templates/catalog'), request('components')])
				.then(function (results) {
					setCatalog(results[0]);
					setComponents(results[1] || []);
				})
				.catch(function (error) {
					show(error && error.message ? error.message : __('Template data could not be loaded.', 'cresco-canvas'), 'error');
				})
				.finally(function () { setLoading(false); });
		}

		useEffect(load, []);

		var templates = useMemo(function () {
			if (!catalog || !Array.isArray(catalog.templates)) {
				return [];
			}
			var term = search.trim().toLowerCase();
			return catalog.templates.filter(function (item) {
				var categoryMatch = category === 'all' || item.category === category;
				var haystack = [item.title, item.description].concat(item.keywords || []).join(' ').toLowerCase();
				return categoryMatch && (!term || haystack.indexOf(term) !== -1);
			});
		}, [catalog, search, category]);

		function insertTemplate(item) {
			try {
				var blocks = wp.blocks.parse(item.content || '');
				if (!blocks.length) {
					throw new Error(__('This template contains no insertable blocks.', 'cresco-canvas'));
				}
				wp.data.dispatch('core/block-editor').insertBlocks(blocks);
				show(__('Template inserted.', 'cresco-canvas'));
			} catch (error) {
				show(error && error.message ? error.message : __('Template insertion failed.', 'cresco-canvas'), 'error');
			}
		}

		function saveSelectedComponent() {
			var selected = wp.data.select('core/block-editor').getSelectedBlock();
			if (!selected) {
				show(__('Select one block before saving a component.', 'cresco-canvas'), 'warning');
				return;
			}
			var title = componentTitle.trim();
			if (!title) {
				show(__('Enter a component title.', 'cresco-canvas'), 'warning');
				return;
			}
			var content = wp.blocks.serialize([selected]);
			request('components', { method: 'POST', data: { title: title, content: content } })
				.then(function () {
					setComponentTitle('');
					show(__('Synced component created.', 'cresco-canvas'));
					return request('components');
				})
				.then(setComponents)
				.catch(function (error) {
					show(error && error.message ? error.message : __('Component creation failed.', 'cresco-canvas'), 'error');
				});
		}

		function insertComponent(component) {
			try {
				wp.data.dispatch('core/block-editor').insertBlocks(wp.blocks.createBlock('core/block', { ref: component.id }));
				show(__('Synced component inserted.', 'cresco-canvas'));
			} catch (error) {
				show(__('Component insertion failed.', 'cresco-canvas'), 'error');
			}
		}

		function exportSiteKit() {
			request('site-kit').then(function (kit) {
				setSiteKitJson(JSON.stringify(kit, null, 2));
				show(__('Site Kit exported.', 'cresco-canvas'));
			}).catch(function (error) {
				show(error && error.message ? error.message : __('Site Kit export failed.', 'cresco-canvas'), 'error');
			});
		}

		function importSiteKit() {
			try {
				var parsed = JSON.parse(siteKitJson);
				request('site-kit', { method: 'POST', data: parsed }).then(function () {
					show(__('Site Kit imported. Reload the editor to refresh global design values.', 'cresco-canvas'));
				}).catch(function (error) {
					show(error && error.message ? error.message : __('Site Kit import failed.', 'cresco-canvas'), 'error');
				});
			} catch (error) {
				show(__('The Site Kit JSON is invalid.', 'cresco-canvas'), 'error');
			}
		}

		var categoryOptions = [{ label: __('All categories', 'cresco-canvas'), value: 'all' }];
		if (catalog && catalog.categories) {
			Object.keys(catalog.categories).forEach(function (key) {
				categoryOptions.push({ label: catalog.categories[key], value: key });
			});
		}

		return el(Fragment, {},
			el(PluginSidebarMoreMenuItem, { target: 'cresco-canvas-templates' }, __('Cresco Templates', 'cresco-canvas')),
			el(PluginSidebar, { name: 'cresco-canvas-templates', title: __('Cresco Templates', 'cresco-canvas'), icon: 'layout', className: 'cresco-templates-sidebar' },
				notice ? el(Notice, { status: noticeStatus, isDismissible: true, onRemove: function () { setNotice(''); } }, notice) : null,
				loading ? el('div', { className: 'cc-template-loading' }, el(Spinner)) : null,
				el(PanelBody, { title: __('Template Library', 'cresco-canvas'), initialOpen: true },
					el(TextControl, { label: __('Search templates', 'cresco-canvas'), value: search, onChange: setSearch }),
					el(SelectControl, { label: __('Category', 'cresco-canvas'), value: category, options: categoryOptions, onChange: setCategory }),
					el('div', { className: 'cc-template-grid' }, templates.map(function (item) {
						return el('article', { className: 'cc-template-card', key: item.id },
							el('strong', {}, item.title),
							el('p', {}, item.description),
							el(Button, { variant: 'primary', onClick: function () { insertTemplate(item); } }, __('Insert', 'cresco-canvas'))
						);
					}))
				),
				el(PanelBody, { title: __('Synced Components', 'cresco-canvas'), initialOpen: false },
					el(TextControl, { label: __('Component title', 'cresco-canvas'), value: componentTitle, onChange: setComponentTitle }),
					el(Button, { variant: 'secondary', onClick: saveSelectedComponent }, __('Save selected block', 'cresco-canvas')),
					el('div', { className: 'cc-component-list' }, components.map(function (component) {
						return el('div', { className: 'cc-component-row', key: component.id },
							el('span', {}, component.title || ('#' + component.id)),
							el(Button, { variant: 'tertiary', onClick: function () { insertComponent(component); } }, __('Insert', 'cresco-canvas'))
						);
					}))
				),
				el(PanelBody, { title: __('Site Kits', 'cresco-canvas'), initialOpen: false },
					el('p', { className: 'cc-template-note' }, __('Site Kits contain sanitized Cresco design settings and references to bundled templates. They never import PHP or JavaScript.', 'cresco-canvas')),
					el(Button, { variant: 'secondary', onClick: exportSiteKit }, __('Export Site Kit', 'cresco-canvas')),
					el(TextareaControl, { label: __('Site Kit JSON', 'cresco-canvas'), rows: 12, value: siteKitJson, onChange: setSiteKitJson }),
					el(Button, { variant: 'primary', disabled: !siteKitJson.trim(), onClick: importSiteKit }, __('Import Site Kit', 'cresco-canvas'))
				)
			)
		);
	}

	wp.plugins.registerPlugin('cresco-canvas-templates', { icon: 'layout', render: TemplatesSidebar });
})(window.wp);
