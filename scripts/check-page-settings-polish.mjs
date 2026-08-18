import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');
const fail = (message) => { throw new Error(`[page-settings-polish] ${message}`); };
const assert = (condition, message) => { if (!condition) fail(message); };

const css = read('assets/css/studio-page-settings-polish.css');
assert(css.includes('@layer cresco.overrides'), 'Page Settings polish must live in the canonical overrides layer.');
assert(css.includes('button[title="Page"].is-active'), 'Every Page Settings rule must be scoped to the active Page rail.');
assert(css.includes('grid-template-columns: repeat(4, minmax(0, 1fr))'), 'Responsive spacing must keep a compact four-side desktop layout.');
assert(css.includes('appearance: none'), 'Scroll Snap must use the shared modern switch presentation.');
assert(css.includes('position: sticky'), 'Save Page Settings action must remain easy to reach while scrolling.');
assert(css.includes('@container (max-width: 300px)'), 'Narrow Inspector fallback must remain responsive.');
assert(!css.includes('!important'), 'Page Settings polish must not rely on !important.');

const php = read('includes/Builder/StudioPageSettingsPolish.php');
assert(php.includes('Presentation-only polish'), 'Page Settings polish service must document presentation-only ownership.');
assert(php.includes('wp_enqueue_style('), 'Page Settings polish must enqueue its scoped stylesheet.');
assert(!php.includes('wp_enqueue_script('), 'Page Settings polish must never introduce a DOM-mutating runtime.');
assert(php.includes("'cresco-canvas-website-builder-studio'"), 'Page Settings polish must depend on the canonical Studio stylesheet.');

const plugin = read('cresco-canvas.php');
assert(plugin.includes('StudioPageSettingsPolish()'), 'Plugin bootstrap must register Page Settings polish.');

const studio = read('runtime-src/build/website-builder-studio.js');
assert(studio.includes('function pagePanel()'), 'Canonical React pagePanel must remain the owner of Page Settings markup/state.');
assert(studio.includes('Save Page Settings'), 'Canonical Page Settings save path must remain present.');

console.log('Page Settings 2.0 presentation contract: PASS');
