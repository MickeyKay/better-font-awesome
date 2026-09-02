# BFAL integration architecture

Better Font Awesome (BFA) delegates Font Awesome version data, icon data, styles, and shortcode rendering to the separately published Better Font Awesome Library (BFAL). BFAL's public API is a compatibility boundary because other plugins and themes can load the same library.

## Compatibility boundary

BFA normally owns the BFAL singleton through its existing first `Better_Font_Awesome_Library::get_instance()` call. When the installed BFAL exposes the asynchronous metadata API, that call supplies two collaborators:

- `release_data_provider` reads one validated local durable option and never performs HTTP or writes state.
- `release_data_refresh_callback` requests one WP-Cron event and returns promptly.

Only the explicit `better_font_awesome_refresh_release_data` worker calls BFAL's bounded `refresh_release_data()` method. Frontend, administrator, REST, editor, shortcode, and ordinary cron-triggering requests do not perform Font Awesome metadata HTTP.

BFA feature-detects the BFAL API it uses so an older dependency can still load for emergency rollback. An older dependency can restore its legacy metadata behavior, including synchronous request handling on a transient cache miss, and is not equivalent to the current asynchronous architecture.

BFAL intentionally keeps the first caller's singleton configuration. Hook priority is the supported precedence mechanism. A plugin or theme that deliberately initializes BFAL first remains authoritative, and BFA neither overrides that owner nor treats the condition as an initialization error. BFA's collaborators may therefore be ignored in that condition, while BFAL continues to serve its validated local or bundled data. Any new ownership API or precedence change requires concrete user-facing evidence and explicit repository-owner approval.

Stable BFAL releases must preserve the public singleton, validation, refresh, stylesheet, shortcode, and filter behaviors used by BFA and other consumers. Compatibility changes belong in the BFAL repository and require standalone BFAL verification before BFA adopts them.

## Option schema

All metadata options are site-scoped and created with autoload disabled.

`better_font_awesome_release_record` stores:

- `schema_version`: BFAL record schema, `1` for the legacy `5.x` channel or `2` for the default `7.x` channel.
- `channel`: the record's declared `5.x` or `7.x` release channel.
- `edition`: `free`.
- `source`: validated BFAL source such as `api` or `transient`.
- `release`: the complete channel-specific release array used for icons, aliases, CSS, compatibility assets, and webfont paths.
- `storage_schema_version`: BFA storage schema, currently `1`.
- `fetched_at`: Unix timestamp of the successful fetch or inferred transient fetch.
- `fresh_until`: Unix timestamp used by BFA freshness evaluation.
- `checksum`: SHA-256 of the serialized validated release array.

`better_font_awesome_release_state` stores:

- `schema_version`
- `fetched_at`
- `attempt_count`
- `last_attempt_at`
- `failure_count`
- `next_retry_at`
- `scheduled_for`
- `status`: one of `never`, `scheduled`, `refreshing`, `fresh`, `stale`, or `failed`.
- `last_error_code`
- `last_error`

`better_font_awesome_metadata_schema` records completed BFA metadata migration version `1`.

`better_font_awesome_refresh_schedule` and `better_font_awesome_refresh_lock` are short-lived ownership records. They are not release data and may be removed during safe stale recovery or deactivation.

## Freshness and stale data

- Normal freshness is 24 hours plus bounded jitter from 0 through 1 hour.
- Records enter the scheduling window 1 hour before `fresh_until`.
- A valid record is served immediately whether fresh, near expiry, or stale.
- There is no maximum stale age.
- Failed refreshes never remove or replace a valid durable record.
- The BFAL bundled fallback is used only when neither the durable provider nor the compatibility transient supplies a valid release.

BFAL 3 defaults to the Font Awesome 7 Free `7.x` channel and its packaged fallback. A plugin or theme that deliberately owns the BFAL singleton first may select the legacy Font Awesome 5 Free `5.x` channel and remains authoritative for that request. BFA validates stored records against their declared schema and channel, offers a valid record as a local provider candidate, and preserves a wrong-channel record when BFAL rejects it. After singleton initialization, refresh and persistence behavior follows BFAL's actual immutable channel. There is no separate Font Awesome 6 channel or claim of comprehensive native Font Awesome 6 support.

## Scheduling, locking, and retries

Scheduling uses an atomic non-autoloaded option claim plus an ownership token passed to one single WP-Cron event. The following invariants define eligible work:

1. Each site has at most one valid schedule claim or one valid in-flight worker lease.
2. A scheduler checks for an active worker before claiming the schedule marker and after its atomic claim. It removes its own claim if a worker won the interleaving.
3. A scheduled worker acquires its worker lease while its matching schedule marker still suppresses schedulers. It consumes the marker only after ownership is established.
4. A marker whose exact token and force arguments still have a WordPress cron event remains valid ownership even when that event is overdue. Startup and repeated scheduling requests do not postpone it.
5. A marker is eligible for grace-period recovery only when its exact matching cron event is missing. A malformed marker remains immediately recoverable.
6. A malformed or expired lease is removed only by exact compare-and-delete. The caller then claims replacement work through the normal schedule path.
7. A crashed worker that already consumed its marker leaves an expiring lease. A later ordinary request recovers the expired lease and schedules a retry.

A claim without its matching event is recoverable after a 10 minute grace period measured from marker creation. This protects a newly claimed marker while another request may not yet see its cron event. The grace period never expires valid ownership while the exact cron event remains present. The 10 minute worker lease is intentionally much longer than BFAL's bounded 5 second metadata HTTP timeout. A worker whose lease has expired is no longer eligible, even if its process has not exited.

Workers acquire a separate option lock using atomic insert. The lock contains a random owner, acquisition time, and 10 minute expiry. Stale locks are removed with compare-and-delete. Before each callback-derived failure, validated record, or success-state write, a worker atomically validates and renews the exact complete lock value while retaining its owner identity. An already expired owner cannot renew, and compare-and-swap failure protects the case where ownership is lost while the remote callback is running. Ownership loss returns the stable internal `bfa_refresh_ownership_lost` result without changing durable data, freshness state, or retry scheduling. A worker releases only a lock whose current owner matches its token, preventing one worker from releasing another worker's lock.

This is a bounded lease, not a generation fence or cross-option transaction. The post-renewal critical window contains only the immediately following bounded local option persistence. An artificial process suspension or local write lasting longer than the full renewed lease could outlive that validation. The residual impact is temporary older metadata rather than user-content loss.

Retry bases are 1 hour, 6 hours, and 24 hours. Retry jitter is bounded from 0 through 15 minutes and the final delay is capped at 24 hours. A successful refresh clears the consecutive failure count, next retry, and sanitized error. A failed refresh schedules the next attempt without changing last-known-good data only after its complete failure and backoff state is verified as stored. If the failure state cannot be stored, the worker publishes no immediate retry from default state. Ordinary stale-work recovery remains available on later traffic.

## WP-Cron operations

Automatic metadata refresh depends on functioning WordPress cron. Default WP-Cron is request-driven, so scheduled work can be delayed on low-traffic sites. Sites that define `DISABLE_WP_CRON` should invoke `wp-cron.php` through a real scheduler or an equivalent hosting mechanism. `wp cron test` is available as a WP-CLI diagnostic, and `wp cron event list --fields=hook,next_run_gmt,next_run_relative` can confirm whether `better_font_awesome_refresh_release_data` is scheduled.

If scheduled refresh does not run, BFA continues serving validated last-known-good metadata or the bundled fallback. Normal page requests do not wait on the Font Awesome metadata service.

## Migration

Migration is idempotent, versioned, and channel-aware. On an eligible upgrade request BFA:

1. Validates the existing `bfa-release-data` transient for BFAL's actual selected channel.
2. Promotes it only when no valid durable record exists for that channel.
3. Infers fetch and freshness times from the transient timeout only when WordPress is not using an external object cache.
4. Under external object caching, ignores any leftover database timeout and treats transient age as unknown. The data remains last-known-good but is stale and immediately eligible for one duplicate-suppressed asynchronous refresh.
5. Persists the complete durable replacement before serving it.
6. Preserves the established transient for BFAL and third-party compatibility, including data for another channel.
7. Never deletes or overwrites an existing valid durable record during migration, including a record that belongs to another channel.

The schema version is the final migration commit marker. A valid transient migration records completion only after the durable record and migrated state are both verified as stored. Any required write failure leaves the schema incomplete, preserves the transient, and allows a later request to retry. Once the schema is complete, ordinary requests perform no recurring migration writes.

Existing BFA settings and shortcode behavior are outside this migration and remain unchanged.

## Lifecycle and multisite

Activation schedules background work only when the installed BFAL exposes the asynchronous validation and refresh API. Deactivation clears pending events, schedule claims, and worker locks while preserving durable release data, failure history, settings, compatibility transients, and content. Reactivation with compatible BFAL support schedules work again. Ordinary startup recovers stale or missed schedules.

Multisite uses per-site options, state, locks, and cron events. Network activation and deactivation iterate only sites belonging to `get_current_network_id()` and always restore the original blog context through `try`/`finally`. BFA registers its new-site lifecycle callback only while WordPress reports the plugin network-active, and the callback revalidates that canonical activation state before delegating. A site-only activation therefore never schedules metadata work for a new inactive site. Network-active BFA schedules newly initialized sites only when they belong to the same relevant network. Lifecycle work on one network does not schedule or clear another network. No network-global release record is shared between sites.

The plugin has no uninstall hook and no `uninstall.php`. Removing plugin files does not delete BFA settings, durable metadata, refresh state, migration markers, compatibility transients, or shortcode content.

## Background-only update experience

Font Awesome metadata updates are automatic and background-only. The normal settings page contains only the established plugin settings. It does not expose metadata status, timestamps, errors, diagnostics, or a manual refresh control, and BFA registers no browser-facing metadata-refresh AJAX action.

Activation, provider freshness checks, and stale-work recovery schedule the WP-Cron worker automatically. No normal browser request waits on or directly contacts the Font Awesome metadata service. Internal status, scheduling, worker, and diagnostic APIs remain available to the automatic orchestration and are not part of the administrator interface.

## External services

The default `7.x` worker posts its fixed public release query to `https://api.fontawesome.com`. When that service reports a newer candidate, the worker confirms the exact `@fortawesome/fontawesome-free` package through `https://registry.npmjs.org/%40fortawesome%2Ffontawesome-free/{version}`. It then downloads an allowlisted CSS and WOFF2 inventory from `https://cdnjs.cloudflare.com/ajax/libs/font-awesome/{version}/` and `https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@{version}/`, requires both providers to return identical bytes, validates CSS-to-font references, and derives integrity values. BFAL does not call a separate cdnjs catalog API. These are bounded server-side background requests.

The packaged Font Awesome 7 fallback uses local CSS and fonts. After a newer record passes complete validation, cdnjs is the browser runtime host for its selected CSS and referenced fonts; jsDelivr remains an independent validation source. A deliberate earlier `5.x` singleton owner may instead select versioned CSS and fonts under `https://use.fontawesome.com/releases/`.

Normal frontend, administrator, REST, editor, settings, shortcode, picker, and getter requests do not perform BFA or BFAL metadata discovery or candidate asset validation. WordPress core may separately fetch a registered external editor stylesheet while constructing Block Editor assets. Provider requests receive ordinary connection data. Server-side requests may disclose the server IP, requested URL and version, timing, and HTTP headers, and WordPress's default user agent may include the WordPress version and site URL. Browser asset requests may disclose the visitor IP, user agent, referrer, and requested asset. BFA adds no post content, user content, Font Awesome account credential, or API token. A failed request or validation retains the packaged or last-known-good release.

Automated tests intercept the WordPress HTTP API and never use the live Font Awesome API or CDN.

## Rollback

The preferred rollback is the prior complete BFA archive. BFA feature detection allows an older BFAL dependency to load and leaves newer durable options untouched for later forward recovery.

An older BFAL dependency can restore synchronous cache-miss HTTP, weaker transport or validation behavior, and transient-only caching. Rollback is an emergency availability path, not an equivalent reliability or security posture. No metadata option deletion is required for rollback.

## Compatibility verification

Verify the integration in two intentionally separate modes:

- Current-required mode must fail before tests load if Composer reports the wrong required BFAL version or source reference, or if the validator, provider record, asynchronous request, or explicit worker API used by BFA is missing.
- Stable rollback mode must install the selected prior BFAL version in an isolated temporary Composer project, leave committed dependency files unchanged, require current-only APIs to be absent when appropriate, and report explicit expected skips for current-only tests.

For each supported WordPress and PHP combination, run single-site and multisite suites with deterministic HTTP fixtures. Cover fresh, stale, failed, malformed, and last-known-good metadata; zero-HTTP normal request paths; scheduler and worker ownership interleavings; lock replacement; failed state persistence; external object-cache migration; site-only activation; network activation; new-site behavior; and blog-context restoration.

Before release, also verify:

- The exact Composer lock and installed BFAL package match the approved dependency identity.
- Every packaged BFAL production file matches the clean Composer install.
- The package includes the validator, fallback data, fallback checksum, runtime files, and licenses, while excluding BFAL development files and development dependencies.
- Frontend, administrator, REST, editor, settings, and shortcode requests perform no metadata HTTP.
- An explicit scheduled worker can adopt validated fixture metadata, while transport and validation failures retain last-known-good data.
- Classic Editor, supported `wp_editor()` integrations, shortcode insertion, frontend rendering, the optional v4 shim, stylesheet ownership, and existing filters remain compatible.

Record exact dependency references, commits, trees, test counts, artifact identities, and completed gate evidence in the release PR and final release record, not in this evergreen architecture document.
