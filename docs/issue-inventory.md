# Better Font Awesome issue inventory

Last reviewed: 2026-08-29

This inventory groups recurring reports from the first ten pages of the [WordPress.org support forum](https://wordpress.org/support/plugin/better-font-awesome/) with the repository's open GitHub issues. A forum topic is evidence of a user problem, not proof of a single root cause. Reproduce each cluster before closing individual topics.

## Priority model

- P0: security, data integrity, widespread breakage, or a prerequisite for a safe release
- P1: major missing functionality or a commonly reported workflow failure
- P2: important compatibility, privacy, performance, or operational improvement
- P3: useful enhancement that can follow stabilization

## Prioritized clusters

| ID | Priority | Cluster | Evidence | Proposed outcome |
| --- | --- | --- | --- | --- |
| BFA-001 | P0 | Live release data is stale or unavailable | Repeated [403 API failures](https://wordpress.org/support/topic/error-fetching-data-from-api-2/), [429 failures](https://wordpress.org/support/topic/429-too-many-requests-7/), remote CSS failures, and GitHub [#27](https://github.com/MickeyKay/better-font-awesome/issues/27) | Move BFAL to validated, asynchronous stale-while-revalidate data with TLS verification, locking, backoff, a last-known-good cache, and a bundled emergency fallback. Resolve an explicit supported Font Awesome major instead of `latest`. |
| BFA-002 | P0 | Security and release safety | BFAL disables TLS certificate verification. The plugin's AJAX settings endpoint verifies a nonce but does not explicitly verify a capability. Previous forum security reports show this area is high sensitivity. | Complete a focused security review, add capability checks and explicit input handling, run Plugin Check, and verify the production archive contents before release. |
| BFA-003 | P0 | Current WordPress and PHP compatibility | A current report uses [WordPress 7.0.2 and PHP 8.3](https://wordpress.org/support/topic/wp-version-compatibility-icons-not-rendering-when-nested-in-another-shortcode/). Several topics report the plugin as untested or abandoned. | Test WordPress 6.5 through current WordPress with PHP 7.4 through 8.4. Triage PHP warnings, activation, settings, shortcode output, and upgrades. Investigate nested shortcode behavior separately because outer shortcode parsing can be owned by the host plugin. |
| BFA-004 | P1 | No native block editor experience | Users explicitly report [no icon block](https://wordpress.org/support/topic/no-icon-block-after-instalation/) and the current UI is TinyMCE-era. | Add an API v3 dynamic block using `block.json`. Store semantic icon attributes and render through the existing PHP output contract so current icon data can update without rewriting posts. Include accessible decorative and labelled modes. |
| BFA-005 | P1 | Classic editor icon picker and search failures | Multiple [selector failures](https://wordpress.org/support/topic/icon-selector-not-working-in-editor-2/) and [search failures](https://wordpress.org/support/topic/search-field-no-longer-works/) recur across the forum. | Reproduce against supported WordPress versions, update or replace the picker dependency, remove deprecated editor assumptions, and cover the picker with browser tests. Keep shortcode insertion available for Classic Editor users. |
| BFA-006 | P1 | Release source and version mismatch | GitHub [#28](https://github.com/MickeyKay/better-font-awesome/issues/28) reports WordPress.org and GitHub release differences. The old Grunt and SVN flow was interactive and CI was Travis-only. | Produce one deterministic archive from a tag, include only production BFAL files, compare the archive to WordPress.org trunk, and publish checksums and a release checklist. |
| BFA-007 | P2 | Local hosting, privacy, and CDN choice | Users request [local CSS and fonts](https://wordpress.org/support/topic/disable-cdn-for-css-files/) for privacy and policy reasons. Font Awesome now supports Free v7 through packages, but its legacy CDN ends at v5. | First make the remote Free path reliable and disclose external services. Then add an opt-in, updateable local asset cache or packaged self-host mode with licensing and disk cleanup defined. |
| BFA-008 | P2 | Theme, builder, ACF, and multiple Font Awesome conflicts | Forum reports mention ACF editor fields, Divi, WPBakery, widgets, and sites loading multiple Font Awesome generations. Old closed GitHub issues [#7](https://github.com/MickeyKay/better-font-awesome/issues/7) and [#8](https://github.com/MickeyKay/better-font-awesome/issues/8) confirm this class of integration risk. | Define supported integration boundaries, make conflict removal opt-in and narrowly scoped, add a diagnostics screen, and test representative Classic Editor, block editor, and builder scenarios. |
| BFA-009 | P2 | Shortcode registry and runtime performance | GitHub [#9](https://github.com/MickeyKay/better-font-awesome/issues/9) reports a large `$shortcode_tags` entry. Current release metadata can also be fetched during request handling. | Profile initialization and frontend requests. Keep only one shortcode callback, avoid transforming the full icon catalog unless a picker needs it, and ensure normal frontend traffic never waits on remote metadata. |
| BFA-010 | P3 | Font Awesome Pro and custom kits | Pro and kit support appears in older requests but adds authentication, licensing, private registry, and support complexity. | Defer until the Free path, block, and release process are stable. Design credentials so secrets are never committed or exposed client-side. |
| BFA-011 | P3 | Styling and composition | Topics request color controls, stacking, mobile behavior, and broader style support. | Use native block supports for color, spacing, dimensions, alignment, and accessible labels. Add advanced composition only after the basic block is stable. |

## Open GitHub issue disposition

| Issue | Current assessment | Action |
| --- | --- | --- |
| [#28 release versions differ](https://github.com/MickeyKay/better-font-awesome/issues/28) | Still relevant | Verify as BFA-006 before the next release. |
| [#27 jsDelivr API stopped](https://github.com/MickeyKay/better-font-awesome/issues/27) | The old API path was replaced, but remote reliability remains unresolved | Supersede with BFA-001 after the BFAL fix ships. |
| [#23 jQuery Migrate deprecation](https://github.com/MickeyKay/better-font-awesome/issues/23) | Reporter noted it was addressed in 2.0 | Reproduce, add a browser smoke test, then close with the validating release. |
| [#19 beta activation fatal](https://github.com/MickeyKay/better-font-awesome/issues/19) | Report targets a 2.0 beta and mixed activation with 1.7.4 | Reproduce clean install and upgrade paths, then close or replace with a current trace. |
| [#9 large shortcode data](https://github.com/MickeyKay/better-font-awesome/issues/9) | Needs measurement | Track under BFA-009. |
| [#6 Composer dependency flow](https://github.com/MickeyKay/better-font-awesome/issues/6) | Implemented in current releases | Verify archive contents and close under BFA-006. |

## Pragmatic delivery order

1. Finish CI, security checks, deterministic packaging, and compatibility smoke tests.
2. Update BFAL in its own repository to satisfy the live-update contract and release it independently.
3. Integrate the new BFAL version here and run failure-mode, upgrade, and release-archive testing.
4. Ship a conservative maintenance release and monitor support reports.
5. Build the dynamic Free icon block and browser-test both editor families.
6. Add privacy/local-hosting and diagnostics improvements.
7. Revisit Pro, kits, and advanced styling.

For BFAL work, use a separate Conductor project and workspace for `better-font-awesome-library`. Test and tag BFAL independently, then update the Composer constraint and lockfile in this plugin. This preserves the library's other consumers and gives each repository its own review and release history.
