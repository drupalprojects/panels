// $Id$

Drupal.Panels = {};

Drupal.Panels.autoAttach = function() {
  $("div.panel-pane").hover(
    function() {
      $('div.panel-hide', this).addClass("panel-hide-hover"); return true;
    },
    function(){
      $('div.panel-hide', this).removeClass("panel-hide-hover"); return true;
    }
  );
}

$(Drupal.Panels.autoAttach);
