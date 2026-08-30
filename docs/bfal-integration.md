# BFAL 2.1 integration architecture

Status: BFA-owned review corrections are implemented and locally validated against corrected BFAL draft `f2f2e41ade5ac02d04a743772d01030f39b3dd31`. This branch is not ready to merge or release. Final dependency integration remains blocked on independent review and publication of a BFAL 2.1.0 release candidate.

## Compatibility boundary

BFA normally owns the BFAL singleton through its existing first `Better_Font_Awesome_Library::get_instance()` call. That call supplies two collaborators:

- `release_data_provider` reads one validated local durable option and never performs HTTP or writes state.
- `release_data_refresh_callback` requests one WP-Cron event and returns promptly.

Only the explicit `better_font_awesome_refresh_release_data` worker calls BFAL's bounded `refresh_release_data()` method. Frontend, administrator, REST, editor, shortcode, and ordinary cron-triggering requests do not perform Font Awesome metadata HTTP.

BFA feature-detects the reviewed BFAL API. BFAL 2.0.3 can still load for emergency rollback, but it resumes the old transient-only synchronous request behavior and is not the intended production configuration for this architecture.

BFAL intentionally keeps the first caller's singleton configuration. Hook priority is the supported precedence mechanism. A plugin or theme that deliberately initializes BFAL first remains authoritative, and BFA neither overrides that owner nor treats the condition as an initialization error. BFA's collaborators may therefore be ignored in that condition, while BFAL continues to serve validated transient or bundled fallback data without request-path metadata HTTP. Any new ownership API or precedence change requires concrete user-facing evidence and explicit repository-owner approval.

## Option schema

All metadata options are site-scoped and created with autoload disabled.

`better_font_awesome_release_record` stores:

- `schema_version`: BFAL record schema, currently `1`
- `channel`: `5.x`
- `edition`: `free`
- `source`: validated BFAL source such as `api` or `transient`
- `release`: the complete validated release array used for icons, CSS, the v4 shim, and webfont paths
- `storage_schema_version`: BFA storage schema, currently `1`
- `fetched_at`: Unix timestamp of the successful fetch or inferred transient fetch
- `fresh_until`: Unix timestamp used by BFA freshness evaluation
- `checksum`: SHA-256 of the serialized validated release array

`better_font_awesome_release_state` stores:

- `schema_version`
- `fetched_at`
- `attempt_count`
- `last_attempt_at`
- `failure_count`
- `next_retry_at`
- `scheduled_for`
- `status`: one of `never`, `scheduled`, `refreshing`, `fresh`, `stale`, or `failed`
- `last_error_code`
- `last_error`

`better_font_awesome_metadata_schema` records completed migration version `1`.

`better_font_awesome_refresh_schedule` and `better_font_awesome_refresh_lock` are short-lived ownership records. They are not release data and may be removed during safe stale recovery or deactivation.

## Freshness and stale data

- Normal freshness is 24 hours plus bounded jitter from 0 through 1 hour.
- Records enter the scheduling window 1 hour before `fresh_until`.
- A valid record is served immediately whether fresh, near expiry, or stale.
- There is no maximum stale age in this tranche.
- Failed refreshes never remove or replace a valid durable record.
- The BFAL bundled fallback is used only when neither the durable provider nor the compatibility transient supplies a valid release.

Font Awesome 7 remains a separate future product tranche. This integration supports the validated Font Awesome 5 Free channel only.

## Scheduling, locking, and retries

Scheduling uses an atomic non-autoloaded option claim plus an ownership token passed to one single WP-Cron event. The following invariants define eligible work:

1. Each site has at most one valid schedule claim or one valid in-flight worker lease.
2. A scheduler checks for an active worker both before claiming the schedule marker and after its atomic claim. It removes its own claim if a worker won the interleaving.
3. A scheduled worker acquires its worker lease while its matching schedule marker still suppresses schedulers. It consumes the marker only after ownership is established.
4. A malformed or expired lease is removed only by exact compare-and-delete. The caller then claims replacement work through the normal schedule path.
5. A crashed worker that already consumed its marker leaves an expiring lease. A later ordinary request recovers the expired lease and schedules a retry.

A claim without its matching event is recoverable after a 10 minute grace period. The 10 minute worker lease is intentionally much longer than BFAL's bounded 5 second HTTP timeout. A worker whose lease has expired is no longer eligible, even if its process has not exited.

Workers acquire a separate option lock using atomic insert. The lock contains a random owner, acquisition time, and 10 minute expiry. Stale locks are removed with compare-and-delete. A worker releases only a lock whose current owner matches its token, preventing one worker from releasing another worker's lock.

Retry bases are 1 hour, 6 hours, and 24 hours. Retry jitter is bounded from 0 through 15 minutes and the final delay is capped at 24 hours. A successful refresh clears the consecutive failure count, next retry, and sanitized error. A failed refresh schedules the next attempt without changing last-known-good data.

## Migration

Migration is idempotent and versioned. On the first upgrade request BFA:

1. Validates the existing `bfa-release-data` transient through BFAL.
2. Promotes it only when no valid durable record exists.
3. Infers fetch and freshness times from the transient timeout when available.
4. Persists the complete durable replacement before serving it.
5. Preserves the established transient for BFAL and third-party compatibility.
6. Never deletes or overwrites an existing valid durable record during migration.

Existing BFA settings and shortcode behavior are outside this migration and remain unchanged.

## Lifecycle and multisite

Activation schedules background work only when the installed BFAL exposes the asynchronous validation and refresh API. BFAL 2.0.3 rollback activation creates no no-op cron event, marker, or multisite new-site hook. Deactivation clears pending events, schedule claims, and worker locks while preserving durable release data and failure history. Reactivation with candidate support schedules work again. Ordinary startup recovers stale or missed schedules.

Multisite uses per-site options, state, locks, and cron events. Network activation and deactivation iterate only sites belonging to `get_current_network_id()` and always restore the original blog context through `try`/`finally`. Newly initialized sites receive work only when they belong to that same relevant network. Lifecycle work on one network does not schedule or clear another network. No network-global release record is shared between sites.

## Administrator refresh

Settings includes a minimal metadata status panel and refresh button. The AJAX action requires `manage_options` and the dedicated nonce. It performs no Font Awesome HTTP.

The administrator override ignores `next_retry_at` and may replace a later pending event with an immediate event. It never bypasses an active worker lock. Repeated clicks remain duplicate-suppressed. The response and status panel expose only stable status labels, fetched time, and sanitized error code and summary.

## External services

The site server contacts `https://api.fontawesome.com` asynchronously for public Font Awesome 5 Free metadata. It intentionally sends no site, user, or content data and uses no account token. Visitors' browsers continue to request versioned CSS and webfonts from `https://use.fontawesome.com` and therefore disclose normal connection data such as IP address and user agent to Fonticons, Inc.

Automated tests intercept the WordPress HTTP API and never use the live Font Awesome API or CDN.

## Rollback

The preferred rollback is the prior complete BFA archive with BFAL 2.0.3. For diagnostic source rollback, restore the Composer requirement and lock entry for BFAL 2.0.3 and reinstall production dependencies. BFA feature detection allows the plugin to load and leaves the new durable options untouched for a later forward recovery.

Rollback to BFAL 2.0.3 restores its known synchronous cache-miss HTTP, weaker TLS and validation behavior, and transient-only cache. It is an emergency availability path, not an equivalent security posture. No metadata option deletion is required for rollback.

## Dependency handoff

Local integration uses a gitignored path checkout at the exact reviewed SHA with Composer `symlink: false`. No path repository or development constraint may be committed. Before hosted CI can validate the candidate, BFAL must either publish an exact release candidate or BFA must adopt a separately reviewed public exact-SHA Composer mechanism. The final stable BFA Composer constraint remains unchanged until that decision.

Validation has two intentionally separate modes:

- Candidate-required mode uses `phpunit.xml.dist` and `phpunit-multisite.xml.dist`. Bootstrap fails before loading tests unless Composer reports exact reference `f2f2e41ade5ac02d04a743772d01030f39b3dd31` and the validator, provider record, asynchronous request, and explicit worker APIs used by BFA exist. Candidate-only tests do not skip in this mode.
- Stable rollback mode uses `phpunit-rollback.xml.dist` and `phpunit-rollback-multisite.xml.dist`. Bootstrap requires the exact BFAL 2.0.3 reference and requires candidate APIs to be absent. Candidate-only tests then report explicit expected skips, while rollback lifecycle coverage runs.

Each mode prints its name, Composer version, and exact package reference in test output. Ordinary hosted CI installs the committed Composer lock and therefore runs the clearly named stable BFAL 2.0.3 rollback job. Hosted candidate coverage remains gated on a reviewed BFAL 2.1.0 release candidate and the final dependency update.
