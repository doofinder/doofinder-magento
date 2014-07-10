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

To add a new version number to the code:

1. Modify it inside package.json
2. Run:

$ grunt release

3. Create package via the master site.
4. Upload to Magento Connect

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

                paths.clean.push(cfg.sites[i] + '/app/code/community/Doofinder/**');
                paths.clean.push(cfg.sites[i] + '/app/etc/modules/Doofinder_Feed.xml');

                if (i === 0) {
                    // The first site in the list is the "master" site we use to
                    // create the Magento Connect package so we need to copy in
                    // reverse direction.
                    paths.copy.push({src: cfg.sites[i] + '/var/connect/Doofinder_Feed.xml', dest: 'var/connect/Doofinder_Feed.xml'});
                    paths.copy.push({src: cfg.sites[i] + '/var/connect/package.xml', dest: 'var/connect/package.xml'});
                } else {
                    paths.copy.push({expand: true, src: 'var/connect/Doofinder_Feed.xml', dest: cfg.sites[i]});
                    paths.copy.push({expand: true, src: 'var/connect/package.xml', dest: cfg.sites[i]});
                    paths.clean.push(cfg.sites[i] + '/var/connect/*.xml');
                }
            }

            return paths;

        })(localconfig);

    grunt.initConfig({
        packageconfig: grunt.file.readJSON('package.json'),

        copy: {
            sync: {
                files: paths.copy
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
    grunt.registerTask('release', ['version:release']);
};