# Testing Better Font Awesome #

**Download:** [Better Font Awesome v0.10.0.beta](https://github.com/MickeyKay/better-font-awesome/blob/master/testing/better-font-awesome-0.10.0.beta.zip?raw=true)

## Intro ##
First of all, if you've made it here, thanks for your help! It makes a HUGE difference to have real-life users like yourself put software through the ringer. So thanks!

## How to Report ##
If you *do* enounter issues or questions, please [file an issue](https://github.com/MickeyKay/better-font-awesome/issues) on this repo.

If you *don't* encounter any issues, I'd still love to know that things went smoothly. Please [file an issue](https://github.com/MickeyKay/better-font-awesome/issues) and indicate what tests you've run and if there is anything else worth reporting.

When reporting (whether success or failures) it can be *extremely* helpful if you provide as much information as possible. This information includes, but isn't limited to:

* What the error is
* Steps to reproduce the error
* Consistency of error (every time vs intermittently)
* WordPress version
* Server setup
* PHP version
* Active theme
* Active plugins
* Anything else you think would be helpful

## What to Watch For ##
Below are various [steps](https://github.com/MickeyKay/better-font-awesome/blob/master/testing/testing.md#testing-steps) that will help you test this plugin. In addition to the suggested steps and tests, please feel free to make note of anything that seems out of place, incorrect, or otherwise wonky. The goal is to get this plugin as perfect as possible, so nothing is off limits. More points if you can break it!

**Note:** While Better Font Awesome has already undergone extensive testing to make it to this point, there is no guarantee that it doesn't contain errors. With your help, we can make that guarantee as close to 100% as possible, but in the meantime please use this testing version accordingly.

## Testing Steps ##
To get started with testing, first download the most recent version of Better Font Awesome and install it on your local/dev install of WordPress:
* [Better Font Awesome v0.10.0.beta](https://github.com/MickeyKay/better-font-awesome/blob/master/testing/better-font-awesome-0.10.0.beta.zip?raw=true)

### Basic Testing ###
Now you're ready to test. In fact, you've already started the testing process! To complete basic testing, please do the following:

* Install and activate the plugin (anything to report in this process?).
* Add a new post/page (does the icon dropdown appear as expected in Visual view?).
* Insert an icon using the dropdown, and check that it appears on the front end.
* Try various settings under `Settings > Better Font Awesome` in the admin and check that everything is still working as expected.
* Do anything and everything you can to try and break things! This is the fun part.

### Bonus Testing ###
Bonus points to anyone who tests the following:

* Test on WordPress 3.8 or below (this uses a different version of TinyMCE).
* Test on the latest dev version of WordPress. Available via [Github](https://github.com/WordPress/WordPress) or the [WordPress Beta Tester plugin](https://wordpress.org/plugins/wordpress-beta-tester/).
* Test on a multisite install.
* Test all the filters listed here: https://github.com/MickeyKay/better-font-awesome-library#filters
* Test on various servers. Try various PHP configurations. Force timeouts. Try running with no internet connection (it should still fall back to the locally included version of Font Awesome 4.1.0).

### Double Bonus Testing ###
If you're feeling super dedicated and want to learn about a cool new library I've been working on, then test the [Better Font Awesome Library](https://github.com/MickeyKay/better-font-awesome-library/), the core stand-alone library upon which the plugin is based. A few things to note about this library:

* It contains a [Git Submodule](http://git-scm.com/book/en/Git-Tools-Submodules) that will require you to run a few special commands to get all necessary files after cloning the library. So the whole process of cloning and updating the library looks like this (performed from the command line):
```
// Clone the repo
git clone https://github.com/MickeyKay/better-font-awesome-library.git

// Enter the newly downloaded repo
cd better-font-awesome-library

// Initialize all submodules
git submodule init

// Pull in updated copies of all submodules
git submodule update
```
* This library is designed to work as a standalone way for you to incorporate the latest version of Font Awesome in your plugin, theme, or other WordPress project. Feel free to test it out in any way you like - I'd love to see what you build.
* The README.md file explains the structure and functionality of the library. Please let me know if anything is unclear or confusing.


