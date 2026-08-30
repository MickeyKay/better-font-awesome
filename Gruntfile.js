/*jslint node: true */
"use strict";

module.exports = function( grunt ) {

  // Grab package as variable for later use/
  var pkg = grunt.file.readJSON( 'package.json' );
  var requestedReleaseVersion = grunt.option( 'release-version' );
  var releaseVersion = requestedReleaseVersion || pkg.version;
  var updateStable = Boolean( grunt.option( 'update-stable' ) );
  var distIgnorePatterns = grunt.file.read( '.distignore' )
    .split( /\r?\n/ )
    .filter( function( entry ) {
      return entry && '#' !== entry.charAt( 0 );
    } )
    .reduce( function( patterns, entry ) {
      return patterns.concat( [ '!' + entry, '!' + entry.replace( /\/$/, '' ) + '/**' ] );
    }, [] );
  var svnTrunkSources = [ '**' ].concat( distIgnorePatterns, [ '!vendor/**' ] );

  // Load all tasks.
  require('load-grunt-tasks')(grunt, {scope: 'devDependencies'});

  // Project configuration
  grunt.initConfig( {
    newVersion: releaseVersion,
    updateStable: updateStable,
    pkg: pkg,
    replace: {
        package: {
            src: ['package.json'],
            overwrite: true,
            replacements: [
            {
                "version": "1.0.0",
                from: /("version":\s*).*,\n/g,
                to: '$1"<%= newVersion %>",\n'
            }
            ]
        },
        readme: {
            src: ['readme.txt'],
            overwrite: true,
            replacements: [
            {
                from: /(Stable tag:\s*)(.*)(\n)/g,
                to: function(matchedText, index, fullText, regexMatches) {
                    return grunt.config('updateStable') ? regexMatches[0] + grunt.config('newVersion') + regexMatches[2]: matchedText;
                }
            }
            ]
        },
        php: {
            src: ['better-font-awesome.php'],
            overwrite: true,
            replacements: [
            {
                from: /(\*\s*Version:\s*).*\n/g,
                to: '$1<%= newVersion %>\n'
            },
            {
                from: /(const VERSION = ').*(';)/g,
                to: '$1<%= newVersion %>$2'
            }
            ]
        }
    },
    wp_readme_to_markdown: {
            readme: {
                files: {
                    'README.md': 'readme.txt'
                },
                options: {
                    post_convert: function(text) {
                        var prefix = [
                        '[![CI](https://github.com/MickeyKay/better-font-awesome/actions/workflows/ci.yml/badge.svg)](https://github.com/MickeyKay/better-font-awesome/actions/workflows/ci.yml)',
                        '[![Downloads](https://img.shields.io/wordpress/plugin/dt/better-font-awesome.svg)](https://wordpress.org/plugins/better-font-awesome/)',
                        '[![License: GPL v3](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)'
                        ].join(' ');
						text = text.replace( /  \n/g, '<br>\n' );

                        return [prefix,text].join('\n\n');
                    }
                }
            },
        },
        copy: {
            composerDeps: {
                cwd: 'vendor/mickey-kay/better-font-awesome-library/',
                src: [
                'better-font-awesome-library.php',
                'composer.json',
                'css/admin-styles.css',
                'css/admin-styles.min.css',
                'inc/class-bfa-release-data-validator.php',
                'inc/fallback-release-data.json',
                'inc/fallback-release-data.sha256',
                'js/admin.js',
                'js/admin.min.js',
                'lib/fontawesome-iconpicker/LICENSE',
                'lib/fontawesome-iconpicker/dist/css/fontawesome-iconpicker.css',
                'lib/fontawesome-iconpicker/dist/css/fontawesome-iconpicker.min.css',
                'lib/fontawesome-iconpicker/dist/js/fontawesome-iconpicker.js',
                'lib/fontawesome-iconpicker/dist/js/fontawesome-iconpicker.min.js',
                'LICENSE',
                'THIRD-PARTY-NOTICES.md'
                ],
                dest: 'svn/trunk/vendor/mickey-kay/better-font-awesome-library/',
                expand: true,
            },
            svnAssets: {
                cwd: 'assets/',
                src: ['**'],
                dest: 'svn/assets/',
                expand: true,
            },
            svnTrunk: {
                src: svnTrunkSources,
                dest: 'svn/trunk/',
            },
            svnTags: {
                cwd:  'svn/trunk/',
                src: ['**'],
                dest: 'svn/tags/<%= newVersion %>/',
                expand: true,
            }
        }
    } );

  grunt.registerTask( 'build', [
    'wp_readme_to_markdown'
    ] );

  grunt.registerTask( 'validate-release-version', function() {
    var versionPattern = /^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$/;
    var tagDirectory = 'svn/tags/' + requestedReleaseVersion;

    if ( 'string' !== typeof requestedReleaseVersion || ! versionPattern.test( requestedReleaseVersion ) ) {
      grunt.fail.fatal( 'Pass an explicit semantic version with --release-version, for example --release-version=2.0.5.' );
    }

    if ( grunt.file.exists( tagDirectory ) ) {
      grunt.fail.fatal( 'Refusing to replace existing WordPress.org tag: ' + tagDirectory );
    }
  } );

  grunt.registerTask( 'prepare-svn-trunk', function() {
    if ( grunt.file.exists( 'svn/trunk' ) ) {
      grunt.file.delete( 'svn/trunk', { force: true } );
    }

    grunt.file.mkdir( 'svn/trunk' );
  } );

  grunt.registerTask( 'prepare-svn-release', function() {
    var tagDirectory = 'svn/tags/' + grunt.config( 'newVersion' );

    if ( grunt.file.exists( tagDirectory ) ) {
      grunt.fail.fatal( 'Refusing to replace existing WordPress.org tag: ' + tagDirectory );
    }

    grunt.task.run( 'prepare-svn-trunk' );
    grunt.file.mkdir( tagDirectory );
  } );

  grunt.registerTask( 'build-release-tree', [
    'wp_readme_to_markdown',
    'prepare-svn-trunk',
    'copy:composerDeps',
    'copy:svnTrunk'
    ] );

  grunt.registerTask( 'release', [
    'validate-release-version',
    'replace',
    'wp_readme_to_markdown',
    'prepare-svn-release',
    'copy'
    ] );

  grunt.registerTask( 'default', 'build' );

  grunt.util.linefeed = '\n';
};
