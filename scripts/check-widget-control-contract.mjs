import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const fail = (message) => { throw new Error(`[widget-control-contract] ${message}`); };
const assert = (condition, message) => { if (!condition) fail(message); };

const studioSource = read('runtime-src/build/website-builder-studio.js');
const studioBuild = read('build/website-builder-studio.js');
assert(studioSource === studioBuild, 'Website Builder Studio source/build parity is required.');
[
  'function schemaPanel(schema)', 'function schemaVisible(schema,values)', 'function propGroups(def,panelName)',
  'function styleKeysFor(tabName)', 'function statesForSelection()', 'function tabsForSelection()',
  "arr(def.style).indexOf(key)>=0", "arr(def.states).indexOf(state)>=0", "kind==='option-select'",
  "kind==='media'", "type==='url'||kind==='link'", "kind==='email'", "kind==='icon'",
  "kind==='repeater'&&type==='json'", 'schema.valueLabels', "'data-cresco-prop-key':key",
  "'data-cresco-style-key':key", "'data-cresco-spacing-kind':prefix.toLowerCase()",
].forEach((token) => assert(studioSource.includes(token), `Missing canonical Inspector contract token: ${token}`));
assert(!studioSource.includes("else if(type==='url')control=h('div',{className:'cc-studio-input-action'"), 'Generic URL fields must not receive the media picker.');
assert(!studioSource.includes("STATES.map(function(s)"), 'Inspector state tabs must be derived from widget capabilities.');
assert(studioSource.includes("tabs.length>1?h('nav',{className:'cc-studio-inspector-tabs'}"), 'Empty/single Inspector tab navigation must not be rendered.');
assert(studioSource.includes("states.length>1?h('div',{className:'cc-studio-state-tabs'}"), 'State tabs must disappear when only Normal is supported.');

const dimensionSource = read('runtime-src/build/studio-dimension-controls.js');
const dimensionBuild = read('build/studio-dimension-controls.js');
assert(dimensionSource === dimensionBuild, 'Dimension source/build parity is required.');
assert(dimensionSource.includes('data-cresco-prop-key'), 'Dimension controls must bind props by canonical key.');
assert(dimensionSource.includes('data-cresco-style-key'), 'Dimension controls must bind styles by canonical key.');
assert(dimensionSource.includes('data-cresco-spacing-kind'), 'Dimension controls must bind spacing by canonical kind.');
assert(!dimensionSource.includes("var keys = Object.keys(obj(def.props));\n    var index = keys.indexOf(key);"), 'Dimension controls must not locate prop fields by catalog index.');

const css = read('assets/css/website-builder-studio.css');
assert(css.includes('CRESCO_WIDGET_CONTROL_INTEGRITY_V1'), 'Inspector control groups/repeaters require scoped styling.');
console.log('Widget catalog → Inspector control contract: PASS');
