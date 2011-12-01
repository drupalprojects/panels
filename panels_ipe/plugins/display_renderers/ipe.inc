<?php

$plugin = array(
  'renderer' => 'panels_renderer_ipe',
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
