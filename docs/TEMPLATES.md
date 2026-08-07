# Templates POC

This POC adds a small Template Manager component and three sample templates located in `/templates`:

- basic.json
- marketing.json
- blog.json

Usage:
- Import the `TemplateManager` React component and provide an `onImport` callback that integrates the selected template into the editor session state.
- For production, consider exposing templates via a REST endpoint and adding metadata (thumbnail, tags).
