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
assert(proSource === proBuild, 'Global Design compatibility source/build parity is required.');
assert(proSource.includes("mode:'retired-react-native-global-panel'"), 'DOM-driven Global Design Pro must remain retired.');
assert(proSource.includes('ownsDom:false'), 'Retired Global Design layer must explicitly declare that it does not own DOM.');
['.insertBefore(', '.appendChild(', '.replaceChildren(', '.removeChild(', 'innerHTML='].forEach((token) => {
  assert(!proSource.includes(token), `Retired Global Design compatibility runtime must not mutate React children (${token}).`);
});

const proPhp = read('includes/Builder/StudioGlobalDesignPro.php');
assert(proPhp.includes('WebsiteBuilderStudio::globalPanel()'), 'PHP compatibility boundary must name the canonical React Global Design owner.');
assert(!/wp_enqueue_(?:script|style)\s*\(/.test(proPhp), 'Retired Global Design Pro service must not enqueue DOM-mutating legacy assets.');

const studioSource = read('runtime-src/build/website-builder-studio.js');
const studioBuild = read('build/website-builder-studio.js');
assert(studioSource === studioBuild, 'Canonical Studio source/build parity is required.');
assert(studioSource.includes('function globalPanel()'), 'Canonical Studio must retain its React-native Global Design panel.');
assert(studioSource.includes("className:'cc-studio-widget-grid'"), 'Canonical Studio must own the widget library grid.');
assert(studioSource.includes("className:'cc-studio-canvas-node'"), 'Canonical Studio must own canvas nodes.');

const ownershipPhp = read('includes/Builder/StudioReactOwnershipGuard.php');
assert(ownershipPhp.includes("'WebsiteBuilderStudio.React'"), 'React ownership guard must declare the canonical Studio owner.');
assert(ownershipPhp.includes("add_action( 'admin_enqueue_scripts', array( $this, 'enforce' ), 99999 )"), 'React ownership guard must run after presentation services enqueue.');
const retiredHandles = [
  'cresco-canvas-website-builder-responsive-properties',
  'cresco-canvas-website-builder-ui-correction',
  'cresco-canvas-website-builder-unset-styles',
  'cresco-canvas-website-builder-architecture-v2',
  'cresco-canvas-studio-dimension-controls',
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
assert(ownershipPhp.includes('wp_dequeue_script( $handle )'), 'Ownership guard must remove retired runtimes from the final script queue.');
assert(ownershipPhp.includes('widget-filter'), 'Ownership migration must clear the legacy widget filter that can hide the entire library.');
assert(ownershipPhp.includes(':focus'), 'Ownership migration must clear the legacy focus-mode state.');

const plugin = read('cresco-canvas.php');
assert(plugin.includes('StudioReactOwnershipGuard()'), 'Plugin bootstrap must register the late React ownership guard.');

console.log('Studio React DOM ownership contract: PASS');
