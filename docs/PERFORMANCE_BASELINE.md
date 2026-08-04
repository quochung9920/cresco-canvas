# Performance Baseline

Recorded: 2026-08-03.

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
| `assets/css/frontend.css` | 494 | 225 | PASS: below 40 KB gzip |
| `build/editor.js` | 12,015 | 3,771 | Admin-only; no frontend budget |
| `build/editor.css` | 3,263 | Not recorded separately | Admin-only |
| `build/container.js` | 4,565 | 1,421 | Editor-only block registration |

The public frontend enqueues no Cresco editor JavaScript. Frontend CSS is conditional on singular Canvas Pages. Container block CSS is block-specific and loaded through WordPress block metadata.

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
