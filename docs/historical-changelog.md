# Better Font Awesome historical changelog

This file preserves release history previously included in the WordPress.org listing. The listing keeps only the current release so that it remains concise and within the directory's recommended size.

## 2.1.0

- Integrates the public stable Better Font Awesome Library 2.1.0 while preserving existing settings and shortcode content.
- Refreshes Font Awesome 5 Free metadata asynchronously through WP-Cron, with no metadata API wait on normal frontend, admin, editor, REST, or shortcode requests.
- Adds validated durable last-known-good metadata, bundled fallback behavior, failure backoff, and recovery.
- Hardens multisite lifecycle handling and settings persistence while retaining Classic Editor, shortcode, frontend, and hybrid `wp_editor()` compatibility.
- Requires WordPress 6.5 or newer and PHP 7.4 or newer, and is tested with WordPress through 7.1.

## 2.0.4

- Bump BFAL to properly escape attributes.
- Add unit tests.

## 2.0.3

- Fix broken icon text selection.
- Improve admin settings success and error message logic.

## 2.0.2

- Fix CSRF vulnerability.

## 2.0.1

- Add necessary `@font-face` mappings to ensure site-specific CSS and pseudo-elements render correctly.

## 2.0.0

- Add support for Font Awesome 5.
- Integrate with the Font Awesome GraphQL API for data fetching.
- Integrate with the Font Awesome CDN for CSS.
- Add an option to include the Font Awesome 4 CSS shim to support older icons, enabled by default for upgrades.
- Update the hard-coded fallback Font Awesome version.
- Change version checks to a 24-hour interval.
- Make admin notices dismissible.
- Lower the data-fetch timeout.
- Remove legacy options that are no longer relevant, including version selection and minification opt-out.
- Add compatibility tests.

## 1.7.6

- Revert to the 1.7.4 codebase.
- Remove calls to jQuery's `ready()` method to support newer jQuery versions.

## 1.7.5

This was marked as a bad build.

- Remove calls to jQuery's `ready()` method to support newer jQuery versions.
- Update the `fontawesome-iconpicker` dependency to repair broken icon selection.

## 1.7.4

- Revert an accidental bump to the underlying Better Font Awesome Library dependency.

## 1.7.3

- Add an admin notice inviting beta testers.

## 1.7.2

- Update the Tested up to value to WordPress 5.5.

## 1.7.1

- Fix hiding and showing admin notices.

## 1.7.0

- Update the fallback to Font Awesome 4.7.0.
- Switch from Git submodules to Composer dependency management for the core library.

## Archived upgrade notices

### 2.1.0

Reliability and compatibility release for Font Awesome 5 Free. Requires WordPress 6.5 or newer and PHP 7.4 or newer. Existing settings and shortcodes are preserved.

### 2.0.4

- Bump BFAL to properly escape attributes.
- Add unit tests.

### 2.0.3

- Fix broken icon text selection.
- Improve admin settings success and error message logic.

### 2.0.2

- Fix CSRF vulnerability.

### 2.0.1

- Add necessary `@font-face` mappings to ensure site-specific CSS and pseudo-elements render correctly.

### 2.0.0

Adds Font Awesome 5, GraphQL metadata, CDN CSS, an optional Font Awesome 4 shim, daily update checks, refreshed fallback data, dismissible notices, and compatibility tests.

### 1.7.6

- Revert to the 1.7.4 codebase.
- Remove calls to jQuery's `ready()` method to support newer jQuery versions.

### 1.7.5

This was marked as a bad build.

- Remove calls to jQuery's `ready()` method to support newer jQuery versions.
- Update the `fontawesome-iconpicker` dependency to repair broken icon selection.

### 1.7.4

- Revert an accidental bump to the underlying Better Font Awesome Library dependency.

### 1.7.3

- Add an admin notice inviting beta testers.

### 1.7.2

- Update the Tested up to value to WordPress 5.5.

### 1.7.1

- Fix hiding and showing admin notices.

### 1.7.0

- Update the fallback to Font Awesome 4.7.0.
- Switch from Git submodules to Composer dependency management for the core library.
