(function (wp) {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var InnerBlocks = wp.blockEditor.InnerBlocks;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var Button = wp.components.Button;
	var ColorPalette = wp.components.ColorPalette;
	var PanelBody = wp.components.PanelBody;
	var RangeControl = wp.components.RangeControl;
	var SelectControl = wp.components.SelectControl;
	var Fragment = wp.element.Fragment;
	var createElement = wp.element.createElement;
	var useEffect = wp.element.useEffect;
	var useState = wp.element.useState;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;

	var EVENT_NAME = 'cresco-canvas-preview-device-change';
	var STORAGE_KEY = 'crescoCanvas.previewDevice';
	var devices = ['4k', 'desktop', 'laptop', 'tablet', 'mobile'];
	var labels = {
		'4k': __('4K', 'cresco-canvas'),
		desktop: __('Desktop', 'cresco-canvas'),
		laptop: __('Laptop', 'cresco-canvas'),
		tablet: __('Tablet', 'cresco-canvas'),
		mobile: __('Mobile', 'cresco-canvas'),
	};
	var metadata = {
		$schema: 'https://schemas.wp.org/trunk/block.json',
		apiVersion: 3,
		name: 'cresco/container',
		version: '0.4.0-alpha.1',
		title: 'Cresco Container',
		category: 'cresco-canvas',
		icon: 'layout',
		description: 'Flexible native layout container for Cresco Canvas.',
		keywords: ['cresco', 'container', 'layout', 'section', 'flex', 'grid'],
		textdomain: 'cresco-canvas',
		attributes: {
			layoutMode: { type: 'string', default: 'flex' },
			direction: { type: 'string', default: 'column' },
			justify: { type: 'string', default: 'flex-start' },
			align: { type: 'string', default: 'stretch' },
			gap: { type: 'number', default: 24 },
			paddingTop: { type: 'number', default: 40 },
			paddingRight: { type: 'number', default: 24 },
			paddingBottom: { type: 'number', default: 40 },
			paddingLeft: { type: 'number', default: 24 },
			maxWidth: { type: 'number', default: 1200 },
			background: { type: 'string', default: '' },
			columns: { type: 'number' },
			wrap: { type: 'string' },
			responsive: { type: 'object' },
		},
		supports: {
			align: ['wide', 'full'],
			anchor: true,
			html: false,
			className: true,
			spacing: { margin: true },
			color: { text: true, link: true },
			typography: { fontSize: true },
		},
		style: 'file:./style.css',
		editorStyle: 'file:./editor.css',
	};
	var defaults = {
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
	var allowed = {
		align: ['center', 'flex-end', 'flex-start', 'stretch'],
		direction: ['column', 'row'],
		justify: ['center', 'flex-end', 'flex-start', 'space-between'],
		layoutMode: ['block', 'flex', 'grid'],
		wrap: ['nowrap', 'wrap'],
	};
	var sides = [
		{ key: 'paddingTop', label: __('Padding top', 'cresco-canvas') },
		{ key: 'paddingRight', label: __('Padding right', 'cresco-canvas') },
		{ key: 'paddingBottom', label: __('Padding bottom', 'cresco-canvas') },
		{ key: 'paddingLeft', label: __('Padding left', 'cresco-canvas') },
	];

	function enumValue(value, values, fallback) {
		return values.indexOf(value) !== -1 ? value : fallback;
	}

	function numberValue(value, minimum, maximum, fallback) {
		if (typeof value !== 'number' || !Number.isFinite(value)) {
			return fallback;
		}
		return Math.min(maximum, Math.max(minimum, Math.round(value)));
	}

	function normalize(value, fallback) {
		fallback = fallback || defaults;
		return {
			align: enumValue(value.align, allowed.align, fallback.align),
			columns: numberValue(value.columns, 1, 12, fallback.columns),
			direction: enumValue(value.direction, allowed.direction, fallback.direction),
			gap: numberValue(value.gap, 0, 240, fallback.gap),
			justify: enumValue(value.justify, allowed.justify, fallback.justify),
			layoutMode: enumValue(
				value.layoutMode,
				allowed.layoutMode,
				fallback.layoutMode
			),
			maxWidth: numberValue(value.maxWidth, 320, 3840, fallback.maxWidth),
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
			wrap: enumValue(value.wrap, allowed.wrap, fallback.wrap),
		};
	}

	function merge(base, override) {
		return override ? normalize(Object.assign({}, base, override), base) : base;
	}

	function resolve(attributes, device) {
		var desktop = normalize(attributes);
		var responsive = attributes.responsive || {};
		if (device === 'desktop') {
			return desktop;
		}
		if (device === '4k') {
			return merge(desktop, responsive['4k']);
		}
		var laptop = merge(desktop, responsive.laptop);
		if (device === 'laptop') {
			return laptop;
		}
		var tablet = merge(laptop, responsive.tablet);
		if (device === 'tablet') {
			return tablet;
		}
		return merge(tablet, responsive.mobile);
	}

	function hasEnhancements(attributes) {
		return (
			typeof attributes.columns === 'number' ||
			typeof attributes.wrap === 'string' ||
			Boolean(
				attributes.responsive &&
					Object.keys(attributes.responsive).some(function (device) {
						var override = attributes.responsive[device];
						return override && Object.keys(override).length > 0;
					})
			)
		);
	}

	function safeColor(value) {
		var color = String(value || '').trim();
		if (!color) {
			return undefined;
		}
		if (
			/^#[0-9a-f]{3,8}$/i.test(color) ||
			/^(?:rgb|hsl)a?\([0-9.,%+\-\s/]+\)$/i.test(color) ||
			/^var\(--[a-z0-9_-]+\)$/i.test(color) ||
			/^(?:currentcolor|transparent)$/i.test(color)
		) {
			return color;
		}
		return undefined;
	}

	function legacyStyle(attributes) {
		return {
			alignItems: attributes.layoutMode === 'block' ? undefined : attributes.align,
			backgroundColor: safeColor(attributes.background),
			display: attributes.layoutMode,
			flexDirection:
				attributes.layoutMode === 'flex' ? attributes.direction : undefined,
			gap: attributes.layoutMode === 'block' ? undefined : attributes.gap + 'px',
			justifyContent:
				attributes.layoutMode === 'block' ? undefined : attributes.justify,
			marginLeft: 'auto',
			marginRight: 'auto',
			maxWidth: attributes.maxWidth + 'px',
			padding:
				attributes.paddingTop +
				'px ' +
				attributes.paddingRight +
				'px ' +
				attributes.paddingBottom +
				'px ' +
				attributes.paddingLeft +
				'px',
		};
	}

	function writeVariables(style, device, layout) {
		style['--cc-display-' + device] = layout.layoutMode;
		style['--cc-direction-' + device] = layout.direction;
		style['--cc-justify-' + device] = layout.justify;
		style['--cc-align-' + device] = layout.align;
		style['--cc-wrap-' + device] = layout.wrap;
		style['--cc-columns-' + device] = String(layout.columns);
		style['--cc-gap-' + device] = layout.gap + 'px';
		style['--cc-padding-top-' + device] = layout.paddingTop + 'px';
		style['--cc-padding-right-' + device] = layout.paddingRight + 'px';
		style['--cc-padding-bottom-' + device] = layout.paddingBottom + 'px';
		style['--cc-padding-left-' + device] = layout.paddingLeft + 'px';
		style['--cc-max-width-' + device] = layout.maxWidth + 'px';
	}

	function styleFromAttributes(attributes) {
		if (!hasEnhancements(attributes)) {
			return legacyStyle(attributes);
		}
		var style = {
			alignItems: 'var(--cc-active-align)',
			backgroundColor: safeColor(attributes.background),
			display: 'var(--cc-active-display)',
			flexDirection: 'var(--cc-active-direction)',
			flexWrap: 'var(--cc-active-wrap)',
			gap: 'var(--cc-active-gap)',
			gridTemplateColumns: 'repeat(var(--cc-active-columns), minmax(0, 1fr))',
			justifyContent: 'var(--cc-active-justify)',
			marginLeft: 'auto',
			marginRight: 'auto',
			maxWidth: 'var(--cc-active-max-width)',
			paddingBottom: 'var(--cc-active-padding-bottom)',
			paddingLeft: 'var(--cc-active-padding-left)',
			paddingRight: 'var(--cc-active-padding-right)',
			paddingTop: 'var(--cc-active-padding-top)',
		};
		devices.forEach(function (device) {
			writeVariables(style, device, resolve(attributes, device));
		});
		return style;
	}

	function readDevice() {
		try {
			var value = window.localStorage.getItem(STORAGE_KEY);
			return devices.indexOf(value) !== -1 ? value : 'desktop';
		} catch (error) {
			return 'desktop';
		}
	}

	function updateOverride(responsive, device, key, value) {
		var next = Object.assign({}, responsive || {});
		next[device] = Object.assign({}, next[device] || {});
		next[device][key] = value;
		return next;
	}

	function resetDevice(responsive, device) {
		if (!responsive || !responsive[device]) {
			return responsive;
		}
		var next = Object.assign({}, responsive);
		delete next[device];
		return Object.keys(next).length ? next : undefined;
	}

	function Edit(props) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;
		var state = useState(readDevice);
		var device = state[0];
		var setDevice = state[1];
		var current = resolve(attributes, device);
		var blockProps = useBlockProps({
			className: 'cc-container',
			style: styleFromAttributes(attributes),
		});

		useEffect(function () {
			function onDeviceChange(event) {
				if (event.detail && devices.indexOf(event.detail.device) !== -1) {
					setDevice(event.detail.device);
				}
			}
			window.addEventListener(EVENT_NAME, onDeviceChange);
			return function () {
				window.removeEventListener(EVENT_NAME, onDeviceChange);
			};
		}, []);

		function update(key, value) {
			if (device === 'desktop') {
				var desktopUpdate = {};
				desktopUpdate[key] = value;
				setAttributes(desktopUpdate);
				return;
			}
			setAttributes({
				responsive: updateOverride(attributes.responsive, device, key, value),
			});
		}

		var layoutChildren = [
			createElement(
				'p',
				{ className: 'cc-responsive-context', key: 'context' },
				device === 'desktop'
					? __(
							'Desktop values are the base inherited by every smaller device.',
							'cresco-canvas'
					  )
					: __(
							'Only changed values are stored for this device; all other values inherit automatically.',
							'cresco-canvas'
					  )
			),
			createElement(SelectControl, {
				key: 'layout',
				label: __('Layout mode', 'cresco-canvas'),
				onChange: function (value) {
					update('layoutMode', value);
				},
				options: [
					{ label: __('Flex', 'cresco-canvas'), value: 'flex' },
					{ label: __('Grid', 'cresco-canvas'), value: 'grid' },
					{ label: __('Block', 'cresco-canvas'), value: 'block' },
				],
				value: current.layoutMode,
			}),
		];

		if (current.layoutMode === 'flex') {
			layoutChildren.push(
				createElement(SelectControl, {
					key: 'direction',
					label: __('Direction', 'cresco-canvas'),
					onChange: function (value) {
						update('direction', value);
					},
					options: [
						{ label: __('Column', 'cresco-canvas'), value: 'column' },
						{ label: __('Row', 'cresco-canvas'), value: 'row' },
					],
					value: current.direction,
				}),
				createElement(SelectControl, {
					key: 'wrap',
					label: __('Wrapping', 'cresco-canvas'),
					onChange: function (value) {
						update('wrap', value);
					},
					options: [
						{ label: __('No wrap', 'cresco-canvas'), value: 'nowrap' },
						{ label: __('Wrap', 'cresco-canvas'), value: 'wrap' },
					],
					value: current.wrap,
				})
			);
		}
		if (current.layoutMode === 'grid') {
			layoutChildren.push(
				createElement(RangeControl, {
					key: 'columns',
					label: __('Columns', 'cresco-canvas'),
					max: 12,
					min: 1,
					onChange: function (value) {
						update('columns', value == null ? 3 : value);
					},
					value: current.columns,
				})
			);
		}
		if (current.layoutMode !== 'block') {
			layoutChildren.push(
				createElement(SelectControl, {
					key: 'justify',
					label: __('Justification', 'cresco-canvas'),
					onChange: function (value) {
						update('justify', value);
					},
					options: [
						{ label: __('Start', 'cresco-canvas'), value: 'flex-start' },
						{ label: __('Center', 'cresco-canvas'), value: 'center' },
						{ label: __('End', 'cresco-canvas'), value: 'flex-end' },
						{
							label: __('Space between', 'cresco-canvas'),
							value: 'space-between',
						},
					],
					value: current.justify,
				}),
				createElement(SelectControl, {
					key: 'align',
					label: __('Alignment', 'cresco-canvas'),
					onChange: function (value) {
						update('align', value);
					},
					options: [
						{ label: __('Stretch', 'cresco-canvas'), value: 'stretch' },
						{ label: __('Start', 'cresco-canvas'), value: 'flex-start' },
						{ label: __('Center', 'cresco-canvas'), value: 'center' },
						{ label: __('End', 'cresco-canvas'), value: 'flex-end' },
					],
					value: current.align,
				}),
				createElement(RangeControl, {
					key: 'gap',
					label: __('Gap', 'cresco-canvas'),
					max: 240,
					min: 0,
					onChange: function (value) {
						update('gap', value == null ? 0 : value);
					},
					value: current.gap,
				})
			);
		}
		layoutChildren.push(
			createElement(RangeControl, {
				key: 'max-width',
				label: __('Maximum width', 'cresco-canvas'),
				max: 3840,
				min: 320,
				onChange: function (value) {
					update('maxWidth', value == null ? 1200 : value);
				},
				value: current.maxWidth,
			})
		);
		if (device !== 'desktop') {
			layoutChildren.push(
				createElement(
					Button,
					{
						disabled: !attributes.responsive || !attributes.responsive[device],
						key: 'reset',
						onClick: function () {
							setAttributes({
								responsive: resetDevice(attributes.responsive, device),
							});
						},
						variant: 'secondary',
					},
					__('Reset device overrides', 'cresco-canvas')
				)
			);
		}

		return createElement(
			Fragment,
			null,
			createElement(
				InspectorControls,
				null,
				createElement(
					PanelBody,
					{
						initialOpen: true,
						title: sprintf(__('Layout · %s', 'cresco-canvas'), labels[device]),
					},
					layoutChildren
				),
				createElement(
					PanelBody,
					{
						initialOpen: false,
						title: sprintf(__('Spacing · %s', 'cresco-canvas'), labels[device]),
					},
					sides.map(function (side) {
						return createElement(RangeControl, {
							key: side.key,
							label: side.label,
							max: 400,
							min: 0,
							onChange: function (value) {
								update(side.key, value == null ? 0 : value);
							},
							value: current[side.key],
						});
					})
				),
				createElement(
					PanelBody,
					{
						initialOpen: false,
						title: __('Style', 'cresco-canvas'),
					},
					createElement('p', null, __('Background color', 'cresco-canvas')),
					createElement(ColorPalette, {
						onChange: function (value) {
							setAttributes({ background: value || '' });
						},
						value: attributes.background,
					})
				)
			),
			createElement(
				'div',
				blockProps,
				createElement(InnerBlocks, {
					renderAppender: InnerBlocks.ButtonBlockAppender,
				})
			)
		);
	}

	function save(props) {
		var blockProps = useBlockProps.save({
			className: 'cc-container',
			style: styleFromAttributes(props.attributes),
		});
		return createElement(
			'div',
			blockProps,
			createElement(InnerBlocks.Content, null)
		);
	}

	registerBlockType(metadata, { edit: Edit, save: save });
})(window.wp);
