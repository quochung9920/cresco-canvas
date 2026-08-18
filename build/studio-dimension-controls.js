(function (wp, window, document) {
  'use strict';

  if (!wp || !wp.element || !window.CrescoStudioSDK) return;

  var h = wp.element.createElement;
  var useEffect = wp.element.useEffect;
  var useMemo = wp.element.useMemo;
  var useState = wp.element.useState;
  var root = document.getElementById('cresco-canvas-standalone-editor');
  if (!root) return;

  var settings = window.crescoWebsiteBuilderSettings || {};
  var catalog = settings.widgetCatalog || {};
  var UNITS = ['px', '%', 'em', 'rem', 'vw', 'vh', 'vmin', 'vmax', 'ch'];
  var GROUPS = {
    layout: ['width', 'maxWidth', 'minWidth', 'height', 'minHeight', 'maxHeight', 'gap', 'columnGap', 'rowGap', 'flexBasis'],
    style: ['fontSize', 'lineHeight', 'letterSpacing', 'borderWidth', 'borderRadius'],
    advanced: ['top', 'right', 'bottom', 'left', 'inset']
  };
  var LABELS = {
    width: 'Width', maxWidth: 'Max width', minWidth: 'Min width', height: 'Height', minHeight: 'Min height', maxHeight: 'Max height',
    gap: 'Gap', columnGap: 'Column gap', rowGap: 'Row gap', flexBasis: 'Flex basis', fontSize: 'Font size', lineHeight: 'Line height',
    letterSpacing: 'Letter spacing', borderWidth: 'Border width', borderRadius: 'Border radius', top: 'Top', right: 'Right', bottom: 'Bottom', left: 'Left', inset: 'Inset',
    marginTop: 'Top', marginRight: 'Right', marginBottom: 'Bottom', marginLeft: 'Left', paddingTop: 'Top', paddingRight: 'Right', paddingBottom: 'Bottom', paddingLeft: 'Left'
  };
  var KEYWORDS = {
    width: [['auto', 'Auto'], ['100%', 'Full (100%)'], ['fit-content', 'Fit content'], ['min-content', 'Min content'], ['max-content', 'Max content']],
    maxWidth: [['none', 'None'], ['100%', 'Full (100%)'], ['fit-content', 'Fit content'], ['min-content', 'Min content'], ['max-content', 'Max content']],
    minWidth: [['auto', 'Auto'], ['0', 'None (0)'], ['100%', 'Full (100%)'], ['fit-content', 'Fit content'], ['min-content', 'Min content'], ['max-content', 'Max content']],
    height: [['auto', 'Auto'], ['100%', 'Full (100%)'], ['fit-content', 'Fit content'], ['min-content', 'Min content'], ['max-content', 'Max content']],
    maxHeight: [['none', 'None'], ['100%', 'Full (100%)'], ['fit-content', 'Fit content'], ['min-content', 'Min content'], ['max-content', 'Max content']],
    minHeight: [['auto', 'Auto'], ['0', 'None (0)'], ['100%', 'Full (100%)'], ['fit-content', 'Fit content'], ['min-content', 'Min content'], ['max-content', 'Max content']],
    gap: [['normal', 'Normal'], ['0', 'None (0)']],
    columnGap: [['normal', 'Normal'], ['0', 'None (0)']],
    rowGap: [['normal', 'Normal'], ['0', 'None (0)']],
    flexBasis: [['auto', 'Auto'], ['content', 'Content'], ['0', 'None (0)'], ['100%', 'Full (100%)'], ['fit-content', 'Fit content'], ['min-content', 'Min content'], ['max-content', 'Max content']],
    lineHeight: [['normal', 'Normal']],
    letterSpacing: [['normal', 'Normal']],
    borderWidth: [['0', 'None (0)'], ['thin', 'Thin'], ['medium', 'Medium'], ['thick', 'Thick']],
    borderRadius: [['0', 'None (0)']],
    top: [['auto', 'Auto']], right: [['auto', 'Auto']], bottom: [['auto', 'Auto']], left: [['auto', 'Auto']], inset: [['auto', 'Auto']],
    marginTop: [['auto', 'Auto'], ['0', 'None (0)']], marginRight: [['auto', 'Auto'], ['0', 'None (0)']], marginBottom: [['auto', 'Auto'], ['0', 'None (0)']], marginLeft: [['auto', 'Auto'], ['0', 'None (0)']],
    paddingTop: [['0', 'None (0)']], paddingRight: [['0', 'None (0)']], paddingBottom: [['0', 'None (0)']], paddingLeft: [['0', 'None (0)']]
  };
  var SPECIAL_UNITS = {
    lineHeight: ['unitless', 'px', '%', 'em', 'rem'],
    letterSpacing: ['px', 'em', 'rem'],
    fontSize: ['px', '%', 'em', 'rem', 'vw', 'vh', 'vmin', 'vmax'],
    borderWidth: ['px', 'em', 'rem'],
    borderRadius: ['px', '%', 'em', 'rem']
  };
  var DIMENSION_PROP_PATTERN = /(width|height|gap|spacing|size|thickness|radius|offset|inset)$/i;

  function arr(value) { return Array.isArray(value) ? value : []; }
  function obj(value) { return value && typeof value === 'object' && !Array.isArray(value) ? value : {}; }
  function text(node) { return node ? String(node.textContent || '').replace(/\s+/g, ' ').trim() : ''; }
  function all(selector, scope) { return Array.prototype.slice.call((scope || root).querySelectorAll(selector)); }
  function hasOwn(source, key) { return !!source && Object.prototype.hasOwnProperty.call(source, key); }
  function unitsFor(key) { return (SPECIAL_UNITS[key] || UNITS).slice(); }
  function keywordPairs(key) { return (KEYWORDS[key] || []).slice(); }
  function keywordLabel(key, value) {
    var pair = keywordPairs(key).find(function (item) { return item[0] === value; });
    return pair ? pair[1] : value;
  }
  function defaultUnit(key) { return key === 'lineHeight' ? 'unitless' : 'px'; }
  function parseSimple(raw, key) {
    var value = String(raw == null ? '' : raw).trim();
    if (!value) return null;
    if (key === 'lineHeight' && /^-?(?:\d+|\d*\.\d+)$/.test(value)) return { number: value, unit: 'unitless' };
    var match = value.match(/^(-?(?:\d+|\d*\.\d+))(px|%|em|rem|vw|vh|vmin|vmax|ch)$/i);
    return match ? { number: match[1], unit: match[2].toLowerCase() } : null;
  }
  function isKeyword(key, value) {
    return keywordPairs(key).some(function (item) { return item[0] === String(value == null ? '' : value).trim(); });
  }
  function inferMode(key, value) {
    var raw = String(value == null ? '' : value).trim();
    if (isKeyword(key, raw)) return raw;
    var parsed = parseSimple(raw, key);
    if (parsed && unitsFor(key).indexOf(parsed.unit) !== -1) return parsed.unit;
    if (!raw) return defaultUnit(key);
    return 'custom';
  }
  function effective(node, device, state) {
    var out = Object.assign({}, obj(node && node.style));
    var order = ['desktop', 'laptop', 'tablet', 'mobile'];
    if (device !== 'wide') {
      for (var i = 0; i < order.length; i++) {
        Object.assign(out, obj(obj(node && node.responsive)[order[i]]));
        if (order[i] === device) break;
      }
    }
    if (state && state !== 'normal') Object.assign(out, obj(obj(node && node.states)[state]));
    return out;
  }
  function owns(node, key, device, state) {
    if (!node) return false;
    if (state && state !== 'normal') return hasOwn(obj(node.states)[state], key);
    if (device === 'wide') return hasOwn(node.style, key);
    return hasOwn(obj(node.responsive)[device], key);
  }
  function inspectorPanel() {
    return all('.cc-studio-panel', root).find(function (panel) {
      var heading = panel.querySelector('.cc-studio-panel-head strong');
      return /^Edit\s+/i.test(text(heading));
    }) || null;
  }
  function styleField(key) {
    var panel = inspectorPanel();
    if (!panel) return null;
    return all('.cc-studio-style-field', panel).find(function (field) {
      var span = field.querySelector('.cc-studio-style-field__label > span');
      return text(span) === key;
    }) || null;
  }
  function sourceControl(key) {
    var field = styleField(key);
    if (!field) return null;
    return all('input,select,textarea', field).find(function (control) { return control.type !== 'color'; }) || null;
  }
  function spacingSection(kind) {
    var panel = inspectorPanel();
    if (!panel) return null;
    return all('.cc-studio-spacing', panel).find(function (section) {
      return text(section.querySelector(':scope > strong')).toLowerCase() === kind;
    }) || null;
  }
  function spacingSource(kind, index) {
    var section = spacingSection(kind);
    if (!section) return null;
    var labels = all(':scope > .cc-studio-spacing__grid > label', section);
    return labels[index] ? labels[index].querySelector('input') : null;
  }
  function definition(node) { return node && catalog[node.type] ? catalog[node.type] : null; }
  function dimensionProps(node) {
    var def = definition(node);
    var props = obj(def && def.props);
    return Object.keys(props).filter(function (key) {
      var schema = props[key];
      if (!schema || schema.type !== 'css' || key === 'aspectRatio' || /ratio/i.test(key)) return false;
      return DIMENSION_PROP_PATTERN.test(key) || /(width|height|gap|spacing|size|thickness|radius|offset|inset)/i.test(schema.label || '');
    });
  }
  function propField(node, key) {
    var def = definition(node);
    if (!def) return null;
    var keys = Object.keys(obj(def.props));
    var index = keys.indexOf(key);
    var panel = inspectorPanel();
    if (index < 0 || !panel) return null;
    var fields = all('.cc-studio-fields > .cc-studio-field', panel);
    return fields[index] || null;
  }
  function propSource(node, key) {
    var field = propField(node, key);
    if (!field) return null;
    return all('input,textarea', field).find(function (control) { return control.type !== 'color'; }) || null;
  }
  function nativeValue(control, value) {
    if (!control) return false;
    var proto = control instanceof window.HTMLTextAreaElement ? window.HTMLTextAreaElement.prototype : control instanceof window.HTMLSelectElement ? window.HTMLSelectElement.prototype : window.HTMLInputElement.prototype;
    var descriptor = Object.getOwnPropertyDescriptor(proto, 'value');
    if (descriptor && descriptor.set) descriptor.set.call(control, String(value == null ? '' : value));
    else control.value = String(value == null ? '' : value);
    control.dispatchEvent(new window.Event(control instanceof window.HTMLSelectElement ? 'change' : 'input', { bubbles: true }));
    return true;
  }
  function applyStyle(key, value) { return nativeValue(sourceControl(key), value); }
  function applySpacing(kind, index, value) { return nativeValue(spacingSource(kind, index), value); }
  function applyProp(node, key, value) { return nativeValue(propSource(node, key), value); }
  function mark(tab, node) {
    var marked = [];
    function tag(target) {
      if (!target) return;
      target.setAttribute('data-cresco-dimension-source', '1');
      marked.push(target);
    }
    if (tab === 'content') dimensionProps(node).forEach(function (key) { tag(propField(node, key)); });
    else {
      arr(GROUPS[tab]).forEach(function (key) { tag(styleField(key)); });
      if (tab === 'advanced') {
        tag(spacingSection('margin'));
        tag(spacingSection('padding'));
      }
    }
    return function () {
      marked.forEach(function (target) {
        if (target && target.isConnected) target.removeAttribute('data-cresco-dimension-source');
      });
    };
  }
  function optionList(key) {
    var units = unitsFor(key).map(function (unit) { return { value: unit, label: unit === 'unitless' ? 'Unitless' : unit }; });
    var keywords = keywordPairs(key).map(function (item) { return { value: item[0], label: item[1] }; });
    return units.concat(keywords).concat([{ value: 'custom', label: 'Custom CSS' }]);
  }
  function modeIsKeyword(key, mode) { return keywordPairs(key).some(function (item) { return item[0] === mode; }); }
  function serial(number, mode) {
    var value = String(number == null ? '' : number).trim();
    if (!value) return '';
    return mode === 'unitless' ? value : value + mode;
  }

  function DimensionControl(props) {
    var raw = String(props.value == null ? '' : props.value);
    var initial = inferMode(props.keyName, raw);
    var modeState = useState(initial);
    var mode = modeState[0];
    var setMode = modeState[1];
    var draftState = useState(function () {
      var parsed = parseSimple(raw, props.keyName);
      return initial === 'custom' ? raw : (parsed ? parsed.number : '');
    });
    var draft = draftState[0];
    var setDraft = draftState[1];

    useEffect(function () {
      var next = inferMode(props.keyName, props.value);
      var parsed = parseSimple(props.value, props.keyName);
      setMode(next);
      setDraft(next === 'custom' ? String(props.value == null ? '' : props.value) : (parsed ? parsed.number : ''));
    }, [props.keyName, String(props.value == null ? '' : props.value), props.device, props.state]);

    var keyword = modeIsKeyword(props.keyName, mode);

    function changeMode(event) {
      var next = event.target.value;
      var parsed = parseSimple(props.value, props.keyName);
      setMode(next);
      if (next === 'custom') {
        setDraft(inferMode(props.keyName, props.value) === 'custom' ? String(props.value || '') : '');
        return;
      }
      if (modeIsKeyword(props.keyName, next)) {
        setDraft('');
        props.onApply(next);
        return;
      }
      var number = parsed ? parsed.number : draft;
      if (number) {
        setDraft(number);
        props.onApply(serial(number, next));
      } else {
        setDraft('');
      }
    }

    function changeValue(event) {
      var next = event.target.value;
      setDraft(next);
      props.onApply(mode === 'custom' ? next : serial(next, mode));
    }

    return h('article', { className: 'cc-studio-native-dimension' + (props.compact ? ' is-compact' : '') },
      h('div', { className: 'cc-studio-native-dimension__head' },
        h('span', null, props.label || LABELS[props.keyName] || props.keyName),
        props.owned ? h('button', {
          type: 'button',
          className: 'cc-studio-native-dimension__reset',
          title: 'Reset override',
          'aria-label': 'Reset ' + (props.label || props.keyName),
          onClick: function () { props.onApply(props.resetValue == null ? '' : props.resetValue); }
        }, h('span', { className: 'dashicons dashicons-undo', 'aria-hidden': 'true' })) : null
      ),
      h('div', { className: 'cc-studio-native-dimension__control', 'data-keyword': keyword ? '1' : '0' },
        h('input', {
          type: mode === 'custom' ? 'text' : 'number',
          step: 'any',
          value: keyword ? '' : draft,
          disabled: keyword,
          placeholder: mode === 'custom' ? 'clamp(), calc(), var(), token' : '0',
          onChange: changeValue
        }),
        h('select', {
          value: mode,
          'aria-label': (props.label || props.keyName) + ' size mode',
          onChange: changeMode
        }, optionList(props.keyName).map(function (option) {
          return h('option', { key: option.value, value: option.value }, option.label);
        })),
        keyword ? h('small', { className: 'cc-studio-native-dimension__keyword' }, keywordLabel(props.keyName, mode)) : null
      )
    );
  }

  function BoxGroup(props) {
    var sides = ['Top', 'Right', 'Bottom', 'Left'];
    var style = effective(props.node, props.device, props.state);
    return h('section', { className: 'cc-studio-native-box' },
      h('div', { className: 'cc-studio-native-box__title' },
        h('strong', null, props.title),
        h('small', null, 'Each side has its own unit / keyword / custom mode')
      ),
      h('div', { className: 'cc-studio-native-box__grid' }, sides.map(function (side, index) {
        var key = props.kind + side;
        return h(DimensionControl, {
          key: key,
          keyName: key,
          label: side,
          value: style[key] || '',
          owned: owns(props.node, key, props.device, props.state),
          device: props.device,
          state: props.state,
          compact: true,
          onApply: function (value) { applySpacing(props.kind.toLowerCase(), index, value); }
        });
      }))
    );
  }

  function NativeSizing(props) {
    useEffect(function () { return mark(props.tab, props.node); }, [props.tab, props.node && props.node.id, props.device, props.state]);
    var style = useMemo(function () { return effective(props.node, props.device, props.state); }, [props.node, props.device, props.state]);
    var keys = props.tab === 'content' ? dimensionProps(props.node) : arr(GROUPS[props.tab]);

    return h('div', { className: 'cc-studio-size-system', 'data-tab': props.tab },
      h('div', { className: 'cc-studio-size-system__meta' },
        h('span', null, (props.device || 'wide') + ' · ' + (props.state || 'normal')),
        h('small', null, 'Preset/unit dropdown + Custom CSS')
      ),
      props.tab === 'advanced' ? h('div', { className: 'cc-studio-native-boxes' },
        h(BoxGroup, { title: 'Margin', kind: 'margin', node: props.node, device: props.device, state: props.state }),
        h(BoxGroup, { title: 'Padding', kind: 'padding', node: props.node, device: props.device, state: props.state })
      ) : null,
      h('div', { className: 'cc-studio-native-dimension-list' }, keys.map(function (key) {
        if (props.tab === 'content') {
          var def = definition(props.node);
          var schema = obj(def && def.props)[key] || {};
          var value = obj(props.node.props)[key];
          var resetValue = schema.default == null ? '' : schema.default;
          var owned = String(value == null ? '' : value) !== String(resetValue);
          return h(DimensionControl, {
            key: key,
            keyName: key,
            label: schema.label || LABELS[key] || key,
            value: value == null ? '' : value,
            owned: owned,
            device: props.device,
            state: props.state,
            resetValue: resetValue,
            onApply: function (next) { applyProp(props.node, key, next); }
          });
        }
        var current = style[key] || '';
        return h(DimensionControl, {
          key: key,
          keyName: key,
          label: LABELS[key] || key,
          value: current,
          owned: owns(props.node, key, props.device, props.state),
          device: props.device,
          state: props.state,
          onApply: function (next) { applyStyle(key, next); }
        });
      }))
    );
  }

  function register(id, label, tab, when) {
    window.CrescoStudioSDK.registerInspectorSection({
      id: id,
      label: label,
      when: function (context) {
        if (!context || context.tab !== tab || !context.node) return false;
        return when ? when(context) : true;
      },
      render: function (context) {
        return h(NativeSizing, { tab: tab, node: context.node, device: context.device, state: context.state });
      }
    });
  }

  register('native-dimensions-content', 'Sizing', 'content', function (context) { return dimensionProps(context.node).length > 0; });
  register('native-dimensions-layout', 'Size & gaps', 'layout');
  register('native-dimensions-style', 'Text & border sizes', 'style');
  register('native-dimensions-advanced', 'Spacing & offsets', 'advanced');

  window.setTimeout(function () {
    try { window.dispatchEvent(new CustomEvent('cresco:studio-extension-change', { detail: { bucket: 'inspectorSections' } })); } catch (error) {}
  }, 0);

  window.crescoStudioDimensionControls = {
    version: '2.0.0',
    mode: 'react-sdk-inspector',
    owner: 'WebsiteBuilderStudio.React',
    ownsDom: false,
    childDomMutations: false,
    sourceBridge: 'native-input-event'
  };
})(window.wp, window, document);
