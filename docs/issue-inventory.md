# Better Font Awesome issue inventory

Last reviewed: 2026-09-01

This inventory groups recurring reports from the [WordPress.org support forum](https://wordpress.org/support/plugin/better-font-awesome/) with the repository's open GitHub issues. A forum topic is evidence of a user problem, not proof of a single root cause. Reproduce each cluster before changing behavior or closing individual topics.

Durable product direction lives in the [project roadmap](../ROADMAP.md). This inventory records current evidence and disposition without promising release dates.

## Priority model

- P0: security, data integrity, widespread breakage, or a prerequisite for a safe release
- P1: major missing functionality or a commonly reported workflow failure
- P2: important compatibility, privacy, performance, or operational improvement
- P3: useful enhancement that can follow stabilization

## Prioritized clusters

| ID | Priority | Cluster | Status | Evidence and next outcome |
| --- | --- | --- | --- | --- |
| BFA-001 | P0 | Live release data is stale or unavailable | Shipped in 2.1, monitor | The former path produced repeated [403 API failures](https://wordpress.org/support/topic/error-fetching-data-from-api-2/), [429 failures](https://wordpress.org/support/topic/429-too-many-requests-7/), remote CSS failures, and GitHub [#27](https://github.com/MickeyKay/better-font-awesome/issues/27). BFA 2.1 and BFAL 2.1 now use validated asynchronous stale-while-revalidate metadata with locking, backoff, persistent last-known-good data, and a bundled fallback. Monitor public behavior before further architecture changes. |
| BFA-002 | P0 | Security and release safety | Shipped in 2.1, maintain | BFA 2.1 restored TLS verification, hardened settings capabilities and input handling, added production archive audits, and expanded automated checks. Preserve these gates for subsequent releases. |
| BFA-003 | P0 | Current WordPress and PHP compatibility | Shipped in 2.1, maintain | The supported WordPress and PHP matrix, clean activation, upgrades, settings, shortcode output, and packaged-plugin behavior were verified for 2.1. Continue the matrix and triage new reports against the current release. |
| BFA-004 | P1 | No native block editor experience | Planned next | Users explicitly report [no icon block](https://wordpress.org/support/topic/no-icon-block-after-instalation/) and the current UI is TinyMCE-era. Add an API v3 dynamic block using `block.json`, semantic icon attributes, existing PHP rendering, accessible decorative and labelled modes, and editor browser tests. |
| BFA-005 | P1 | Classic Editor icon picker and search failures | Needs current reproduction | Multiple [selector failures](https://wordpress.org/support/topic/icon-selector-not-working-in-editor-2/) and [search failures](https://wordpress.org/support/topic/search-field-no-longer-works/) recur across older forum reports. Reproduce with BFA 2.1 on supported WordPress versions before changing the picker. Keep shortcode insertion available for Classic Editor users. |
| BFA-006 | P1 | Release source and version mismatch | Resolved in 2.1 | GitHub [#28](https://github.com/MickeyKay/better-font-awesome/issues/28) reported WordPress.org and GitHub release differences. The 2.1 Git tag, GitHub release, public WordPress.org package, dependency contents, and checksums were reconciled and verified. Maintain the guarded release procedure. |
| BFA-007 | P2 | Local hosting, privacy, and CDN choice | Future | Users request [local CSS and fonts](https://wordpress.org/support/topic/disable-cdn-for-css-files/) for privacy and policy reasons. Evaluate an opt-in updateable local asset cache or packaged self-host mode with licensing, storage, updates, and cleanup explicitly defined. |
| BFA-008 | P2 | Theme, builder, ACF, and multiple Font Awesome conflicts | Future | Reports mention ACF editor fields, Divi, WPBakery, widgets, and sites loading multiple Font Awesome generations. Define support boundaries, keep conflict removal opt-in and narrowly scoped, add diagnostics, and test representative Classic Editor, block editor, and hybrid scenarios. |
| BFA-009 | P2 | Shortcode registry and runtime performance | Measure first | GitHub [#9](https://github.com/MickeyKay/better-font-awesome/issues/9) reports a large `$shortcode_tags` entry. Profile initialization, shortcode registration, catalog transformations, and picker loading on BFA 2.1 before selecting a fix. Normal frontend traffic must continue to avoid metadata HTTP calls. |
| BFA-010 | P3 | Font Awesome Pro and custom kits | Deferred | Pro and kits add authentication, licensing, private registry, and support complexity. Revisit after the Free update path, block, and current Font Awesome generation support are stable. |
| BFA-011 | P3 | Styling and composition | Later block enhancement | Requests include color controls, stacking, mobile behavior, and broader style support. Start with appropriate native block supports and accessible labels. Add advanced composition only after the basic block is stable. |
| BFA-012 | P2 | Nested shortcode interoperability | Investigate ownership boundary | A current report describes an `[icon]` shortcode nested inside a third-party Heroic shortcode. Reproduce with an outer shortcode that does and does not execute `do_shortcode()` on its content. Change BFA only if the failure is within its parsing or rendering contract, and otherwise document the host shortcode's responsibility. |
| BFA-013 | P3 | WordPress.org Playground Live Preview | Planned enabling work | The Plugin Directory reports a missing or invalid `blueprint.json`, so Live Preview cannot be enabled. Add a schema-valid, deterministic Blueprint that installs or activates the production plugin, creates useful preview content, and is verified through WordPress Playground before enabling the directory toggle. |

## Open GitHub issue disposition

| Issue | Current assessment | Recommended action |
| --- | --- | --- |
| [#28 release versions differ](https://github.com/MickeyKay/better-font-awesome/issues/28) | Resolved by the verified 2.1 Git, GitHub, Composer dependency, SVN, and WordPress.org package publication | Close with links to the 2.1 release evidence. |
| [#27 jsDelivr API stopped](https://github.com/MickeyKay/better-font-awesome/issues/27) | Superseded by the BFA and BFAL 2.1 asynchronous metadata, validation, and fallback architecture | Close with the new architecture and failure-mode behavior documented. |
| [#23 jQuery Migrate deprecation](https://github.com/MickeyKay/better-font-awesome/issues/23) | The reporter noted it was addressed in 2.0, but the relevant picker path remains | Reproduce on BFA 2.1 and add browser coverage before closing. |
| [#19 beta activation fatal](https://github.com/MickeyKay/better-font-awesome/issues/19) | Targets a 2.0 beta and mixed activation with 1.7.4; current clean install and upgrade paths passed | Close unless a current release trace can reproduce it. |
| [#9 large shortcode data](https://github.com/MickeyKay/better-font-awesome/issues/9) | Still needs measurement on the current architecture | Track under BFA-009 and avoid speculative refactoring. |
| [#6 Composer dependency flow](https://github.com/MickeyKay/better-font-awesome/issues/6) | Implemented and production package contents were verified in 2.1 | Close with the current Composer and packaging workflow. |

## Pragmatic delivery order

1. Monitor BFA 2.1 and triage current support reports against the public package.
2. Close or update resolved legacy issues with release evidence.
3. Build the native dynamic Free icon block in a focused implementation tranche.
4. Research and design Font Awesome 6 and 7 Free support without weakening the live-update architecture.
5. Add local hosting, diagnostics, and representative integration coverage based on evidence.
6. Revisit Pro, kits, and advanced styling only after the Free experience is stable.

For BFAL work, use a separate Conductor project and workspace for `better-font-awesome-library`. Test and tag BFAL independently, then update the Composer constraint and lockfile in this plugin. This preserves the library's other consumers and gives each repository its own review and release history.
