import { readFile, readdir } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';

const errors = [];

async function walkPhp( directory ) {
	const entries = await readdir( directory, { withFileTypes: true } );
	const files = [];
	for ( const entry of entries ) {
		const target = path.join( directory, entry.name );
		if ( entry.isDirectory() ) {
			files.push( ...( await walkPhp( target ) ) );
		} else if ( entry.isFile() && entry.name.endsWith( '.php' ) ) {
			files.push( target.replaceAll( path.sep, '/' ) );
		}
	}
	return files;
}

function requireTokens( label, source, tokens ) {
	for ( const token of tokens ) {
		if ( ! source.includes( token ) ) {
			errors.push( `${ label } is missing production contract token: ${ token }` );
		}
	}
}

const phpFiles = await walkPhp( 'includes' );
const publicRouteFiles = new Set( [
	'includes/Forms/FormBuilder.php',
	'includes/Forms/FormCompletion.php',
	'includes/Forms/FormEnhancements.php',
	'includes/Dynamic/InteractiveQuery.php',
	'includes/Dynamic/DynamicCompletion.php',
] );

for ( const file of phpFiles ) {
	const source = await readFile( file, 'utf8' );
	const routeCalls = source.match( /register_rest_route\s*\(/g ) || [];
	if ( ! routeCalls.length ) {
		continue;
	}
	const permissionCallbacks = source.match(
		/['"]permission_callback['"]\s*=>/g
	) || [];
	if ( permissionCallbacks.length < routeCalls.length ) {
		errors.push(
			`${ file } registers ${ routeCalls.length } REST route(s) but declares only ${ permissionCallbacks.length } permission callback(s).`
		);
	}
	if (
		source.includes( "'permission_callback' => '__return_true'" ) &&
		! publicRouteFiles.has( file )
	) {
		errors.push(
			`${ file } exposes an anonymous REST callback outside the approved public-route modules.`
		);
	}
}

const publicContracts = {
	'includes/Forms/FormBuilder.php': [ '/forms/submit' ],
	'includes/Forms/FormCompletion.php': [ '/forms/verify-captcha' ],
	'includes/Forms/FormEnhancements.php': [ '/forms/submit-multipart' ],
	'includes/Dynamic/InteractiveQuery.php': [ '/dynamic/interactive-query' ],
	'includes/Dynamic/DynamicCompletion.php': [ '/dynamic/facet-counts' ],
};
for ( const [ file, routes ] of Object.entries( publicContracts ) ) {
	const source = await readFile( file, 'utf8' );
	requireTokens( file, source, routes );
	if ( ! source.includes( "'permission_callback' => '__return_true'" ) ) {
		errors.push(
			`${ file } no longer declares its expected public REST boundary explicitly.`
		);
	}
}

const security = await readFile(
	'includes/Security/SecurityHardening.php',
	'utf8'
);
requireTokens( 'SecurityHardening', security, [
	"add_filter( 'rest_pre_dispatch'",
	"add_filter( 'rest_post_dispatch'",
	'MAX_DEFAULT_JSON_BYTES',
	'MAX_FORM_JSON_BYTES',
	'MAX_DYNAMIC_JSON_BYTES',
	'MAX_MULTIPART_BYTES',
	'IDEMPOTENCY_PENDING_TTL',
	'finalize_idempotent_request',
	'validate_public_shape',
	'validate_public_https_url',
	'FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE',
	"$args['redirection']         = 0",
	"$args['reject_unsafe_urls']  = true",
	"$args['sslverify']           = true",
	"$args['limit_response_size'] = 65536",
	'redact_sensitive',
] );

const uploads = await readFile(
	'includes/Security/UploadSecurity.php',
	'utf8'
);
requireTokens( 'UploadSecurity', uploads, [
	'MAX_UPLOAD_BYTES',
	'MAX_UPLOADS',
	'allowed_mimes',
	'wp_check_filetype_and_ext',
	'actual_mime',
	'has_dangerous_extension',
	'cresco_upload_executable_payload',
	'cresco_upload_image_polyglot',
	'cresco_upload_active_pdf',
	'private_root',
	'path_is_within',
	'check_admin_referer',
	'X-Content-Type-Options: nosniff',
] );

const forms = await readFile(
	'includes/Forms/FormAdministration.php',
	'utf8'
);
requireTokens( 'FormAdministration', forms, [
	'MAX_EXPORT_ROWS',
	'MAX_CELL_BYTES',
	'safe_csv_cell',
	'wp_privacy_personal_data_exporters',
	'wp_privacy_personal_data_erasers',
	'UploadSecurity::delete_for_submission',
] );

const webhook = await readFile(
	'includes/Forms/FormEnhancements.php',
	'utf8'
);
requireTokens( 'FormEnhancements', webhook, [
	'SecurityHardening::validate_public_https_url',
	"'redirection' => 0",
	"'reject_unsafe_urls' => true",
	"'limit_response_size' => 65536",
	'X-Cresco-Signature',
	'X-Cresco-Delivery-Id',
	'log_webhook_failure',
] );

const plugin = await readFile( 'includes/Plugin.php', 'utf8' );
requireTokens( 'Plugin downgrade boundary', plugin, [
	'Migrator::is_downgrade()',
	'Fail closed on downgrade',
	'render_failure_notice',
] );

const migrator = await readFile( 'includes/Migration/Migrator.php', 'utf8' );
requireTokens( 'Migrator', migrator, [
	'SNAPSHOT_OPTION',
	'LOCK_TTL',
	'is_downgrade',
	'downgrade_error',
	'cresco_canvas_migration_failed',
	'cresco_canvas_before_migration',
	'cresco_canvas_after_migration',
] );

const uninstall = await readFile( 'uninstall.php', 'utf8' );
requireTokens( 'Uninstall', uninstall, [
	'UninstallPolicy::owned_post_types()',
	'UninstallPolicy::owned_options()',
	'UninstallPolicy::owned_post_meta_keys()',
	'removeDataOnUninstall',
	'User-authored posts and page body content are never removed',
	'switch_to_blog',
	'restore_current_blog',
] );

const releaseFiles = await readFile( 'scripts/release-files.mjs', 'utf8' );
for ( const policy of [
	'docs/SECURITY.md',
	'docs/PRIVACY.md',
	'docs/UPGRADE_ROLLBACK.md',
	'docs/PRODUCTION_HARDENING_VERIFICATION.md',
] ) {
	if ( ! releaseFiles.includes( `'${ policy }'` ) ) {
		errors.push( `Release ZIP allowlist is missing ${ policy }` );
	}
}

const securityDoc = await readFile( 'docs/SECURITY.md', 'utf8' );
requireTokens( 'Security documentation', securityDoc, [
	'## REST route inventory',
	'## File upload policy',
	'## Webhook SSRF and delivery policy',
	'## CSV export policy',
	'## Dynamic-query resource policy',
	'## AI/import security boundary',
	'time-of-check/time-of-use',
] );

const privacyDoc = await readFile( 'docs/PRIVACY.md', 'utf8' );
requireTokens( 'Privacy documentation', privacyDoc, [
	'## Data ownership',
	'## WordPress personal-data exporter and eraser',
	'## Uninstall',
	'post_content',
] );

const rollbackDoc = await readFile( 'docs/UPGRADE_ROLLBACK.md', 'utf8' );
requireTokens( 'Upgrade/rollback documentation', rollbackDoc, [
	'## Migration failure and retry',
	'## Downgrade detection',
	'## Multisite behavior',
	'## Uninstall and rollback invariants',
] );

const verificationDoc = await readFile(
	'docs/PRODUCTION_HARDENING_VERIFICATION.md',
	'utf8'
);
requireTokens( 'Production hardening verification runbook', verificationDoc, [
	'## 2. REST authentication and public-route boundary',
	'## 3. Payload, rate, and idempotency verification',
	'## 4. Private upload storage',
	'## 5. Webhook SSRF and delivery',
	'## 6. CSV export',
	'## 7. Privacy and retention',
	'## 8. Migration, downgrade, and rollback',
	'## 9. Deactivation, uninstall, and multisite',
] );

for ( const testFile of [
	'tests/php/SecurityHardeningTest.php',
	'tests/php/UploadSecurityTest.php',
	'tests/php/CsvSecurityTest.php',
	'tests/php/RestPermissionsTest.php',
	'tests/php/PrivacyLifecycleTest.php',
	'tests/php/LifecycleMultisiteTest.php',
	'tests/php/MigrationHistoricalFixtureTest.php',
] ) {
	try {
		await readFile( testFile, 'utf8' );
	} catch {
		errors.push(
			`Production hardening regression suite is missing ${ testFile }`
		);
	}
}

if ( errors.length ) {
	process.stderr.write( `${ errors.join( '\n' ) }\n` );
	process.exit( 1 );
}

process.stdout.write(
	`Production hardening contract checked across ${ phpFiles.length } PHP files.\n`
);
