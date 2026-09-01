# Better Font Awesome development guide

## Mission

Better Font Awesome is a widely installed WordPress plugin. Preserve existing shortcode, settings, filters, and frontend behavior unless a change is explicitly approved and covered by regression tests.

The plugin delegates Font Awesome version and icon data to `mickey-kay/better-font-awesome-library` (BFAL). BFAL is a separately published OSS library used by other projects. Treat its public API as a compatibility boundary and make BFAL changes in its own repository.

## Current priorities

1. Stabilize the existing plugin and its live-update architecture.
2. Restore reliable Font Awesome Free metadata updates with validated cached and bundled fallbacks.
3. Add modern editor support with a dynamic block while keeping the shortcode canonical and backward compatible.
4. Consider Font Awesome Pro only after the Free experience is stable.

Do not make a production request wait on Font Awesome metadata. Remote refreshes should use the WordPress HTTP API, TLS verification, timeouts, validation, persistent last-known-good data, locking, backoff, and asynchronous scheduling.

## BFAL ownership and live updates

The live-ish Font Awesome Free update path serves validated local data immediately and uses bundled metadata only as a fallback. Normal requests must never perform metadata HTTP. Under the established precedence model, BFA owns WordPress persistence, freshness, scheduling, locking, retries, and migration when its first `Better_Font_Awesome_Library::get_instance()` call owns the BFAL singleton.

BFAL first-caller ownership is intentional. BFA passes its provider and asynchronous refresh callback through its existing first call, but it does not override a deliberate earlier owner. Do not reclassify that precedence as a defect without concrete user-facing evidence and explicit repository-owner approval. New BFAL public APIs or precedence changes also require owner approval.

## Local workflow

Prerequisites are Docker, npm, and a supported Node.js LTS release: 20.19 or newer, 22.13 or newer, or 24 or newer.

```sh
npm ci
npm run composer:install
npm run env:start
npm test
npm run lint
npm run analyze
```

The development site is available on the port selected by `WP_ENV_PORT`, defaulting to 8888. The test site defaults to 8889. Use `npm run env:stop` when finished and reserve `npm run env:destroy` for intentionally deleting local WordPress state.

## Quality bar

- Keep runtime support at PHP 7.4 or newer and WordPress 6.5 or newer unless a release decision changes those floors.
- Add focused PHPUnit coverage for every PHP behavior change.
- Add Playwright coverage for editor workflows when block development begins.
- Do not make tests depend on the live Font Awesome service. Mock HTTP responses and test fresh, stale, failed, and malformed response paths.
- Run PHPCS and PHPStan on first-party code. Keep legacy baselines narrow and do not grow them for new code.
- Escape output late, sanitize and validate explicit input fields, and pair nonces with capability checks.
- Keep generated release archives free of development files and include the exact production Composer dependencies.
- Never commit secrets, access tokens, local WordPress state, `vendor/`, or `node_modules/`.

## Change discipline

Prefer small, reviewable changes. Separate infrastructure, BFAL transport/cache behavior, editor work, and UI redesigns. For any compatibility-sensitive change, document the user-visible behavior, failure mode, rollback path, and tests before release.
