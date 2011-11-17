<?php

/**
 * @file
 * Contains the administrative UI for Panels renderer pipelines.
 */

class panels_pipelines_ui extends ctools_export_ui {

  public function hook_menu(&$items) {
    // During updates, this can run before our schema is set up, so our
    // plugin can be empty.
    if (empty($this->plugin['schema'])) {
      return;
    }

    // Make the list page appear as a tab under the general Panels settings.
    $this->plugin['menu']['items']['list callback']['type'] = MENU_LOCAL_TASK;
    parent::hook_menu($items);
  }

  public function list_build_row($item, &$form_state, $operations) {
    parent::list_build_row($item, $form_state, $operations);
  }
}
