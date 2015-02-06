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

To sync files:

$ grunt

To create a new release:

1. Dump new version number inside package.json
2. Run:

    $ grunt release

3. Create package via the master site admin.
4. Run:

    $ grunt update

6. Commit & Push

*/

module.exports = function(grunt) {

    var localconfig = grunt.file.readJSON('localconfig.json'),
        paths = (function(cfg){

            var paths = {
                copy: [],
                clean: [],
            };

            for (var i = 0, j = cfg.sites.length; i < j; i++)
            {
                paths.copy.push({expand: true, src: 'app/code/community/Doofinder/**', dest: cfg.sites[i]});
                paths.copy.push({expand: true, src: 'app/etc/modules/Doofinder_Feed.xml', dest: cfg.sites[i]});
                paths.copy.push({expand: true, src: 'var/connect/Doofinder_Feed.xml', dest: cfg.sites[i]});
                paths.copy.push({expand: true, src: 'var/connect/package.xml', dest: cfg.sites[i]});

                paths.clean.push(cfg.sites[i] + '/app/code/community/Doofinder/**');
                paths.clean.push(cfg.sites[i] + '/app/etc/modules/Doofinder_Feed.xml');
                paths.clean.push(cfg.sites[i] + '/var/connect/**');
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
            release: {
                options: {
                    prefix: '\\s+<version>'
                },
                src: ['app/code/community/Doofinder/Feed/etc/config.xml']
            }
        }
    });

    grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.loadNpmTasks('grunt-version');

    grunt.registerTask('default', ['clean:sync', 'copy:sync']);
    grunt.registerTask('sync', ['clean:sync', 'copy:sync']);
    grunt.registerTask('release', ['version:release', 'sync']);
    grunt.registerTask('update', ['copy:release']);
};