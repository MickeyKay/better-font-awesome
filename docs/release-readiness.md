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
- [x] GitHub Actions stable BFAL 2.0.3 rollback job is defined for WordPress 6.5, 6.7, and latest with PHP 7.4, 8.1, 8.3, and 8.4
- [x] PHPUnit uses only deterministic HTTP fixtures
- [ ] Browser smoke tests cover activation, settings save, Classic Editor picker, and shortcode rendering
- [x] The canonical WordPress.org SVN release tree is built separately from the source tree and checked by CI

## Security gates

- [x] Add an explicit capability check to the AJAX settings endpoint
- [x] Remove the broad PHPCS nonce and input suppressions after fixing each finding
- [ ] Enable TLS verification in BFAL
- [ ] Validate remote metadata before persistence or output
- [x] Review every external service and disclose it in `readme.txt`
- [x] Run Plugin Check against the exact WordPress.org SVN release tree
- [x] Review admin notices and logs for sensitive upstream data

The notice review found that BFAL 2.0.3 can print raw upstream error details without escaping. The reviewed BFAL draft sanitizes those failures, and BFA stores and displays only sanitized codes and summaries. The stable BFAL dependency remains a release blocker until the draft is independently merged and released.

## Compatibility gates

- [ ] Clean activation and deactivation
- [ ] Upgrade from 1.7.4 and 2.0.4 with existing settings preserved
- [ ] Existing `[icon]` shortcode attributes and filters remain unchanged
- [ ] v4 shim behavior remains unchanged for existing installs
- [ ] Classic Editor insertion and search work
- [ ] Block Editor shortcode and Shortcode block rendering work before the native block ships
- [ ] No duplicate Font Awesome load when a supported theme or plugin already provides it
- [x] Multisite lifecycle is tested across two networks and scoped to the current network

Automated initialization coverage now preserves current 2.0.4 options and normalizes both array and serialized legacy options while retaining the v4 shim migration. The upgrade gate remains open until complete installs are exercised across the supported upgrade paths.

## BFAL gates

- [ ] BFAL live-update contract is implemented and independently released
- [ ] No frontend request performs a blocking Font Awesome API call
- [ ] Last-known-good data survives transient expiry and upstream outages
- [ ] 403, 429, timeout, invalid JSON, and invalid schema paths are tested
- [ ] Font Awesome metadata and asset delivery versions cannot diverge
- [ ] Rollback to the previous BFAL package requires only a lockfile change

## BFAL candidate integration status

- [x] Reviewed BFAL draft `9d9a4a60b291de5190f6e3f4ab7f289869e80798` tested through a local Composer path copy with symlinks disabled
- [ ] BFA adopts BFAL's supported post-construction collaborator-registration API at the new exact reviewed SHA
- [x] Versioned non-autoloaded durable storage, transient migration, scheduler-worker ownership invariants, stale lock and crashed-worker recovery, backoff, jitter, lifecycle, and manual refresh implemented
- [x] Single-site and multisite candidate suites cover stale data, failures, deterministic scheduler-worker interleavings, two-network isolation, zero-HTTP request paths, and synthetic Font Awesome 5.15.5 adoption
- [x] Candidate-required bootstrap fails closed on the wrong BFAL reference or missing API
- [x] Stable BFAL 2.0.3 rollback activation creates no asynchronous cron event or orphaned marker
- [ ] BFAL PR #39 merged and released as a public reproducible release candidate
- [ ] BFA stable Composer constraint and lockfile updated to that BFAL release candidate
- [ ] Hosted WordPress and PHP compatibility matrix rerun with the public BFAL package
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

Do not merge or publish yet. The previously reviewed BFAL draft remains useful for local BFA validation, but final integration is blocked on BFAL's supported collaborator-registration API and a new exact reviewed SHA. Public release also remains blocked on a BFAL 2.1.0 release candidate, the final stable BFA Composer update, the hosted candidate compatibility matrix, browser coverage, and release candidate testing on independent sites.
