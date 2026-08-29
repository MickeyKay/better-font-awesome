# BFAL live-update contract

Status: proposed contract for the next BFAL release

## Why the current implementation is stuck

BFAL 2.0.3 queries GraphQL with `release(version: "latest")`. Font Awesome documents that `latest` resolves to the latest full v5 release and is deprecated for v6 and newer. BFAL also constructs asset URLs under `use.fontawesome.com/releases`, while Font Awesome documents that v5 is the last version available from that CDN.

This means metadata selection and asset delivery must be upgraded together. Changing only the GraphQL query to `7.x` would generate invalid legacy CDN URLs.

The current implementation also:

- performs a synchronous remote POST when the transient is absent
- disables TLS certificate verification
- uses one expiring transient rather than a durable last-known-good value
- has no request lock, retry backoff, or response schema validation
- falls directly back to bundled v5.14.0 data after remote failure
- can display repeated admin notices for upstream failures

## Product contract

The architecture remains live-first. The plugin may ship emergency fallback metadata, but a plugin release is not required for routine Font Awesome Free patch and minor updates within the selected compatible major.

Major Font Awesome upgrades are compatibility decisions, not silent data refreshes. BFAL must resolve a named channel such as `7.x`, not the ambiguous `latest`. The selected major must be filterable and observable.

Metadata resolution and browser asset delivery are separate adapters:

1. Resolve the latest concrete version for the supported channel from Font Awesome's public API.
2. Fetch and validate Free icon metadata for that concrete version.
3. Build CSS URLs from an explicitly supported provider for the same concrete version.

The leading Free delivery candidate is the versioned `@fortawesome/fontawesome-free` package on jsDelivr. A probe for `7.2.0/css/all.min.css` currently returns an immutable HTTP 200 response. This provider choice still needs license, uptime, privacy disclosure, relative webfont URL, and WordPress directory review before implementation.

## Request and cache state machine

Normal frontend and editor requests must never wait for Font Awesome's API.

| State | Request behavior | Background behavior |
| --- | --- | --- |
| Fresh last-known-good data | Return it immediately | None |
| Stale last-known-good data | Return it immediately | Schedule one refresh if no lock exists |
| No last-known-good data | Return the bundled fallback immediately | Schedule one refresh if no lock exists |
| Refresh succeeds | Keep serving current data for that request | Validate, atomically replace last-known-good data, record success, clear backoff |
| Refresh fails | Keep serving last-known-good or fallback | Record a sanitized error, increase backoff, retain existing data |

Required storage:

- a non-expiring option containing validated last-known-good release data
- metadata containing source version, fetched time, schema version, and checksum
- a short-lived refresh lock
- failure count, next retry time, and last sanitized error
- a fresh-until timestamp with small random jitter to avoid fleet-wide refresh bursts

Recommended retry progression is roughly 1 hour, 6 hours, then 24 hours, with jitter and a cap. A successful refresh resets it. Cron callbacks must be idempotent, and an authenticated WP-CLI or admin action must support a manual refresh for diagnosis.

## Transport and validation rules

- Use the WordPress HTTP API with TLS verification enabled.
- Use bounded connect and response timeouts.
- Handle `WP_Error` before reading an HTTP status or body.
- Treat non-2xx responses and GraphQL `errors` as failures.
- Reject empty, malformed, unexpectedly large, or structurally incomplete responses.
- Require a valid semantic version, a non-empty Free icon list, known style names, and required asset metadata.
- Never replace last-known-good data until the complete response validates.
- Do not expose raw upstream bodies, tokens, headers, or stack traces in admin notices.
- Provide filters around the channel and request arguments, but do not permit callers to disable TLS by default.

## Backward compatibility

Existing BFAL consumers must retain these public behaviors unless a major library release explicitly changes them:

- the `Better_Font_Awesome_Library` class and singleton entry point
- the `[icon]` shortcode and existing attributes
- current filters such as `bfa_icon`, `bfa_icon_array`, and `bfa_icon_class`
- existing settings for the v4 shim and conflict removal
- semantic icon output that remains renderable for saved content

Add new provider, cache, and refresh collaborators behind the public class. Avoid making the plugin repository the only environment in which BFAL can be tested.

## Required BFAL tests

- fresh cache returns without an HTTP request
- stale cache returns immediately and schedules one refresh
- cache miss returns bundled fallback and schedules one refresh
- concurrent stale requests create only one scheduled refresh
- valid response atomically replaces last-known-good data
- timeout, DNS, TLS, 403, 429, 500, GraphQL error, malformed JSON, and invalid schema retain old data
- backoff and jitter cap request frequency
- selected metadata version and CSS package version always match
- relative CSS webfont URLs resolve through the chosen provider
- v4-compatible shortcode markup still renders after the major upgrade
- filters and public method signatures remain compatible

## Repository coordination

Implement and release this contract in `MickeyKay/better-font-awesome-library` first. Run its standalone unit suite across supported PHP and WordPress versions, tag a prerelease, then point this plugin at the exact prerelease for integration testing. Merge and tag the stable BFAL release before updating this plugin's production lockfile.
