// $Id$

Drupal.Panels = {};

Drupal.Panels.autoAttach = function() {
  if ($.browser.msie) {
    // If IE, attach a hover event so we can see our admin links.
    $("div.panel-pane").hover(
      function() {
        $('div.panel-hide', this).addClass("panel-hide-hover"); return true;
      },
      function(){
        $('div.panel-hide', this).removeClass("panel-hide-hover"); return true;
      }
    );
  }
}

$(Drupal.Panels.autoAttach);
