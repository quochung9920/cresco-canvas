# Performance Gate — 1.0.0-rc.1

The previous Gutenberg-era static measurements are not a valid baseline for the current standalone Cresco Session editor. This document intentionally resets the release baseline rather than carrying forward unrelated numbers.

## Automated benchmark

`tests/performance/editor-performance.spec.ts` runs on Chromium through `playwright.performance.config.ts` and creates three document sizes:

- 50 nodes;
- 200 nodes;
- 500 nodes.

For each size it records:

| Metric | Meaning |
| --- | --- |
| `editorLoadMs` | navigation until the editor and expected node count are usable |
| `selectionMs` | click a representative widget until its Inspector is visible |
| `inspectorTabMs` | switch to the Inspector Style tab |
| `settingsTabMs` | open the Settings Center |
| `saveMs` | Update until the saved state is acknowledged |

The test writes `CRESCO_PERF_METRIC` JSON to the job log and attaches per-size JSON evidence.

## Initial safety ceilings

Before an evidence-based baseline exists, the test only rejects obvious freezes:

- editor load: 30 seconds;
- selection: 5 seconds;
- Inspector tab: 5 seconds;
- Settings tab: 5 seconds;
- save: 10 seconds.

These are anti-freeze ceilings, **not** commercial performance targets.

## Regression baseline rule

Status before the first successful controlled release run: **NOT RUN**.

After the first successful run on the release runner, record its commit, runner class, browser, WordPress/PHP versions, and metric artifacts. Only then establish a regression threshold. The threshold should be based on repeated measurements and normal CI variance; it must not be invented retroactively to make a release pass.

## Frontend review

The package verifier provides the exact production file inventory. Release review must additionally confirm:

- editor/admin-only modules are not enqueued on unrelated frontend requests;
- Cresco frontend assets remain conditional on relevant Cresco content;
- no new unconditional module or stylesheet is introduced without justification.

Asset-count/per-request measurements are still **NOT RUN** for this commit until workflow evidence is produced.
