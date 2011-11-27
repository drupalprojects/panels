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

    dsm($this, 'pipelines ui object');

    // Make the list page appear as a tab under the general Panels settings.
    $this->plugin['menu']['items']['list callback']['type'] = MENU_LOCAL_TASK;

    parent::hook_menu($items);
  }

  public function get_default_operation_trail($item, $operations) {
    return array('operations', 'summary');
  }

  public function get_operations($item) {
    $operations = array();

    // The primary "operations" group is anonymous.
    $operations['operations'] = array(
      '#type' => 'ctools_operation_group',
    );

    $operations['operations']['summary'] = array(
      '#type' => 'link',
      '#title' => t('Summary'),
      '#description' => t('See a summary of all the variants contained within this pipeline.'),
    );

    $operations['operations']['edit'] = array(
      '#type' => 'link',
      '#title' => t('Edit'),
      '#description' => t('Edit this item'),
      '#operation' => array(
        'form' => 'ctools_export_ui_edit_item_form',
      ),
    );

    $operations['actions'] = array(
      '#type' => 'ctools_operation_group',
    );

    $operations['actions']['disable'] = array(
      '#type' => 'link',
      '#title' => t('Disable'),
      '#description' => t('Disable this pipeline'),
    );

    drupal_alter('ctools_export_ui_operations', $operations, $this);
    return $operations;
  }
}
