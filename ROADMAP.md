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

## Publicly shipped foundation

BFA 3.0.0 is publicly released and builds on the foundation established by the 2.1 generation:

- Reliable asynchronous Font Awesome 5 Free metadata updates with cached and bundled fallbacks.
- Current supported WordPress and PHP compatibility coverage.
- Hardened settings persistence and release packaging.
- A separately tested and released BFAL dependency.
- A documented, reproducible WordPress.org release process.
- A native API v3 dynamic icon block registered through `block.json`, with semantic attributes, server-side rendering, searchable Free icon selection, accessible decorative or labelled modes, and browser coverage.
- A channel-aware BFAL integration that defaults to Font Awesome 7 Free, serves packaged Font Awesome 7 assets immediately, refreshes compatible releases in the background, and preserves Font Awesome 4 and 5 content compatibility.

## Now

### Correct the cross-major legacy-transient warning

- Release BFAL 3.0.2 followed by BFA 3.0.1 as the immediate corrective sequence for the cross-major legacy-transient warning.

### Monitor and consolidate the 3.0 release

- Triage new support reports against the released package before changing behavior.
- Close or update legacy GitHub issues whose underlying work shipped in 3.0.
- Reproduce remaining Classic Editor picker, nested-shortcode, and performance reports on supported versions.

### Improve the contributor and release experience

- Make a fresh local WordPress environment work with one documented command and predictable credentials.
- Turn the guarded release procedure into reusable automation while keeping publication authorization explicit.
- Add a valid WordPress Playground `blueprint.json` so the WordPress.org Live Preview can be enabled and tested.
- Keep agent guidance portable and minimize duplicated tool-specific instructions.

## Next

### Refine the native block editor experience

- Add a dedicated Style control to the native Icon block, populated dynamically from the Free styles available for the selected icon. Preserve the existing `iconStyle` attribute, rendering behavior, saved content, and combined icon-search compatibility. Show only available Free styles such as Solid, Regular, and Brands. Do not expose Light, Thin, Duotone, Sharp, or other Pro-only families until Font Awesome Pro support is explicitly designed and approved.
- Refine alignment behavior and other block supports based on editor testing.
- Decide whether inline icon handling belongs in the block contract without changing saved shortcode compatibility.
- Extend browser coverage when new block behavior is approved.
- Preserve the Classic Editor picker and existing shortcode workflows while refining the block.

## Following

### Maintain current Font Awesome Free support

- Monitor the Font Awesome 7 Free metadata and asset providers without weakening the validated live-update architecture.
- Preserve the live-update value proposition rather than shipping a plugin release for every compatible icon-data update.
- Keep migration and coexistence behavior for existing Font Awesome 4 and 5 content covered as the current channel evolves.
- Investigate demand and compatibility requirements before considering a separate Font Awesome 6 channel; the current implementation does not claim comprehensive native Font Awesome 6 support.
- Continue testing caching, asset loading, conflict behavior, accessibility, and rollback independently from editor refinements.

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
