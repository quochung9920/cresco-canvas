import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const fail = (message) => { throw new Error(`[react-dom-ownership] ${message}`); };
const assert = (condition, message) => { if (!condition) fail(message); };

const consistencySource = read('runtime-src/build/website-builder-consistency-guard.js');
const consistencyBuild = read('build/website-builder-consistency-guard.js');
assert(consistencySource === consistencyBuild, 'Consistency Guard source/build parity is required.');
assert(!/wp\.element\.createElement\s*=/.test(consistencySource), 'Do not monkey-patch wp.element.createElement; WordPress exposes it as a read-only React boundary.');
assert(!/Object\.defineProperty\s*\(\s*wp\.element\s*,\s*['"]createElement['"]/.test(consistencySource), 'Do not redefine wp.element.createElement.');
assert(consistencySource.includes('window.crescoSanitizeStudioPreviewHtml=sanitizePreviewHtml'), 'Preview sanitizer must be exported explicitly at the preview boundary.');
assert(consistencySource.includes("safePreviewBoundary:'explicit-sanitizer'"), 'Consistency Guard must declare the explicit sanitizer boundary.');

const proSource = read('runtime-src/build/studio-global-design-pro.js');
const proBuild = read('build/studio-global-design-pro.js');
assert(proSource === proBuild, 'Global Design React source/build parity is required.');
assert(proSource.includes("registerPanel({id:'global-design-react'"), 'Professional Global Design must register through the Studio React SDK.');
assert(proSource.includes("mode:'react-sdk-panel'"), 'Global Design Pro must declare the React SDK mode.');
assert(proSource.includes("owner:'WebsiteBuilderStudio.React'"), 'Global Design Pro must declare the canonical React owner.');
assert(proSource.includes('ownsDom:false'), 'Global Design Pro must explicitly declare that it does not own DOM.');
['document.createElement(', '.insertBefore(', '.appendChild(', '.replaceChildren(', '.removeChild(', '.replaceWith(', 'innerHTML=', 'outerHTML='].forEach((token) => {
  assert(!proSource.includes(token), `React-native Global Design must not imperatively mutate React children (${token}).`);
});

const dimensionSource = read('runtime-src/build/studio-dimension-controls.js');
const dimensionBuild = read('build/studio-dimension-controls.js');
assert(dimensionSource === dimensionBuild, 'React-native dimension controls source/build parity is required.');
assert(dimensionSource.includes("mode: 'react-sdk-inspector'"), 'Dimension controls must run through the Studio React SDK.');
assert(dimensionSource.includes("owner: 'WebsiteBuilderStudio.React'"), 'Dimension controls must declare the canonical React owner.');
assert(dimensionSource.includes('ownsDom: false'), 'Dimension controls must not own the Studio DOM.');
assert(dimensionSource.includes("label: 'Custom CSS'"), 'Every native dimension control must retain Custom CSS mode.');
assert(dimensionSource.includes("['100%', 'Full (100%)']"), 'Box dimensions must expose the Full 100% semantic preset.');
assert(dimensionSource.includes("['fit-content', 'Fit content']"), 'Box dimensions must expose fit-content.');
assert(dimensionSource.includes("register('native-dimensions-layout'"), 'Layout sizing inspector must be registered.');
assert(dimensionSource.includes("register('native-dimensions-style'"), 'Style sizing inspector must be registered.');
assert(dimensionSource.includes("register('native-dimensions-advanced'"), 'Advanced spacing/offset inspector must be registered.');
assert(dimensionSource.includes("register('native-dimensions-content'"), 'Schema-driven Content sizing inspector must be registered.');
['document.createElement(', '.insertBefore(', '.appendChild(', '.replaceChildren(', '.removeChild(', '.replaceWith(', 'innerHTML=', 'outerHTML='].forEach((token) => {
  assert(!dimensionSource.includes(token), `React-native dimension controls must not mutate React child structure (${token}).`);
});

const dimensionPhp = read('includes/Builder/StudioDimensionControls.php');
assert(dimensionPhp.includes('React-native Cresco Studio dimension controls'), 'Dimension service must document React-native ownership.');
assert(dimensionPhp.includes("array( WebsiteBuilderStudio::HANDLE, 'wp-element' )"), 'Dimension SDK runtime must load after the canonical Studio owner.');
assert(!dimensionPhp.includes('WebsiteBuilderAsset::url( self::SYNC_SCRIPT )'), 'Historical DOM synchronization runtime must stay retired.');

const proPhp = read('includes/Builder/StudioGlobalDesignPro.php');
assert(proPhp.includes('React-native professional Global Design workspace'), 'Global Design PHP boundary must document React-native ownership.');
assert(proPhp.includes("array( WebsiteBuilderStudio::HANDLE, 'wp-element', 'wp-api-fetch' )"), 'Global Design React runtime must load after the canonical Studio owner.');
assert(proPhp.includes("'cresco-global-design-pro/v2'"), 'Global Design React config must use the v2 contract marker.');
assert(!proPhp.includes('WebsiteBuilderAsset::url( self::AUTHORITY_SCRIPT )'), 'Historical Global Design authority runtime must stay retired.');
assert(!proPhp.includes('WebsiteBuilderAsset::url( self::WORKFLOW_SCRIPT )'), 'Historical Global Design workflow runtime must stay retired.');
assert(!proPhp.includes('WebsiteBuilderAsset::url( self::COMPACT_SCRIPT )'), 'Historical Global Design compact runtime must stay retired.');
assert(!proPhp.includes('WebsiteBuilderAsset::url( self::SHARED_SCRIPT )'), 'Historical Global Design shared-control runtime must stay retired.');

const studioSource = read('runtime-src/build/website-builder-studio.js');
const studioBuild = read('build/website-builder-studio.js');
assert(studioSource === studioBuild, 'Canonical Studio source/build parity is required.');
assert(studioSource.includes('function globalPanel()'), 'Canonical Studio must retain its safe fallback Global Design panel.');
assert(studioSource.includes("className:'cc-studio-widget-grid'"), 'Canonical Studio must own the widget library grid.');
assert(studioSource.includes("className:'cc-studio-canvas-node'"), 'Canonical Studio must own canvas nodes.');

// Every box-model size property must offer a mode dropdown, not a bare text box.
['width', 'minWidth', 'maxWidth', 'height', 'minHeight', 'maxHeight', 'flexBasis'].forEach((key) => {
  assert(dimensionSource.includes(key + ': [['), `Size property must expose semantic keyword modes: ${key}.`);
});
assert(dimensionSource.includes('SPECIAL_UNITS'), 'Typography/border sizes must restrict units to the set their property supports.');
assert(dimensionSource.includes("lineHeight: ['unitless'"), 'Line height must offer the unitless mode.');

// Margin/Padding: four independent sides plus a presentation-only link toggle.
assert(dimensionSource.includes('function BoxGroup(props)'), 'Margin/Padding must render through the shared box group.');
assert(dimensionSource.includes("h(BoxGroup, { title: 'Margin'"), 'Margin box must be registered.');
assert(dimensionSource.includes("h(BoxGroup, { title: 'Padding'"), 'Padding box must be registered.');
assert(dimensionSource.includes('cc-studio-native-box__link'), 'Margin/Padding must expose a link toggle.');
assert(dimensionSource.includes('for (var i = 0; i < sides.length; i++) applySpacing(kind, i, value);'), 'Linked spacing must write every side through the canonical control bridge.');
assert(!dimensionSource.includes('linked:'), 'Widget spacing must not invent a persisted linked key; widget sides are four independent CSS strings.');

// Responsive must read the canonical desktop-first cascade, not a second engine.
assert(dimensionSource.includes("var order = ['desktop', 'laptop', 'tablet', 'mobile'];"), 'Dimension controls must use the canonical responsive inheritance order.');
assert(dimensionSource.includes('function owns(node, key, device, state)'), 'Dimension controls must distinguish an explicit override from an inherited value.');

// Page Settings ownership is unchanged: one shared unit for all four sides.
const pageSettingsPhp = read('includes/Page/PageSettings.php');
assert(pageSettingsPhp.includes("array( 'px', '%', 'em', 'rem', 'vh', 'vw' )"), 'Page Settings must keep its shared spacing unit enum.');
['unitTop', 'unitRight', 'unitBottom', 'unitLeft'].forEach((key) => {
  assert(!pageSettingsPhp.includes(key), `Page Settings must not gain per-side units without a full schema upgrade: ${key}.`);
});
assert(studioSource.includes("['px','%','em','rem','vh','vw']"), 'Page Settings UI must offer exactly the units the backend stores.');
assert(studioSource.includes('cc-studio-spacing__link'), 'Page Settings spacing must expose its persisted link flag.');
assert(studioSource.includes("target.linked)['top','right','bottom','left'].forEach"), 'Page Settings link toggle must drive values only, never per-side units.');

const ownershipPhp = read('includes/Builder/StudioReactOwnershipGuard.php');
assert(ownershipPhp.includes("'WebsiteBuilderStudio.React'"), 'React ownership guard must declare the canonical Studio owner.');
assert(ownershipPhp.includes("add_action( 'admin_enqueue_scripts', array( $this, 'enforce' ), 99999 )"), 'React ownership guard must run after presentation services enqueue.');
const retiredHandles = [
  'cresco-canvas-website-builder-responsive-properties',
  'cresco-canvas-website-builder-ui-correction',
  'cresco-canvas-website-builder-unset-styles',
  'cresco-canvas-website-builder-architecture-v2',
  'cresco-canvas-studio-dimension-controls-sync',
  'cresco-canvas-studio-typography-popup',
  'cresco-canvas-studio-widget-state-tabs',
  'cresco-canvas-studio-ux-pro',
  'cresco-canvas-studio-ux-pro-guard',
  'cresco-canvas-studio-light-first-runtime',
];
retiredHandles.forEach((handle) => {
  assert(ownershipPhp.includes(`'${handle}'`), `DOM-mutating Studio runtime must stay retired from the final queue: ${handle}`);
});
assert(!retiredHandles.includes('cresco-canvas-studio-global-design-pro'), 'React-native Global Design must stay in the final queue.');
assert(!retiredHandles.includes('cresco-canvas-studio-dimension-controls'), 'React-native dimension controls must stay in the final queue.');
assert(!ownershipPhp.includes("\t\t'cresco-canvas-studio-dimension-controls',"), 'Ownership guard must not dequeue the React-native dimension handle.');
assert(ownershipPhp.includes('wp_dequeue_script( $handle )'), 'Ownership guard must remove retired runtimes from the final script queue.');
assert(ownershipPhp.includes('widget-filter'), 'Ownership migration must clear the legacy widget filter that can hide the entire library.');
assert(ownershipPhp.includes(':focus'), 'Ownership migration must clear the legacy focus-mode state.');

const plugin = read('cresco-canvas.php');
assert(plugin.includes('StudioReactOwnershipGuard()'), 'Plugin bootstrap must register the late React ownership guard.');
assert(plugin.includes('StudioDimensionControls()'), 'Plugin bootstrap must register the React-native dimension service.');

console.log('Studio React DOM ownership contract: PASS');
