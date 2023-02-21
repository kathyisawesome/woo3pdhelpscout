module.exports = function(grunt) {

	require( 'load-grunt-tasks' )( grunt );

	// Project configuration.
	grunt.initConfig(
        {
		pkg: grunt.file.readJSON( 'package.json' ),

		// # Build and release

		// Remove any files in zip destination and build folder
		clean: {
			main: ['build/**']
		},

		// Copy the plugin into the build directory
		copy: {
			main: {
				src: [
				'**',
				'!node_modules/**',
				'!build/**',
				'!deploy/**',
				'!svn/**',
				'!**/*.zip',
				'!**/*.bak',
				'!wp-assets/**',
				'!package-lock.json',
				'!screenshots/**',
				'!.git/**',
				'!**.md',
				'!Gruntfile.js',
				'!package.json',
				'!gitcreds.json',
				'!.gitcreds',
				'!.gitignore',
				'!.gitmodules',
				'!sftp-config.json',
				'!**.sublime-workspace',
				'!**.sublime-project',
				'!deploy.sh',
				'!**/*~',
				'!phpcs.xml',
				'!none',
				'!includes/compatibility/backcompatibility/**'
				],
				dest: 'build/'
			}
		},

		// Make a zipfile.
		compress: {
			main: {
				options: {
					mode: 'zip',
					archive: 'deploy/<%= pkg.name %>-<%= pkg.version %>.zip'
				},
				expand: true,
				cwd: 'build/',
				src: ['**/*'],
				dest: '/<%= pkg.name %>'
			}
		},

		// # Internationalization

		// Add text domain
		addtextdomain: {
			options: {
				textdomain: '<%= pkg.name %>'    // Project text domain.
			},
			target: {
				files: {
					src: ['*.php', '**/*.php', '**/**/*.php', '!node_modules/**', '!deploy/**']
				}
			}
		},

		// Generate .pot file
		makepot: {
			target: {
				options: {
					domainPath: '/languages', // Where to save the POT file.
					exclude: ['deploy','build','node_modules'], // List of files or directories to ignore.
					mainFile: '<%= pkg.name %>.php', // Main project file.
					potFilename: '<%= pkg.name %>.pot', // Name of the POT file.
					type: 'wp-plugin', // Type of project (wp-plugin or wp-theme).
					potHeaders: {
						'Report-Msgid-Bugs-To': '<%= pkg.bugs.issues %>'
					}
				}
			}
		},

		// bump version numbers (replace with version in package.json)
		replace: {
			Version: {
				src: [
				'readme.txt',
				'<%= pkg.name %>.php'
				],
				overwrite: true,
				replacements: [
				{
					from: /Stable tag:.*$/m,
					to: "Stable tag: <%= pkg.version %>"
				},
				{
					from: /Version:.*$/m,
					to: "Version: <%= pkg.version %>"
				},
				{
					from: /public \$version = \'.*.'/m,
					to: "public $version = '<%= pkg.version %>'"
				},
				{
					from: /public \$version = \'.*.'/m,
					to: "public $version = '<%= pkg.version %>'"
				},
				{
					from: /public static \$version = \'.*.'/m,
					to: "public static $version = '<%= pkg.version %>'"
				},
				{
					from: /const VERSION = \'.*.'/m,
					to: "const VERSION = '<%= pkg.version %>'"
				}
				]
			}
		}

        }
    );


	grunt.registerTask(
        'zip',
        [
		'clean',
		'copy',
		'compress'
        ]
    );

	grunt.registerTask( 'build', [ 'replace', 'addtextdomain', 'makepot' ] );
	grunt.registerTask( 'release', [ 'build', 'zip', 'clean' ] );

};
