import { createHash } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';
import process from 'node:process';
const errors=[];const read=(f)=>readFile(f,'utf8');const hash=(v)=>createHash('sha256').update(v).digest('hex');
const [interchange,componentSync,v3Php,themeSession,plugin,source,build,css,manifestText,releaseFiles,pkgText,docs]=await Promise.all([
 read('includes/Builder/WebsiteBuilderInterchange.php'),read('includes/Builder/WebsiteBuilderComponentSync.php'),read('includes/Builder/WebsiteBuilderComprehensiveV3.php'),read('includes/Theme/ThemeSessionBridge.php'),read('includes/Plugin.php'),read('runtime-src/build/website-builder-comprehensive-v3.js'),read('build/website-builder-comprehensive-v3.js'),read('assets/css/website-builder-comprehensive-v3.css'),read('runtime-src/manifest.json'),read('scripts/release-files.mjs'),read('package.json'),read('docs/WEBSITE_BUILDER_V3.md')
]);
for(const file of ['runtime-src/build/website-builder-comprehensive-v3.js','build/website-builder-comprehensive-v3.js','scripts/check-comprehensive-v3.mjs']){const r=spawnSync(process.execPath,['--check',file],{encoding:'utf8'});if(r.status!==0)errors.push(`${file}: ${r.stderr||r.stdout||'syntax check failed'}`);}
if(hash(source)!==hash(build))errors.push('Comprehensive V3 source/build mismatch.');
for(const t of["const SCHEMA = 'cresco-interchange/v1'",'PatchValidator::validate','ScopeResolver::resolve',"'replace-page'",'dependency_warnings'])if(!interchange.includes(t))errors.push(`Interchange missing ${t}`);
for(const t of["'/website-builder/components/sync'",'IdRemapper::remap_subtree',"'componentId'",'syncedInstances'])if(!componentSync.includes(t))errors.push(`Component sync missing ${t}`);
for(const t of['replace_legacy_compiled_css','WebsiteBuilderCssCompiler::compile','normalize_runtime_capabilities','has_woocommerce',"'/website-builder/v3/diagnostics/"])if(!v3Php.includes(t))errors.push(`V3 integration missing ${t}`);
for(const t of["const BLOCK     = 'cresco/theme-session'",'register_block_type',"'/website-builder/theme-session/(?P<postId>\\d+)'",'WebsiteRenderer::render_document','WebsiteBuilderCssCompiler::compile','decorate_theme_template_responses','cresco_theme_preview'])if(!themeSession.includes(t))errors.push(`Theme Session bridge missing ${t}`);
for(const t of['WebsiteBuilderInterchange','WebsiteBuilderComponentSync','WebsiteBuilderComprehensiveV3','ThemeSessionBridge','( new WebsiteBuilderInterchange() )->register();','( new WebsiteBuilderComponentSync() )->register();','( new WebsiteBuilderComprehensiveV3() )->register();','( new ThemeSessionBridge() )->register();'])if(!plugin.includes(t))errors.push(`Plugin registration missing ${t}`);
for(const t of['Cresco Comprehensive V3','Preview Diff','Pixel 100%','Scan Canvas Accessibility','Sync linked component instances','Run Diagnostics','cresco-session/v1','cc-v3-live-custom-css'])if(!source.includes(t))errors.push(`V3 runtime missing ${t}`);
for(const t of['.cc-v3-modal','.cc-v3-grid','.cc-v3-toast','.cc-widget-form','prefers-reduced-motion','forced-colors'])if(!css.includes(t))errors.push(`V3 CSS missing ${t}`);
const manifest=JSON.parse(manifestText);if(!manifest.reviewed.includes('website-builder-comprehensive-v3.js'))errors.push('Runtime manifest does not review website-builder-comprehensive-v3.js.');
for(const t of["'assets/css/website-builder-comprehensive-v3.css'","'build/website-builder-comprehensive-v3.js'","'docs/WEBSITE_BUILDER_V3.md'"])if(!releaseFiles.includes(t))errors.push(`Release allowlist missing ${t}`);
const pkg=JSON.parse(pkgText);if(pkg.scripts?.['check:comprehensive-v3']!=='node scripts/check-comprehensive-v3.mjs')errors.push('package.json is missing check:comprehensive-v3.');if(!String(pkg.scripts?.['check:quality']||'').includes('check:comprehensive-v3'))errors.push('check:quality does not include Comprehensive V3 gate.');
for(const t of['Session-native bridge','cresco/theme-session','WooCommerce','Preview Diff','1.0.0-rc.1'])if(!docs.includes(t))errors.push(`V3 documentation missing ${t}`);
if(errors.length){process.stderr.write(errors.join('\n')+'\n');process.exit(1);}process.stdout.write('Comprehensive Builder V3 interchange, parity, Theme Session bridge, component sync, accessibility/performance diagnostics, source/build parity and release inventory verified.\n');
