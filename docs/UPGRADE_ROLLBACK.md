# Cresco Canvas Upgrade, Downgrade, Rollback, and Multisite Lifecycle

## Lifecycle invariant

Cresco Canvas migrations may modify Cresco-owned settings/metadata, but lifecycle code must not rewrite or delete user-authored `post_content` as a migration/uninstall side effect.

The supported lifecycle is:

`clean install -> activation -> use -> deactivate -> reactivate -> upgrade -> migration -> rollback/downgrade detection -> uninstall`

## Clean install and activation

Activation validates plugin requirements and runs versioned migrations for the current site. Successful activation schedules daily submission/upload retention jobs.

For network activation on multisite, Cresco enumerates sites in batches of 100, switches to each site, runs that site's migration and schedules that site's jobs. Sites created later are initialized when the plugin is network-active.

If migration fails during activation, activation is stopped and the plugin is deactivated for the relevant site/network scope. The user receives a safe error message; page body content is not changed.

## Migration contract

Schema version is stored in `cresco_canvas_db_version`.

Migration behavior:

- a lock prevents concurrent migration writers and expires after 5 minutes if stale;
- a one-time `cresco_canvas_migration_backup` snapshot records the pre-migration Cresco settings and starting/target schema versions;
- migration steps execute in ascending order;
- after each successful step, the completed schema version is persisted immediately;
- a failed later step therefore retries from the last completed version rather than replaying prior completed steps;
- failure state records a non-secret error fingerprint, not the exception message/stack/private values;
- migrations are designed to be idempotent where possible.

Historical test fixtures cover old settings, malformed settings, a historical Session v1 document and malformed Session input.

## Upgrade

After a WordPress plugin upgrade completes, Cresco detects whether its plugin package was updated. It then runs the same per-site migration/scheduling path. Network-active installs process each site independently in bounded batches.

Recommended production procedure:

1. Back up database and the Cresco private upload directory.
2. Record the installed plugin and schema versions.
3. Deploy the new plugin package.
4. Activate/update and confirm migration state is `complete`.
5. Smoke-test the editor, frontend rendering, forms, private downloads, webhooks and retention jobs.
6. Keep the pre-deploy package and backup until verification is complete.

## Migration failure and retry

If migration state is `failed`:

1. Do not manually edit the stored schema version upward.
2. Record the displayed reference/error fingerprint.
3. Inspect server/PHP logs with normal secret-redaction procedures.
4. Resolve the underlying environment/data issue.
5. Retry activation or the normal migration path. Completed versions are not rerun.
6. If recovery is not possible, restore the pre-upgrade database/private-file backup and the matching plugin package.

## Downgrade detection

If `cresco_canvas_db_version` is greater than the schema version understood by the running plugin, Cresco enters a compatibility pause. It does **not** silently write the older format.

In this mode the normal Cresco editor/REST/domain services are not booted and administrators receive a warning. Recovery choices are:

- reinstall the plugin version that supports the stored schema; or
- restore a database/private-file backup captured before the newer schema was introduced, then install the matching older plugin package.

Do not lower `cresco_canvas_db_version` manually. A version number change does not reverse migrated data.

## Deactivation and reactivation

Deactivation is non-destructive. It removes migration locks and unschedules Cresco cleanup/retention/webhook-retry jobs, including retry events that have arguments. Settings, sessions, revisions, submissions and private uploads are preserved.

Reactivation reruns migration checks and schedules jobs idempotently.

## Multisite behavior

Cresco stores operational data per site. Network lifecycle operations use site batches of 100 and always pair `switch_to_blog()` with `restore_current_blog()`.

Required multisite verification:

- activate per-site and confirm only that site's options/meta are initialized;
- network-activate and confirm all existing sites initialize;
- create a new site while network-active and confirm it initializes;
- deactivate per-site and network-wide and confirm data is preserved but cron is cleared in the intended scope;
- upgrade a network-active plugin and confirm migration state independently on every site;
- uninstall with mixed `removeDataOnUninstall` settings and verify one site's cleanup decision does not delete another site's data.

## Uninstall and rollback invariants

Default uninstall preserves Cresco data. Explicit cleanup is opt-in and deletes only the resource types documented by `Lifecycle\\UninstallPolicy`.

Absolute invariant: **Cresco uninstall never deletes ordinary WordPress `post`/`page` records and never deletes or rewrites user-authored `post_content`.**

For rollback, restoring the matching database and private upload backup is the authoritative reversal path. The migration settings snapshot is an additional diagnostic/recovery aid, not a full database rollback mechanism.
