<?php

$plugin = array(
  'renderer' => 'panels_renderer_ipe',
  'title' => t('In-Place Editor'),
  'editor' => TRUE,
  'description' => t('The In-Place Editor (IPE)'),

  'pipeline defaults' => array(
    'title' => '',
    'access' => array(),
    'renderer' => 'ipe',
    'options' => array(
      'content' => array(),
      'layouts' => array(),
      'general' => array(
        'manage_styles' => TRUE,
        'manage_caching' => TRUE,
        'manage_locking' => TRUE,
        'manage_layouts' => TRUE,
        'add_panes' => TRUE,
        'remove_panes' => TRUE,
      ),
    ),
  ),

  'operations' => array(
    'general' => array(
      '#type' => 'link',
      '#title' => t('General'),
      '#description' => t('Manage general settings for this renderer.'),
      '#weight' => -5,
      '#operation' => array(
        'form' => 'panels_ipe_renderer_settings_form',
      ),
    ),
  ),
);

/**
 * Form for configuring general settings
 *
 */
function panels_ipe_renderer_settings_form($form, &$form_state) {
  $settings = $form_state['renderer_instance']['options']['general'];

  $form['title'] = array(
    '#type' => 'textfield',
    '#title' => t('Title'),
    '#description' => t('The administrative title for this renderer configuration on this pipeline.'),
    '#default_value' => $form_state['renderer_instance']['title'],
  );

  $form['general'] = array('#tree' => TRUE);

  $form['general']['manage_styles'] = array(
    '#type' => 'checkbox',
    '#title' => t('Manage styles'),
    '#description' => t('Determines whether users can change styles on panes and regions.'),
    '#default_value' => $settings['manage_styles'],
  );

  $form['general']['manage_caching'] = array(
    '#type' => 'checkbox',
    '#title' => t('Manage caching'),
    '#description' => t('Determines whether users can manage caching configuration.'),
    '#default_value' => $settings['manage_caching'],
  );

  $form['general']['manage_locking'] = array(
    '#type' => 'checkbox',
    '#title' => t('Manage pane locking'),
    '#description' => t('Determines whether users can lock and unlock individual panes.'),
    '#default_value' => $settings['manage_locking'],
  );

  $form['general']['manage_layouts'] = array(
    '#type' => 'checkbox',
    '#title' => t('Manage layouts'),
    '#description' => t('Determines whether users can change the layout being used by the panel.'),
    '#default_value' => $settings['manage_layouts'],
  );

  $form['general']['add_panes'] = array(
    '#type' => 'checkbox',
    '#title' => t('Add new panes'),
    '#description' => t('Determines whether users can add new panes.'),
    '#default_value' => $settings['add_panes'],
  );

  $form['general']['remove_panes'] = array(
    '#type' => 'checkbox',
    '#title' => t('Remove panes'),
    '#description' => t('Determines whether users can remove existing panes.'),
    '#default_value' => $settings['remove_panes'],
  );

  return $form;
}

function panels_ipe_renderer_settings_form_submit($form, &$form_state) {
  $form_state['renderer_instance']['title'] = $form_state['values']['title'];
  $form_state['renderer_instance']['options']['general'] = $form_state['values']['general'];
}