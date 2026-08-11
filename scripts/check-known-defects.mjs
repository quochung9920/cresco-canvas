import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const errors = [];
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');
const exists = (relative) => fs.existsSync(path.join(root, relative));
const expect = (relative, token) => {
	if (!exists(relative)) return errors.push(`Missing ${relative}`);
	if (!read(relative).includes(token)) errors.push(`${relative} missing ${token}`);
};
const reject = (relative, token) => {
	if (exists(relative) && read(relative).includes(token)) errors.push(`${relative} must not contain ${token}`);
};

for (const token of [
	"mode:'responsive-ui-only'",
	"dragOwnership:'pointer-drag-only'",
	'function syncDeviceBars()',
	'function enhanceGridColumns(field)',
]) expect('runtime-src/build/website-builder-responsive-properties.js', token);
for (const token of ['dragSession', 'refreshDragSession', 'DRAG_MIME', "addEventListener('dragstart'", "method:'POST'"]) reject('runtime-src/build/website-builder-responsive-properties.js', token);

for (const token of [
	"version:'6.0.0'",
	'function canContain(id)',
	'function validTarget(sourceId,targetId,zone)',
	'function restoreTree()',
	'temporaryStructureExpansion:true',
	"zone==='inside'&&!canContain(targetId)",
]) expect('runtime-src/build/website-builder-pointer-drag.js', token);

for (const token of [
	"version:'2.0.0'",
	'function localChangedSince(data)',
	"auxDirty:{pageSettings:false,globalSettings:false}",
	'function sanitizePreviewHtml(value)',
	'cresco_save_superseded',
	"addEventListener('beforeunload'",
]) expect('runtime-src/build/website-builder-consistency-guard.js', token);

for (const token of [
	"recoveryOwner:'runtime-guard'",
	'path===paths.session||path===paths.pageSettings',
	"addEventListener('cresco:studio-ready'",
]) expect('runtime-src/build/website-builder-bootstrap.js', token);
reject('runtime-src/build/website-builder-bootstrap.js', 'showRecovery(');

for (const token of [
	"add_filter( 'rest_post_dispatch', array( $this, 'verify_and_release' ), 90, 3 )",
	'add_option( $key, $value',
	"'cresco_builder_write_busy'",
	"'cresco_builder_persistence_mismatch'",
]) expect('includes/Builder/WebsiteBuilderConcurrencyGuard.php', token);

for (const token of [
	"critical=(p===paths.session||p===paths.pageSettings)",
	"root.querySelector('.cc-studio-app')",
]) expect('includes/Builder/WebsiteBuilderBootstrapResilience.php', token);
reject('includes/Builder/WebsiteBuilderBootstrapResilience.php', "if(p===paths.pageSettings)return{matched:true,value:{settings:{}}}");

for (const token of [
	"function bootstrap(){return window.crescoWebsiteBuilderBootstrap||window.crescoWebsiteBuilderRequestGuard||null;}",
	"window.addEventListener('cresco:builder-bootstrap-fatal'",
]) expect('includes/Builder/WebsiteBuilderRuntimeGuard.php', token);

for (const token of [
	"globalHiddenPreview:true",
	'is-cresco-globally-hidden-preview',
	'crescoStudioConsistencyGuard',
]) expect('runtime-src/build/website-builder-ui-correction.js', token);

if (errors.length) {
	process.stderr.write(`${errors.join('\n')}\n`);
	process.exit(1);
}
process.stdout.write('[known-defects] Save races, atomic persistence, single drag ownership, fail-closed startup, hidden preview, auxiliary dirty-state, and safe rich-text preview contracts verified.\n');
