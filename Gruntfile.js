/*jslint node: true */
"use strict";

module.exports = function( grunt ) {

  // Grab package as variable for later use/
  var pkg = grunt.file.readJSON( 'package.json' );
  var releaseVersion = grunt.option( 'release-version' ) || pkg.version;
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
                'inc/fallback-release-data.json',
                'js/admin.js',
                'js/admin.min.js',
                'lib/fontawesome-iconpicker/dist/css/fontawesome-iconpicker.css',
                'lib/fontawesome-iconpicker/dist/css/fontawesome-iconpicker.min.css',
                'lib/fontawesome-iconpicker/dist/js/fontawesome-iconpicker.js',
                'lib/fontawesome-iconpicker/dist/js/fontawesome-iconpicker.min.js'
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

  grunt.registerTask( 'prepare-svn-release', function() {
    var releaseDirectories = [
      'svn/trunk',
      'svn/tags/' + grunt.config( 'newVersion' )
    ];

    releaseDirectories.forEach( function( directory ) {
      if ( grunt.file.exists( directory ) ) {
        grunt.file.delete( directory, { force: true } );
      }

      grunt.file.mkdir( directory );
    } );
  } );

  grunt.registerTask( 'release', [
    'replace',
    'wp_readme_to_markdown',
    'prepare-svn-release',
    'copy'
    ] );

  grunt.registerTask( 'default', 'build' );

  grunt.util.linefeed = '\n';
};
