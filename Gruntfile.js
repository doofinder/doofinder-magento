/*

localconfig.json

{
    "sites": [
        "/path/to/magento/1.9.0.1", << This is the master site used to create Magento Connect packages
        "/path/to/magento/1.8.0.1"
    ]
}

The first time you checkout the repo, copy Doofinder_Feed.xml and package.xml to
/var/connect/ in the master site.

While developing execute:

$ grunt

and files will be synced automatically.

To create a new release:

1. Dump new version number inside package.json
2. Run:

    $ grunt release:start

3. Create package via the master site admin.
4. Run:

    $ grunt release:finish

6. Commit & Push

*/

module.exports = function(grunt) {

    var localconfig = grunt.file.readJSON('localconfig.json'),
        paths = (function(cfg){

            var paths = {
                copy: [],
                clean: [],
            };

            var content_paths = [
                'app/code/community/Doofinder/**',
                'app/design/**/doofinder.xml',
                'app/etc/modules/Doofinder_Feed.xml',
                'js/doofinder/**',
                'lib/Doofinder/**', // TODO: Remove this entry
                'lib/php-doofinder/**',
                'skin/adminhtml/default/default/doofinder/**',
                'var/connect/Doofinder_Feed.xml',
                'var/connect/package.xml'
            ];

            for (var i = 0, j = cfg.sites.length; i < j; i++) {
                for (var x = 0, y = content_paths.length; x < y; x++) {
                    paths.copy.push({expand: true, src: content_paths[x], dest: cfg.sites[i]});
                    paths.clean.push(cfg.sites[i] + content_paths[x]);
                }
            }

            return paths;

        })(localconfig);

    grunt.initConfig({
        packageconfig: grunt.file.readJSON('package.json'),

        copy: {
            sync: {
                files: paths.copy
            },
            release: {
                files: [{
                    expand: true,
                    cwd: localconfig.sites[0] + "/var/connect",
                    src: '**',
                    dest: 'var/connect/'
                }]
            }
        },

        clean: {
            sync: {
                options: {
                    force: true
                },
                src: paths.clean
            }
        },

        version: {
            config: {
                options: {
                    prefix: '\\s+<version>'
                },
                src: ['app/code/community/Doofinder/Feed/etc/config.xml']
            },
            php: {
                options: {
                    prefix: '@version\\s*'
                },
                src: grunt.file.expand({filter: 'isFile'}, 'app/**/*.php')
            }
        },

        watch: {
            dev: {
                files: ['app/**/*'],
                tasks: ['clean:sync', 'copy:sync']
            }
        },

        marked: {
            options: {
                highlight: false,
                sanitize: false
            },
            dist: {
                files: {
                    'tmp/README.html': 'README.md'
                }
            }
        }
    });

    grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-marked');
    grunt.loadNpmTasks('grunt-version');

    grunt.registerTask('default', ['clean:sync', 'copy:sync', 'watch:dev']);
    grunt.registerTask('sync', ['clean:sync', 'copy:sync']);
    grunt.registerTask('release:start', ['version', 'sync']);
    grunt.registerTask('release:finish', ['copy:release']);
    grunt.registerTask('docs', ['marked:dist']);
};
