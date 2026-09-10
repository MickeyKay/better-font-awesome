# Hosted Font Awesome Pro Kits

Hosted Pro is an opt-in integration for an existing Font Awesome subscription. Automatic Font Awesome Free remains the default. BFA does not sell Pro access, manage billing or change a Font Awesome account.

## Connect and use

In Font Awesome, publish a v7 Pro **By Style** Kit, select **Web Fonts** and **CSS-only** embedding, and enable compatibility. Include Classic Solid, Regular and Brands. Classic Light and Thin are optional. Additional families, custom icons, By Icon Kits, SVG delivery and older versions are unsupported. Configure permitted domains in Font Awesome, including your staging domain. Keep the version on Latest 7.x or a specific v7 release.

In Settings > Better Font Awesome, leave local Free delivery off. Under Hosted Font Awesome Pro Kit, enter the Kit identifier from `https://kit.fontawesome.com/KIT_ID.css` and an account API token with **Read Kits Data** permission. The verified minimum scopes are `public` and `kits_read`; SVG, download, account profile and billing scopes are unnecessary. Use HTTPS for the WordPress administrator connection. A blank token reuses a saved active connection's token.

Connect Kit starts validation immediately. The settings page continues preparation in bounded asynchronous steps and shows progress. Keep it open until activation finishes; reloading resumes pending work. Initial connection does not require WP-Cron. Preparation retains the working Free or previous Pro configuration until a complete candidate passes validation. Refresh Kit uses the same immediate flow, and scheduled daily refresh uses the same acquisition code. Connected is shown only after a new request confirms effective Kit delivery under BFA ownership. An initialization filter that prevents that selection is reported as an ownership/configuration conflict. Reopen editors after activation to obtain the new catalog.

Pick an icon by name and choose an available Style. Classic/hybrid pickers insert the existing shortcode. The native block retains its existing name/style attributes and dynamic renderer. Site default can use the available Classic styles; Brands is an icon-specific style, not a site default. An omitted native block style remains Solid; omitted shortcode styles keep their established legacy `fa` markup. Explicit saved choices remain explicit even when unavailable. Inherited icons try the requested default, Solid, Regular, Brands, Light, then Thin. Missing names retain the requested style. These rules do not rewrite content.

## Delivery, failures and switching

A connected Kit is **hosted delivery**, separate from bundled-local Free. Browsers load CSS from `kit.fontawesome.com` and imported CSS/fonts from Font Awesome's delivery hosts, including `ka-p.fontawesome.com`. These requests disclose ordinary connection information to the vendor and may be subject to domain restrictions, subscription conditions and Kit usage limits. No Font Awesome JavaScript loader is used. BFA does not download, mirror or distribute Pro assets. TinyMCE receives a direct CSS URL through `mce_css`; remote theme-editor imports are not installed.

Selecting Serve Font Awesome locally cancels Pro preparation and refresh and preserves the saved active connection. Local Free loads no Pro assets or metadata. Unchecking local resumes automatic Free. Refresh Kit validates and reactivates a saved Pro connection. Use automatic Free also pauses Pro without deleting the saved connection. Disconnect and forget Kit deletes the saved credential, catalog and pending work. Neither action changes content: unavailable Pro icons may become blank. A failed or incomplete replacement keeps the old active snapshot.

Temporary service failures retain last-known-good metadata and retry at bounded intervals, stopping after five failed requests in one preparation attempt. An existing active Kit gets another scheduled recovery opportunity after a day; an initial failed connection needs a new Connect action. Authorization failures, unsupported configuration, incomplete catalogs and revision changes stop preparation with an actionable status; Connect or Refresh starts again. Interrupted steps have an expiring lease. Old or canceled work cannot promote a candidate or recreate a canceled event.

Cached metadata is neither proof of current entitlement nor a guarantee of hosted font availability. Blocked or unavailable CSS/fonts can leave blank glyphs; there is no silent Free replacement or authentication retry during rendering. External edits to a hosted Kit take effect independently of BFA. A local metadata snapshot cannot roll back those remote edits.

## Ownership and local state

BFA captures whether its existing first BFAL initialization actually ran. An earlier owner remains authoritative even if its Kit URL matches. BFA installs its Pro catalog only when that ownership and the effective Kit assets match. The Free metadata getters retain their Free meaning. BFAL receives only the immutable delivery mode, validated CSS URL and 7.x channel, never credentials or a Pro release manifest.

Deactivation cancels pending Pro work while retaining the saved delivery choice for explicit reactivation. Network activation/deactivation applies only to the current network. Workers do not reuse credentials after switching to a different site.

Pro credentials, candidate and active metadata use a separate per-site, non-autoloaded WordPress option. Icon identities are stored compactly and converted to the existing picker records on demand. AES-256-GCM encrypts tokens using site-specific material derived from WordPress authentication keys/salts in wp-config.php. OpenSSL and configured AUTH_KEY/AUTH_SALT constants of at least 32 characters each are required for connecting; there is no plaintext fallback. Salt changes require reconnection. Encryption protects a database-only disclosure, not a compromised WordPress runtime. Secrets are never included in editor configuration, status responses or BFA diagnostics. Administrators need `manage_options` and a valid nonce for connection operations. WordPress editing permission does not itself confer a Font Awesome license.

Only explicit Connect/Refresh batches and scheduled Pro work contact the fixed Font Awesome API. Each batch performs one bounded request with a timeout, TLS verification, no redirects and a response-size limit. Kit-scoped pagination checks counts, unique identities, selected styles and revision consistency. Same-release Free metadata enriches labels/aliases and checks coverage; the current local Free catalog provides an additional compatibility floor. Ordinary frontend, admin, editor, picker and getter requests read local data only. Free acquisition, records and retry behavior remain unchanged. Its activation guard also skips scheduling when a saved Pro connection will own delivery.

## Verification and publication gates

Synthetic tests exercise state transitions, ownership, security, inheritance and editor loading. Browser fixtures may use a full-sized synthetic catalog and a publicly distributed Free font to test transport; these are not real Pro delivery acceptance. A securely supplied non-production Kit must still validate actual delivery and supported styles before publication.

The integration may temporarily pin an exact reviewed BFAL development commit. This is not a stable dependency adoption. Before publication, adopt its reviewed public stable release, restore exact stable dependency assertions and pass strict Composer validation and release-tree identity checks without development-pin exceptions. Keep full package QA, WordPress-floor editor acceptance and owner approval as release gates. No new BFA version or publication is implied by this feature branch.

Sources: [Font Awesome Kit setup](https://docs.fontawesome.com/web/setup/use-kit), [API scopes](https://docs.fontawesome.com/apis/graphql/auth/), [token exchange](https://docs.fontawesome.com/apis/graphql/token-endpoint), [Kit-scoped objects](https://docs.fontawesome.com/apis/graphql/objects), [WordPress mce_css](https://developer.wordpress.org/reference/hooks/mce_css/).

Rollback should use the previous complete BFA package. Pro names/styles remain in saved content, but older BFA block renderers may interpret Light/Thin as Solid and Free assets cannot render Pro-only icons. An older BFAL dependency is a Free emergency compatibility path, not supported Pro delivery.
