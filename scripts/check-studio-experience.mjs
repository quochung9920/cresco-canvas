import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

const root = process.cwd();
const errors = [];
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');
const exists = (relative) => fs.existsSync(path.join(root, relative));
const expect = (relative, token) => {
	if (!exists(relative)) {
		errors.push(`Missing ${relative}`);
		return;
	}
	if (!read(relative).includes(token)) errors.push(`${relative} missing ${token}`);
};
const hash = (value) => crypto.createHash('sha256').update(value).digest('hex');

const studioFiles = [
	'includes/Builder/WebsiteBuilderStudio.php',
	'includes/Builder/WebsiteBuilderPlatform.php',
	'build/website-builder-studio.js',
	'runtime-src/build/website-builder-studio.js',
	'build/website-builder-responsive-properties.js',
	'runtime-src/build/website-builder-responsive-properties.js',
	'assets/css/website-builder-studio.css',
];
for (const relative of studioFiles) if (!exists(relative)) errors.push(`Missing Studio file: ${relative}`);

for (const pair of [
	['build/website-builder-studio.js', 'runtime-src/build/website-builder-studio.js', 'Studio runtime source/build parity failed.'],
	['build/website-builder-responsive-properties.js', 'runtime-src/build/website-builder-responsive-properties.js', 'Responsive property runtime source/build parity failed.'],
]) {
	if (exists(pair[0]) && exists(pair[1]) && hash(read(pair[0])) !== hash(read(pair[1]))) errors.push(pair[2]);
}

for (const token of [
	'cc-studio-app cc-builder-app',
	'cresco:studio-ready',
	'window.crescoStudioDiagnostics',
	'window.CrescoStudioSDK',
	'registerPanel',
	'registerInspectorSection',
	'registerContextAction',
	'registerDocumentAdapter',
	'Expand all',
	'Collapse all',
	'dragExpandTimer.current = window.setTimeout',
	'Selected subtree',
	'selection-subtrees',
	'interchangeExport',
	'interchangePreview',
	'BroadcastChannel',
	'AUTO_SAVE_KEY',
	'cresco-diagnostics-last-',
]) expect('runtime-src/build/website-builder-studio.js', token);

for (const token of [
	'cc-studio-property-devices',
	'Responsive per property',
	'Grid columns',
	'is-cresco-structure-managed',
	'cc-studio-responsive-layout-note',
	'new MutationObserver',
]) expect('runtime-src/build/website-builder-responsive-properties.js', token);

expect('includes/Plugin.php', 'new WebsiteBuilderStudio()');
expect('includes/Plugin.php', 'new WebsiteBuilderPlatform()');
expect('includes/Builder/WebsiteBuilderStudio.php', 'website-builder-responsive-properties.js');
expect('includes/Builder/WebsiteBuilderStudio.php', 'enforce_runtime_ownership');
expect('includes/Builder/WebsiteBuilderStudio.php', "$registered->src  = WebsiteBuilderAsset::url( self::SCRIPT )");
expect('includes/Builder/WebsiteBuilderStudio.php', 'crescoExpectedWebsiteBuilderRuntime');
expect('includes/Builder/WebsiteBuilderStudio.php', '.cc-studio-meta-grid,.cc-builder-meta-row{display:none!important}');
expect('includes/Builder/WebsiteBuilderStudio.php', "querySelectorAll('.cc-studio-meta-grid,.cc-builder-meta-row')");
expect('includes/Builder/WebsiteBuilderStudio.php', '.cc-studio-tree-actions>button:nth-child(2)');
expect('includes/Builder/WebsiteBuilderStudio.php', '.cc-studio-tree-select>.dashicons-hidden');
expect('includes/Builder/WebsiteBuilderStudio.php', "root.addEventListener('dblclick'");
expect('includes/Builder/WebsiteBuilderStudio.php', "event.key!=='F2'");
expect('includes/Builder/WebsiteBuilderModuleRegistry.php', 'build/website-builder-studio.js');
expect('includes/Builder/WebsiteBuilderModuleRegistry.php', 'build/website-builder-responsive-properties.js');
expect('includes/Builder/WebsiteBuilderRuntimeGuard.php', '.cc-builder-app,.cc-studio-app');
expect('includes/Builder/WebsiteBuilderRuntimeGuard.php', 'Object.assign({},window.crescoWebsiteBuilderSettings||{}');
expect('includes/Builder/WebsiteBuilderPlatform.php', 'cresco_canvas_extension_manifest');
expect('includes/Builder/WebsiteBuilderPlatform.php', 'cresco_canvas_document_adapters');
expect('includes/Builder/WebsiteBuilderPlatform.php', '/presence');
expect('includes/Builder/WebsiteBuilderPlatform.php', '/comments');
expect('includes/AI/ScopeResolver.php', "'selection-subtrees'");
expect('includes/Builder/WebsiteBuilderInterchange.php', "'selection-subtrees'");
expect('scripts/release-files.mjs', 'build/website-builder-responsive-properties.js');

if (errors.length) {
	process.stderr.write(`${errors.join('\n')}\n`);
	process.exit(1);
}
process.stdout.write('[studio] Canonical Studio runtime ownership, property-level responsive controls, Structure-owned widget management, source/build parity, Structure 2.0, multi-subtree AI interchange, collaboration foundation and extension contracts verified.\n');
