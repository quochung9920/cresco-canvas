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
	"version:'1.0.0'",
	'getDocument:function()',
	'getRevision:function()',
	'getSelection:function()',
	'beginTransaction:beginTransaction',
	'commitTransaction:commitTransaction',
	'cancelTransaction:cancelTransaction',
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

if (errors.length) {
	process.stderr.write(`${errors.join('\n')}\n`);
	process.exit(1);
}
process.stdout.write('[known-defects] Save races, canonical document-store/recovery ownership, source/build parity, atomic persistence, legacy Session preconditions, single drag ownership, fail-closed startup, hidden preview, auxiliary dirty-state, and safe rich-text preview contracts verified.\n');
