=== Better Font Awesome ===
Contributors: McGuive7, MIGHTYminnow
Tags: better, font, awesome, icon, icons, bootstrap, fontstrap, cdn, shortcode
Donate link: http://mightyminnow.com
Requires at least: 3.0
Tested up to: 4.4
Stable tag: 1.5.0
License: GPLv2+
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The Better Font Awesome plugin for WordPress. Shortcodes, HTML, TinyMCE, various Font Awesome versions, backwards compatibility, CDN speeds, and more.

== Description ==

**Do you find this plugin helpful? Please consider [leaving a 5-star review](https://wordpress.org/support/view/plugin-reviews/better-font-awesome).**

Better Font Awesome allows you to automatically integrate the latest available version of [Font Awesome](http://fontawesome.io/) into your WordPress project, along with accompanying CSS, shortcodes, and TinyMCE icon shortcode generator.


= Features =

* **Always up-to-date** - automatically fetches the most recent available version of Font Awesome, meaning you no longer need to manually update the version included in your theme/plugin.

* **Backwards compatible** - shortcode output is automatically updated depending on which version of Font Awesome you choose, meaning that you can switch versions without having to modify your shortcodes.

* **Compatible with other plugins** - designed to work with shortcodes generated with plugins like [Font Awesome Icons](http://wordpress.org/plugins/font-awesome/ "Font Awesome Icons"), [Font Awesome More Icons](https://wordpress.org/plugins/font-awesome-more-icons/ "Font Awesome More Icons"), and [Font Awesome Shortcodes](https://wordpress.org/plugins/font-awesome-shortcodes/), so you can switch to Better Font Awesome and your existing shortcodes will still work.

* **CDN speeds** - Font Awesome CSS is pulled from the super-fast and reliable [jsDelivr CDN](http://www.jsdelivr.com/#!fontawesome).

* **Shortcode generator** - includes an easy-to-use TinyMCE dropdown shortcode generator.

= Settings =
All settings can be adjusted via **Settings &rarr; Better Font Awesome**.

= Usage =
Better Font Awesome can be used in 3 different ways: shortcode, HTML, and TinyMCE

= 1. Shortcode =
`[icon name="flag" class="2x spin border" unprefixed_class="my-custom-class"]`
Note that prefixes (`fa-` and `icon-`) are not required, but if you do include them things will still work just fine! Better Font Awesome is smart enough to know what version of Font Awesome you're using and correct of the appropriate prefix.

That means that all of the following shortcodes will work, regardless of what version of Font Awesome you choose:
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

This plugin is unique in that it automatically pulls in *all* available versions of Font Awesome, meaning you never have to wait for the plugin developer to add the latest version. Furthermore, Better Font Awesome is designed to work with a wide variety of shortcode formats used by other Font Awesome plugins - this means that you can easily switch to Better Font Awesome (if, for example, you need to include icons from the most recent version of Font Awesome, which isn't always available with other plugins), and they will still work.

= Do I have to install any font files? =

Nope. Better Font Awesome automatically pulls in everything you need, and it does it from the lightning-fast jsDelivr CDN.

= What happens if I have another plugin/theme that uses Font Awesome? =

Better Font Awesome does it's best to load after any existing Font Awesome CSS, which can minimize conflicts. If you are experiencing any unexpected behavior resulting from plugin/theme conflicts, you can try checking the box to "Remove existing Font Awesome styles" in under **Settings &rarr; Better Font Awesome**.


== Screenshots ==
1. The icon shortcode dropdown selector
2. Better Font Awesome settings, accessed via Settings &rarr; Better Font Awesome


== Changelog ==

= 1.5.0 =
* Update fallback Font Awesome to v4.5.0.
* Add new `bfa_icon_tag` to allow for filtering default `<i>` tag.

= 1.4.3 =
* Fix: refactor JS to allow icon shortcode insertion button to work in all instances (ACF Flexible and Repeater fields, divi, Black Studio TinyMCE ).

= 1.4.2 =
* Fix: icon picker not working for ACF Pro repeater field.

= 1.4.1 =
* Fix: icon picker not working for ACF repeater field.

= 1.4.0 =
* Fix: icon picker not working for Black Studio TinyMCE Widget. (props @EJOweb and @marcochiesi)
* Update fallback Font Awesome to version 4.4.0.

= 1.3.4 =
* Update Better Font Awesome Library to version 1.3.4.
* Fix double shortcode insert issue.
* Fix behavior in which clicking shortcode insert button scrolls to top of page.

= 1.3.3 =
* Update plugin and BFAL to all fire on `init` hook instead of mix of `plugins_loaded` and `after_theme_setup`. This should fix issues in which icons don't show up when BFAL is used in other plugins.
* Update iconpicker JS to avoid conflict that arose from preventing subsequent `mouseup` event listeners from firing.
* Change appearance of iconpicker button to match default buttons.

= 1.3.2 =
* Update Better Font Awesome Library with better prefix removal method for filtered icons.

= 1.3.1 =
* Update admin JS to trigger icon picker on ALL TinyMCE initializations (e.g. Visual Composer and AJAX)

= 1.3.0 =
* Replace outdated TinyMCE shortcode selector brand new jQuery dropdown selector that works in both the visual and text editor
* Clean up CSS and JS

= 1.2.1 =
* Update get_instance() call to work for older versions of PHP (< 5.3)

= 1.2.0 =
* Attach load functionality to after_theme_setup hook to allow themes to filter options
* Update fallback Font Awesome to version 4.3.0

= 1.1.0 =
* Implement Ajax to save plugin settings (thanks [Braad](https://profiles.wordpress.org/braad))

= 1.0.10 =
* Fix SSL bug breaking wp_remote_get() from https.

= 1.0.9 =
* Fix debuggin hook set to init instead of plugins_loaded.

= 1.0.8 =
* Add admin setting to hide admin notices for API and CDN connectivity warnings.
* Update translations.

= 1.0.7 =
* Update included fallback to Font Awesome version 4.3.

= 1.0.6 =
* Unhook library load() function from plugins_loaded and run directly from constructor (fixes bug preventing developers from overriding initialization easily).

= 1.0.5 =
* Add fa_force_fallback and bfa_show_errors filters.
* Add hex icon values as $icon array indexes.

= 1.0.4 =
* Add missing isset() check that was causing intermittent warning.

= 1.0.3 =
* Add French translation.
* Correct text domain slug.

= 1.0.2 =
* Add updated .pot file.
* Further improve error handling and fallback.

= 1.0.1 =
* Fix error handling for 404 API requests.

= 1.0.0 =
* Fully refactor the back-end.
* Switch to just using the jsDelivr CDN.
* Implement transients to minimize load time.
* Implement improved fallback handling (transient &rarr; wp_remote_get() &rarr; locally included files)
* Switch out bulky Titan Framework for native Settings API.

= 0.9.6 =
* Fixed missing icon previews in WordPress 3.8 and below.

= 0.9.5 =
* Added ability to choose which CDN to use.
* Added `unprefixed_class` shortcode attribute to allow for unprefixed shortcodes.
* Updated prefixes to now return just the prefix without the dash (-).

= 0.9.4 =
* Switched default &nbsp; being output. Now the default "space" attribute is false, and can be set to true to optionally include a space.
* PLEASE NOTE: this will affect existing shortcodes.

= 0.9.3 =
* Fixed admin-styles.css bug that was applying FontAwesome font-face outside TinyMCE
* Print JS variables in front-end to aid developers
* Create global $better_font_awesome object for developers to access

= 0.9.2 =
* Fixes issue of missing icon drop-down select menu in TinyMCE (adds compatibility for TinyMCE v4)

= 0.9.1 =
* Added fixes for older versions of PHP (Titan Framework not found, unexpected "[")

= 0.9.0 =
* First release!


== Upgrade Notice ==

= 1.5.0 =
* Update fallback Font Awesome to v4.5.0.
* Add new `bfa_icon_tag` to allow for filtering default `<i>` tag.

= 1.4.3 =
* Fix: refactor JS to allow icon shortcode insertion button to work in all instances (ACF Flexible and Repeater fields, divi, Black Studio TinyMCE ).

= 1.4.2 =
* Fix: icon picker not working for ACF Pro repeater field.

= 1.4.1 =
* Fix: icon picker not working for ACF repeater field.

= 1.4.0 =
* Fix: icon picker not working for Black Studio TinyMCE Widget. (props @EJOweb and @marcochiesi)
* Update fallback Font Awesome to version 4.4.0.

= 1.3.4 =
* Update Better Font Awesome Library to version 1.3.4.
* Fix double shortcode insert issue.
* Fix behavior in which clicking shortcode insert button scrolls to top of page.

= 1.3.3 =
* Update plugin and BFAL to all fire on `init` hook instead of mix of `plugins_loaded` and `after_theme_setup`. This should fix issues in which icons don't show up when BFAL is used in other plugins.
* Update iconpicker JS to avoid conflict that arose from preventing subsequent `mouseup` event listeners from firing.
* Change appearance of iconpicker button to match default buttons.

= 1.3.2 =
* Update Better Font Awesome Library with better prefix removal method.

= 1.3.1 =
* Update admin JS to trigger icon picker on ALL TinyMCE initializations (e.g. Visual Composer and AJAX)

= 1.3.0 =
* Replace outdated TinyMCE shortcode selector brand new jQuery dropdown selector that works in both the visual and text editor
* Clean up CSS and JS

= 1.2.1 =
* Update get_instance() call to work for older versions of PHP (< 5.3)

= 1.2.0 =
* Attach load functionality to after_theme_setup hook to allow themes to filter options
* Update fallback Font Awesome to version 4.3.0

= 1.1.0 =
* Implement Ajax to save plugin settings (thanks [Braad](https://profiles.wordpress.org/braad))

= 1.0.10 =
* Fix SSL bug breaking wp_remote_get() from https

= 1.0.9 =
* Fix debuggin hook set to init instead of plugins_loaded.

= 1.0.8 =
* Add admin setting to hide admin notices for API and CDN connectivity warnings.
* Update translations.

= 1.0.7 =
* Update included fallback to Font Awesome version 4.3.

= 1.0.6 =
* Unhook library load() function from plugins_loaded and run directly from constructor (fixes bug preventing developers from overriding initialization easily).

= 1.0.5 =
* Add fa_force_fallback and bfa_show_errors filters.
* Add hex icon values as $icon array indexes.

= 1.0.4 =
* Add missing isset() check that was causing intermittent warning.

= 1.0.3 =
* Add French translation.
* Correct text domain slug.

= 1.0.2 =
* Add updated .pot file.
* Further improve error handling and fallback.

= 1.0.1 =
* Fix error handling for 404 API requests.

= 1.0.0 =
* Fully refactor the back-end.
* Switch to just using the jsDelivr CDN.
* Implement transients to minimize load time.
* Implement improved fallback handling (transient &rarr; wp_remote_get() &rarr; locally included files)
* Switch out bulky Titan Framework for native Settings API.

= 0.9.6 =
* Fixed missing icon previews in WordPress 3.8 and below.

= 0.9.5 =
* Added ability to choose which CDN to use.
* Added `unprefixed_class` shortcode attribute to allow for unprefixed shortcodes.
* Updated prefixes to now return just the prefix without the dash (-).

= 0.9.4 =
* Switched default &nbsp; being output. Now the default "space" attribute is false, and can be set to true to optionally include a space.
* PLEASE NOTE: this will affect existing shortcodes.

= 0.9.3 =
* Fixed admin-styles.css bug that was applying FontAwesome font-face outside TinyMCE
* Print JS variables in front-end to aid developers
* Create global $better_font_awesome object for developers to access

= 0.9.2 =
* Fixes issue of missing icon drop-down select menu in TinyMCE (adds compatibility for TinyMCE v4)

= 0.9.1 =
* Added fixes for older versions of PHP (Titan Framework not found, unexpected "[")

= 0.9.0 =
* First release!
