// $Id$

Drupal.Panels = {};

Drupal.Panels.autoAttach = function() {
  $("div.panel-pane").hover(
    function() { 
      $('div.panel-hide', this).addClass("hover"); 
    }, 
    function(){ 
      $('div.panel-hide', this).removeClass("hover"); 
    }
  );
}

$(Drupal.Panels.autoAttach);
