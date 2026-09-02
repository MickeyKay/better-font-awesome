# BFAL live-update contract

Status: current evergreen contract

Better Font Awesome (BFA) and Better Font Awesome Library (BFAL) keep Font Awesome Free metadata current without making ordinary WordPress requests depend on remote discovery. The plugin ships a validated local fallback, serves validated local data immediately, and performs remote refresh work only in an explicit background worker.

## Channel and ownership contract

BFAL 3 supports two immutable channels:

- `7.x` uses schema-2 family-aware Font Awesome 7 Free records and is BFAL's default.
- `5.x` uses schema-1 Font Awesome 5 Free records for deliberate legacy ownership and rollback compatibility.

There is no separate Font Awesome 6 channel or claim of comprehensive native Font Awesome 6 support. Major-channel changes are compatibility decisions and cannot occur as a routine metadata refresh.

BFAL resolves its channel once through its public initialization process. The first singleton caller remains authoritative. BFA supplies its local provider and asynchronous refresh callback when BFA owns that first call, but it does not instantiate BFAL early or override a deliberate earlier owner.

Before BFA receives the singleton, its provider validates the one durable record using the channel declared by that record and returns it as a candidate. BFAL accepts the candidate only when its schema and channel match BFAL's already-selected immutable channel. BFA preserves rejected wrong-channel records. After BFA receives the singleton, provider, migration, refresh, and persistence behavior use BFAL's actual channel.

## Request and cache behavior

| State | Ordinary request behavior | Background behavior |
| --- | --- | --- |
| Fresh last-known-good data | Return it immediately | No refresh required |
| Stale last-known-good data | Return it immediately | Schedule one refresh when work is not already owned |
| No compatible durable data | Return BFAL's validated bundled fallback immediately | Schedule one refresh when work is not already owned |
| Refresh succeeds | Keep serving current request data | Validate and durably replace compatible last-known-good data, then clear backoff |
| Refresh fails | Keep serving last-known-good data or fallback | Record a sanitized failure and capped retry without replacing valid data |

Normal frontend, administrator, REST, editor, settings, shortcode, picker, getter, and ordinary cron-triggering requests perform no BFA or BFAL metadata discovery or candidate asset-validation HTTP. They may schedule work and return promptly. WordPress core may separately fetch a registered external editor stylesheet while constructing Block Editor assets; that is not BFA or BFAL metadata validation.

## Durable state

The site-scoped, non-autoloaded `better_font_awesome_release_record` option contains one completely validated release record plus BFA storage metadata:

- BFAL schema version `1` with channel `5.x`, or schema version `2` with channel `7.x`
- Free edition and validated source
- complete channel-specific release data
- BFA storage schema version
- fetch and freshness timestamps
- checksum of the validated release data

Separate non-autoloaded options hold refresh status, the migration marker, one short-lived schedule claim, and one short-lived worker lease. A valid record has no maximum stale age. Failed refreshes and wrong-channel selection never delete it.

## Scheduling, locking, and retries

BFA owns freshness, scheduling, locking, backoff, durable persistence, and migration when it owns BFAL's first singleton call.

- Normal freshness is 24 hours plus bounded jitter of up to 1 hour.
- Records enter the scheduling window 1 hour before their freshness deadline.
- One atomic schedule claim suppresses duplicate WP-Cron events.
- One atomic, renewable worker lease protects callback-derived record and state writes.
- Exact ownership checks prevent one request or worker from releasing another's work.
- Retry bases are 1 hour, 6 hours, and 24 hours, with bounded jitter and a 24 hour cap.
- A successful refresh resets failure state. A failed refresh schedules a retry only after its failure state is stored successfully.

Cron callbacks are idempotent. The normal settings page exposes no metadata status, diagnostics, or manual refresh control.

## Font Awesome 7 providers and validation

The default `7.x` background worker uses these services in order:

1. It posts a fixed GraphQL query to `https://api.fontawesome.com` for the latest `7.x` Free version, icons, aliases, families, and styles.
2. If the candidate is newer, it requests `https://registry.npmjs.org/%40fortawesome%2Ffontawesome-free/{version}` and verifies the exact official package name, version, and Free package license expression.
3. It downloads an allowlisted set of exact-version CSS and WOFF2 assets from `https://cdnjs.cloudflare.com/ajax/libs/font-awesome/{version}/`.
4. It downloads the same files independently from `https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@{version}/` and requires byte-for-byte agreement with cdnjs.
5. It validates CSS-to-font references, required styles and fonts, SRI values, semantic version, Free-only metadata, families, styles, icon identifiers, and aliases before returning a complete schema-2 record.

BFAL does not call a separate cdnjs catalog API. cdnjs is both a background asset-validation source and the browser runtime host after a newer release is adopted. jsDelivr is an independent background validation source, not the selected browser runtime host.

The worker uses the WordPress HTTP API with TLS verification, unsafe-URL rejection, no redirects, bounded per-request and aggregate response sizes, bounded request count, and bounded time. It handles transport, HTTP, JSON, GraphQL, publication, provider disagreement, and schema failures without replacing valid data.

## Browser asset delivery

The packaged Font Awesome 7 fallback serves its CSS and fonts from the plugin on the site's own origin. A newer completely validated `7.x` record uses exact-version cdnjs URLs for the main stylesheet, Font Awesome 5 font-face compatibility stylesheet, optional Font Awesome 4 compatibility stylesheets, and the WOFF2 fonts those styles reference.

A deliberate earlier `5.x` singleton owner uses the legacy channel's versioned `use.fontawesome.com/releases/` CSS and font paths. BFA does not silently convert a selected `5.x` owner to `7.x`.

## External request data

No Font Awesome account or API token is required for the Free channels. BFA does not add post content, user content, or Font Awesome credentials to provider requests.

External providers receive ordinary connection data. Server-side background requests may expose the server IP address, requested URL and candidate version, timing, and HTTP headers. WordPress's default HTTP user agent may include the WordPress version and site URL. Browser asset requests may expose the visitor IP address, user agent, referring page, and requested asset.

## Compatibility and rollback

The `[icon]` shortcode, its established attributes, BFAL public singleton, existing filters, Classic Editor picker, and conflict settings remain compatibility boundaries. Font Awesome 4 and 5 shortcode names and classes remain supported on the default channel through validated aliases and compatibility CSS where the selected Font Awesome 7 Free package provides them.

An emergency rollback to an older BFAL may restore legacy request, cache, and asset behavior. Newer durable options remain preserved for later forward recovery. Rollback compatibility does not redefine the current background-only contract.

## Verification contract

Automated tests use deterministic fixtures and intercept every Font Awesome, npm, cdnjs, and jsDelivr request. Required coverage includes:

- immediate packaged fallback with zero ordinary-request provider HTTP
- fresh, stale, missing, malformed, and wrong-channel durable records
- schema-1 and schema-2 validation and preservation
- one-shot immutable channel selection and deliberate first-caller ownership
- successful candidate adoption and last-known-good retention on every failure class
- schedule, lock, lease-expiry, retry, object-cache, persistence-failure, and multisite interleavings
- selected metadata and asset versions remaining identical
- relative CSS font references, provider byte agreement, and required compatibility assets
- current and supported rollback dependency modes

Exact dependency references, test counts, archive checksums, and release-specific evidence belong in a release pull request or release record, not in this evergreen contract.
