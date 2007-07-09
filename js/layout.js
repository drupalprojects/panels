// $Id$
/**
 * @file layout.js 
 *
 * Contains javascript to make layout modification a little nicer.
 */

Drupal.PanelsLayout = {};
Drupal.PanelsLayout.autoAttach = function() {
  $('div.form-item div.layout-icon').click(function() {
    $(this).prev().find('input').attr('checked', true);
  });
}

$(Drupal.PanelsLayout.autoAttach);
