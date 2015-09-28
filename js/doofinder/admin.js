(function() {
    'use strict';

    document.observe("dom:loaded", function() {
        try {
            var $td = $('row_doofinder_cron_schedule_settings_time').select('td.value')[0];
            $td.innerHTML = $td.innerHTML.replace(/&nbsp;:&nbsp;/g, '<span class="df-separator"></span>');
            $td.select('.df-separator:last')[0].hide();
            $td.select('select:last')[0].hide();
        } catch (e) {}
    });
})();
