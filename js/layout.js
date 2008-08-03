// $Id$
/**
 * @file layout.js 
 *
 * Contains javascript to make layout modification a little nicer.
 */

Drupal.Panels.Layout = {};
Drupal.Panels.Layout.autoAttach = function() {
  $('div.form-item .layout-icon').click(function() {
    $(this).prev().attr('checked', true);
  });
};

$(Drupal.Panels.Layout.autoAttach);
