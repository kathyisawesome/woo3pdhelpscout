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
				'!vendor/bin/**',
				'!bin/**',
				'!composer.json',
				'!composer.lock',
				'!src/Api/*.json',
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

	grunt.registerTask( 'build', [ 'replace' ] );
	grunt.registerTask( 'release', [ 'build', 'zip', 'clean' ] );

};
