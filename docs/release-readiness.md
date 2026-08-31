# Release readiness checklist

Use a fresh copy of this checklist for each maintenance release. Keep the committed template unchecked. Record completed gates, exact commits, dependency references, test counts, and artifact checksums in the release PR and final release record.

This checklist covers conservative maintenance releases. Feature work such as a native block should use separate planning and review.

## Source and release identity

- [ ] The release starts from the approved base and the exact candidate commit is recorded.
- [ ] The candidate worktree is clean and contains only reviewed release changes.
- [ ] Plugin header, runtime version constant, package metadata, lockfile metadata, stable tag, POT identity, changelog, upgrade notice, and release-identity tests agree on the release version.
- [ ] WordPress and PHP compatibility floors are intentional and synchronized across public metadata.
- [ ] The required BFAL version and exact source and distribution references are recorded and reproducible from a clean Composer install.
- [ ] Generated files are rebuilt with repository commands and are byte-stable.

## Automated quality gates

- [ ] Current single-site PHPUnit passes with deterministic HTTP fixtures.
- [ ] Current multisite PHPUnit passes with deterministic HTTP fixtures.
- [ ] The isolated stable rollback single-site suite passes without changing committed dependency files.
- [ ] The isolated stable rollback multisite suite passes without changing committed dependency files.
- [ ] PHPCS passes on first-party code.
- [ ] PHPStan passes on first-party runtime code.
- [ ] Strict Composer validation passes.
- [ ] Composer audit reports no known advisories.
- [ ] npm audit reports no advisories at the configured severity.
- [ ] PHP syntax checks pass for the source and packaged trees.
- [ ] `git diff --check` passes.
- [ ] Hosted quality, compatibility, and official Plugin Check jobs pass on the exact candidate commit.

## Security and external services

- [ ] Explicit input fields are sanitized and validated, output is escaped late, and state-changing requests pair nonces with capability checks.
- [ ] Metadata transport uses the WordPress HTTP API, TLS verification, bounded timeouts, validation, locking, and backoff.
- [ ] Normal frontend, administrator, REST, editor, settings, and shortcode requests perform no Font Awesome metadata HTTP.
- [ ] Failed or malformed metadata never replaces validated last-known-good data.
- [ ] External services and transmitted data are accurately disclosed in the canonical readme.
- [ ] Admin notices and stored diagnostics expose no sensitive upstream data.
- [ ] Secret scanning finds no credentials or tokens in the release history or candidate tree.

## Compatibility gates

- [ ] Clean activation, deactivation, and reactivation complete without plugin warnings or errors.
- [ ] Upgrade from each selected official prior release preserves settings and existing shortcode content.
- [ ] Existing `[icon]` shortcode attributes and filters remain unchanged.
- [ ] Classic Editor insertion and search work.
- [ ] Block Editor shortcode editing and Shortcode block rendering work before a native block ships.
- [ ] Supported `wp_editor()` integrations continue to insert icons.
- [ ] Frontend rendering and the optional v4 shim remain compatible.
- [ ] Supported theme or plugin stylesheet ownership does not cause an unintended duplicate Font Awesome load.
- [ ] Multisite lifecycle behavior is scoped to the current network and gated by canonical network activation for new sites.

## Metadata and BFAL gates

- [ ] The required stable BFAL package is publicly available and matches its approved source reference.
- [ ] BFAL first-caller singleton ownership remains unchanged unless a separately approved compatibility change says otherwise.
- [ ] Durable storage, transient migration, scheduling, worker ownership, locking, retry, and lifecycle behavior pass focused regressions.
- [ ] Fresh, stale, failed, malformed, rate-limited, timed-out, and invalid-schema paths are covered with fixtures.
- [ ] Last-known-good metadata survives transient expiry and upstream outages.
- [ ] Font Awesome metadata and asset delivery versions cannot diverge.
- [ ] An explicit scheduled worker can persist validated fixture metadata.
- [ ] Automatic refresh behavior and WP-Cron operational requirements are documented.
- [ ] The selected prior BFAL rollback package loads through feature detection and its weaker behavior is documented.

## Package gates

- [ ] Two independent clean exports of the exact candidate commit produce byte-identical production trees and ZIP archives.
- [ ] The artifact file count, byte size, SHA-256, archive root, and exact source commit are recorded.
- [ ] The canonical release-tree audit passes.
- [ ] The extracted ZIP passes the same package audit.
- [ ] Every packaged BFAL production file matches the exact clean Composer install.
- [ ] The package excludes tests, agent files, repository metadata, release tooling, caches, `node_modules`, Composer development dependencies, credentials, secrets, and unexpected paths.
- [ ] The exact ZIP installs and activates on a clean supported WordPress site without Composer.
- [ ] Upgrade from the selected official rollback ZIP to the exact candidate ZIP preserves settings and shortcode content.
- [ ] Packaged smoke coverage confirms the expected BFA and BFAL identities, zero metadata HTTP on normal paths, scheduled refresh behavior, last-known-good retention, and lifecycle persistence.
- [ ] Plugin Check passes against the exact WordPress.org release tree, with any established warnings documented.

## Rollback gates

- [ ] The prior official BFA ZIP is downloaded from WordPress.org and its final URL, size, SHA-256, archive root, BFA version, and BFAL version are recorded.
- [ ] A database and files backup procedure is prepared.
- [ ] The rollback ZIP activates successfully on a supported site.
- [ ] Settings and shortcode content are verified before and after rollback.
- [ ] Forward recovery is verified or its expected behavior is documented.
- [ ] The WordPress.org stable-tag rollback procedure is prepared and does not delete an SVN tag.

## Publication gates

- [ ] The release PR is reviewed and merged.
- [ ] The exact merge commit and tree are verified after merge.
- [ ] Deterministic build and package checks are repeated on the exact merge commit.
- [ ] Explicit repository-owner authorization for publication is recorded.
- [ ] The Git tag is created at the authorized release commit.
- [ ] The GitHub release is published from the authorized tag and release notes.
- [ ] WordPress.org SVN trunk and the new tag match the verified production tree.
- [ ] The WordPress.org SVN commit is published only after explicit authorization.
- [ ] Any required WordPress.org Release Management confirmation is complete.
- [ ] The public plugin page, metadata, and download are verified.
- [ ] The public ZIP checksum and activation smoke results are recorded in the final release record.
