# Maintenance release readiness

This is the release gate for the first conservative maintenance release. It intentionally precedes block editor feature work.

## Automated gates

- [x] Project-local WordPress agent skills and durable `AGENTS.md` guidance
- [x] Docker-based `wp-env` development and test sites
- [x] Composer and npm lockfiles refreshed with no known dependency advisories
- [x] PHPCS using current WordPress Coding Standards
- [x] PHPStan level 5 on first-party runtime code
- [x] PHPUnit 9 compatibility and current local WordPress execution
- [x] GitHub Actions quality, compatibility, and official Plugin Check jobs defined
- [x] GitHub Actions public stable BFAL 2.1.0 and stable BFAL 2.0.3 rollback jobs are defined for WordPress 6.5, 6.7, and latest with PHP 7.4, 8.1, 8.3, and 8.4
- [x] PHPUnit uses only deterministic HTTP fixtures
- [ ] Browser smoke tests cover activation, settings save, Classic Editor picker, and shortcode rendering
- [x] Public BFAL rc.2 cache transition, CORS, CSSOM, Block Editor, traditional `wp_editor()`, Classic Editor, v4-shim, picker, insertion, and frontend rendering checks pass in one persistent browser profile
- [x] The canonical WordPress.org SVN release tree is built separately from the source tree and checked by CI

## Security gates

- [x] Add an explicit capability check to the AJAX settings endpoint
- [x] Remove the broad PHPCS nonce and input suppressions after fixing each finding
- [x] Enable TLS verification in public stable BFAL 2.1.0
- [x] Validate remote metadata before persistence or output in public stable BFAL 2.1.0
- [x] Review every external service and disclose it in `readme.txt`
- [x] Run Plugin Check against the exact WordPress.org SVN release tree
- [x] Review admin notices and logs for sensitive upstream data

The notice review found that BFAL 2.0.3 can print raw upstream error details without escaping. Public stable BFAL `2.1.0` sanitizes those failures, and BFA stores only sanitized codes and summaries for internal diagnostics. The normal settings page exposes no metadata diagnostics. BFAL 2.0.3 remains an emergency rollback path with its documented weaker security and request-path behavior.

## Compatibility gates

- [ ] Clean activation and deactivation
- [ ] Upgrade from 1.7.4 and 2.0.4 with existing settings preserved
- [x] Existing `[icon]` shortcode attributes and filters remain unchanged under automated and packaged smoke coverage
- [x] v4 shim behavior remains unchanged for existing installs
- [x] Classic Editor insertion and search work
- [x] Block Editor shortcode and Shortcode block rendering work before the native block ships
- [ ] No duplicate Font Awesome load when a supported theme or plugin already provides it
- [x] Multisite lifecycle is tested across two networks and scoped to the current network

Automated initialization coverage now preserves current 2.0.4 options and normalizes both array and serialized legacy options while retaining the v4 shim migration. The upgrade gate remains open until complete installs are exercised across the supported upgrade paths.

## BFAL gates

- [x] BFAL live-update contract is implemented and available as public stable `2.1.0`
- [x] Deterministic tests prove ordinary request paths perform no blocking Font Awesome metadata API call
- [x] Metadata refresh is automatic and background-only, with no metadata status, diagnostics, nonce, or refresh control on the normal settings page
- [x] Last-known-good data survives transient expiry and upstream outages
- [x] 403, 429, timeout, invalid JSON, and invalid schema paths are tested
- [x] Font Awesome metadata and asset delivery versions cannot diverge
- [x] Rollback to the previous BFAL package requires only an isolated dependency change
- [x] BFAL stable 2.1.0 is published after release-candidate review

## BFAL stable integration status

- [x] BFAL PRs #39, #40, #43, and #44 merged
- [x] Public reproducible BFAL `2.1.0` is available from GitHub and Packagist at `b845f8d2c105c34a9afe62e8470d67d0e3978164`
- [x] Stable BFAL 2.1.0 preserves rc.2 runtime behavior byte-for-byte after normalizing only the version constant, with the expected stylesheet cache-key transition to `?ver=2.1.0`
- [x] The historical rc.2 correction restores the static parent-document enqueue, adds anonymous CORS mode to the exact BFAL stylesheet handles, and changes the cache key so rc.1 responses cannot be reused
- [x] BFAL's intentional first-caller precedence and safe earlier-owner behavior are documented and covered by a WordPress-backed regression
- [x] Versioned non-autoloaded durable storage, transient migration, scheduler-worker ownership invariants, stale lock and crashed-worker recovery, backoff, jitter, and automatic lifecycle refresh implemented
- [x] Single-site and multisite current suites cover stale data, failures, deterministic scheduler-worker interleavings, two-network isolation, zero-HTTP request paths, and synthetic Font Awesome 5.15.5 adoption
- [x] Current-required bootstrap fails closed on the wrong BFAL version, wrong reference, or missing API
- [x] Stable BFAL 2.0.3 rollback activation creates no asynchronous cron event or orphaned marker
- [x] BFA exact Composer constraint and lockfile validated against public stable BFAL `2.1.0`
- [x] Hosted WordPress and PHP compatibility matrix is configured for the exact public stable BFAL package
- [x] Local public stable 2.1.0, stable 2.0.3 rollback, quality, package, activation, Plugin Check, and focused browser suites pass
- [ ] Independent review and two-site release candidate testing complete

## Release process gates

- [ ] Version matches plugin header, class constant, `package.json`, changelog, tag, GitHub release, and WordPress.org stable tag
- [x] Release tree audit rejects every `phpunit*.xml*` file and excludes `.codex`, `.conductor`, `.context`, `.github`, tests, source tooling, caches, and development dependencies
- [x] Release tree installs on a clean site without Composer
- [ ] Database and filesystem writes are documented and reversible
- [ ] Changelog names compatibility floors and external service changes
- [ ] Release candidate receives manual testing on at least two independent sites
- [ ] Rollback archive and WordPress.org SVN procedure are prepared before publish

## Current recommendation

Do not merge or publish yet. Public stable BFAL `2.1.0` at `b845f8d2c105c34a9afe62e8470d67d0e3978164` is the exact BFA production dependency under the approved first-caller precedence contract. The historical rc.1-to-rc.2 browser transition, rc.2-to-stable equivalence proof, and local two-version test matrices pass. BFA remains blocked on independent review, complete settings-save and upgrade-path release-candidate testing, BFA final-version decisions, packaging and changelog review, and the remaining WordPress.org publication gates.
