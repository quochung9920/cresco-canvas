# Cresco Canvas

**Build visually. Run natively.**

Cresco Canvas is a lightweight, native visual website builder for WordPress. It uses the WordPress block engine for content, keeps frontend output portable, and provides a focused builder interface designed to be easier to use than traditional page builders.

## Current status

This repository contains the first MVP foundation (`0.1.0`):

- Full-screen visual builder inside WordPress admin
- Native block content stored in `post_content`
- Responsive preview for 4K, desktop, laptop, tablet, and mobile
- Custom Cresco Container block
- Core WordPress blocks enabled inside the builder
- Element panel, structure navigator, starter templates, and inspector
- Global design settings and CSS variables
- REST API for loading and saving pages
- Lightweight frontend CSS with no React runtime

## Requirements

- WordPress 6.7 or newer
- PHP 8.1 or newer
- A block-based or classic WordPress theme

## Installation

1. Download or clone this repository.
2. Copy the `cresco-canvas` directory into `wp-content/plugins/`.
3. Activate **Cresco Canvas** in WordPress.
4. Open **Cresco Canvas** in the WordPress admin menu.
5. Choose a page and click **Edit in Canvas**.

No Node.js build is required for the MVP. The editor uses WordPress-provided JavaScript packages.

## Product principles

1. Native WordPress data, not a proprietary page format.
2. Small set of composable blocks instead of hundreds of widgets.
3. Design tokens and global styles before per-element overrides.
4. Responsive values inherit until explicitly overridden.
5. Frontend assets load only when needed.
6. React stays in the editor; the frontend remains semantic HTML and CSS.

## Roadmap

- `0.1.x`: editor stability, autosave, revisions, accessibility
- `0.2.x`: theme builder and display conditions
- `0.3.x`: synced components and template library
- `0.4.x`: ACF, custom fields, query builder, and conditional visibility
- `0.5.x`: WooCommerce and form integrations
- `1.0.0`: production-ready visual site builder

## License

GPL-2.0-or-later
