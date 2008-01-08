// $Id$

Drupal.Panels = {};

Drupal.Panels.autoAttach = function() {
  $("div.panel-pane").hover(
    function() { 
      $('div.panel-hide', this).addClass("hover"); 
      return true;
    }, 
    function(){ 
      $('div.panel-hide', this).removeClass("hover"); 
      return true;
    }
  );
}

$(Drupal.Panels.autoAttach);
