# Better Font Awesome roadmap

Better Font Awesome provides a stable, backward-compatible way to use Font Awesome Free in WordPress while keeping icon metadata current without making normal page requests depend on a remote service.

This roadmap describes direction, not delivery dates. Concrete defects and supporting evidence are tracked in [the issue inventory](docs/issue-inventory.md) and GitHub issues.

## Product principles

- Preserve existing shortcodes, settings, filters, and rendered content.
- Keep the shortcode as the canonical content contract, including for modern editor features.
- Keep normal WordPress requests independent of Font Awesome metadata HTTP calls.
- Maintain validated persistent metadata, last-known-good data, locking, backoff, and a bundled emergency fallback.
- Develop and release Better Font Awesome Library independently because other projects consume its public API.
- Support Font Awesome Free first. Consider Pro and kits only after the Free experience is stable.
- Require focused automated tests, production-package checks, manual acceptance coverage, and a rollback path for compatibility-sensitive releases.

## Shipped foundation

The 2.1 generation established the baseline for subsequent work:

- Reliable asynchronous Font Awesome 5 Free metadata updates with cached and bundled fallbacks.
- Current supported WordPress and PHP compatibility coverage.
- Hardened settings persistence and release packaging.
- A separately tested and released BFAL dependency.
- A documented, reproducible WordPress.org release process.

## Now

### Monitor and consolidate the 2.1 release

- Triage new support reports against the released package before changing behavior.
- Close or update legacy GitHub issues whose underlying work shipped in 2.1.
- Reproduce remaining Classic Editor picker, nested-shortcode, and performance reports on supported versions.

### Improve the contributor and release experience

- Make a fresh local WordPress environment work with one documented command and predictable credentials.
- Turn the guarded release procedure into reusable automation while keeping publication authorization explicit.
- Add a valid WordPress Playground `blueprint.json` so the WordPress.org Live Preview can be enabled and tested.
- Keep agent guidance portable and minimize duplicated tool-specific instructions.

## Next

### Add a native block editor experience

- Add an API v3 dynamic icon block registered through `block.json`.
- Store semantic icon choices and render through the existing PHP and shortcode-compatible output contract.
- Provide searchable Font Awesome Free icon selection and accessible decorative or labelled modes.
- Add browser coverage for insertion, editing, serialization, frontend rendering, and editor combinations that include legacy `wp_editor()` instances.
- Preserve the Classic Editor picker and existing shortcode workflows.

## Following

### Support current Font Awesome Free generations

- Research the official Font Awesome 6 and 7 Free metadata and asset channels before selecting an implementation.
- Preserve the live-update value proposition rather than shipping a plugin release for every icon-data update.
- Define migration and coexistence behavior for existing Font Awesome 4 and 5 content.
- Test caching, asset loading, conflict behavior, accessibility, and rollback independently from the initial block release.

### Improve privacy, diagnostics, and integrations

- Evaluate opt-in local asset hosting with clear update, licensing, disk-use, and cleanup behavior.
- Add narrowly scoped diagnostics for active Font Awesome versions, duplicate assets, metadata freshness, cron health, and common integration conflicts.
- Document and test boundaries with themes, builders, ACF fields, widgets, Classic Editor, and hybrid editor screens.
- Measure initialization and picker performance before changing the shortcode or icon-catalog architecture.

## Later

- Font Awesome Pro and custom kit support with secure credential handling.
- Advanced styling and composition after the basic block and Free asset path are stable.
- Broader builder-specific integrations where evidence shows durable user value.

## Tracking work

Use this file for durable direction, [the issue inventory](docs/issue-inventory.md) for evidence and prioritization, and GitHub issues or pull requests for implementation status. Do not turn release-specific checklists, commit hashes, or temporary investigation notes into permanent roadmap commitments.
