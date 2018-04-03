var doofinderBanner = Class.create();

doofinderBanner.prototype = {
  initialize : function(ajaxUrl, bannerId, bannerElementId, bannerInsertionPoint, bannerInsertionMethod) {
    this.ajaxUrl = ajaxUrl;
    this.bannerId = bannerId;
    this.bannerElementId = bannerElementId;
    this.bannerInsertionPoint = bannerInsertionPoint;
    this.bannerInsertionMethod = bannerInsertionMethod

    this.setPlacement();
    this.watchClick();
  },

  setPlacement: function() {
    var point = null;
    var banner = document.getElementById(this.bannerElementId);
    var insertionPoint = this.bannerInsertionPoint;
    var method = this.bannerInsertionMethod;

    if (insertionPoint.charAt(0) === '#') {
      point = document.getElementById(insertionPoint.substr(1));
    }
    if (insertionPoint.charAt(0) === '.') {
      point = document.getElementsByClassName(insertionPoint.substr(1))[0];
    }

    if (point !== null) {
      switch (method) {
        case 'before':
          point.before(banner);
          break;
        case 'after':
          point.after(banner);
          break;
        case 'prepend':
          point.prepend(banner);
          break;
        case 'append':
          point.appendChild(banner);
          break;
        case 'replace':
          point.replace(banner);
          break;
      }
    }
  },

  watchClick: function() {
    var self = this;
    $(self.bannerElementId).select('a')[0].observe('click', function(event) {
      if ($(this).readAttribute('target') !== '_blank') {
        event.preventDefault();
        self.registerClick();
        window.location = $(this).readAttribute('href');
      }
      else {
        self.registerClick();
      }
    });
  },

  registerClick: function() {
    new Ajax.Request(this.ajaxUrl, {
      method: 'post',
      parameters: {bannerId: this.bannerId}
    });
  }
};
