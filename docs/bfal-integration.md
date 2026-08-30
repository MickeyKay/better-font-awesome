# BFAL 2.1 integration architecture

Status: ready for independent review against BFAL draft `9d9a4a60b291de5190f6e3f4ab7f289869e80798`. This is not public release approval.

## Compatibility boundary

BFA remains the first caller of `Better_Font_Awesome_Library::get_instance()`. It injects two collaborators:

- `release_data_provider` reads one validated local durable option and never performs HTTP or writes state.
- `release_data_refresh_callback` requests one WP-Cron event and returns promptly.

Only the explicit `better_font_awesome_refresh_release_data` worker calls BFAL's bounded `refresh_release_data()` method. Frontend, administrator, REST, editor, shortcode, and ordinary cron-triggering requests do not perform Font Awesome metadata HTTP.

BFA feature-detects the reviewed BFAL API. BFAL 2.0.3 can still load for emergency rollback, but it resumes the old transient-only synchronous request behavior and is not the intended production configuration for this architecture.

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

Scheduling uses an atomic non-autoloaded option claim plus an ownership token passed to one single WP-Cron event. Duplicate callers cannot create duplicate valid workers. A claim without its matching event is recoverable after a 10 minute grace period.

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

Activation schedules background work. Deactivation clears pending events, schedule claims, and worker locks while preserving durable release data and failure history. Reactivation schedules work again. Ordinary startup recovers stale or missed schedules.

Multisite uses per-site options, state, locks, and cron events. Network activation and deactivation iterate existing sites in their own blog context. Newly initialized sites receive their own event. No network-global release record is shared between sites.

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
