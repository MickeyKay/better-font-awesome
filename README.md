[![CI](https://github.com/MickeyKay/better-font-awesome/actions/workflows/ci.yml/badge.svg)](https://github.com/MickeyKay/better-font-awesome/actions/workflows/ci.yml) [![Downloads](https://img.shields.io/wordpress/plugin/dt/better-font-awesome.svg)](https://wordpress.org/plugins/better-font-awesome/) [![License: GPL v3](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)

# Better Font Awesome #
**Contributors:** [mcguive7](https://profiles.wordpress.org/mcguive7/), [aaronbmm](https://profiles.wordpress.org/aaronbmm/), [mightyminnow](https://profiles.wordpress.org/mightyminnow/)<br>
**Tags:** font awesome, icons, icon font, shortcode, block editor<br>
**Donate link:** https://mickeykay.me<br>
**Requires at least:** 6.5<br>
**Tested up to:** 7.1<br>
**Requires PHP:** 7.4<br>
**Stable tag:** 2.1.0<br>
**License:** GPLv2+<br>
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html<br>

Font Awesome 7 Free icons for WordPress with a native block, shortcodes, HTML, TinyMCE, automatic metadata updates, and Font Awesome 4 and 5 compatibility.

## Description ##

[![CI](https://github.com/MickeyKay/better-font-awesome/actions/workflows/ci.yml/badge.svg)](https://github.com/MickeyKay/better-font-awesome/actions/workflows/ci.yml)

**Do you find this plugin helpful? Please consider [leaving a 5-star review](https://wordpress.org/support/view/plugin-reviews/better-font-awesome).**

Better Font Awesome integrates the current [Font Awesome 7 Free](https://fontawesome.com/search?o=r&m=free) channel into your WordPress project through Better Font Awesome Library 3, along with accompanying CSS, shortcodes, and a TinyMCE icon shortcode generator. A packaged Font Awesome 7 fallback works immediately, without waiting for WordPress cron or a remote metadata request.


### Features ###

* **Automatically updated** - refreshes validated metadata in the background for newer compatible Font Awesome 7 Free releases, so normal requests never wait for metadata discovery or asset validation.

* **Backwards compatible** - established Font Awesome 4 and 5 shortcode names and classes remain compatible through validated aliases, Font Awesome 5 font-face compatibility CSS, and the optional Font Awesome 4 shim.

* **Immediate packaged fallback** - includes validated Font Awesome 7 Free CSS, fonts, and metadata so icons work even when cron or external services are unavailable.

* **Native icon block** - search the current Font Awesome Free catalog in the Block Editor, add an optional accessible label, and keep icon rendering dynamic.

* **Compatible with other plugins** - designed to work with shortcodes generated with plugins like [Font Awesome Icons](http://wordpress.org/plugins/font-awesome/ "Font Awesome Icons"), [Font Awesome More Icons](https://wordpress.org/plugins/font-awesome-more-icons/ "Font Awesome More Icons"), and [Font Awesome Shortcodes](https://wordpress.org/plugins/font-awesome-shortcodes/), so you can switch to Better Font Awesome and your existing shortcodes will still work.

* **Validated CDN delivery** - a newer background-validated release uses exact-version cdnjs CSS and font assets with integrity metadata.

* **Shortcode generator** - includes an easy-to-use TinyMCE dropdown shortcode generator.

### Settings ###
All settings can be adjusted via **Settings &rarr; Better Font Awesome**.

### Usage ###
Better Font Awesome can be used in 4 different ways: Block Editor, shortcode, TinyMCE, and HTML.

### 1. Block Editor ###
Insert the **Font Awesome Icon** block, then use its block settings to search the current Font Awesome Free catalog. Add an accessible label when the icon communicates meaning, or leave the label empty when the icon is decorative.

The block is rendered dynamically through the same established server-side icon contract as the shortcode. Saved posts store the semantic icon name and style rather than fixed icon markup.

### 2. Shortcode ###
`[icon name="flag" class="2x spin border" unprefixed_class="my-custom-class"]`
Note that prefixes (`fa-` and `icon-`) are not required, but if you do include them things will still work just fine. Better Font Awesome normalizes established shortcode prefixes and resolves validated legacy icon-name aliases for the current Font Awesome 7 Free channel.

That means that all of the following established shortcode forms continue to work with the current Font Awesome 7 Free channel:
`[icon name="flag" class="2x spin border"]`
`[icon name="icon-flag" class="icon-2x icon-spin icon-border"]`
`[icon name="fa-flag" class="fa-2x fa-spin fa-border"]`
`[icon name="icon-flag" class="fa-2x spin icon-border"]`

You can read more about shortcode usage on [Github](https://github.com/MickeyKay/better-font-awesome-library#shortcode)

### 3. TinyMCE ###
Better Font Awesome also provides you with an easy-to-use drop down menu when editing in TinyMCE's visual mode. Check out our [Screenshots](https://wordpress.org/plugins/better-font-awesome/screenshots/ "Screenshots") to see what it looks like.

### 4. HTML ###
Note that prefixes are required for HTML usage, and are version-specific. For this reason, shortcode usage is encouraged over HTML. If you do want to use HTML, however, you can read more on the [Font Awesome site](http://fortawesome.github.io/Font-Awesome/examples/).

### Advanced / Integration ###
Better Font Awesome is built around the [Better Font Awesome Library](https://github.com/MickeyKay/better-font-awesome-library). This library allows you to integrate Better Font Awesome into any custom project you want to create (perhaps a theme or plugin with a constantly up-to-date icon list), and includes all the [filters](https://github.com/MickeyKay/better-font-awesome-library#filters) you might need.

### External services ###
Better Font Awesome works immediately from its packaged Font Awesome 7 Free fallback. It uses the following external services only for background updates or for assets from a newer validated release. No Font Awesome account or API token is required for the Free channel.

* **Font Awesome GraphQL API** (`https://api.fontawesome.com`) - an asynchronous server-side WP-Cron worker requests the latest public `7.x` Free release version, icon names, aliases, families, and styles. Review the [Font Awesome terms of service](https://fontawesome.com/tos) and [privacy policy](https://fontawesome.com/privacy).
* **npm registry** (`https://registry.npmjs.org/%40fortawesome%2Ffontawesome-free/{version}`) - when Font Awesome reports a newer candidate, the same background worker confirms that exact official Free package version, name, and license. Review the [npm terms](https://docs.npmjs.com/policies/terms) and [privacy notice](https://docs.npmjs.com/policies/privacy).
* **cdnjs asset service** (`https://cdnjs.cloudflare.com/ajax/libs/font-awesome/{version}/`) - the background worker downloads an allowlisted set of exact-version CSS and WOFF2 files for validation. BFAL does not call a separate cdnjs catalog API. After a newer release passes every check, visitors' browsers may request its selected CSS and referenced font files from this host. Review the [cdnjs terms](https://cdnjs.com/terms) and [Cloudflare privacy policy](https://www.cloudflare.com/privacypolicy/).
* **jsDelivr asset service** (`https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@{version}/`) - the background worker independently downloads the same allowlisted files and requires their bytes to match cdnjs. BFA does not select jsDelivr as the browser runtime host. Review the [jsDelivr terms and policies](https://www.jsdelivr.com/terms).
* **Legacy Font Awesome 5 CDN** (`https://use.fontawesome.com/releases/`) - if another plugin or theme deliberately initializes BFAL first and selects the legacy `5.x` channel, visitors' browsers may request that selected version's CSS and fonts from this host.

Normal frontend, administrator, REST, editor, settings, shortcode, picker, and getter requests do not perform BFA or BFAL metadata discovery or candidate asset-validation HTTP. Separately, WordPress core may fetch a registered external editor stylesheet while constructing Block Editor assets; that core behavior is not a BFA or BFAL metadata-validation request.

Server-side provider requests expose ordinary connection data such as the server IP address, requested URL and version, timing, and HTTP headers. WordPress's default HTTP user agent may include the WordPress version and site URL. Browser asset requests can expose ordinary connection data such as the visitor's IP address, user agent, referring page, and requested asset. BFA does not add post content, user content, Font Awesome credentials, or an API token to these requests. If discovery, publication, transport, or validation fails, BFA continues using the packaged fallback or validated last-known-good release.

### Languages / Translations ###
* English
* French (thanks to [David Tisserand](http://www.pixemotion.fr))

### Credits ###
Many thanks to the following plugins and their authors:

* [Font Awesome Icons](http://wordpress.org/plugins/font-awesome/ "Font Awesome Icons") by [Rachel Baker](http://rachelbaker.me/ "Rachel Baker")
* [Font Awesome More Icons](https://wordpress.org/plugins/font-awesome-more-icons/ "Font Awesome More Icons") by [Web Guys](http://webguysaz.com/ "Web Guys")
* [Font Awesome Shortcodes](https://wordpress.org/plugins/font-awesome-shortcodes/) by [FoolsRun](https://profiles.wordpress.org/foolsrun/ "FoolsRun")
* Dmitriy Akulov and the awesome folks at [jsDelivr](http://www.jsdelivr.com/)

And many thanks to the following folks who helped with testing and QA:

* [Jeffrey Dubinksy](http://vanishingforests.org/)
* [Neil Gee](https://twitter.com/_neilgee)
* [Michael Beil](https://twitter.com/MichaelBeil)
* [Rob Neue](https://twitter.com/rob_neu)
* [Gary Jones](https://twitter.com/GaryJ)
* [Jan Hoek](https://twitter.com/JanHoekdotCom)


## Installation ##

This section describes how to install the plugin and get it working.

1. Upload Better Font Awesome to the /wp-content/plugins/ directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. That's it! You can now use the native block, shortcode, TinyMCE, or HTML methods outlined in the [Description](https://wordpress.org/plugins/better-font-awesome "Description") section.


## Frequently Asked Questions ##

### How is this plugin different from other Font Awesome plugins? ###

This plugin defaults to the current Font Awesome 7 Free channel and refreshes validated metadata for compatible 7.x releases in the background. It also supports established Font Awesome 4 and 5 shortcode names and classes through compatibility assets and aliases, so existing compatible content can continue to work. It does not provide a separate Font Awesome 6 channel or claim comprehensive native support for every Font Awesome 6 name or markup pattern.

### How does automatic metadata refresh work? ###

Automatic metadata refresh depends on functioning WordPress cron. Default WP-Cron is request-driven, so refresh can be delayed on low-traffic sites. Sites using `DISABLE_WP_CRON` should invoke `wp-cron.php` through a real scheduler or an equivalent hosting mechanism. `wp cron test` is available as a WP-CLI diagnostic.

If scheduled refresh does not run, Better Font Awesome continues serving validated last-known-good metadata or its bundled fallback. Normal page requests do not wait on the Font Awesome metadata service.

### Do I have to install any font files? ###

Nope. The packaged Font Awesome 7 fallback loads CSS and fonts from your own site. If a newer release is validated and adopted, the browser loads that exact release's selected CSS and font files from cdnjs.

### What happens if I have another plugin/theme that uses Font Awesome? ###

Better Font Awesome does it's best to load after any existing Font Awesome CSS, which can minimize conflicts. If you are experiencing any unexpected behavior resulting from plugin/theme conflicts, you can try checking the box to "Remove existing Font Awesome styles" in under **Settings &rarr; Better Font Awesome**.


## Screenshots ##
1. The icon shortcode dropdown selector
2. Better Font Awesome settings, accessed via Settings &rarr; Better Font Awesome


## Changelog ##

### 2.1.0 ###
* Integrates the public stable Better Font Awesome Library 2.1.0 while preserving existing settings and shortcode content.
* Refreshes Font Awesome 5 Free metadata asynchronously through WP-Cron, with no metadata API wait on normal frontend, admin, editor, REST, or shortcode requests.
* Adds validated durable last-known-good metadata, bundled fallback behavior, failure backoff, and recovery.
* Hardens multisite lifecycle handling and settings persistence while retaining Classic Editor, shortcode, frontend, and hybrid `wp_editor()` compatibility.
* Requires WordPress 6.5 or newer and PHP 7.4 or newer, and is tested with WordPress through 7.1.

### 2.0.4 ###
* Bump BFAL to properly esc attributes
* Add unit tests

### 2.0.3 ###
* Bugfix: fix broken icon text selection
* Improve admin settings success/error message logic

### 2.0.2 ###
* Bugfix: fix CSRF vulnerability

### 2.0.1 ###
* Bugfix: add necessary @font-face mappings to ensure site-specific CSS and pseudo-elements render correctly

### 2.0.0 ###
* Add support for Font Awesome v5
* Integration with Font Awesome GraphQL API for all data fetching (improve performance)
* Integrate with Font Awesome CDN for all CSS
* Add option to include the v4 Font Awesome CSS shim to support older icons (default on for upgrades)
* Updatee hard-coded fallback Font Awesome version
* Modify version check frequency to a saner 24 hour interval
* Ensure admin notices are dismissible
* Lower data fetch timeout to mitigate performance risks
* Remove legacy options that are no longer relevant (version select, minification opt-out)
* Add more/better unit tests to ensure things are working as expected

### 1.7.6 ###
* Fix: revert to 1.7.4 codebase.
* Fix: remove calls to `ready()` jQuery method to support latest jQuery versions.

### 1.7.5 ###
(BAD BUILD)
* Fix: remove calls to `ready()` jQuery method to support latest jQuery versions.
* Fix: update fontawesome-iconpicker dependency to repair broken icon select functionality.

### 1.7.4 ###
* Fix: revert accidental bump to underlying Better Font Awesome Library dependency.

### 1.7.3 ###
* Add admin notice to invite beta testers.

### 1.7.2 ###
* Bump "tested up to" value to 5.5.

### 1.7.1 ###
* Fix functionality to hide/show admin notices.

### 1.7.0 ###
* Update fallback Font Awesome to v4.7.0.
* Switch from using git submodules to composer dependency management for core library inclusion.

## Upgrade Notice ##

### 2.1.0 ###
Reliability and compatibility release for Font Awesome 5 Free. Requires WordPress 6.5 or newer and PHP 7.4 or newer. Existing settings and shortcodes are preserved.

### 2.0.4 ###
* Bump BFAL to properly esc attributes
* Add unit tests

### 2.0.3 ###
* Bugfix: fix broken icon text selection
* Improve admin settings success/error message logic

### 2.0.2 ###
* Bugfix: fix CSRF vulnerability

### 2.0.1 ###
* Bugfix: add necessary @font-face mappings to ensure site-specific CSS and pseudo-elements render correctly

### 2.0.0 ###
* Adds Font Awesome 5, GraphQL metadata, CDN CSS, an optional v4 shim, daily update checks, refreshed fallback data, dismissible notices, and compatibility tests.

### 1.7.6 ###
* Fix: revert to 1.7.4 codebase.
* Fix: remove calls to `ready()` jQuery method to support latest jQuery versions.

### 1.7.5 ###
(BAD BUILD)
* Fix: remove calls to `ready()` jQuery method to support latest jQuery versions.
* Fix: update fontawesome-iconpicker dependency to repair broken icon select functionality.

### 1.7.4 ###
* Fix: revert accidental bump to underlying Better Font Awesome Library dependency.

### 1.7.3 ###
* Add admin notice to invite beta testers.

### 1.7.2 ###
* Bump "tested up to" value to 5.5.

### 1.7.1 ###
* Fix functionality to hide/show admin notices.

### 1.7.0 ###
* Update fallback Font Awesome to v4.7.0.
* Switch from using git submodules to composer dependency management for core library inclusion.
