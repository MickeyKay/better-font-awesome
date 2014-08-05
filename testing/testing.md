# Testing Better Font Awesome #

## Intro ##
First of all, if you've made it here, thanks for your help! It makes a HUGE difference to have real-life users like yourself put software through the ringer. So thanks!

## How to Report ##
First things first. If you *do* enounter issues or questions, please [file an issue](https://github.com/MickeyKay/better-font-awesome/blob/master/testing/better-font-awesome-beta.zip?raw=true) on this repo.

If you *don't* encounter any issues, I'd still like to know. Please file [file an issue](https://github.com/MickeyKay/better-font-awesome/blob/master/testing/better-font-awesome-beta.zip?raw=true) and indicate what you've done in the way of testing.

## What to Watch For ##
Below are various [testing steps] you can take to put this plugin through the ringer. In addition to the specific steps and tests suggested, please feel free to make note of anything that seems out of place, incorrect, or otherwise wonky. The goal is to get this plugin as perfect as possible, so nothing is off limits.

## Testing Steps ##
To get started with testing, download the following beta version of Better Font Awesome and install it on your local/dev install of WordPress:
* [Better Font Awesome v0.10.0.beta](https://github.com/MickeyKay/better-font-awesome/blob/master/testing/better-font-awesome-0.10.0.beta.zip?raw=true)

Now you're ready to test. In fact, you've already started the testing process! Basic testing consists of the following:

* Install and activate the plugin.
* Add a new post/page (does the icon dropdown appear as expected in visual view?).
* Insert an icon using the dropdown and check that it appears on the front end.
* Try various settings under Settings > Better Font Awesome in the admin and check that everything is still working as expected.
* Do anything and everything you can to try and break BFA!

### Bonus Testing Steps ###
Bonus points to anyone who tests the following:

* Test on WordPress 3.8 or below (this uses a different version of TinyMCE).
* Test on the latest dev version of WordPress. Available via [Github](https://github.com/WordPress/WordPress) or the [WordPress Beta Tester plugin](https://wordpress.org/plugins/wordpress-beta-tester/).
* Test on a multisite install.
* Test all the filters listed here: https://github.com/MickeyKay/better-font-awesome-library#filters
* Test on various servers. Try various PHP configurations. Force timeouts. Try running with no internet connection (it should still fall back to the locally included version of Font Awesome 4.1.0).

### Double Bonus Testing Steps ###
Test the [Better Font Awesome Library](https://github.com/MickeyKay/better-font-awesome-library/), the core stand-alone library upon which the plugin is based. A few things to note about this library:

* It contains a [Git Submodule](http://git-scm.com/book/en/Git-Tools-Submodules) that will require you to run the following two commands to get all necessary files after cloning the library:
```
git submodule init
git submodule update
```
* This library is designed to work as a standalone way for you to incorporate the latest version of Font Awesome in your plugin, theme, or other WordPress project. Feel free to test it out in any way you like - I'd love to see what you build.
* The README.md file explains the structure and functionality of the library. Please let me know if anything is unclear or confusing.


