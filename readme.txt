=== Better Font Awesome ===
Contributors: McGuive7, MIGHTYminnow
Tags: better, font, awesome, icon, bootstrap, fontstrap, cdn, shortcode
Donate link: http://mightyminnow.com
Requires at least: 3.0
Tested up to: 3.9
Stable tag: 0.9.4
License: GPLv2+
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The Better Font Awesome plugin for WordPress. Shortcodes, HTML, TinyMCE, various Font Awesome versions, backwards compatibility, CDN speeds, and more.

== Description ==

Better Font Awesome gives you easy access to the full [Font Awesome](http://fortawesome.github.io/Font-Awesome/ "Font Awesome icon website") icon library using shortcodes, HTML, and TinyMCE, and allows you to update your version of Font Awesome without having to change your existing shortcodes.


= Features =

* **Choose your version** - automatically checks the [Font Awesome CDN](http://www.bootstrapcdn.com/#fontawesome_tab "Font Awesome CDN") for all available versions, and lets you choose which one you want to use from a simple drop down menu. You also have the option to choose "Latest," which means the plugin will automatically switch to the latest version of Font Awesome as soon as it's released.

* **Backwards compatible** - shortcode output is automatically updated depending on which version you choose, meaning that you can switch versions without having to modify your shortcodes.

* **Compatible with other plugins** - designed to work with shortcodes generated with plugins like [Font Awesome Icons](http://wordpress.org/plugins/font-awesome/ "Font Awesome Icons"), [Font Awesome More Icons](https://wordpress.org/plugins/font-awesome-more-icons/ "Font Awesome More Icons"), and [Font Awesome Shortcodes](https://wordpress.org/plugins/font-awesome-shortcodes/), so you can switch to Better Font Awesome and your existing shortcodes will still work.

* **CDN speeds** - utilizes CSS &amp; font files hosted by the excellent [Bootstrap CDN](http://www.bootstrapcdn.com/ "Bootstrap CDN"), which means super-fast load times.

= Settings =
All settings can be adjusted via **Settings &rarr; Better Font Awesome**.

= Usage =
Better Font Awesome can be used in 3 different ways: shortcode, HTML, and TinyMCE

= 1. Shortcode =
`[icon name="flag" class="2x spin border" space="true"]`

The **`name`** attribute is simply the name of the icon (see note below on prefixes, which are totally optional).

The **`class`** attribute can include any of the available Font Awesome classes listed on the Font Awesome [Examples Page](http://fortawesome.github.io/Font-Awesome/examples/ "Font Awesome Examples").

The **`space`** attribute (optional) can be used to include a `&nbsp;` within the generated `<i>` element.

**Prefixes** (`icon-` and `fa-`) are not necessary for shortcode usage! What's great is that Better Font Awesome will automatically remove and replace prefixes depending on the Font Awesome version you choose. So if you have existing Font Awesome shortcodes *with prefixes* (from other plugins, for example), they'll still work just fine. 

That means that the following shortcodes will all work, regardless of what version of Font Awesome you choose:
`[icon name="flag" class="2x spin border"]`
`[icon name="icon-flag" class="icon-2x icon-spin icon-border"]`
`[icon name="fa-flag" class="fa-2x fa-spin fa-border"]`
`[icon name="icon-flag" class="fa-2x spin icon-border"]`

*Note: icon names and classes will only work for Font Awesome versions in which they are included.*

= 2. HTML =
Note that prefixes are required for HTML usage, and are version-specific. For this reason, shortcode usage is encouraged over HTML.

Version 4:
`<i class="fa-flag fa-2x fa-spin fa-border"></i>`

Version 3:
`<i class="icon-flag icon-2x icon-spin icon-border"></i>`

= 3. TinyMCE =
Better Font Awesome also provides you with an easy-to-use drop down menu in the default WordPress TinyMCE, which you can use to automatically generate shortcodes for the icons you want. This drop-down list will automatically update with all available icons for whichever version you choose. Check out our [Screenshots](https://wordpress.org/plugins/better-font-awesome/screenshots/ "Screenshots") to see what it looks like.

= Advanced / Integration =
Please feel free to integrate Better Font Awesome in your plugin or theme! If you want to hook into Better Font Awesome, the best way is via the global `$better_font_awesome` object, which has a few public properties that might be useful:

`$better_font_awesome->prefix`
The prefix (e.g. "icon-" or "fa-") that should be used with the selected version of Font Awesome.

`$better_font_awesome->icons`
An alphabetical array of all available icons based on the selected version of Font Awesome.


= Credits =
Many thanks to the following plugins and their authors:

* [Font Awesome Icons](http://wordpress.org/plugins/font-awesome/ "Font Awesome Icons") by [Rachel Baker](http://rachelbaker.me/ "Rachel Baker")
* [Font Awesome More Icons](https://wordpress.org/plugins/font-awesome-more-icons/ "Font Awesome More Icons") by [Web Guys](http://webguysaz.com/ "Web Guys")
* [Font Awesome Shortcodes](https://wordpress.org/plugins/font-awesome-shortcodes/) by [FoolsRun](https://profiles.wordpress.org/foolsrun/ "FoolsRun")


== Installation ==

This section describes how to install the plugin and get it working.

1. Upload Font Awesome Icons to the /wp-content/plugins/ directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. That's it! Now you can use 3 different methods (shortcode, HTML, TinyMCE) to insert Font Awesome icons, all outlined in the [Description](https://wordpress.org/plugins/better-font-awesome "Description") section.


== Frequently Asked Questions ==

= How is this plugin different from other Font Awesome plugins? =

This plugin is unique in that it automatically pulls in *all* available versions of Font Awesome, meaning you never have to wait for the plugin developer to add the latest version. Furthermore, Better Font Awesome is designed to work with a wide variety of shortcode formats used by other Font Awesome plugins - this means that you can easily switch to Better Font Awesome (if, for example, you need to include icons from the most recent version of Font Awesome, which isn't always available with other plugins), and they will still work.

= Do I have to install any font files? =

Nope. Better Font Awesome automatically pulls in everything you need, and it does it from the lightning-fast Bootstrap CDN.

= What happens if I have another plugin/theme that uses Font Awesome? =

Better Font Awesome does it's best to load after any existing Font Awesome CSS, which can minimize conflicts. If you are experiencing any unexpected behavior resulting from plugin/theme conflicts, you can try checking the box to "Remove existing Font Awesome styles" in under **Settings &rarr; Better Font Awesome**.


== Screenshots ==

1. Better Font Awesome settings, accessed via Settings &rarr; Better Font Awesome
2. Using Better Font Awesome via TinyMCE


== Changelog ==

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