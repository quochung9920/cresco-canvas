# Cresco Canvas Privacy and Data Retention

## Data ownership

Cresco Canvas distinguishes plugin-owned records from user-authored WordPress content.

Cresco-owned records/resources include:

- `cresco_submission` private form-submission posts;
- `cresco_upload` private upload ownership records and their files;
- `cresco_revision` Cresco Session revision records;
- Cresco options, migration state/backups, webhook failure metadata and scoped transients;
- Cresco-specific post/user metadata documented by `Lifecycle\\UninstallPolicy`.

Normal WordPress `post` and `page` records are **not Cresco-owned**. User-authored `post_content` is never an uninstall cleanup target.

## Form submissions

A form stores submission data only when `storeSubmissions` is enabled in its signed server-authored configuration. Stored records are private and include:

- sanitized field values;
- the owning form ID;
- a deletion timestamp derived from the form retention policy.

Default retention is **30 days**. The signed form configuration can choose 1-365 days. The daily retention job removes expired Cresco submissions in bounded batches and cascades deletion to private uploads linked to the submission.

Email and optional webhook delivery can transmit submitted values to the destinations configured by the site administrator. Operators are responsible for configuring lawful recipients, retention and data-processing arrangements for those external systems.

## Uploaded files

New form uploads are stored in Cresco private storage outside the web document root and represented by private `cresco_upload` records. They are not public Media Library URLs.

Each upload records an expiration timestamp and, after the submission is stored, the linked submission ID. The daily cleanup job deletes expired orphan/private upload records and files in bounded batches. Erasing a matching form submission also deletes its linked private uploads.

Legacy Media Library attachments marked `_cresco_form_upload=1` remain recognized for cleanup/erasure compatibility.

## Webhook retry state and failure logs

Failed webhook delivery stores a short-lived opaque retry token in WordPress transients. The transient contains the minimum delivery state necessary to retry and expires after one hour. Cron arguments contain only the retry token and attempt number, not the form payload or webhook secret.

Webhook failure logs contain only operational metadata: form ID, destination host, attempt count, response status/reason and timestamp. They do not contain submitted values, full destination URLs/query strings, webhook secrets, authorization headers, cookies, CAPTCHA secrets or passwords.

## CSV exports

CSV export is an administrator action protected by `manage_options` and an admin nonce. Export is capped at 2,000 records and formula-like spreadsheet cells are neutralized before output. CSV files may contain private submission data, so administrators must store and transmit exports according to their site's privacy policy.

## WordPress personal-data exporter and eraser

Cresco registers a WordPress personal-data exporter and eraser for form submissions.

The exporter:

- normalizes the requested email address;
- searches private Cresco submissions in bounded pages of 100;
- recursively matches the email in nested submitted values;
- returns matching submission fields in the WordPress privacy-export format.

The eraser:

- uses a two-pass marker so deletion does not skip later records while paging;
- removes matching private submissions;
- removes linked private/legacy Cresco uploads;
- reports `items_removed=true` when a record was actually deleted.

No ordinary WordPress page/post is erased by the Cresco privacy eraser.

## Deactivation and reactivation

Deactivation preserves all Cresco data. It removes migration locks and scheduled Cresco background work. Reactivation reruns idempotent migrations as necessary and recreates periodic retention jobs.

## Uninstall

Default uninstall behavior is **preserve data**. Scheduled Cresco jobs are cleared, but stored Cresco data remains unless the administrator explicitly enabled `removeDataOnUninstall` before uninstalling.

Explicit cleanup deletes only resources on the Cresco ownership allowlist. It does not delete `post` or `page` records and does not alter user-authored `post_content`.

On multisite, uninstall processes each site's own tables in bounded batches using `switch_to_blog()`/`restore_current_blog()`. Cleanup decisions are read from each site's Cresco settings, so one site's opt-in does not authorize deletion of another site's data.

## Site-operator responsibilities

Before collecting production form data, document:

1. what each form collects and why;
2. whether submission storage is enabled;
3. the configured retention period;
4. external email/webhook recipients and processors;
5. the private upload directory and backup policy;
6. how WordPress privacy export/erasure requests are handled;
7. whether uninstall should preserve or explicitly clean Cresco-owned data.
