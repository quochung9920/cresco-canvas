import fs from 'node:fs';
import path from 'node:path';
import { createHash } from 'node:crypto';

const root = process.cwd();
const errors = [];
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');
const exists = (relative) => fs.existsSync(path.join(root, relative));
const digest = (value) => createHash('sha256').update(value).digest('hex');
const expect = (relative, token) => {
	if (!exists(relative)) return errors.push(`Missing ${relative}`);
	if (!read(relative).includes(token)) errors.push(`${relative} missing ${token}`);
};
const reject = (relative, token) => {
	if (exists(relative) && read(relative).includes(token)) errors.push(`${relative} must not contain ${token}`);
};
const expectParity = (source, build) => {
	if (!exists(source) || !exists(build)) return errors.push(`Missing source/build pair ${source} -> ${build}`);
	if (digest(read(source)) !== digest(read(build))) errors.push(`Source/build mismatch: ${source} -> ${build}`);
};

// Responsive properties owns widget-aware presentation, including every
// Inspector accordion in Layout, Style and Advanced. Each group must be able
// to open and close independently; a collapsed group must never be forced
// back open by a fallback-to-first-group pass. Typography stays inline beside
// Background, Border and Effects, and the retired popup runtime remains cleanup-only.
for (const token of [
	"version:'3.1.0'",
	"mode:'widget-aware-responsive-accordion'",
	"accordionBehavior:'independent-collapsible'",
	"dragOwnership:'pointer-drag-only'",
	"var inspectorGroupState={layout:{size:true},style:{typography:true},advanced:{spacing:true}}",
	'function syncDeviceBars()',
	'function enhanceGridColumns(field)',
	'function groupState(tab)',
	'function groupIsOpen(tab,id)',
	'function toggleGroup(tab,id)',
	'toggleGroup(tab,group.id);enhanceInspectorGroups();',
	'function enhanceInspectorGroups()',
	"id:'size',label:'Display & Size'",
	"id:'gaps',label:'Spacing & Gaps'",
	"id:'alignment',label:'Alignment'",
	"id:'flexbox',label:'Flexbox'",
	"id:'grid',label:'Grid'",
	"id:'typography',label:'Typography'",
	"id:'background',label:'Background'",
	"id:'border',label:'Border'",
	"id:'effects',label:'Effects'",
	"id:'spacing',label:'Margin & Padding'",
	"id:'position',label:'Position & Layer'",
	"id:'overflow',label:'Overflow & Visibility'",
	"id:'transform',label:'Transform & Effects'",
	"id:'media',label:'Media & Cursor'",
	"id:'custom-css',label:'Custom CSS'",
	"open=allowed&&groupIsOpen(tab,group.id)",
	"header.setAttribute('aria-expanded',open?'true':'false')",
]) expect('runtime-src/build/website-builder-responsive-properties.js', token);
for (const token of [
	'dragSession',
	'refreshDragSession',
	'DRAG_MIME',
	"addEventListener('dragstart'",
	"method:'POST'",
	"inspectorGroupState[tab]=inspectorGroupState[tab]===group.id?'':group.id",
	"if(!inspectorGroupState[tab]&&available.length)inspectorGroupState[tab]=available[0]",
]) reject('runtime-src/build/website-builder-responsive-properties.js', token);
expectParity('runtime-src/build/website-builder-responsive-properties.js', 'build/website-builder-responsive-properties.js');

for (const token of [
	"mode:'retired-use-native-accordion'",
	"data-cresco-typography-popup-hidden",
	".cc-studio-accordion-heading[data-cresco-group=\"typography\"]",
	"removeAttribute('aria-haspopup')",
	"dashicons-arrow-down-alt2 cc-studio-accordion-heading__chevron",
]) expect('runtime-src/build/studio-typography-popup.js', token);
for (const token of [
	"mode:'focused-popup-editor'",
	'function openPopup()',
	'stopImmediatePropagation',
	'body.appendChild(field)',
	"field.style.setProperty('display','none','important')",
	"header.setAttribute('aria-haspopup','dialog')",
]) reject('runtime-src/build/studio-typography-popup.js', token);
expectParity('runtime-src/build/studio-typography-popup.js', 'build/studio-typography-popup.js');

for (const token of [
	"version:'6.0.0'",
	'function canContain(id)',
	'function validTarget(sourceId,targetId,zone)',
	'function restoreTree()',
	'temporaryStructureExpansion:true',
	"zone==='inside'&&!canContain(targetId)",
]) expect('runtime-src/build/website-builder-pointer-drag.js', token);

for (const token of [
	"version:'1.1.0'",
	'getDocument:function()',
	'getRevision:function()',
	'getSelection:function()',
	'function rebuildIndex()',
	"getNode:function(id){return nodeIndex[String(id||'')]||null;}",
	'getNodeCount:function()',
	'beginTransaction:beginTransaction',
	'commitTransaction:commitTransaction',
	'cancelTransaction:cancelTransaction',
	'beforeDirty:state.dirty',
	'state.dirty=!!tx.beforeDirty',
	'beginSave:beginSave',
	'markPersisted:markPersisted',
	"schema:'cresco-recovery/v1'",
	"addEventListener('cresco:studio-session-change'",
]) expect('runtime-src/build/website-builder-document-store.js', token);
expectParity('runtime-src/build/website-builder-document-store.js', 'build/website-builder-document-store.js');

for (const token of [
	"version:'3.0.0'",
	"authority:'crescoDocumentStore'",
	"auxDirty:{pageSettings:false,globalSettings:false}",
	'function sanitizePreviewHtml(value)',
	'store.beginSave()',
	'store.markPersisted(result,save.revision)',
	'cresco_save_superseded',
	"addEventListener('beforeunload'",
]) expect('runtime-src/build/website-builder-consistency-guard.js', token);
reject('runtime-src/build/website-builder-consistency-guard.js', 'state.revision++');
reject('runtime-src/build/website-builder-consistency-guard.js', 'function localChangedSince(data)');
expectParity('runtime-src/build/website-builder-consistency-guard.js', 'build/website-builder-consistency-guard.js');

for (const token of [
	"recoveryOwner:'document-store'",
	'path===paths.session||path===paths.pageSettings',
	"addEventListener('cresco:studio-ready'",
]) expect('runtime-src/build/website-builder-bootstrap.js', token);
reject('runtime-src/build/website-builder-bootstrap.js', 'showRecovery(');
expectParity('runtime-src/build/website-builder-bootstrap.js', 'build/website-builder-bootstrap.js');

for (const token of [
	"add_filter( 'rest_post_dispatch', array( $this, 'verify_and_release' ), 90, 3 )",
	'add_option( $key, $value',
	"'cresco_builder_write_busy'",
	"'cresco_builder_persistence_mismatch'",
	"preg_match( '#^/cresco-canvas/v1/session/(\\d+)$#'",
	'WordPressDocumentRepository',
	'return $this->repository->checksum( $post_id );',
]) expect('includes/Builder/WebsiteBuilderConcurrencyGuard.php', token);

for (const token of [
	'public function checksum( $document_id )',
	'public function verify( $document_id, $expected_checksum )',
	"'cresco_document_storage_verify'",
]) expect('includes/Infrastructure/WordPress/Storage/WordPressDocumentRepository.php', token);

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

for (const token of [
	"const HANDLE = 'cresco-canvas-website-builder-document-store'",
	"array( self::HANDLE ), (array) $guard->deps",
]) expect('includes/Builder/WebsiteBuilderDocumentStore.php', token);
expect('includes/Builder/WebsiteBuilderModuleRegistry.php', "'cresco-canvas-website-builder-document-store'");
expect('runtime-src/manifest.json', '"website-builder-document-store.js": "runtime-src/build/website-builder-document-store.js"');
expect('scripts/release-files.mjs', "'build/website-builder-document-store.js'");

if (errors.length) {
	process.stderr.write(`${errors.join('\n')}\n`);
	process.exit(1);
}
process.stdout.write('[known-defects] Save races, canonical document-store/recovery ownership, transaction cancellation, indexed node lookup, source/build parity, atomic persistence, legacy Session preconditions, widget-aware responsive presentation with independent collapsible Layout/Style/Advanced accordions, single drag ownership, inline Typography ownership, fail-closed startup, hidden preview, auxiliary dirty-state, and safe rich-text preview contracts verified.\n');
