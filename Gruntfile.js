/*jslint node: true */
"use strict";

module.exports = function( grunt ) {

	// Grab package as variable for later use/
	var pkg = grunt.file.readJSON( 'package.json' );

	// Load all tasks.
	require('load-grunt-tasks')(grunt, {scope: 'devDependencies'});

	// Project configuration
	grunt.initConfig( {
		pkg: pkg,
		devUpdate: {
	        main: {
	            options: {
	                updateType: 'prompt',
	                packages: {
	                    devDependencies: true
	                },
	            }
	        }
	    },
	    prompt: {
			version: {
				options: {
					questions: [
						{
							config:  'newVersion',
							type:    'input',
							message: 'What specific version would you like',
							default: '<%= pkg.version %>'
						},
						{
							config:  'updateStable',
							type:    'confirm',
							message: 'Bump stable version?',
							default: false
						}
					]
				}
			}
		},
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
	    			}
    			]
			}
		},
	    makepot: {
	        target: {
	            options: {
	                domainPath: '/languages/',    // Where to save the POT file.
	                potFilename: 'better-font-awesome.pot',   // Name of the POT file.
	                type: 'wp-plugin'  // Type of project (wp-plugin or wp-theme).
	            }
	        }
	    },
	    wp_readme_to_markdown: {
	    	readme: {
	    		files: {
	    			'readme.md': 'readme.txt'
	    		},
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
		'prompt',
		'replace',
		'makepot',
		'wp_readme_to_markdown',
		'copy'
	] );

	grunt.registerTask( 'default', 'build' );

	grunt.util.linefeed = '\n';
};