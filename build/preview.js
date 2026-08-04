(function (wp, bootstrap) {
	'use strict';

	if (!wp || !bootstrap) {
		return;
	}

	var Button = wp.components.Button;
	var Modal = wp.components.Modal;
	var Notice = wp.components.Notice;
	var PanelBody = wp.components.PanelBody;
	var useSelect = wp.data.useSelect;
	var PluginSidebar = wp.editor.PluginSidebar;
	var PluginSidebarMoreMenuItem = wp.editor.PluginSidebarMoreMenuItem;
	var Fragment = wp.element.Fragment;
	var createElement = wp.element.createElement;
	var useEffect = wp.element.useEffect;
	var useMemo = wp.element.useMemo;
	var useRef = wp.element.useRef;
	var useState = wp.element.useState;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var registerPlugin = wp.plugins.registerPlugin;

	var EVENT_NAME = 'cresco-canvas-preview-device-change';
	var STORAGE_KEY = 'crescoCanvas.previewDevice';
	var devices = [
		{ id: '4k', width: 1920 },
		{ id: 'desktop', width: 1440 },
		{ id: 'laptop', width: 1024 },
		{ id: 'tablet', width: 768 },
		{ id: 'mobile', width: 390 },
	];
	var labels = {
		'4k': __('4K', 'cresco-canvas'),
		desktop: __('Desktop', 'cresco-canvas'),
		laptop: __('Laptop', 'cresco-canvas'),
		tablet: __('Tablet', 'cresco-canvas'),
		mobile: __('Mobile', 'cresco-canvas'),
	};

	function isDevice(value) {
		return devices.some(function (device) {
			return device.id === value;
		});
	}

	function readDevice() {
		try {
			var value = window.localStorage.getItem(STORAGE_KEY);
			return isDevice(value) ? value : 'desktop';
		} catch (error) {
			return 'desktop';
		}
	}

	function persistDevice(device) {
		try {
			window.localStorage.setItem(STORAGE_KEY, device);
		} catch (error) {
			// Storage can be unavailable in hardened browser contexts.
		}
	}

	function deviceWidth(device) {
		var match = devices.find(function (candidate) {
			return candidate.id === device;
		});
		return match ? match.width : 1440;
	}

	function applyDevice(targetDocument, device) {
		if (targetDocument) {
			targetDocument.documentElement.dataset.ccPreviewDevice = device;
		}
	}

	function appendRefreshToken(url, refreshKey) {
		if (!url) {
			return '';
		}
		try {
			var parsed = new URL(url, window.location.href);
			parsed.searchParams.set('cresco_canvas_refresh', String(refreshKey));
			return parsed.toString();
		} catch (error) {
			return url;
		}
	}

	function PreviewSidebar() {
		var deviceState = useState(readDevice);
		var device = deviceState[0];
		var setDevice = deviceState[1];
		var openState = useState(false);
		var previewOpen = openState[0];
		var setPreviewOpen = openState[1];
		var refreshState = useState(0);
		var refreshKey = refreshState[0];
		var setRefreshKey = refreshState[1];
		var wasSaving = useRef(false);
		var editorState = useSelect(function (select) {
			var editor = select('core/editor');
			return {
				previewUrl:
					(typeof editor.getEditedPostPreviewLink === 'function' &&
						editor.getEditedPostPreviewLink()) ||
					bootstrap.previewUrl,
				saving: Boolean(
					(typeof editor.isSavingPost === 'function' && editor.isSavingPost()) ||
						(typeof editor.isAutosavingPost === 'function' &&
							editor.isAutosavingPost())
				),
			};
		}, [bootstrap.previewUrl]);
		var previewSrc = useMemo(
			function () {
				return appendRefreshToken(editorState.previewUrl, refreshKey);
			},
			[editorState.previewUrl, refreshKey]
		);

		useEffect(
			function () {
				persistDevice(device);
				window.dispatchEvent(
					new CustomEvent(EVENT_NAME, { detail: { device: device } })
				);

				var iframeListeners = new Map();
				function attachIframe(iframe) {
					if (iframeListeners.has(iframe)) {
						return;
					}
					var onLoad = function () {
						applyDevice(iframe.contentDocument, device);
					};
					iframeListeners.set(iframe, onLoad);
					iframe.addEventListener('load', onLoad);
					onLoad();
				}
				function scan() {
					applyDevice(document, device);
					document
						.querySelectorAll('iframe[name="editor-canvas"]')
						.forEach(attachIframe);
				}

				scan();
				var observer = new MutationObserver(scan);
				observer.observe(document.documentElement, {
					childList: true,
					subtree: true,
				});

				return function () {
					observer.disconnect();
					iframeListeners.forEach(function (onLoad, iframe) {
						iframe.removeEventListener('load', onLoad);
					});
				};
			},
			[device]
		);

		useEffect(
			function () {
				if (wasSaving.current && !editorState.saving && previewOpen) {
					setRefreshKey(function (value) {
						return value + 1;
					});
				}
				wasSaving.current = editorState.saving;
			},
			[editorState.saving, previewOpen]
		);

		return createElement(
			Fragment,
			null,
			createElement(
				PluginSidebarMoreMenuItem,
				{ target: 'cresco-canvas-preview' },
				__('Cresco Preview', 'cresco-canvas')
			),
			createElement(
				PluginSidebar,
				{
					className: 'cresco-canvas-preview-sidebar',
					icon: 'visibility',
					name: 'cresco-canvas-preview',
					title: __('Cresco Preview', 'cresco-canvas'),
				},
				createElement(
					PanelBody,
					{
						initialOpen: true,
						title: __('Responsive viewport', 'cresco-canvas'),
					},
					createElement(
						'div',
						{
							'aria-label': __('Preview device', 'cresco-canvas'),
							className: 'cc-preview-device-grid',
							role: 'group',
						},
						devices.map(function (candidate) {
							return createElement(
								Button,
								{
									'aria-pressed': device === candidate.id,
									key: candidate.id,
									onClick: function () {
										setDevice(candidate.id);
									},
									variant:
										device === candidate.id ? 'primary' : 'secondary',
								},
								labels[candidate.id]
							);
						})
					),
					createElement(
						'p',
						{ className: 'cc-preview-note' },
						sprintf(
							__('%1$s uses a %2$dpx logical viewport.', 'cresco-canvas'),
							labels[device],
							deviceWidth(device)
						)
					)
				),
				createElement(
					PanelBody,
					{
						initialOpen: true,
						title: __('Live frontend preview', 'cresco-canvas'),
					},
					editorState.previewUrl
						? createElement(
							Fragment,
							null,
							createElement(
								'p',
								{ className: 'cc-preview-note' },
								__(
									'The iframe shows WordPress frontend output and refreshes after a save or autosave finishes.',
									'cresco-canvas'
								)
							),
							createElement(
								'div',
								{ className: 'cc-preview-actions' },
								createElement(
									Button,
									{
										onClick: function () {
											setPreviewOpen(true);
										},
										variant: 'primary',
									},
									__('Open frontend preview', 'cresco-canvas')
								),
								createElement(
									Button,
									{
										href: editorState.previewUrl,
										target: '_blank',
										variant: 'secondary',
									},
									__('Open in new tab', 'cresco-canvas')
								)
							)
						)
						: createElement(
							Notice,
							{ isDismissible: false, status: 'warning' },
							__(
								'A frontend preview URL is not available for this Page yet.',
								'cresco-canvas'
							)
						)
				)
			),
			previewOpen && previewSrc
				? createElement(
					Modal,
					{
						className: 'cc-frontend-preview-modal',
						onRequestClose: function () {
							setPreviewOpen(false);
						},
						title: __('Live frontend preview', 'cresco-canvas'),
					},
					createElement(
						'div',
						{ className: 'cc-frontend-preview-toolbar' },
						createElement(
							'span',
							{ 'aria-live': 'polite' },
							editorState.saving
								? __('Waiting for WordPress to finish saving…', 'cresco-canvas')
								: __('Showing the latest saved preview.', 'cresco-canvas')
						),
						createElement(
							Button,
							{
								onClick: function () {
									setRefreshKey(function (value) {
										return value + 1;
									});
								},
								variant: 'secondary',
							},
							__('Refresh', 'cresco-canvas')
						)
					),
					createElement(
						'div',
						{ className: 'cc-frontend-preview-stage' },
						createElement('iframe', {
							className: 'cc-frontend-preview-frame',
							key: device + '-' + refreshKey,
							src: previewSrc,
							style: { inlineSize: deviceWidth(device) },
							title: __('Cresco frontend Page preview', 'cresco-canvas'),
						})
					)
				)
				: null
		);
	}

	registerPlugin('cresco-canvas-preview', {
		icon: 'visibility',
		render: PreviewSidebar,
	});
})(window.wp, window.crescoCanvasPreviewSettings);
