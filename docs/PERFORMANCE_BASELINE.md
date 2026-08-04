# Performance Baseline

Recorded: 2026-08-04 after the native Gutenberg refactor.

## Environment

| Item | Value |
| --- | --- |
| Kernel/architecture | Linux 6.12.13, x86_64 |
| Available CPUs | 9 |
| Reported memory | 16,704,468 kB |
| Local Node/npm | Node 24.14.0, npm 11.9.0 |
| Project CI Node | Node 22 (`.nvmrc`) |
| WordPress/PHP/browser runtime | Not available locally; CI configured |

## Static asset measurements

Measurements use production output from `npm run build` and GNU `gzip -c`.

| Asset | Raw bytes | Gzip bytes | Budget/result |
| --- | ---: | ---: | --- |
| `assets/css/frontend.css` | 200 | 147 | PASS: below 40 KB gzip |
| Frontend CSS plus default inline design rules | 901 | 364 | Combined-source measurement; emitted only on Canvas Pages |
| `build/editor.js` | 6,071 | 2,341 | Admin-only; 49.5% raw reduction from 0.2 |
| `build/editor.css` | 886 | 421 | Admin-only; sidebar styles only |
| `build/container.js` | 4,565 | 1,421 | Editor-only block registration |

The public frontend enqueues no Cresco editor JavaScript. Frontend CSS is conditional on singular Canvas Pages. Container block CSS is block-specific and loaded through WordPress block metadata. Removing the duplicate Page editor shell reduced the editor JavaScript by 5,944 raw bytes and removed duplicate document-state/rendering work in favor of Gutenberg's already-loaded runtime.

## Runtime budgets

| Target | Result |
| --- | --- |
| Editor usable under 2.5 s | NOT TESTED |
| Typing response p95 under 50 ms | NOT TESTED |
| Block selection p95 under 100 ms | NOT TESTED |
| Normal Page save under 1.5 s | NOT TESTED |
| 500-block Page remains usable | NOT TESTED |
| Frontend CLS under 0.1 | NOT TESTED |
| Release regression no greater than 5% | NOT TESTED; no prior measured release |

Gate 6 remains `NOT VERIFIED` until browser/runtime and large-document measurements exist, despite the static CSS budget passing.
