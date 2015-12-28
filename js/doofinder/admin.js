;(function() {
  'use strict';

  /**
   * This file is part of Doofinder_Feed.
   */

  /**
   * @category   Javascript
   * @package    Doofinder_Feed
   * @version    1.5.9
   */

  var add_message = function(message_type, text) {
    var html = '' +
      '<ul class="messages">' +
      '  <li class="' + message_type + '-msg">' + text + '</li>' +
      '</ul>';
    $('messages').insert(html);
  };

  document.observe("dom:loaded", function() {
    try {
      var $td = $('row_doofinder_cron_schedule_settings_time').select('td.value')[0];
      $td.innerHTML = $td.innerHTML.replace(/&nbsp;:&nbsp;/g, '<span class="df-separator"></span>');
      $td.select('.df-separator')[1].hide();
      $td.select('select')[2].hide();
    } catch (e) {}

    if ($('doofinder_cron_feed_settings')) {
      var changed = false;
      new Form.Observer('config_edit_form', 0.3, function(form, value) {
        if (changed) return;
        add_message('notice', 'Configuration has changed. The feed generation will be rescheduled after saving.');
        form.insert('<input type="hidden" name="reset" value="1"/>');
        changed = true;
      });
    }
  });

})();
