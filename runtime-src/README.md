# Reviewed runtime source

`runtime-src/build/` is the authoritative source for reviewed JavaScript, CSS, and WordPress asset manifests that are shipped as runtime files but are not produced by the current webpack entry graph.

Rules:

- Edit the reviewed source here, never the matching `build/` file directly.
- `npm run build` compiles webpack-owned entries and then copies reviewed runtime source into `build/`.
- `npm run check:build-integrity` requires byte-for-byte parity between reviewed source and its built counterpart and verifies that every production `build/` file has an owner.
- Files listed under `generated` in `manifest.json` remain owned by the normal source tree and webpack.
- `runtime-src/` is never included in the production ZIP.

This directory is an explicit transition away from manually maintained `build/` outputs. Future runtime refactors may move reviewed files into TypeScript modules, but release candidates must remain reproducible while that work is separate from release engineering.
