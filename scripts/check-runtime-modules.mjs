import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');
const exists = (relative) => fs.existsSync(path.join(root, relative));
const fail = (message) => {
	console.error(`[runtime-modules] ${message}`);
	process.exitCode = 1;
};
const expect = (relative, token) => {
	if (!exists(relative)) return fail(`Missing ${relative}`);
	if (!read(relative).includes(token)) fail(`${relative} is missing required contract token: ${token}`);
};

const infrastructure = [
	'includes/Builder/WebsiteBuilderRuntimeContext.php',
	'includes/Builder/WebsiteBuilderAsset.php',
	'includes/Builder/WebsiteBuilderEditorConfig.php',
	'includes/Builder/WebsiteBuilderModuleRegistry.php',
];
for (const relative of infrastructure) {
	if (!exists(relative)) fail(`Missing runtime consolidation file: ${relative}`);
}

const registry = 'includes/Builder/WebsiteBuilderModuleRegistry.php';
for (const key of ['bootstrap', 'core', 'controls', 'professional-ux', 'architecture', 'comprehensive-v3', 'workflow']) {
	expect(registry, `'${key}'`);
}
expect(registry, "'quarantinedDefault' => true");
expect('includes/Builder/WebsiteBuilderRuntimeGuard.php', 'WebsiteBuilderModuleRegistry::enabled_keys');
expect('includes/Builder/WebsiteBuilderRuntimeGuard.php', 'window.crescoRuntimePolicy');
expect('includes/Builder/WebsiteBuilderRuntimeGuard.php', 'WebsiteBuilderEditorConfig::for_context');
expect('includes/Builder/WebsiteBuilderBootstrapResilience.php', 'WebsiteBuilderEditorConfig::bootstrap_paths');
expect('includes/Builder/WebsiteBuilderDiagnostics.php', 'WebsiteBuilderModuleRegistry::asset_reports');
expect('includes/Builder/WebsiteBuilderDiagnostics.php', 'eventloop.stall');
expect('includes/Builder/WebsiteBuilderWorkflowExtensions.php', '/website-builder/woocommerce/templates/single');
expect('includes/Builder/WebsiteBuilderComprehensiveV3.php', '/website-builder/document-diagnostics/');

const workflow = read('includes/Builder/WebsiteBuilderWorkflowExtensions.php');
if (workflow.includes("array( 'cresco-canvas-website-builder-comprehensive-v3', 'wp-api-fetch' )")) {
	fail('Workflow extensions still depend on Comprehensive V3 presentation runtime.');
}

for (const relative of ['build/website-builder-architecture.js', 'runtime-src/build/website-builder-architecture.js']) {
	expect(relative, 'new MutationObserver(scheduleShell)');
	expect(relative, 'observerStats');
}

const builderDir = path.join(root, 'includes/Builder');
if (fs.existsSync(builderDir)) {
	for (const file of fs.readdirSync(builderDir)) {
		if (/WebsiteBuilder.*V[4-9]/.test(file)) fail(`Do not create another numbered builder generation: ${file}`);
	}
}

if (!process.exitCode) console.log('[runtime-modules] Runtime consolidation contracts look consistent.');
