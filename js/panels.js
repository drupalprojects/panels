// $Id$

Drupal.Panels = {};

Drupal.Panels.autoAttach = function() {
  // Using .hover seems to mess with the href in statusbar when hovering over links in FF.
  $("div.panel-pane").mouseover(
    function() {
      $('div.panel-hide', this).addClass("panel-hide-hover"); return true;
    }
   );
  $("div.panel-pane").mouseout(
   function(){
      $('div.panel-hide', this).removeClass("panel-hide-hover"); return true;
    }
  );
}

$(Drupal.Panels.autoAttach);
