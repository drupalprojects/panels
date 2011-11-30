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
      '#description' => t('See a summary of all the renderers in this pipeline.'),
      '#weight' => -99,
      '#no-ajax' => TRUE,
      '#operation' => array(
        'type' => 'summary',
      ),
    );

    $operations['operations']['general'] = array(
      '#type' => 'link',
      '#title' => t('General'),
      '#description' => t('Manage general settings for this pipeline.'),
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

    // Action to add a new IPE renderer. This is hardcoded and crappy; can be
    // made better & flexible later.
    $operations['actions']['add-ipe'] = array(
      '#type' => 'link',
      '#title' => t('Add IPE renderer'),
      '#description' => t('Add an IPE renderer to this pipeline.'),
      '#operation' => array(
        'form' => array('panels_pipelines_add_ipe_renderer'),
      ),
    );

    drupal_alter('ctools_export_ui_operations', $operations, $this);
    return $operations;
  }

  public function render_operation_type_summary($form_state, $info, $operation) {
    return array(
      'title' => t('Cool summary, bro.'),
      'content' => t('Blah blah lotsa info about all the contained pipelines blah.'),
    );
  }
}
