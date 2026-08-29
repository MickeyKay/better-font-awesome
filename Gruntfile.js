/*jslint node: true */
"use strict";

module.exports = function( grunt ) {

  // Grab package as variable for later use/
  var pkg = grunt.file.readJSON( 'package.json' );
  var releaseVersion = grunt.option( 'version' ) || pkg.version;
  var updateStable = Boolean( grunt.option( 'update-stable' ) );

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
                    'readme.md': 'readme.txt'
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
                src: [
                'vendor/mickey-kay/**'
                ],
                dest: 'svn/trunk/'
            },
            svnAssets: {
                cwd: 'assets/',
                src: ['**'],
                dest: 'svn/assets/',
                expand: true,
            },
            svnTrunk: {
                src:  [
                '**',
                '!node_modules/**',
                '!vendor/**',
                '!svn/**',
                '!.git/**',
                '!.gitignore',
                '!.gitmodules',
                '!.sass-cache/**',
                '!bin/**',
                '!tests/**',
                '!css/src/**',
                '!js/src/**',
                '!img/src/**',
                '!assets/**',
                '!design/**',
                '!Gruntfile.js',
                '!package.json',
                '!composer*',
                ],
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

  grunt.registerTask( 'release', [
    'replace',
    'wp_readme_to_markdown',
    'copy'
    ] );

  grunt.registerTask( 'default', 'build' );

  grunt.util.linefeed = '\n';
};
