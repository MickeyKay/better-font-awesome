=== Better Font Awesome ===
Contributors: McGuive7, aaronbmm, mightyminnow
Tags: font awesome, icons, icon font, shortcode, block editor
Donate link: https://mickeykay.me
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.0.0
License: GPLv2+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add Font Awesome 7 Free icons with a native WordPress block, shortcodes, a Classic Editor picker, automatic icon updates, and legacy support.

== Description ==

Add Font Awesome Free icons to WordPress using a native Icon block, shortcodes, the Classic Editor picker, or HTML. Search the latest compatible Free catalog, customize icons in the Block Editor, and keep established Font Awesome 4 and 5 content working.

A built-in fallback means icons work immediately. Better Font Awesome automatically keeps Font Awesome Free icons current with newer compatible releases in the background, while pages and editors keep loading normally.

= Key features =

* **Native Icon block** - search the latest compatible Font Awesome Free icons and styles, then adjust size, color, spacing, alignment, and an optional accessible label.
* **Flexible workflows** - use the block, shortcodes, the Classic Editor picker, or HTML and CSS.
* **Automatic icon updates** - start with the built-in fallback and receive newer compatible icons automatically in the background.
* **Legacy compatibility** - keep established Font Awesome 4 and 5 content working, with optional controls for older or competing styles.

= Getting started =

== Native Icon block ==

In the Block Editor, insert the **Font Awesome Icon** block. Choose an icon and available Free style, then use WordPress controls for font size, text color, margin, padding, and left, center, or right alignment. Add an accessible label when the icon communicates meaning, or leave it empty for a decorative icon.

The Icon block is a standalone block that works in layouts such as Groups, Rows, and Columns. To place an icon directly within a line of text, use the shortcode.

== Shortcodes ==

Use the `[icon]` shortcode wherever WordPress processes shortcodes:

`[icon name="flag" class="2x spin border" unprefixed_class="my-custom-class"]`

The `fa-` and `icon-` prefixes are optional in shortcode attributes, and established prefixed forms continue to work.

== Classic Editor picker ==

In the Classic Editor visual toolbar, choose **Insert Icon**, search the Free catalog, and select an icon. The picker inserts the matching shortcode into your content.

== HTML and CSS ==

You can also use Font Awesome classes in HTML or CSS. Unlike shortcodes, HTML class prefixes are required and depend on the selected style. For current syntax, see the [Font Awesome guide to adding icons](https://docs.fontawesome.com/web/add-icons/how-to/).

= Automatic icon updates and built-in fallback =

Validated Font Awesome 7 Free CSS, fonts, and icon data ship with the plugin, so icons render immediately after activation. A scheduled task checks for newer compatible Font Awesome 7 releases in the background and uses one only after validation. If a check is delayed or fails, the last validated release or packaged fallback stays active.

= Compatibility and conflicts =

Established Font Awesome 4 and 5 shortcode names and classes are supported through compatible aliases and styles, plus an optional Font Awesome 4 CSS shim. Font Awesome Pro and a separate Font Awesome 6 channel are not provided.

If a theme or another plugin also loads Font Awesome, go to **Settings > Better Font Awesome** and enable **Remove existing Font Awesome**. This can reduce duplicate or conflicting styles, but no automatic conflict tool can cover every theme or plugin integration.

== Installation ==

1. Install and activate Better Font Awesome from **Plugins > Add New**.
2. Add the **Font Awesome Icon** block, use an `[icon]` shortcode, or choose **Insert Icon** in the Classic Editor. Optional compatibility controls are under **Settings > Better Font Awesome**.

== Frequently Asked Questions ==

= Does Better Font Awesome support Font Awesome Pro? =

No. Better Font Awesome supports Font Awesome Free.

= Do visitors download fonts from another service? =

The packaged fallback loads CSS and fonts from your own site. If the plugin validates and adopts a newer compatible release, visitors' browsers may load that release's selected CSS and font files from cdnjs.

= Will existing Font Awesome 4 and 5 content keep working? =

Established shortcode names and classes are supported through compatible aliases and styles. For Font Awesome 4 content, enable the optional CSS shim when needed. Unusual custom markup or CSS may still need site-specific adjustments.

= What if another plugin or theme loads Font Awesome? =

Enable **Remove existing Font Awesome** under **Settings > Better Font Awesome** to attempt to remove competing styles and shortcodes. Test the result with your theme and plugins because integrations vary.

== External services ==

Better Font Awesome works immediately from its packaged Font Awesome 7 Free fallback. It uses the following external services only for background updates or for assets from a newer validated release. No Font Awesome account or API token is required for the Free channel.

* **Font Awesome GraphQL API** (`https://api.fontawesome.com`) - an asynchronous server-side WP-Cron worker requests the latest public `7.x` Free release version, icon names, aliases, families, and styles. Review the [Font Awesome terms of service](https://fontawesome.com/tos) and [privacy policy](https://fontawesome.com/privacy).
* **npm registry** (`https://registry.npmjs.org/%40fortawesome%2Ffontawesome-free/{version}`) - when Font Awesome reports a newer candidate, the same background worker confirms that exact official Free package version, name, and license. Review the [npm terms](https://docs.npmjs.com/policies/terms) and [privacy notice](https://docs.npmjs.com/policies/privacy).
* **cdnjs asset service** (`https://cdnjs.cloudflare.com/ajax/libs/font-awesome/{version}/`) - the background worker downloads an allowlisted set of exact-version CSS and WOFF2 files for validation. BFAL does not call a separate cdnjs catalog API. After a newer release passes every check, visitors' browsers may request its selected CSS and referenced font files from this host. Review the [cdnjs service information](https://cdnjs.com/about), [Cloudflare website terms](https://www.cloudflare.com/website-terms/), and [Cloudflare privacy policy](https://www.cloudflare.com/privacypolicy/).
* **jsDelivr asset service** (`https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@{version}/`) - the background worker independently downloads the same allowlisted files and requires their bytes to match cdnjs. BFA does not select jsDelivr as the browser runtime host. Review the [jsDelivr terms and policies](https://www.jsdelivr.com/terms).
* **Legacy Font Awesome 5 CDN** (`https://use.fontawesome.com/releases/`) - if another plugin or theme deliberately initializes BFAL first and selects the legacy `5.x` channel, visitors' browsers may request that selected version's CSS and fonts from this host.

Normal frontend, administrator, REST, editor, settings, shortcode, picker, and getter requests do not perform BFA or BFAL metadata discovery or candidate asset-validation HTTP. Separately, WordPress core may fetch a registered external editor stylesheet while constructing Block Editor assets; that core behavior is not a BFA or BFAL metadata-validation request.

Server-side provider requests expose ordinary connection data such as the server IP address, requested URL and version, timing, and HTTP headers. WordPress's default HTTP user agent may include the WordPress version and site URL. Browser asset requests can expose ordinary connection data such as the visitor's IP address, user agent, referring page, and requested asset. BFA does not add post content, user content, Font Awesome credentials, or an API token to these requests. If discovery, publication, transport, or validation fails, BFA continues using the packaged fallback or validated last-known-good release.

== Support ==

Need help? Start a topic in the [WordPress.org support forum](https://wordpress.org/support/plugin/better-font-awesome/). Developers can also report reproducible issues on [GitHub](https://github.com/MickeyKay/better-font-awesome/issues).

If Better Font Awesome helps your site, please consider leaving a brief [WordPress.org review](https://wordpress.org/support/plugin/better-font-awesome/reviews/).

== Screenshots ==

1. Search the Font Awesome Free catalog, choose an available style, and add an accessible label.
2. Adjust icon alignment, size, color, and spacing with WordPress block controls.
3. Combine standalone Icon blocks with text in a polished frontend layout.
4. Search and insert a matching shortcode with the Classic Editor picker.
5. Review the active Font Awesome version and optional compatibility settings.

== Changelog ==

= 3.0.0 =
* Adds Font Awesome 7 Free with validated background updates and a packaged fallback.
* Adds a native Icon block with catalog search, style and design controls, alignment, and accessible labels.
* Preserves existing shortcodes, settings, integrations, Classic Editor picker, and Font Awesome 4 and 5 compatibility.

Older release history is preserved in the project's [historical changelog](https://github.com/MickeyKay/better-font-awesome/blob/master/docs/historical-changelog.md).

== Upgrade Notice ==

= 3.0.0 =
Adds Font Awesome 7 Free, background updates, a packaged fallback, and the native Icon block while preserving existing settings, shortcodes, and Classic Editor workflows. Requires WordPress 6.5+ and PHP 7.4+.
