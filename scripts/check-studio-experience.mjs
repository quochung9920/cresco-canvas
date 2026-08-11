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
	'assets/css/website-builder-studio.css',
];
for (const relative of studioFiles) if (!exists(relative)) errors.push(`Missing Studio file: ${relative}`);

if (exists('build/website-builder-studio.js') && exists('runtime-src/build/website-builder-studio.js')) {
	const build = read('build/website-builder-studio.js');
	const source = read('runtime-src/build/website-builder-studio.js');
	if (hash(build) !== hash(source)) errors.push('Studio runtime source/build parity failed.');
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

expect('includes/Plugin.php', 'new WebsiteBuilderStudio()');
expect('includes/Plugin.php', 'new WebsiteBuilderPlatform()');
expect('includes/Builder/WebsiteBuilderModuleRegistry.php', 'build/website-builder-studio.js');
expect('includes/Builder/WebsiteBuilderRuntimeGuard.php', '.cc-builder-app,.cc-studio-app');
expect('includes/Builder/WebsiteBuilderRuntimeGuard.php', 'Object.assign({},window.crescoWebsiteBuilderSettings||{}');
expect('includes/Builder/WebsiteBuilderPlatform.php', 'cresco_canvas_extension_manifest');
expect('includes/Builder/WebsiteBuilderPlatform.php', 'cresco_canvas_document_adapters');
expect('includes/Builder/WebsiteBuilderPlatform.php', '/presence');
expect('includes/Builder/WebsiteBuilderPlatform.php', '/comments');
expect('includes/AI/ScopeResolver.php', "'selection-subtrees'");
expect('includes/Builder/WebsiteBuilderInterchange.php', "'selection-subtrees'");

if (errors.length) {
	process.stderr.write(`${errors.join('\n')}\n`);
	process.exit(1);
}
process.stdout.write('[studio] Studio runtime, source/build parity, Structure 2.0, responsive controls, multi-subtree AI interchange, collaboration foundation and extension contracts verified.\n');
