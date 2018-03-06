var doofinderBanner = Class.create();

doofinderBanner.prototype = {
  initialize : function(ajaxUrl, bannerId, bannerElementId, bannerPlacement) {
    this.ajaxUrl = ajaxUrl;
    this.bannerId = bannerId;
    this.bannerElementId = bannerElementId;
    this.bannerPlacement = bannerPlacement;

    this.setPlacement();
    this.watchClick();
  },

  setPlacement: function() {
    var place = null;

    if (this.bannerPlacement.charAt(0) === '#') {
      place = document.getElementById(this.bannerPlacement.substr(1));
    }
    if (this.bannerPlacement.charAt(0) === '.') {
      place = document.getElementsByClassName(this.bannerPlacement.substr(1))[0];
    }

    if (place !== null) {
      place.appendChild(document.getElementById(this.bannerElementId));
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
