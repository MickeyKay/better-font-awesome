=== Better Font Awesome ===
Contributors: McGuive7, aaronbmm, mightyminnow
Tags: font awesome, icons, icon font, shortcode, classic editor
Donate link: https://mickeykay.me
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPLv2+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Font Awesome 5 Free icons for WordPress with shortcodes, HTML, TinyMCE, automatic metadata updates, backwards compatibility, and CDN delivery.

== Description ==

[![CI](https://github.com/MickeyKay/better-font-awesome/actions/workflows/ci.yml/badge.svg)](https://github.com/MickeyKay/better-font-awesome/actions/workflows/ci.yml)

**Do you find this plugin helpful? Please consider [leaving a 5-star review](https://wordpress.org/support/view/plugin-reviews/better-font-awesome).**

Better Font Awesome integrates the latest available release in the supported [Font Awesome 5 Free](https://fontawesome.com/v5/search?o=r&m=free) channel into your WordPress project, along with accompanying CSS, shortcodes, and a TinyMCE icon shortcode generator. Font Awesome 6 and 7 are coming soon.


= Features =

* **Automatically updated** - refreshes validated metadata in the background for the most recent available Font Awesome 5 Free release, so normal requests never wait for the metadata service.

* **Backwards compatible** - established Font Awesome 4 and 5 shortcode prefixes remain compatible, including the optional Font Awesome 4 shim for upgraded sites.

* **Compatible with other plugins** - designed to work with shortcodes generated with plugins like [Font Awesome Icons](http://wordpress.org/plugins/font-awesome/ "Font Awesome Icons"), [Font Awesome More Icons](https://wordpress.org/plugins/font-awesome-more-icons/ "Font Awesome More Icons"), and [Font Awesome Shortcodes](https://wordpress.org/plugins/font-awesome-shortcodes/), so you can switch to Better Font Awesome and your existing shortcodes will still work.

* **CDN speeds** - Font Awesome CSS is loaded from a versioned public CDN URL.

* **Shortcode generator** - includes an easy-to-use TinyMCE dropdown shortcode generator.

= Settings =
All settings can be adjusted via **Settings &rarr; Better Font Awesome**.

= Usage =
Better Font Awesome can be used in 3 different ways: shortcode, HTML, and TinyMCE

= 1. Shortcode =
`[icon name="flag" class="2x spin border" unprefixed_class="my-custom-class"]`
Note that prefixes (`fa-` and `icon-`) are not required, but if you do include them things will still work just fine. Better Font Awesome normalizes established shortcode prefixes for the supported Font Awesome 5 Free channel.

That means that all of the following established shortcode forms continue to work with the supported Font Awesome 5 Free channel:
`[icon name="flag" class="2x spin border"]`
`[icon name="icon-flag" class="icon-2x icon-spin icon-border"]`
`[icon name="fa-flag" class="fa-2x fa-spin fa-border"]`
`[icon name="icon-flag" class="fa-2x spin icon-border"]`

You can read more about shortcode usage on [Github](https://github.com/MickeyKay/better-font-awesome-library#shortcode)

= 2. TinyMCE =
Better Font Awesome also provides you with an easy-to-use drop down menu when editing in TinyMCE's visual mode. Check out our [Screenshots](https://wordpress.org/plugins/better-font-awesome/screenshots/ "Screenshots") to see what it looks like.

= 3. HTML =
Note that prefixes are required for HTML usage, and are version-specific. For this reason, shortcode usage is encouraged over HTML. If you do want to use HTML, however, you can read more on the [Font Awesome site](http://fortawesome.github.io/Font-Awesome/examples/).

= Advanced / Integration =
Better Font Awesome is built around the [Better Font Awesome Library](https://github.com/MickeyKay/better-font-awesome-library). This library allows you to integrate Better Font Awesome into any custom project you want to create (perhaps a theme or plugin with a constantly up-to-date icon list), and includes all the [filters](https://github.com/MickeyKay/better-font-awesome-library#filters) you might need.

= External services =
Better Font Awesome uses two Font Awesome services to provide current Free icon metadata and browser assets. No Font Awesome account or API token is required.

* **Font Awesome GraphQL API** (`https://api.fontawesome.com`) - the site server requests public Font Awesome 5 Free release and icon metadata in an asynchronous WP-Cron worker, normally about once per day. Browser, frontend, REST, editor, settings, and shortcode requests only read validated local data and never wait for this API. The plugin does not intentionally send site, user, or content data in the request. Failed requests retain the last validated metadata and use capped retry backoff.
* **Font Awesome Free CDN** (`https://use.fontawesome.com`) - visitors' browsers request the versioned CSS and font files needed to display icons. Font Awesome receives normal web request data such as the visitor's IP address and user agent.

These services are provided by Fonticons, Inc. Review the [Font Awesome terms of service](https://fontawesome.com/tos) and [privacy policy](https://fontawesome.com/privacy) for details.

= Languages / Translations =
* English
* French (thanks to [David Tisserand](http://www.pixemotion.fr))

= Credits =
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


== Installation ==

This section describes how to install the plugin and get it working.

1. Upload Better Font Awesome to the /wp-content/plugins/ directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. That's it! Now you can use 3 different methods (shortcode, HTML, TinyMCE) to insert Font Awesome icons, all outlined in the [Description](https://wordpress.org/plugins/better-font-awesome "Description") section.


== Frequently Asked Questions ==

= How is this plugin different from other Font Awesome plugins? =

This plugin automatically refreshes validated metadata for the latest release in its supported Font Awesome 5 Free channel. Better Font Awesome is also designed to work with a wide variety of established shortcode formats used by other Font Awesome plugins, so existing compatible shortcodes can continue to work. Font Awesome 6 and 7 are not yet supported.

= Do I have to install any font files? =

Nope. Better Font Awesome automatically loads the required CSS and font files from the Font Awesome Free CDN.

= What happens if I have another plugin/theme that uses Font Awesome? =

Better Font Awesome does it's best to load after any existing Font Awesome CSS, which can minimize conflicts. If you are experiencing any unexpected behavior resulting from plugin/theme conflicts, you can try checking the box to "Remove existing Font Awesome styles" in under **Settings &rarr; Better Font Awesome**.


== Screenshots ==
1. The icon shortcode dropdown selector
2. Better Font Awesome settings, accessed via Settings &rarr; Better Font Awesome


== Changelog ==

= 2.1.0 =
* Integrates the public stable Better Font Awesome Library 2.1.0 while preserving existing settings and shortcode content.
* Refreshes Font Awesome 5 Free metadata asynchronously through WP-Cron, with no metadata API wait on normal frontend, admin, editor, REST, or shortcode requests.
* Adds validated durable last-known-good metadata, bundled fallback behavior, failure backoff, and recovery.
* Hardens multisite lifecycle handling and settings persistence while retaining Classic Editor, shortcode, frontend, and hybrid `wp_editor()` compatibility.
* Requires WordPress 6.5 or newer and PHP 7.4 or newer, and is tested with WordPress through 7.1.

= 2.0.4 =
* Bump BFAL to properly esc attributes
* Add unit tests

= 2.0.3 =
* Bugfix: fix broken icon text selection
* Improve admin settings success/error message logic

= 2.0.2 =
* Bugfix: fix CSRF vulnerability

= 2.0.1 =
* Bugfix: add necessary @font-face mappings to ensure site-specific CSS and pseudo-elements render correctly

= 2.0.0 =
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

= 1.7.6 =
* Fix: revert to 1.7.4 codebase.
* Fix: remove calls to `ready()` jQuery method to support latest jQuery versions.

= 1.7.5 =
(BAD BUILD)
* Fix: remove calls to `ready()` jQuery method to support latest jQuery versions.
* Fix: update fontawesome-iconpicker dependency to repair broken icon select functionality.

= 1.7.4 =
* Fix: revert accidental bump to underlying Better Font Awesome Library dependency.

= 1.7.3 =
* Add admin notice to invite beta testers.

= 1.7.2 =
* Bump "tested up to" value to 5.5.

= 1.7.1 =
* Fix functionality to hide/show admin notices.

= 1.7.0 =
* Update fallback Font Awesome to v4.7.0.
* Switch from using git submodules to composer dependency management for core library inclusion.

== Upgrade Notice ==

= 2.1.0 =
Reliability and compatibility release for Font Awesome 5 Free. Requires WordPress 6.5 or newer and PHP 7.4 or newer. Existing settings and shortcodes are preserved.

= 2.0.4 =
* Bump BFAL to properly esc attributes
* Add unit tests

= 2.0.3 =
* Bugfix: fix broken icon text selection
* Improve admin settings success/error message logic

= 2.0.2 =
* Bugfix: fix CSRF vulnerability

= 2.0.1 =
* Bugfix: add necessary @font-face mappings to ensure site-specific CSS and pseudo-elements render correctly

= 2.0.0 =
* Adds Font Awesome 5, GraphQL metadata, CDN CSS, an optional v4 shim, daily update checks, refreshed fallback data, dismissible notices, and compatibility tests.

= 1.7.6 =
* Fix: revert to 1.7.4 codebase.
* Fix: remove calls to `ready()` jQuery method to support latest jQuery versions.

= 1.7.5 =
(BAD BUILD)
* Fix: remove calls to `ready()` jQuery method to support latest jQuery versions.
* Fix: update fontawesome-iconpicker dependency to repair broken icon select functionality.

= 1.7.4 =
* Fix: revert accidental bump to underlying Better Font Awesome Library dependency.

= 1.7.3 =
* Add admin notice to invite beta testers.

= 1.7.2 =
* Bump "tested up to" value to 5.5.

= 1.7.1 =
* Fix functionality to hide/show admin notices.

= 1.7.0 =
* Update fallback Font Awesome to v4.7.0.
* Switch from using git submodules to composer dependency management for core library inclusion.
