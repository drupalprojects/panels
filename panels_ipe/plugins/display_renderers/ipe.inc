<?php

$plugin = array(
  'renderer' => 'panels_renderer_ipe',
  'title' => t('In-Place Editor'),
  'editor' => TRUE,
  'description' => t('The In-Place Editor (IPE)'),

  'operations' => array(
    'general' => array(
      '#type' => 'link',
      '#title' => t('General'),
      '#description' => t('Manage general settings for this renderer.'),
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
  $renderer = &$form_state['item']->settings['renderers'][$form_state['operation']['instance name']];
  $settings = $renderer['options']['general'];

  $form['title'] = array(
    '#type' => 'textfield',
    '#title' => t('Title'),
    '#description' => t('The administrative title for this renderer configuration on this pipeline.'),
    '#default_value' => $renderer['title'],
  );


}